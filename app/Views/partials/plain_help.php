<?php
/**
 * Plain-language helpers for non-technical users (shared by the Lab Reports screens).
 * Include once near the top of a view:  <?= view('partials/plain_help') ?>
 *   anTip('text')                      -> small "i" badge, explanation on hover / focus / tap
 *   anGuide('Title', ['step', ...])    -> collapsible "How this page works" box (open state remembered per page)
 *   anSummary('Headline', 'detail', tone)
 * Nothing here reads or changes data.
 */
if (! function_exists('anTip')) {
    function anTip(string $text): string
    {
        $t = esc($text, 'attr');

        return ' <span class="an-i" tabindex="0" role="note" aria-label="' . $t . '" data-tip="' . $t . '">i</span>';
    }
}
if (! function_exists('anSummary')) {
    function anSummary(string $headline, string $detail = '', string $tone = 'info'): string
    {
        return '<div class="an-sum an-sum-' . esc($tone, 'attr') . '" role="status"><b>' . esc($headline) . '</b>'
            . ($detail !== '' ? '<span>' . esc($detail) . '</span>' : '') . '</div>';
    }
}
if (! function_exists('anGuide')) {
    /** @param list<string> $steps plain sentences, shown as a numbered list */
    function anGuide(string $key, string $title, array $steps, string $note = ''): string
    {
        $li = '';
        foreach ($steps as $s) {
            $li .= '<li>' . esc($s) . '</li>';
        }

        return '<details class="an-guide" data-guide="' . esc($key, 'attr') . '"><summary>' . esc($title) . '</summary>'
            . '<ol>' . $li . '</ol>' . ($note !== '' ? '<p>' . esc($note) . '</p>' : '') . '</details>';
    }
}
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/partials-plain_help-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/partials-plain_help-1.css') ?>">
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('details.an-guide[data-guide]').forEach(function (d) {
        var k = 'an-guide:' + d.getAttribute('data-guide');
        try { if (localStorage.getItem(k) === '1') { d.open = true; } } catch (e) {}
        d.addEventListener('toggle', function () { try { localStorage.setItem(k, d.open ? '1' : '0'); } catch (e) {} });
    });
});
</script>
