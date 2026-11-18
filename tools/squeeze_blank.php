<?php
// php tools/squeeze_blank.php file.php : removes the runs of empty lines between statements (never touches strings/heredocs); checks that the code tokens stay identical
$f = $argv[1] ?? '';
$src = file_get_contents($f);
$tok = token_get_all($src);
$out = '';
$n = count($tok);
for ($i = 0; $i < $n; $i++) {
    $t = $tok[$i];
    if (is_array($t) && $t[0] === T_WHITESPACE) {
        $nl = substr_count($t[1], "\n");
        if ($nl >= 3) {
            $next = $tok[$i + 1] ?? null;
            $nextId = is_array($next) ? $next[0] : 0;
            $big = in_array($nextId, [T_DOC_COMMENT, T_PUBLIC, T_PRIVATE, T_PROTECTED, T_FUNCTION, T_STATIC], true)
                || (is_array($next) && $next[0] === T_COMMENT && str_starts_with($next[1], '//') && false);
            $ws = substr($t[1], strrpos($t[1], "\n") + 1);   // the indentation of the next line
            $out .= ($big ? "\n\n" : "\n") . $ws;
            continue;
        }
        $out .= $t[1];
        continue;
    }
    $out .= is_array($t) ? $t[1] : $t;
}
$strip = static function (string $s): string {
    $r = '';
    foreach (token_get_all($s) as $t) {
        if (is_array($t) && $t[0] === T_WHITESPACE) { continue; }
        $r .= (is_array($t) ? $t[0] . ':' . $t[1] : $t) . '|';
    }
    return md5($r);
};
if ($strip($src) !== $strip($out)) { fwrite(STDERR, "token mismatch, not written\n"); exit(1); }
file_put_contents($f, $out);
echo count(explode("\n", $src)) . ' -> ' . count(explode("\n", $out)) . " lines\n";
