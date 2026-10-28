<?php

namespace App\Libraries;

/**
 * Fifth batch: InterLink works per user. NEXUS-L no longer logs in to InterLink or stores any InterLink login; the PIC
 * signs in with their own account in their own browser. This batch replaces the answers about the old server-side
 * connection (they are retired by `php spark brain:seed`).
 */
class BrainSeed5
{
    /** Titles of built-in answers that described the old shared server session / queue. */
    public const RETIRE = [
        'How do I sign in to InterLink in the Sync Center?', 'InterLink says the session ended', 'The background worker is not running',
        'How often does the Sync Center check InterLink?', 'How do I choose which Lab References to sync?', 'What does "prepared" mean in the queue?',
        'Can I sync many Lab References at once?', 'How do I cancel a queued Lab Reference?', 'Why did a queue item fail?',
        'What does the red banner in the Sync Center mean?', 'What does Paste cookie do in the Sync Center?', 'What does Disconnect do in the Sync Center?',
        'What are Live changes and tracked columns in the Sync Center?', 'The Sign in to InterLink window did not open', 'The InterLink sign-in window closed by itself',
        'The Sync Center says Connected but the queue stays waiting', 'InterLink says Connected but the list is empty', 'How do I stop the background sync?',
        'Is the InterLink password stored in NEXUS-L?', 'Why did a queue item fail?', 'Session ended - sign in again" in the Sync Center',
        '"Session ended - sign in again" in the Sync Center', '"Could not store the session"', '"That does not look like a Cookie header"', '"The sign-in helper is not installed"',
        'Check the InterLink connection', 'InterLink is unreachable from the server', 'Does the Sync Center write results into InterLink by itself?',
        'How do I register the background worker task?', 'The server was restarted: what must be running?',
        'The InterLink list is not up to date', 'What is the Sync Center?',
    ];

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $o = static fn (string $label, string $url): array => ['type' => 'open', 'label' => $label, 'url' => $url];
        $k = static fn (string $title, string $keywords, string $body, array $steps = [], array $extra = []): array => array_merge(['title' => $title, 'keywords' => $keywords, 'body' => $body, 'steps' => $steps], $extra);
        $il = ['page_match' => 'interlink'];

        return [
            $k('How do I sign in to InterLink?', 'sign in interlink, login interlink, connect interlink, interlink account, log in to interlink, how to login interlink, interlink sign in', 'Press "Open InterLink (sign in with your account)" on the InterLink page. It opens InterLink in a new tab of your own browser; sign in with your own InterLink account there. NEXUS-L does not log in for you and never stores your InterLink login.', ['Press Open InterLink (sign in with your account).', 'Sign in with your own InterLink account.', 'Come back to NEXUS-L to prepare the values, then fill the form in InterLink.'], $il + ['actions' => [$o('Open InterLink Sync', 'interlink')]]),
            $k('Why does NEXUS-L not log in to InterLink for me?', 'why not log in for me, nexus-l login interlink, server login, shared login, why my own account, own account interlink, who is signed in', 'The report must be sent by the person responsible (the PIC) under their own InterLink account. So every person signs in to InterLink themselves, in their own browser, and NEXUS-L keeps no InterLink login or session.', [], $il),
            $k('Is my InterLink login stored anywhere in NEXUS-L?', 'interlink password stored, is my login saved, credentials stored, cookie stored, session stored, saved interlink login', 'No. NEXUS-L never sees or stores your InterLink password, code or session. Your sign-in stays in your own browser.', [], $il),
            $k('What does the Sync Center do now?', 'what does sync center do, sync center purpose, send a report to interlink panel, interlink panel, how does sync work now', 'It lets you pick an Application No. and a Lab Reference and prepares the values for the InterLink form. You then open InterLink with your own account, click the NEXUS-L fill bookmark so the boxes are filled (green outline), check them, and press Save / Submit yourself.', ['Type the Application No. and pick the Lab Reference.', 'Press "Prepare the values to fill InterLink".', 'Copy fill data, open the InterLink form, click the NEXUS-L fill bookmark.', 'Review and submit in InterLink.'], $il),
            $k('InterLink asks me to sign in again while I work', 'interlink session ended, session ended interlink, interlink session expired, interlink sign in again, interlink logged me out, interlink session timeout, interlink asks login, relogin interlink', 'InterLink signs people out after a while. Sign in again in your InterLink tab with your own account, then continue; the NEXUS-L values are still available (press Copy fill data again if needed).', [], $il),
            $k('Why is the InterLink list in NEXUS-L not live?', 'interlink list not live, applications list old, not updated list, list stored, cached applications, interlink list source', 'NEXUS-L cannot read InterLink without a login, so the list shows the applications already stored in NEXUS-L. For the newest applications use InterLink itself in your own browser.', [], $il),
            $k('Does the background worker still do anything?', 'background worker, scheduled task, worker still needed, worker purpose, interlink worker task', 'The scheduled task "NEXUS-L InterLink Worker" now only saves the assistant\'s memory (the Brain files) every minute. It no longer touches InterLink.', [], ['role_min' => 'admin', 'diagnose' => 'worker']),
            $k('The values were not filled in InterLink', 'values not filled, fill did nothing, bookmark did nothing, interlink boxes empty after fill, fill failed', 'Make sure you pressed "Copy fill data" in NEXUS-L, then clicked the NEXUS-L fill bookmark while the InterLink form (the Upload form) is open in your tab. If the bookmark is missing, drag it again from the fill panel.', [], $il),
            $k('Which InterLink account should I use?', 'which interlink account, my own account, whose account, account to use interlink, personal account', 'Always your own account: the person who submits is recorded by InterLink. Do not use a colleague\'s account.', [], $il),
        ];
    }
}
