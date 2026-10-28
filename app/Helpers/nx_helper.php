<?php

/**
 * nx_asset('js/app.js') -> versioned URL of an asset. Serves public/assets/min/<same path> when it exists and is not
 * older than the source (built by tools/build/build.js), otherwise the source file itself.
 */
if (! function_exists('nx_asset')) {
    function nx_asset(string $rel): string
    {
        $src = FCPATH . 'assets/' . $rel;
        $min = FCPATH . 'assets/min/' . $rel;
        $use = $rel;
        $t   = @filemtime($src);
        if (is_file($min) && @filemtime($min) >= (int) $t) {
            $use = 'min/' . $rel;
            $t   = @filemtime($min);
        }

        return base_url('assets/' . $use) . '?v=' . (int) $t;
    }
}
