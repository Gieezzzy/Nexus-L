<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5">
<title><?= ! empty($link['title']) ? esc($link['title']) : 'Daily Bonding Failure — CAPA Form' ?> · NEXUS-L</title>
<link rel="stylesheet" href="<?= base_url('assets/css/views/quality-capa_public-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/quality-capa_public-1.css') ?>">
</head>
<body>
<div class="wrap">
<?php if (! empty($invalid)): ?>
  <div class="head"><div class="head-info"><h1>Invalid or Expired CAPA Link</h1><p>Please ask the QC Lab team for a new link.</p></div></div>
<?php else: ?>
  <?php $doneCount = count(array_filter($cases, static fn ($c) => ! empty($c['locked']))); ?>
  <header class="head">
    <div class="head-info">
      <h1><?= esc($link['title'] ?: 'Daily Bonding Failure — Corrective Action Plan') ?></h1>
      <p>Fill out each card below and attach up to <b>3 photos</b> (upload or camera). Once you submit a card, it becomes <b>view-only</b> while other draft cards stay editable.</p>
    </div>
    <div class="prog" id="cp-prog"><?= $doneCount ?> / <?= count($cases) ?> Submitted</div>
  </header>

  <div class="grid" id="cp-grid" data-token="<?= esc($token, 'attr') ?>" data-base="<?= esc(site_url('capa-form/' . $token), 'attr') ?>">
  <?php foreach ($cases as $c): $lk = ! empty($c['locked']); $dis = $lk ? ' disabled' : ''; ?>
    <div class="card-col">
    <article class="card <?= $lk ? 'locked' : '' ?>" data-id="<?= (int) $c['id'] ?>" data-locked="<?= $lk ? '1' : '0' ?>">
      <div class="card-head">
        <div class="card-title">
          <div><span class="line-pill"><?= esc($c['line'] ?: 'LINE') ?></span><span class="ref"><?= esc($c['ref']) ?></span></div>
          <div class="meta"><?= esc($c['style'] ?: '—') ?><?= $c['cement'] ? ' · ' . esc($c['cement']) : '' ?> · Failed <?= esc($c['failed_date'] ?: '—') ?></div>
        </div>
        <span class="st <?= $lk ? 'done' : 'draft' ?>"><?= $lk ? 'SUBMITTED · VIEW ONLY' : 'DRAFT · EDITABLE' ?></span>
      </div>

      <div class="row2">
        <label class="col-prep"><span class="fld-lbl">Prepared by *</span><input type="text" data-f="prepared_by" value="<?= esc($c['prepared_by'], 'attr') ?>" placeholder="Last Name, First Name"<?= $dis ?>></label>
        <label class="col-date"><span class="fld-lbl">Production Date</span><input type="date" data-f="production_date" value="<?= esc($c['production_date'], 'attr') ?>" placeholder="YYYY-MM-DD"<?= $dis ?>></label>
      </div>

      <label class="fld"><span class="fld-lbl">Problem Statement *</span><textarea rows="2" data-f="root_cause" placeholder="e.g. UPPER TO FOXING - POOR PRESSING"<?= $dis ?>><?= esc($c['root_cause']) ?></textarea></label>
      <label class="fld"><span class="fld-lbl">Action Plan *</span><textarea rows="3" data-f="corrective_action" placeholder="Describe the immediate corrective action taken..."<?= $dis ?>><?= esc($c['action']) ?></textarea></label>
      <label class="fld"><span class="fld-lbl">Preventive Action</span><textarea rows="2" data-f="preventive_action" placeholder="Optional preventive measure to stop recurrence..."<?= $dis ?>><?= esc($c['preventive_action']) ?></textarea></label>

      <div class="photos-box">
        <div class="photos-top">
          <span class="p-count">Photos (<?= count($c['photos']) ?> / 3)</span>
          <?php if (! $lk): ?>
          <div class="photo-btns">
            <input type="file" accept="image/*" capture="environment" class="f-cam" style="display:none">
            <input type="file" accept="image/*" class="f-up" style="display:none">
            <button type="button" class="btn btn-cam"<?= count($c['photos']) >= 3 ? ' disabled' : '' ?>>Take Photo</button>
            <button type="button" class="btn btn-up"<?= count($c['photos']) >= 3 ? ' disabled' : '' ?>>Upload Photo</button>
          </div>
          <?php endif ?>
        </div>
        <div class="thumbs">
          <?php foreach ($c['photos'] as $ph): ?>
            <div class="thumb" data-pid="<?= (int) $ph['id'] ?>">
              <img src="<?= esc($ph['url'], 'attr') ?>" alt="CAPA photo">
              <?php if (! $lk): ?><button type="button" class="rm" title="Remove photo (logged in 72h audit history)">&times;</button><?php endif ?>
            </div>
          <?php endforeach ?>
        </div>
      </div>

      <div class="act">
        <?php if ($lk): ?>
          <span class="msg ok">Submitted by <?= esc($c['submitted_by'] ?: 'Staff') ?><?= $c['submitted_at'] ? ' on ' . esc(substr($c['submitted_at'], 0, 16)) : '' ?> · Locked (view-only)</span>
        <?php else: ?>
          <button type="button" class="btn btn-draft">Save Draft</button>
          <button type="button" class="btn primary btn-submit">Submit CAPA</button>
          <span class="msg" role="status"></span>
        <?php endif ?>
      </div>
    </article>
    </div>
  <?php endforeach ?>
  </div>
<?php endif ?>
</div>
<div class="lb" id="cp-lb"><img src="" alt="Preview"></div>
<script src="<?= base_url('assets/js/views/quality-capa_public-1.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/views/quality-capa_public-1.js') ?>"></script>
</body>
</html>
