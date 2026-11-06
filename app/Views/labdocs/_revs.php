<?php /** @var array $labRevs  @var string $revKind */
$labRevs = $labRevs ?? ['items' => [], 'ro' => false];
?>
<script type="application/json" id="ld-revs"><?= json_encode($labRevs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php if (! empty($labCollab)): ?><script type="application/json" id="ld-collab"><?= json_encode($labCollab, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endif ?>
