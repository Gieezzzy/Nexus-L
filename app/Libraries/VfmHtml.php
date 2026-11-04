<?php

namespace App\Libraries;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The text of an edited test method document is HTML typed by a person: only simple formatting survives
 * (bold, italic, underline, line breaks, sub / superscript, and a span with a colour or a font size). Everything else is removed.
 */
class VfmHtml
{
    private const TAGS = ['b' => 1, 'strong' => 'b', 'i' => 1, 'em' => 'i', 'u' => 1, 'br' => 1, 'sub' => 1, 'sup' => 1, 'span' => 1];

    public static function clean(string $html): string
    {
        $html = mb_substr($html, 0, 20000);
        if (trim($html) === '') {
            return '';
        }
        $d = new DOMDocument();
        libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="utf-8"?><div id="r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $d->getElementById('r');
        if (! $root) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return self::walk($root);
    }

    private static function walk(DOMNode $n): string
    {
        $out = '';
        foreach ($n->childNodes as $c) {
            if ($c->nodeType === XML_TEXT_NODE) {
                $out .= htmlspecialchars((string) $c->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($c instanceof DOMElement) {
                $tag = strtolower($c->tagName);
                if ($tag === 'script' || $tag === 'style') {
                    continue;
                }
                $map = self::TAGS[$tag] ?? null;
                if ($map === null) {
                    $out .= ($tag === 'div' || $tag === 'p') ? self::walk($c) . ' ' : self::walk($c);     // unknown tag: keep its text only
                    continue;
                }
                $t = is_string($map) ? $map : $tag;
                if ($t === 'br') {
                    $out .= '<br>';
                } elseif ($t === 'span') {
                    $style = [];
                    $raw   = $c->getAttribute('style');
                    if (preg_match('/(?<![-\w])color\s*:\s*(#[0-9a-fA-F]{3,6})\b/', $raw, $m)) {
                        $style[] = 'color:' . $m[1];
                    }
                    if (preg_match('/font-size\s*:\s*(\d{1,2}(?:\.\d)?)pt/', $raw, $m) && (float) $m[1] >= 6 && (float) $m[1] <= 40) {
                        $style[] = 'font-size:' . $m[1] . 'pt';
                    }
                    $inner = self::walk($c);
                    $out  .= $style ? '<span style="' . implode(';', $style) . '">' . $inner . '</span>' : $inner;
                } else {
                    $out .= "<{$t}>" . self::walk($c) . "</{$t}>";
                }
            }
        }
        return $out;
    }

    /** a list of document blocks from the browser, checked and cleaned; null when it is not usable */
    public static function blocks(mixed $in): ?array
    {
        if (! is_array($in) || count($in) > 1500) {
            return null;
        }
        $out = [];
        foreach ($in as $b) {
            if (! is_array($b)) {
                continue;
            }
            $t = $b['t'] ?? 'p';
            if ($t === 'p') {
                $out[] = ['t' => 'p', 'ind' => max(0, min(400, (int) ($b['ind'] ?? 0))), 'list' => ! empty($b['list']), 'al' => in_array($b['al'] ?? 'l', ['l', 'c', 'r'], true) ? $b['al'] : 'l',
                    'sz' => (float) ($b['sz'] ?? 0), 'html' => self::clean((string) ($b['html'] ?? ''))];
            } elseif ($t === 'img') {
                $src = (string) ($b['src'] ?? '');
                if (preg_match('~^[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+\.(png|jpe?g|gif)$~', $src)) {
                    $out[] = ['t' => 'img', 'src' => $src, 'w' => max(10, min(620, (int) ($b['w'] ?? 300))), 'h' => max(10, min(900, (int) ($b['h'] ?? 200)))];
                }
            } elseif ($t === 'table' && is_array($b['rows'] ?? null) && count($b['rows']) <= 200) {
                $rows = [];
                foreach ($b['rows'] as $r) {
                    $rows[] = array_map(static fn ($c) => self::clean((string) $c), array_slice(array_values((array) $r), 0, 20));
                }
                $out[] = ['t' => 'table', 'rows' => $rows, 'box' => ! empty($b['box'])];
            }
        }
        return $out;
    }
}
