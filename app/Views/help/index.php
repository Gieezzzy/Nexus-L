<?php
/** Help center. Data: $topics (role-filtered), $news, $hidden, $isAdmin */
$articleCount = 0;
foreach ($topics as $t) {
    $articleCount += count($t['articles']);
}
?>
<div class="hp" id="hp">
    <header class="hp-head">
        <div>
            <h1>Help</h1>
            <p>How each part of NEXUS-L works, in the order you meet it. Showing what your role can open.</p>
        </div>
        <div class="hp-search">
            <label for="hp-q" class="sr-only">Search the guide</label>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="hp-q" placeholder="Search the guide: calendar, alert, QR, ERP, access …" autocomplete="off" spellcheck="false">
            <kbd aria-hidden="true">/</kbd>
        </div>
    </header>

    <div class="hp-layout">
        <nav class="hp-nav" id="hp-nav" aria-label="Help topics">
            <?php if ($news): ?><a href="#new" class="is-on" data-topic="new">What's new</a><?php endif ?>
            <?php foreach ($topics as $t): ?>
                <a href="#<?= esc($t['id'], 'attr') ?>" data-topic="<?= esc($t['id'], 'attr') ?>"><?= esc($t['title']) ?> <span><?= count($t['articles']) ?></span></a>
            <?php endforeach ?>
        </nav>

        <main class="hp-main" id="hp-main">
            <p class="hp-status" id="hp-status" role="status" aria-live="polite"><?= $articleCount ?> answers in <?= count($topics) ?> topics</p>

            <?php if ($news): ?>
            <section class="hp-topic" id="new" data-topic="new" aria-labelledby="h-new">
                <div class="hp-topic-head"><h2 id="h-new">What's new</h2><p>Added lately. Open the page to try it.</p></div>
                <ul class="hp-news">
                    <?php foreach ($news as $n): ?>
                    <li class="hp-art-row" data-text="<?= esc(strtolower($n[1] . ' ' . $n[2]), 'attr') ?>">
                        <time><?= esc($n[0]) ?></time>
                        <div><b><?= esc($n[1]) ?></b><span><?= esc($n[2]) ?></span></div>
                        <a class="hp-open" href="<?= esc(site_url($n[3]), 'attr') ?>">Open<span class="sr-only"> <?= esc($n[1]) ?></span></a>
                    </li>
                    <?php endforeach ?>
                </ul>
            </section>
            <?php endif ?>

            <?php foreach ($topics as $t): ?>
            <section class="hp-topic" id="<?= esc($t['id'], 'attr') ?>" data-topic="<?= esc($t['id'], 'attr') ?>" aria-labelledby="h-<?= esc($t['id'], 'attr') ?>">
                <div class="hp-topic-head">
                    <div>
                        <h2 id="h-<?= esc($t['id'], 'attr') ?>"><?= esc($t['title']) ?></h2>
                        <p><?= esc($t['lead']) ?></p>
                    </div>
                    <?php if (! empty($t['url'])): ?><a class="hp-open" href="<?= esc(site_url($t['url']), 'attr') ?>">Open the page</a><?php endif ?>
                </div>
                <div class="hp-arts">
                    <?php foreach ($t['articles'] as $a): ?>
                    <details class="hp-art" data-text="<?= esc(strtolower(strip_tags($a[0] . ' ' . $a[1] . ' ' . $t['title'])), 'attr') ?>">
                        <summary><?= $a[0] ?></summary>
                        <div class="hp-body"><?= $a[1] ?></div>
                    </details>
                    <?php endforeach ?>
                </div>
            </section>
            <?php endforeach ?>

            <div class="hp-empty" id="hp-empty" hidden>
                <b>Nothing matches "<span id="hp-term"></span>".</b>
                <p>Try one word (for example <i>alert</i>, <i>QR</i> or <i>access</i>), or ask the assistant in the corner of the screen.</p>
            </div>

            <?php if ($hidden > 0): ?>
            <p class="hp-note"><?= $hidden ?> topic<?= $hidden === 1 ? ' is' : 's are' ?> not shown because your role cannot open those pages. An Admin can change that in Settings &rarr; Users &amp; roles.</p>
            <?php endif ?>
        </main>
    </div>
</div>
