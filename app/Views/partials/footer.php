<?php
/**
 * Footer. Pass ['compact' => true] for the auth layout.
 */
?>
<?php if (! empty($compact)): ?>
    <p class="mt-6 text-center text-xs text-white/50">&copy; <?= date('Y') ?> NEXUS-L · Networked Execution &amp; Unified System for Laboratories</p>
<?php else: ?>
    <footer class="border-t border-white/10 px-6 py-4 text-xs text-slate-500 sm:flex sm:items-center sm:justify-between">
        <p>&copy; <?= date('Y') ?> NEXUS-L · Networked Execution &amp; Unified System for Laboratories</p>
        <p class="mt-1 sm:mt-0">v1.0.0</p>
    </footer>
<?php endif ?>
