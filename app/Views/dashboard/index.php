<?php
$u    = service('auth')->user();
$max  = max(1, ...array_values(array_map(static fn ($d) => $d['ok'] + $d['bad'], $days)));
$icon = [
    'user'  => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0',
    'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z',
    'clock' => 'M12 7v5l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'timer' => 'M12 8v4l2 2M9 3h6M12 3v2M20 12a8 8 0 11-16 0 8 8 0 0116 0z',
];
?>
<div class="stats">
    <?php foreach ([
        ['Signed in as', '@' . $u['username'], 'user'],
        ['Role', ucfirst($u['role']), 'shield'],
        ['Session started', date('h:i A', $u['login_at']), 'clock'],
        ['Idle timeout', config('Auth')->idleTimeoutMinutes . ' min', 'timer'],
    ] as [$label, $value, $ic]): ?>
        <div class="card glass stat">
            <div><small><?= esc($label) ?></small><b><?= esc($value) ?></b></div>
            <span class="ico"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="<?= $icon[$ic] ?>"/></svg></span>
        </div>
    <?php endforeach ?>
</div>

<div class="g-hero mt">
    <div class="card glass hero">
        <small class="muted">Welcome back,</small>
        <h2><?= esc($u['name']) ?></h2>
        <p class="muted">Networked Execution &amp; Unified System for Laboratories.<?= $dept ? ' · ' . esc($dept) : '' ?></p>
        <div class="hero-meta">
            <div><small>Last sign-in</small><b><?= $lastLogin ? esc(date('M d, Y h:i A', strtotime($lastLogin))) : '—' ?></b></div>
            <div><small>From IP</small><b class="mono"><?= esc($lastIp ?? '—') ?></b></div>
        </div>
    </div>

    <div class="card glass">
        <h3>Sign-in health</h3>
        <p class="muted sm">Last 7 days</p>
        <?php $tot = $okTotal + $badTotal; $pct = $tot ? round($okTotal / $tot * 100) : 100; ?>
        <div class="ring" style="--p:<?= $pct ?>"><span><b><?= $pct ?>%</b><small>successful</small></span></div>
    </div>

    <div class="card glass">
        <h3>Security summary</h3>
        <p class="muted sm">Your account, last 7 days</p>
        <div class="pair"><small>Successful sign-ins</small><b><?= (int) $okTotal ?></b></div>
        <div class="pair"><small>Failed attempts</small><b class="<?= $badTotal ? 'bad' : '' ?>"><?= (int) $badTotal ?></b></div>
    </div>
</div>

<div class="g-main mt">
    <div class="card glass">
        <h3>Sign-in activity</h3>
        <p class="muted sm">Successful and failed sign-ins per day</p>
        <div class="chart" role="img" aria-label="Sign-ins per day">
            <?php foreach ($days as $d => $v): ?>
                <div class="col">
                    <div class="stack" title="<?= esc(date('M d', strtotime($d))) ?>: <?= $v['ok'] ?> ok, <?= $v['bad'] ?> failed">
                        <i class="seg-bad" style="height:<?= round($v['bad'] / $max * 100) ?>%"></i>
                        <i class="seg-ok" style="height:<?= round($v['ok'] / $max * 100) ?>%"></i>
                    </div>
                    <small><?= esc(date('D', strtotime($d))) ?></small>
                </div>
            <?php endforeach ?>
        </div>
        <p class="legend"><span><i class="seg-ok"></i> Successful</span><span><i class="seg-bad"></i> Failed</span></p>
    </div>

    <div class="card glass">
        <h3>Recent activity</h3>
        <ul class="tl">
            <?php foreach ($recent as $r): ?>
                <li>
                    <i class="dot <?= $r['Success'] ? 'ok' : 'bad' ?>"></i>
                    <div><b><?= esc(ucwords(str_replace('_', ' ', $r['Event']))) ?></b>
                    <small><?= esc(date('M d, h:i A', strtotime($r['CreatedAt']))) ?></small></div>
                </li>
            <?php endforeach ?>
            <?php if (! $recent): ?><li class="muted">No activity yet.</li><?php endif ?>
        </ul>
    </div>
</div>

<div class="card glass mt">
    <h3>Security log</h3>
    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Event</th><th>Status</th><th>IP</th><th>When</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= esc(ucwords(str_replace('_', ' ', $r['Event']))) ?></td>
                        <td><span class="badge <?= $r['Success'] ? 'b-ok' : 'b-bad' ?>"><?= $r['Success'] ? 'Success' : 'Failed' ?></span></td>
                        <td class="mono"><?= esc($r['IPAddress']) ?></td>
                        <td><?= esc(date('M d, Y h:i A', strtotime($r['CreatedAt']))) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
