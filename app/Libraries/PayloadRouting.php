<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Where e-mail and Notifier alerts are sent from (Settings > Payload routing): the free lab runs its Outlook Relay /
 * Notifier on one of two PCs, .43 or .45 (or a custom address). When a target is chosen, only the Hub running on that
 * PC may claim e-mails or dispatch alerts; a Hub on the other PC sees nothing to do, so two Hubs never compete.
 * "auto" (the default) means either PC may do the work, exactly as before this setting existed.
 */
class PayloadRouting
{
    /** @return array{target:string,ip:string,label:string} */
    public static function state(): array
    {
        $target = (string) SystemSettings::get('ops.target', 'auto');
        $ip     = match ($target) {
            '43'     => (string) SystemSettings::get('ops.ip_43', '192.168.32.43'),
            '45'     => (string) SystemSettings::get('ops.ip_45', '192.168.32.45'),
            'custom' => (string) SystemSettings::get('ops.ip_custom', ''),
            default  => '',
        };
        $label = $target === 'auto' ? 'Any PC may send' : 'Only ' . ($ip !== '' ? $ip : '(no address set)') . ' may send';

        return ['target' => $target, 'ip' => $ip, 'label' => $label];
    }

    public static function allowed(RequestInterface $request): bool
    {
        $s = self::state();
        if ($s['target'] === 'auto' || $s['ip'] === '') {
            return true;
        }

        return $request->getIPAddress() === $s['ip'];
    }

    /** what a Hub/Notifier app is told, so it can show the admin why it is idle */
    public static function info(RequestInterface $request): array
    {
        $s = self::state();

        return [
            'target'     => $s['target'],
            'is_target'  => self::allowed($request),
            'note'       => $s['target'] === 'auto' ? '' : $s['label'] . ' (Settings > Payload routing); this PC is ' . $request->getIPAddress() . '.',
        ];
    }
}
