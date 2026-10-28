<?php

namespace App\Controllers;

use App\Libraries\Chat\ChatStore;
use App\Libraries\Chat\NexusStore;
use App\Libraries\Chat\RmsStore;
use App\Libraries\Chat\UserDirectory;
use App\Libraries\InboxNotifier;
use App\Models\UserModel;

/**
 * Inbox. Everyone chats on NEXUS-L's own database (dbnexus). Admins additionally see, and can use,
 * the RMS Android app's messaging database; their RMS identity is their Synology ID.
 * A chat is addressed by key "<source>:<peerId>" (n = NEXUS-L, r = RMS).
 */
class InboxController extends BaseController
{
    private const MAX_FILE = 10 * 1024 * 1024;
    private const IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    private const FILE_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar', '7z'];

    private array $webUser = [];
    private bool $admin    = false;

    public function index(): string
    {
        return $this->render('inbox/index', [
            'title' => 'Inbox', 'withCss' => 'inbox.css', 'withJs' => ['inbox-emoji.js', 'inbox.js'],
            'isAdmin' => strcasecmp((string) ($this->auth->user()['role'] ?? ''), 'admin') === 0,
        ]);
    }

    // ------------------------------------------------------------------ lists

    public function conversations()
    {
        return $this->guard(function () {
            $q    = trim((string) $this->request->getGet('q'));
            $out  = ['conversations' => [], 'people' => [], 'warnings' => []];
            foreach ($this->stores() as [$store, $me]) {
                try {
                    foreach ($store->conversations($me) as $c) {
                        $out['conversations'][] = $this->tag($c, $store);
                    }
                    if ($q !== '') {
                        foreach ($store->search($me, mb_substr($q, 0, 50)) as $p) {
                            $out['people'][] = $this->tag($p, $store);
                        }
                    }
                } catch (\Throwable $e) {
                    log_message('error', '[Inbox] ' . $store->source() . ': ' . $e->getMessage());
                    $out['warnings'][] = $store->source() === 'r' ? 'The RMS service is unavailable right now.' : 'Messages are unavailable right now.';
                }
            }
            usort($out['conversations'], static fn ($a, $b) => strcmp($b['at'], $a['at']));
            if ($q === '') {
                unset($out['people']);
            }
            $out['admin'] = $this->admin;

            return $out;
        });
    }

    /**
     * GET ?q= : unique people you can start a chat with. Everyone sees the NEXUS-L users; admins also get the RMS users.
     * A person who exists in both systems (same Synology ID) is returned once, with a chat key per system.
     * Empty q = 4 random suggestions (like the app); otherwise up to 20 matches.
     */
    public function users()
    {
        return $this->guard(function () {
            $q    = mb_substr(trim((string) $this->request->getGet('q')), 0, 50);
            $res  = UserDirectory::collect($this->stores(), $this->synologyId(), $q);
            $list = $res['users'];
            if ($q === '') {
                shuffle($list);              // like the app: a few random suggestions until you search
                $list = array_slice($list, 0, 4);
            } else {
                $list = array_slice($list, 0, 20);
            }

            return ['users' => $list, 'warnings' => $res['warnings'], 'admin' => $this->admin];
        });
    }

    public function unread()
    {
        return $this->guard(function () {
            $n = 0;
            foreach ($this->stores() as [$store, $me]) {
                try {
                    $n += $store->unreadCount($me);
                } catch (\Throwable $e) {
                    // one source being down must not hide the other
                }
            }

            return ['unread' => $n];
        });
    }

    // ------------------------------------------------------------------ thread

    public function thread(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $targetId, $person, , $isGroup] = $this->scope($key);
            $after = max(0, (int) $this->request->getGet('after'));
            if ($isGroup) {
                $data = $s->groupThread($me, $targetId, $after, $after > 0 ? 100 : 60);
                $data['messages'] = $this->withUrls($data['messages'], $key);

                return ['peer' => $this->tag($person, $s), 'me' => $me, 'isGroup' => true, 'group' => $data['group']] + $data;
            }

            $data  = $s->thread($me, $targetId, $after, $after > 0 ? 100 : 60);
            $s->markRead($me, $targetId);
            $data['messages'] = $this->withUrls($data['messages'], $key);

            return ['peer' => $this->tag($person, $s), 'me' => $me, 'isGroup' => false, 'nicknames' => $s->nicknames($me, $targetId)] + $data;
        });
    }

    public function send(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $targetId, $person, $myName, $isGroup] = $this->scope($key);
            $this->throttle($me);
            $in   = $this->input();
            $body = trim((string) ($in['body'] ?? ''));
            if ($body === '' || mb_strlen($body) > 2000) {
                throw new \InvalidArgumentException('Message must be 1–2000 characters.');
            }
            if ($isGroup) {
                $id = $s->sendGroup($me, $targetId, $body, [], (int) ($in['reply_to'] ?? 0) ?: null, $this->admin);

                return ['id' => $id, 'message' => $this->one($s, $me, $targetId, $id, $key, true)];
            }
            $id = $s->send($me, $targetId, $body, [], (int) ($in['reply_to'] ?? 0) ?: null);
            $this->notify($s, $me, $myName, $targetId, $person, $body);

            return ['id' => $id, 'message' => $this->one($s, $me, $targetId, $id, $key, false)];
        });
    }

    // ------------------------------------------------------------------ attachments

    /** multipart: files[] (≤5) + optional body (caption for the first). */
    public function upload(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $targetId, $person, $myName, $isGroup] = $this->scope($key);
            $this->throttle($me);
            $files = $this->request->getFileMultiple('files') ?: array_filter([$this->request->getFile('file')]);
            if (! $files || count($files) > 5) {
                throw new \InvalidArgumentException('Attach between 1 and 5 files.');
            }
            $caption = trim((string) $this->request->getPost('body'));
            if (mb_strlen($caption) > 2000) {
                throw new \InvalidArgumentException('Caption is too long.');
            }

            $out = [];
            foreach (array_values($files) as $i => $f) {
                if (! $f->isValid() || $f->getSize() > self::MAX_FILE) {
                    throw new \InvalidArgumentException('Each file must be valid and at most 10 MB.');
                }
                $att  = $s instanceof RmsStore ? $this->rmsAttachment($s, $me, $targetId, $f) : $this->nexusAttachment($f);
                $text = $i === 0 ? $caption : '';
                $replyId = $i === 0 ? ((int) $this->request->getPost('reply_to') ?: null) : null;
                if ($isGroup) {
                    $id = $s->sendGroup($me, $targetId, $text, $att, $replyId, $this->admin);
                    $out[] = $this->one($s, $me, $targetId, $id, $key, true);
                } else {
                    $id = $s->send($me, $targetId, $text, $att, $replyId);
                    $out[] = $this->one($s, $me, $targetId, $id, $key, false);
                    $this->notify($s, $me, $myName, $targetId, $person, $text !== '' ? $text : ($att['name'] ?? 'Sent an attachment'));
                }
            }

            return ['messages' => $out];
        });
    }

    private function rmsAttachment(RmsStore $s, string $me, string $peer, $f): array
    {
        $mime = $f->getMimeType();
        if (! isset(self::IMAGE_MIMES[$mime]) || @getimagesize($f->getTempName()) === false) {
            throw new \InvalidArgumentException('Only images can be sent to RMS chats.');
        }

        return ['imagePath' => $s->saveImage($me, $peer, $this->toJpeg($f->getTempName()))];
    }

    /** Re-encode as JPEG (max 1600px): strips metadata/payloads, matches what the Android app produces. */
    private function toJpeg(string $path): string
    {
        $raw = file_get_contents($path);
        if (! function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Image support (GD) is not enabled on the server.');
        }
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            throw new \InvalidArgumentException('That image could not be read.');
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        if (max($w, $h) > 1600) {
            $r   = 1600 / max($w, $h);
            $img = imagescale($img, (int) round($w * $r), (int) round($h * $r)) ?: $img;
        }
        $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        ob_start();
        imagejpeg($bg, null, 85);

        return (string) ob_get_clean();
    }

    private function nexusAttachment($f): array
    {
        $mime = $f->getMimeType();
        $ext  = strtolower($f->getClientExtension());
        $isImg = isset(self::IMAGE_MIMES[$mime]);
        if ($isImg) {
            if (@getimagesize($f->getTempName()) === false) {
                throw new \InvalidArgumentException('That image could not be read.');
            }
            $ext = self::IMAGE_MIMES[$mime];
        } elseif (! in_array($ext, self::FILE_EXTS, true)) {
            throw new \InvalidArgumentException('This file type is not allowed. Allowed: images, PDF, Office files, TXT, CSV, ZIP, RAR, 7Z.');
        }
        $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = dirname(NexusStore::absolutePath($rel));
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            throw new \RuntimeException('Could not create the upload folder.');
        }
        if (! $f->move($dir, basename($rel))) {
            throw new \RuntimeException('Could not store the file.');
        }
        $name = preg_replace('/[\x00-\x1f\\\\\/:*?"<>|]+/', '_', basename($f->getClientName()));

        return ['path' => $rel, 'name' => mb_substr($name, 0, 200), 'size' => $f->getSize(), 'type' => $isImg ? $mime : ($this->mimeForExt($ext))];
    }

    private function mimeForExt(string $ext): string
    {
        return ['pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv', 'zip' => 'application/zip'][$ext] ?? 'application/octet-stream';
    }

    /** Authenticated download/inline view of a NEXUS-L attachment. */
    public function file(string $key, int $id)
    {
        $user = $this->boot();
        try {
            [$s, $me, $peer] = $this->scope($key);
            $row = $s instanceof NexusStore ? $s->rawMessage($me, $peer, $id) : null;
        } catch (\Throwable $e) {
            $row = null;
        }
        $path = $row && ! empty($row['FilePath']) ? NexusStore::absolutePath((string) $row['FilePath']) : null;
        if ($path === null || ! is_file($path) || ! str_starts_with(realpath($path) ?: '', realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'inbox') ?: "\0")) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $type  = (string) $row['FileType'];
        $inline = isset(self::IMAGE_MIMES[$type]);
        $name  = (string) $row['FileName'];

        return $this->response
            ->setHeader('Content-Type', $inline ? $type : 'application/octet-stream')
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . addcslashes($name, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($name))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($path));
    }

    // ------------------------------------------------------------------ message actions

    /** POST {id, emoji} toggles/switches your reaction. */
    public function react(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer, , $myName] = $this->scope($key);
            $in = $this->input();

            return ['reactions' => $s->react($me, $peer, (int) ($in['id'] ?? 0), (string) ($in['emoji'] ?? ''), $myName)];
        });
    }

    public function edit(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer] = $this->scope($key);
            $in   = $this->input();
            $text = trim((string) ($in['body'] ?? ''));
            if ($text === '' || mb_strlen($text) > 2000) {
                throw new \InvalidArgumentException('Message must be 1–2000 characters.');
            }
            $m = $s->edit($me, $peer, (int) ($in['id'] ?? 0), $text);

            return ['message' => $this->withUrls([$m], $key)[0]];
        });
    }

    public function bump(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer, $person, $myName] = $this->scope($key);
            $this->throttle($me);
            $id = $s->bump($me, $peer, (int) ($this->input()['id'] ?? 0), $myName, $person['name']);
            $this->notify($s, $me, $myName, $peer, $person, 'Bumped a message');

            return ['message' => $this->one($s, $me, $peer, $id, $key)];
        });
    }

    /** POST {id, to: "<src>:<peer>"} re-sends the text to another chat of the same source. */
    public function forward(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer] = $this->scope($key);
            $in = $this->input();
            $to = (string) ($in['to'] ?? '');
            [$s2, , $peer2, $person2, $myName] = $this->scope($to);
            if ($s2->source() !== $s->source()) {
                throw new \InvalidArgumentException('Messages can only be forwarded within the same system.');
            }
            $this->throttle($me);
            $text = $s->plainText($me, $peer, (int) ($in['id'] ?? 0));
            if ($text === null || trim($text) === '') {
                throw new \InvalidArgumentException('Attachments cannot be forwarded yet.');
            }
            $s2->send($me, $peer2, '[Forwarded] ' . $text);
            $this->notify($s2, $me, $myName, $peer2, $person2, '[Forwarded] ' . $text);

            return ['ok' => true];
        });
    }

    // ------------------------------------------------------------------ chat details

    public function details(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $targetId, $person, , $isGroup] = $this->scope($key);
            if ($isGroup) {
                return ['peer' => $this->tag($person, $s), 'isGroup' => true, 'settings' => $this->settings($person)];
            }
            $d = $s->details($me, $targetId);

            return ['peer' => $this->tag($person, $s), 'isGroup' => false, 'settings' => $this->settings($d)];
        });
    }

    /** POST {key, value} (SecuredChat | IsReadReceipt | CurrentEmoji | CurrentTheme) or {nickname_for: me|peer, nickname}. */
    public function setting(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $targetId, , , $isGroup] = $this->scope($key);
            $in = $this->input();
            if ($isGroup) {
                if (isset($in['key']) && isset($in['value'])) {
                    $s->setGroupSetting($targetId, (string) $in['key'], (string) $in['value']);
                }

                return ['settings' => $s->groupDetails($me, $targetId)];
            }
            if (isset($in['nickname_for'])) {
                $s->setNickname($me, $targetId, (string) $in['nickname_for'] === 'me' ? $me : $targetId, (string) ($in['nickname'] ?? ''));
            } else {
                $s->setSetting($me, $targetId, (string) ($in['key'] ?? ''), (string) ($in['value'] ?? ''));
            }

            return ['settings' => $this->settings($s->details($me, $targetId))];
        });
    }

    /** POST create a new group. Enforces 50-member limit (requires Admin approval if > 50). */
    public function createGroup()
    {
        return $this->guard(function () {
            $in      = $this->input();
            $name    = trim((string) ($in['name'] ?? ''));
            $members = (array) ($in['members'] ?? []);
            $src     = trim((string) ($in['source'] ?? 'n'));

            if ($name === '' || mb_strlen($name) > 100) {
                throw new \InvalidArgumentException('Group name must be between 1 and 100 characters.');
            }

            $isRms = ($src === 'r' || $src === 'rg');
            if ($isRms) {
                if (! $this->admin) {
                    throw new \DomainException('Admin permission required for RMS groups.', 403);
                }
                $store   = new RmsStore();
                $creator = $this->synologyId();
            } else {
                $store   = new NexusStore();
                $creator = (string) $this->webUser['UserID'];
            }

            // Clean members
            $cleanMembers = array_values(array_unique(array_filter(array_map('trim', $members))));
            if (! in_array($creator, $cleanMembers, true)) {
                $cleanMembers[] = $creator;
            }

            $totalCount = count($cleanMembers);
            if ($totalCount < 2) {
                throw new \InvalidArgumentException('Please select at least 1 other member to create a group.');
            }

            // Limit of 50 members: Above 50 requires Administrator approval
            $pendingApproval = false;
            if ($totalCount > 50) {
                if ($this->admin) {
                    $policies = 'Active;MemberCount=' . $totalCount . ';';
                } else {
                    $policies = 'PendingAdminApproval;MemberCount=' . $totalCount . ';';
                    $pendingApproval = true;
                }
            } else {
                $policies = 'Active;MemberCount=' . $totalCount . ';';
            }

            $gid = $store->createGroupRecord($creator, $name, $cleanMembers, $policies);
            $key = ($isRms ? 'rg:' : 'g:') . $gid;

            return [
                'ok'              => true,
                'groupId'         => (string) $gid,
                'key'             => $key,
                'name'            => $name,
                'memberCount'     => $totalCount,
                'pendingApproval' => $pendingApproval,
                'message'         => $pendingApproval ? 'Group created. Because it has over 50 members, it is pending Admin approval.' : 'Group created successfully.',
            ];
        });
    }

    /** POST approve or reject group with >50 members (Admins only). */
    public function approveGroup(string $key)
    {
        return $this->guard(function () use ($key) {
            if (! $this->admin) {
                throw new \DomainException('Only administrators can approve or reject groups.', 403);
            }
            [$s, , $targetId, , , $isGroup] = $this->scope($key);
            if (! $isGroup) {
                throw new \InvalidArgumentException('Not a group chat.');
            }
            $in     = $this->input();
            $action = trim((string) ($in['action'] ?? 'approve'));
            $approve = ($action === 'approve');
            $s->approveGroup($targetId, $approve);

            return ['ok' => true, 'action' => $action, 'group' => $s->groupDetails('', $targetId)];
        });
    }

    /** POST toggle or approve disappearing messages (1:1 or Group). */
    public function setDisappearing(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $targetId, , , $isGroup] = $this->scope($key);
            $in   = $this->input();
            $mode = trim((string) ($in['mode'] ?? 'toggle'));
            if ($isGroup) {
                $res = $s->setGroupDisappearingMode($targetId, $mode);
            } else {
                $res = $s->setDisappearingMode($me, $targetId, $mode);
            }

            return ['ok' => true, 'disappearing' => $res['status'], 'raw' => $res['raw']];
        });
    }

    public function markTriggered(string $key, int $id)
    {
        return $this->guard(function () use ($key, $id) {
            [$s] = $this->scope($key);
            $s->markTriggered($id);

            return ['ok' => true];
        });
    }

    public function pinned(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer] = $this->scope($key);

            return ['messages' => $this->withUrls($s->pinned($me, $peer), $key)];
        });
    }

    /** POST {id, pin: bool} */
    public function pin(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer] = $this->scope($key);
            $in  = $this->input();
            $ids = $s->setPinned($me, $peer, (int) ($in['id'] ?? 0), (bool) ($in['pin'] ?? true));

            return ['pinned' => $ids];
        });
    }

    public function chatSearch(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer] = $this->scope($key);
            $q = trim((string) $this->request->getGet('q'));

            return ['messages' => mb_strlen($q) >= 2 ? $s->searchChat($me, $peer, mb_substr($q, 0, 60)) : []];
        });
    }

    public function media(string $key)
    {
        return $this->guard(function () use ($key) {
            [$s, $me, $peer] = $this->scope($key);
            $items = $s->media($me, $peer, max(0, (int) $this->request->getGet('before')), (bool) $this->request->getGet('files'));

            return ['items' => $this->withUrls($items, $key)];
        });
    }

    /** POST {token, device_type: 'web', browser} registers computer FCM web push token into tbl_user_push_tokens */
    public function saveToken()
    {
        return $this->guard(function () {
            $in    = $this->input();
            $token = trim((string) ($in['token'] ?? ''));
            if ($token === '' || mb_strlen($token) > 500) {
                throw new \InvalidArgumentException('Invalid token.');
            }
            $userId = (string) $this->webUser['UserID'];
            $db     = db_connect();
            $now    = date('Y-m-d H:i:s');
            $device = trim((string) ($in['device_type'] ?? 'web'));
            $ua     = mb_substr(trim((string) ($this->request->getUserAgent() ?? '')), 0, 100);

            if ($db->tableExists('tbl_user_push_tokens')) {
                $db->query(
                    "INSERT INTO tbl_user_push_tokens (user_id, token, device_type, browser, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), browser = VALUES(browser), updated_at = VALUES(updated_at)",
                    [$userId, $token, $device, $ua, $now, $now]
                );
            }

            // Mirror to dbo.users in RMS as NEXUS_WEB:<token> if admin has a linked SynologyID
            if ($this->admin && $this->synologyId() !== '') {
                try {
                    $rows = \App\Libraries\RmsDb::rows("SELECT fcm_token FROM dbo.users WHERE synologyID = ?", [$this->synologyId()]);
                    if ($rows) {
                        $existing = array_filter(array_map('trim', explode(';', (string) ($rows[0]['fcm_token'] ?? ''))));
                        $nexusToken = 'NEXUS_WEB:' . $token;
                        $cleaned = [];
                        foreach ($existing as $ex) {
                            if ($ex === $token || $ex === $nexusToken) continue;
                            $cleaned[] = $ex;
                        }
                        $cleaned[] = $nexusToken;
                        \App\Libraries\RmsDb::exec("UPDATE dbo.users SET fcm_token = ? WHERE synologyID = ?", [implode(';', $cleaned), $this->synologyId()]);
                    }
                } catch (\Throwable $e) {
                    log_message('error', '[Inbox] Token RMS sync failed: ' . $e->getMessage());
                }
            }

            return ['ok' => true];
        });
    }

    /** GET returns active themes matching Android Studio theme studio */
    public function themes()
    {
        return $this->guard(function () {
            return [
                'themes' => [
                    [
                        'key'         => 'default',
                        'name'        => 'Default RMS Blue',
                        'primary'     => '#2563EB',
                        'secondary'   => '#3B82F6',
                        'accent'      => '#60A5FA',
                        'bubble_me'   => '#2563EB',
                        'bubble_them' => '#F1F5F9',
                        'bg'          => '#FFFFFF',
                        'trigger'     => '👍',
                        'description' => 'Classic clean RMS Blue interface',
                    ],
                    [
                        'key'         => 'christmas',
                        'name'        => 'Christmas Holidays',
                        'primary'     => '#DC2626',
                        'secondary'   => '#166534',
                        'accent'      => '#F59E0B',
                        'bubble_me'   => '#DC2626',
                        'bubble_them' => '#DCFCE7',
                        'bg'          => '#FEF2F2',
                        'trigger'     => '❄️',
                        'description' => 'Festive snow particles, sleigh bells chime & Santa greetings',
                    ],
                    [
                        'key'         => 'sadako',
                        'name'        => 'Sadako Cursed Horror',
                        'primary'     => '#7F1D1D',
                        'secondary'   => '#0F172A',
                        'accent'      => '#EF4444',
                        'bubble_me'   => '#7F1D1D',
                        'bubble_them' => '#1E293B',
                        'bg'          => '#090D16',
                        'trigger'     => '👻',
                        'description' => 'Cursed well, wall cracks, creepy crawling sequence & scream audio',
                    ],
                    [
                        'key'         => 'valentines',
                        'name'        => 'Sweet Valentines',
                        'primary'     => '#EC4899',
                        'secondary'   => '#BE185D',
                        'accent'      => '#F472B6',
                        'bubble_me'   => '#EC4899',
                        'bubble_them' => '#FCE7F3',
                        'bg'          => '#FFF1F2',
                        'trigger'     => '❤️',
                        'description' => 'Romantic pastel atmosphere with floating 3D hearts burst',
                    ],
                    [
                        'key'         => 'anime_cyberpunk',
                        'name'        => 'Anime Cyberpunk',
                        'primary'     => '#8B5CF6',
                        'secondary'   => '#06B6D4',
                        'accent'      => '#F43F5E',
                        'bubble_me'   => '#8B5CF6',
                        'bubble_them' => '#0F172A',
                        'bg'          => '#030712',
                        'trigger'     => '⚡',
                        'description' => 'Electric neon grid, laser bursts & neon sparkle animations',
                    ],
                    [
                        'key'         => 'holloween',
                        'name'        => 'Halloween Terror',
                        'primary'     => '#EA580C',
                        'secondary'   => '#1C1917',
                        'accent'      => '#F97316',
                        'bubble_me'   => '#EA580C',
                        'bubble_them' => '#1C1917',
                        'bg'          => '#0C0A09',
                        'trigger'     => '🎃',
                        'description' => 'HD haunted Halloween wallpaper with eerie amber glow',
                    ],
                    [
                        'key'         => 'friday_13th',
                        'name'        => 'Friday the 13th',
                        'primary'     => '#991B1B',
                        'secondary'   => '#18181B',
                        'accent'      => '#DC2626',
                        'bubble_me'   => '#991B1B',
                        'bubble_them' => '#18181B',
                        'bg'          => '#09090B',
                        'trigger'     => '🔪',
                        'description' => 'HD Camp Crystal Lake wallpaper with blood-moon aura',
                    ],
                    [
                        'key'         => 'bonifacio',
                        'name'        => 'Bonifacio Day',
                        'primary'     => '#B91C1C',
                        'secondary'   => '#1E293B',
                        'accent'      => '#EF4444',
                        'bubble_me'   => '#B91C1C',
                        'bubble_them' => '#1E293B',
                        'bg'          => '#0F172A',
                        'trigger'     => '🚩',
                        'description' => 'HD Katipunan memorial wallpaper with patriotic crimson',
                    ],
                    [
                        'key'         => 'freddy_krueger',
                        'name'        => 'Nightmare Elm',
                        'primary'     => '#78350F',
                        'secondary'   => '#1C1917',
                        'accent'      => '#D97706',
                        'bubble_me'   => '#78350F',
                        'bubble_them' => '#1C1917',
                        'bg'          => '#0A0606',
                        'trigger'     => '🩸',
                        'description' => 'HD nightmare wallpaper with claw scratch vibes',
                    ],
                ],
            ];
        });
    }

    // ------------------------------------------------------------------ plumbing

    private function settings(array $d): array
    {
        $d['pinnedCount'] = count($d['pinned'] ?? []);

        return $d;
    }

    private function input(): array
    {
        return $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
    }

    private function throttle(string $me): void
    {
        if (service('throttler')->check('msg-' . $this->webUser['UserID'], 40, MINUTE) === false) {
            throw new \DomainException('Sending too fast. Please wait a moment.', 429);
        }
    }

    /** Load the web account; returns it. */
    private function boot(): array
    {
        if (! $this->webUser) {
            $this->webUser = model(UserModel::class)->find($this->auth->id()) ?? [];
            $this->admin   = strcasecmp((string) ($this->webUser['Role'] ?? ''), 'admin') === 0;
        }

        return $this->webUser;
    }

    private function synologyId(): string
    {
        return trim((string) ($this->webUser['SynologyID'] ?? ''));
    }

    /** @return array<int,array{0:ChatStore,1:string}> stores this user may use, with their identity in each */
    private function stores(): array
    {
        $out = [[new NexusStore(), (string) $this->webUser['UserID']]];
        if ($this->admin && $this->synologyId() !== '') {
            $out[] = [new RmsStore(), $this->synologyId()];
        }

        return $out;
    }

    /** @return array{0:ChatStore,1:string,2:string,3:array,4:string,5:bool} store, my id, peer/group id, peer/group info, my display name, isGroup */
    private function scope(string $key): array
    {
        if (! preg_match('/^(n|r|g|rg):([A-Za-z0-9_.\-]{1,100})$/', $key, $m)) {
            throw new \DomainException('Chat not found.', 404);
        }
        [, $src, $targetId] = $m;
        $isGroup = ($src === 'g' || $src === 'rg');
        $isRms   = ($src === 'r' || $src === 'rg');

        if ($isRms) {
            if (! $this->admin) {
                throw new \DomainException('Chat not found.', 404);
            }
            if ($this->synologyId() === '') {
                throw new \DomainException('Your account has no Synology ID, so it is not linked to the RMS inbox.', 409);
            }
            $store = new RmsStore();
            $me    = $this->synologyId();
            $name  = $store->person($me)['name'] ?? (string) $this->webUser['FullName'];
        } else {
            $store = new NexusStore();
            $me    = (string) $this->webUser['UserID'];
            $name  = (string) $this->webUser['FullName'];
        }

        if ($isGroup) {
            $person = $store->groupDetails($me, $targetId);
            $person['src'] = $isRms ? 'rg' : 'g';
        } else {
            $person = $targetId === $me
                ? ($store->person($me) ?? ['id' => $me, 'name' => $name, 'username' => '', 'online' => true, 'avatar' => null, 'src' => $src])
                : ($store->person($targetId) ?? ['id' => $targetId, 'name' => 'User ' . $targetId, 'username' => '', 'online' => false, 'avatar' => null, 'src' => $src]);
        }

        return [$store, $me, $targetId, $person, $name, $isGroup];
    }

    private function tag(array $row, ChatStore $s): array
    {
        $isGroup = ! empty($row['isGroup']);
        $srcTag  = $isGroup ? ($s->source() === 'r' ? 'rg' : 'g') : $s->source();
        $row['src'] = $srcTag;
        $row['key'] = $srcTag . ':' . $row['id'];
        unset($row['synologyId']);

        return $row;
    }

    private function one(ChatStore $s, string $me, string $targetId, int $id, string $key, bool $isGroup = false): ?array
    {
        $m = $isGroup ? $s->groupMessage($targetId, $id) : $s->message($me, $targetId, $id);

        return $m === null ? null : $this->withUrls([$m], $key)[0];
    }

    /** Attach authenticated URLs to NEXUS-L attachments. */
    private function withUrls(array $items, string $key): array
    {
        foreach ($items as &$m) {
            $url = site_url('inbox/file/' . rawurlencode($key) . '/' . $m['id']);
            if (($m['image'] ?? null) === '@file') {
                $m['image'] = $url;
            }
            if (! empty($m['file'])) {
                $m['file']['url'] = $url;
            }
        }

        return $items;
    }

    private function notify(ChatStore $s, string $me, string $myName, string $peer, array $person, string $text): void
    {
        try {
            (new InboxNotifier())->newMessage($s, $me, $myName, $peer, $text, isset($person['synologyId']) ? (string) $person['synologyId'] : null);
        } catch (\Throwable $e) {
            log_message('error', '[Inbox] notify: ' . $e->getMessage());
        }
    }

    /** Authenticate/identify, run the action, and translate failures into JSON errors. */
    private function guard(callable $fn)
    {
        $this->boot();
        if (! $this->webUser) {
            return $this->fail(401, 'Not signed in.');
        }
        try {
            $res = $fn();
        } catch (\DomainException $e) {
            return $this->fail($e->getCode() ?: 400, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->fail(422, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[Inbox] ' . $e->getMessage());

            return $this->fail(503, 'The messaging server is unavailable right now. Please try again shortly.');
        }

        return is_array($res) ? $this->response->setJSON($res + ['csrf' => csrf_hash()]) : $res;
    }

    private function fail(int $code, string $msg)
    {
        return $this->response->setStatusCode($code)->setJSON(['error' => $msg, 'csrf' => csrf_hash()]);
    }
}
