<?php
/** @var array $rows */
/** @var array $f */
$sel = static function (string $name, array $list, string $cur, string $all): string {
    $h = '<select name="' . $name . '"><option value="">' . esc($all) . '</option>';
    foreach ($list as $v) {
        $h .= '<option value="' . esc($v, 'attr') . '"' . ($v === $cur ? ' selected' : '') . '>' . esc($v) . '</option>';
    }

    return $h . '</select>';
};
?>
<style>
.al-wrap{padding:4px 0}
.al-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-bottom:12px}
.al-bar label{display:flex;flex-direction:column;font-size:12px;color:#475569;gap:3px}
.al-bar input,.al-bar select{padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;font-size:13px;background:#fff}
.al-bar .chk{flex-direction:row;align-items:center;gap:6px}
.al-btn{padding:7px 14px;border-radius:6px;border:1px solid #2563eb;background:#2563eb;color:#fff;font:inherit;font-size:13px;cursor:pointer;text-decoration:none}
.al-btn.ghost{background:#fff;color:#2563eb}
.al-tbl-wrap{overflow:auto;max-height:calc(100vh - 260px);border:1px solid #e2e8f0;border-radius:8px}
.al-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.al-tbl th{position:sticky;top:0;background:#f1f5f9;text-align:left;padding:7px 9px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.al-tbl td{padding:6px 9px;border-bottom:1px solid #f1f5f9;vertical-align:top}
.al-tbl td.det{max-width:520px;white-space:pre-wrap;word-break:break-word;color:#334155}
.al-act{display:inline-block;padding:1px 7px;border-radius:9px;background:#e0e7ff;color:#3730a3;font-size:11.5px;white-space:nowrap}
.al-act.del{background:#fee2e2;color:#991b1b}
.al-n{font-size:12px;color:#64748b;margin:0 0 6px}
</style>
<section class="card glass al-wrap">
    <form class="al-bar" method="get">
        <label>From<input type="date" name="from" value="<?= esc($f['from'], 'attr') ?>"></label>
        <label>To<input type="date" name="to" value="<?= esc($f['to'], 'attr') ?>"></label>
        <label>Person<?= $sel('user', $users, $f['user'], 'Everyone') ?></label>
        <label>Module<?= $sel('module', $modules, $f['module'], 'All modules') ?></label>
        <label>Action<?= $sel('action', $actions, $f['action'], 'All actions') ?></label>
        <label>Text<input type="search" name="q" value="<?= esc($f['q'], 'attr') ?>" placeholder="File, box, path…"></label>
        <label class="chk"><input type="checkbox" name="all" value="1"<?= $f['all'] ? ' checked' : '' ?>> Also every saved page request</label>
        <button class="al-btn" type="submit">Show</button>
        <a class="al-btn ghost" href="?<?= esc(http_build_query(array_filter($f + ['export' => 1])), 'attr') ?>" download>Export CSV</a>
    </form>
    <p class="al-n"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?><?= count($rows) >= 1000 ? ' (the newest 1000; export gives up to 20000)' : '' ?>. Deletions are also in the lab delete log.</p>
    <div class="al-tbl-wrap">
        <table class="al-tbl">
            <thead><tr><th>When</th><th>Who</th><th>Module</th><th>Action</th><th>What</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= esc(date('d M Y H:i:s', strtotime($r['created_at']))) ?></td>
                    <td><?= esc($r['user_name']) ?><br><small style="color:#64748b"><?= esc($r['user_role']) ?></small></td>
                    <td><?= esc($r['module']) ?></td>
                    <td><span class="al-act<?= str_contains($r['action'], 'delete') ? ' del' : '' ?>"><?= esc($r['action'] === 'request' ? $r['method'] . ' ' . $r['status'] : $r['action']) ?></span></td>
                    <td><?= esc($r['target']) ?></td>
                    <td class="det"><?= esc((string) $r['detail']) ?><?= $r['ip'] !== '' ? '<br><small style="color:#94a3b8">' . esc($r['ip']) . '</small>' : '' ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (! $rows): ?><tr><td colspan="6" style="text-align:center;color:#64748b;padding:24px">Nothing recorded for these filters.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</section>
