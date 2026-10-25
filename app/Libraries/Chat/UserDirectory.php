<?php

namespace App\Libraries\Chat;

/**
 * The "who can I message" list for the New message dialog: unique people across the databases a user may use.
 * A person present in both systems (same Synology ID) appears once, with a chat key per system.
 */
class UserDirectory
{
    /**
     * @param array<int,array{0:ChatStore,1:string}> $stores  [store, my id in that store]
     * @return array{users:array<int,array>,warnings:string[]}
     */
    public static function collect(array $stores, string $mySynologyId, string $q, int $perStoreLimit = 300): array
    {
        $byKey = [];
        $warn  = [];
        foreach ($stores as [$store, $me]) {
            try {
                foreach ($store->directory($me, $q, $perStoreLimit) as $p) {
                    $syn = $store->source() === 'r' ? (string) $p['id'] : trim((string) ($p['synologyId'] ?? ''));
                    if ($mySynologyId !== '' && strcasecmp($syn, $mySynologyId) === 0) {
                        continue; // this is me, seen through either system
                    }
                    $synValid = $syn !== '' && strcasecmp($syn, 'n/a') !== 0 && strcasecmp($syn, 'null') !== 0;
                    $k = $synValid ? 'syn:' . strtolower($syn) : $store->source() . ':' . $p['id'];
                    if (! isset($byKey[$k])) {
                        $byKey[$k] = [
                            'id' => $k, 'name' => (string) $p['name'], 'username' => (string) ($p['username'] ?? ''), 'dept' => (string) ($p['dept'] ?? ''),
                            'avatar' => $p['avatar'] ?? null, 'online' => ! empty($p['online']), 'systems' => [],
                        ];
                    }
                    $byKey[$k]['systems'][$store->source()] = $store->source() . ':' . $p['id'];
                    if ($store->source() === 'r') {
                        $byKey[$k]['avatar'] = $p['avatar'] ?: $byKey[$k]['avatar'];
                        $byKey[$k]['online'] = ! empty($p['online']);
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', '[Inbox] users ' . $store->source() . ': ' . $e->getMessage());
                $warn[] = $store->source() === 'r' ? 'The RMS user list is unavailable right now.' : 'The user list is unavailable right now.';
            }
        }

        $list = array_values($byKey);
        usort($list, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['users' => $list, 'warnings' => $warn];
    }
}
