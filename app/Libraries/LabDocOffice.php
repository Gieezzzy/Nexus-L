<?php

namespace App\Libraries;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Handles reading and updating Office documents (.docx, .pptx) for in-browser editing and presentations.
 */
class LabDocOffice
{
    /**
     * Reads a docx file and extracts its structured HTML.
     */
    public static function docxToHtml(string $filePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '<p>Unable to open document archive.</p>';
        }

        $xmlStr = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $xmlStr) {
            return '<p>Document content is empty or unreadable.</p>';
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($xmlStr);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $body = $xpath->query('//w:body')->item(0);
        if (! $body) {
            return '<p></p>';
        }

        $html = '';
        foreach ($body->childNodes as $node) {
            if ($node->nodeName === 'w:p') {
                $html .= self::paragraphToHtml($node, $xpath);
            } elseif ($node->nodeName === 'w:tbl') {
                $html .= self::tableToHtml($node, $xpath);
            }
        }

        return trim($html) !== '' ? $html : '<p>Start typing your document here...</p>';
    }

    private static function paragraphToHtml(DOMElement $p, DOMXPath $xpath): string
    {
        // Check for heading style
        $styleNode = $xpath->query('.//w:pPr/w:pStyle/@w:val', $p)->item(0);
        $style = $styleNode ? strtolower((string) $styleNode->nodeValue) : '';

        // Check for list item
        $isList = $xpath->query('.//w:pPr/w:numPr', $p)->length > 0;

        $tag = 'p';
        if (str_contains($style, 'heading1') || str_contains($style, 'title')) {
            $tag = 'h1';
        } elseif (str_contains($style, 'heading2')) {
            $tag = 'h2';
        } elseif (str_contains($style, 'heading3')) {
            $tag = 'h3';
        } elseif ($isList) {
            $tag = 'li';
        }

        // Check alignment
        $jcNode = $xpath->query('.//w:pPr/w:jc/@w:val', $p)->item(0);
        $align = $jcNode ? (string) $jcNode->nodeValue : '';
        $styleAttr = '';
        if ($align === 'center') {
            $styleAttr = ' style="text-align:center;"';
        } elseif ($align === 'right') {
            $styleAttr = ' style="text-align:right;"';
        } elseif ($align === 'both') {
            $styleAttr = ' style="text-align:justify;"';
        }

        $runsHtml = '';
        $runs = $xpath->query('.//w:r', $p);
        foreach ($runs as $r) {
            $textNode = $xpath->query('.//w:t', $r)->item(0);
            if (! $textNode) {
                continue;
            }
            $text = htmlspecialchars((string) $textNode->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($text === '') {
                continue;
            }

            $isBold   = $xpath->query('.//w:rPr/w:b', $r)->length > 0;
            $isItalic = $xpath->query('.//w:rPr/w:i', $r)->length > 0;
            $isUnder  = $xpath->query('.//w:rPr/w:u', $r)->length > 0;
            $isStrike = $xpath->query('.//w:rPr/w:strike', $r)->length > 0;
            $colorNode = $xpath->query('.//w:rPr/w:color/@w:val', $r)->item(0);
            $color    = $colorNode ? '#' . $colorNode->nodeValue : '';

            if ($color && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $text = '<span style="color:' . $color . '">' . $text . '</span>';
            }
            if ($isBold) {
                $text = '<b>' . $text . '</b>';
            }
            if ($isItalic) {
                $text = '<i>' . $text . '</i>';
            }
            if ($isUnder) {
                $text = '<u>' . $text . '</u>';
            }
            if ($isStrike) {
                $text = '<s>' . $text . '</s>';
            }
            $runsHtml .= $text;
        }

        if (trim($runsHtml) === '') {
            return $tag === 'li' ? '<li>&nbsp;</li>' : '<p><br></p>';
        }

        return '<' . $tag . $styleAttr . '>' . $runsHtml . '</' . $tag . '>';
    }

    private static function tableToHtml(DOMElement $tbl, DOMXPath $xpath): string
    {
        $html = '<table class="docx-table" style="width:100%; border-collapse:collapse; margin:14px 0;"><tbody>';
        $rows = $xpath->query('.//w:tr', $tbl);
        foreach ($rows as $ri => $tr) {
            $html .= '<tr>';
            $cells = $xpath->query('.//w:tc', $tr);
            foreach ($cells as $tc) {
                $cellTag = ($ri === 0) ? 'th' : 'td';
                $cellContent = '';
                $paras = $xpath->query('.//w:p', $tc);
                foreach ($paras as $p) {
                    $pContent = '';
                    $runs = $xpath->query('.//w:r', $p);
                    foreach ($runs as $r) {
                        $t = $xpath->query('.//w:t', $r)->item(0);
                        if ($t) {
                            $txt = htmlspecialchars((string) $t->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            if ($xpath->query('.//w:rPr/w:b', $r)->length > 0) {
                                $txt = '<b>' . $txt . '</b>';
                            }
                            if ($xpath->query('.//w:rPr/w:i', $r)->length > 0) {
                                $txt = '<i>' . $txt . '</i>';
                            }
                            $pContent .= $txt;
                        }
                    }
                    $cellContent .= ($cellContent === '' ? '' : '<br>') . $pContent;
                }
                $html .= '<' . $cellTag . ' style="border:1px solid #cbd5e1; padding:6px 10px;">' . ($cellContent !== '' ? $cellContent : '&nbsp;') . '</' . $cellTag . '>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Saves edited HTML content back into a .docx zip file.
     */
    public static function htmlToDocx(string $filePath, string $html): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return false;
        }

        // Parse HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);
        $wXml = '<' . '?xml version="1.0" encoding="UTF-8" standalone="yes"?' . ">\n";
        $wXml .= '<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"'
            . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<w:body>';

        if ($root) {
            foreach ($root->childNodes as $node) {
                $wXml .= self::htmlNodeToDocx($node);
            }
        }

        $wXml .= '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>';
        $wXml .= '</w:body></w:document>';

        $zip->addFromString('word/document.xml', $wXml);
        $zip->close();

        return true;
    }

    private static function htmlNodeToDocx(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $val = trim((string) $node->nodeValue);
            return $val !== '' ? '<w:p><w:r><w:t>' . htmlspecialchars($val, ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p>' : '';
        }

        if (! ($node instanceof DOMElement)) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if ($tag === 'table') {
            $tblXml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
                . '<w:top w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
                . '<w:left w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
                . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
                . '<w:right w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
                . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
                . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="CCCCCC"/>'
                . '</w:tblBorders></w:tblPr>';
            foreach ($node->getElementsByTagName('tr') as $tr) {
                $tblXml .= '<w:tr>';
                foreach ($tr->childNodes as $tc) {
                    if ($tc instanceof DOMElement && in_array(strtolower($tc->tagName), ['td', 'th'], true)) {
                        $tblXml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($tc->textContent, ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p></w:tc>';
                    }
                }
                $tblXml .= '</w:tr>';
            }
            $tblXml .= '</w:tbl>';
            return $tblXml;
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            $listXml = '';
            foreach ($node->getElementsByTagName('li') as $li) {
                $listXml .= '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>'
                    . '<w:r><w:t>' . htmlspecialchars($li->textContent, ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p>';
            }
            return $listXml;
        }

        $pPr = '';
        if ($tag === 'h1') {
            $pPr = '<w:pPr><w:pStyle w:val="Heading1"/></w:pPr>';
        } elseif ($tag === 'h2') {
            $pPr = '<w:pPr><w:pStyle w:val="Heading2"/></w:pPr>';
        } elseif ($tag === 'h3') {
            $pPr = '<w:pPr><w:pStyle w:val="Heading3"/></w:pPr>';
        }

        // Walk children of paragraph for inline styles
        $runs = self::htmlInlineToDocxRuns($node);
        return '<w:p>' . $pPr . $runs . '</w:p>';
    }

    private static function htmlInlineToDocxRuns(\DOMNode $node, bool $bold = false, bool $italic = false, bool $underline = false): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c->nodeType === XML_TEXT_NODE) {
                $txt = htmlspecialchars((string) $c->nodeValue, ENT_XML1, 'UTF-8');
                if ($txt !== '') {
                    $rPr = '';
                    if ($bold) {
                        $rPr .= '<w:b/>';
                    }
                    if ($italic) {
                        $rPr .= '<w:i/>';
                    }
                    if ($underline) {
                        $rPr .= '<w:u w:val="single"/>';
                    }
                    $out .= '<w:r>' . ($rPr !== '' ? '<w:rPr>' . $rPr . '</w:rPr>' : '') . '<w:t xml:space="preserve">' . $txt . '</w:t></w:r>';
                }
            } elseif ($c instanceof DOMElement) {
                $tag = strtolower($c->tagName);
                $isB = $bold || in_array($tag, ['b', 'strong'], true);
                $isI = $italic || in_array($tag, ['i', 'em'], true);
                $isU = $underline || ($tag === 'u');
                if ($tag === 'br') {
                    $out .= '<w:r><w:br/></w:r>';
                } else {
                    $out .= self::htmlInlineToDocxRuns($c, $isB, $isI, $isU);
                }
            }
        }
        return $out;
    }

    /**
     * Reads a .pptx file and extracts slide titles, content paragraphs, and notes.
     * @return array<int, array{id: int, title: string, content: array<string>, notes: string}>
     */
    public static function pptxToSlides(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [
                ['id' => 1, 'title' => 'Presentation', 'content' => ['Unable to read PowerPoint file.'], 'notes' => '']
            ];
        }

        $slideFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/^ppt\/slides\/slide(\d+)\.xml$/', $name, $m)) {
                $slideFiles[(int) $m[1]] = $name;
            }
        }
        ksort($slideFiles);

        if (empty($slideFiles)) {
            $zip->close();
            return [
                ['id' => 1, 'title' => 'Empty Slide Deck', 'content' => ['No slides found in this presentation.'], 'notes' => '']
            ];
        }

        $slides = [];
        foreach ($slideFiles as $idx => $slidePath) {
            $xmlStr = $zip->getFromName($slidePath);
            if (! $xmlStr) {
                continue;
            }

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadXML($xmlStr);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            $title = '';
            $paragraphs = [];

            // Look for shapes
            $shapes = $xpath->query('//p:sp');
            foreach ($shapes as $sp) {
                $isTitleShape = $xpath->query('.//p:nvSpPr/p:nvPr/p:ph[@type="title" or @type="ctrTitle"]', $sp)->length > 0;
                $spParas = $xpath->query('.//p:txBody/a:p', $sp);
                $pTexts = [];
                foreach ($spParas as $p) {
                    $txt = '';
                    $runs = $xpath->query('.//a:r/a:t', $p);
                    foreach ($runs as $r) {
                        $txt .= $r->nodeValue;
                    }
                    $txt = trim($txt);
                    if ($txt !== '') {
                        $pTexts[] = $txt;
                    }
                }

                if ($isTitleShape && $title === '' && ! empty($pTexts)) {
                    $title = implode(' ', $pTexts);
                } else {
                    foreach ($pTexts as $pt) {
                        if ($title === '') {
                            $title = $pt;
                        } else {
                            $paragraphs[] = $pt;
                        }
                    }
                }
            }

            // Look for notes
            $notes = '';
            $notesPath = 'ppt/notesSlides/notesSlide' . $idx . '.xml';
            $notesXml = $zip->getFromName($notesPath);
            if ($notesXml) {
                $nDom = new DOMDocument();
                libxml_use_internal_errors(true);
                $nDom->loadXML($notesXml);
                libxml_clear_errors();
                $nXpath = new DOMXPath($nDom);
                $nXpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                $noteRuns = $nXpath->query('//a:t');
                $noteParts = [];
                foreach ($noteRuns as $nr) {
                    $v = trim((string) $nr->nodeValue);
                    if ($v !== '' && ! is_numeric($v)) {
                        $noteParts[] = $v;
                    }
                }
                $notes = implode("\n", $noteParts);
            }

            $slides[] = [
                'id' => $idx,
                'title' => $title !== '' ? $title : ('Slide ' . $idx),
                'content' => ! empty($paragraphs) ? $paragraphs : ['Click to edit bullet points.'],
                'notes' => $notes,
            ];
        }

        $zip->close();
        return $slides;
    }

    /**
     * Saves updated slide titles and content back into a .pptx file.
     * @param array<int, array{id: int, title: string, content: array<string>, notes?: string}> $slides
     */
    public static function slidesToPptx(string $filePath, array $slides): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return false;
        }

        foreach ($slides as $i => $slide) {
            $slideNum = (int) ($slide['id'] ?? ($i + 1));
            $slidePath = 'ppt/slides/slide' . $slideNum . '.xml';
            $xmlStr = $zip->getFromName($slidePath);
            if (! $xmlStr) {
                // If slide does not exist, try copying slide 1 as template
                $templateXml = $zip->getFromName('ppt/slides/slide1.xml');
                if ($templateXml) {
                    $xmlStr = $templateXml;
                } else {
                    continue;
                }
            }

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadXML($xmlStr);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            $titleSet = false;
            $titleShapes = $xpath->query('//p:sp[.//p:nvSpPr/p:nvPr/p:ph[@type="title" or @type="ctrTitle"]]');
            if ($titleShapes->length > 0) {
                $tSp = $titleShapes->item(0);
                self::setShapeParagraphs($tSp, [(string) ($slide['title'] ?? '')], $dom);
                $titleSet = true;
            }

            $bodyShapes = $xpath->query('//p:sp[.//p:nvSpPr/p:nvPr/p:ph[@type="body"]]');
            $contentList = is_array($slide['content'] ?? null) ? $slide['content'] : [];
            if ($bodyShapes->length > 0) {
                $bSp = $bodyShapes->item(0);
                self::setShapeParagraphs($bSp, $contentList, $dom);
            } elseif (! $titleSet) {
                // Set first shape as title, second as body
                $allShapes = $xpath->query('//p:sp');
                if ($allShapes->length > 0) {
                    self::setShapeParagraphs($allShapes->item(0), [(string) ($slide['title'] ?? '')], $dom);
                }
                if ($allShapes->length > 1) {
                    self::setShapeParagraphs($allShapes->item(1), $contentList, $dom);
                }
            }

            $zip->addFromString($slidePath, $dom->saveXML());
        }

        $zip->close();
        return true;
    }

    private static function setShapeParagraphs(DOMElement $sp, array $lines, DOMDocument $dom): void
    {
        $txBody = null;
        foreach ($sp->childNodes as $child) {
            if ($child->nodeName === 'p:txBody') {
                $txBody = $child;
                break;
            }
        }
        if (! $txBody) {
            return;
        }

        // Keep bodyPr and lstStyle
        $bodyPr = null;
        $lstStyle = null;
        $toRemove = [];
        foreach ($txBody->childNodes as $child) {
            if ($child->nodeName === 'a:bodyPr') {
                $bodyPr = $child;
            } elseif ($child->nodeName === 'a:lstStyle') {
                $lstStyle = $child;
            } elseif ($child->nodeName === 'a:p') {
                $toRemove[] = $child;
            }
        }
        foreach ($toRemove as $rem) {
            $txBody->removeChild($rem);
        }

        foreach ($lines as $line) {
            $pNode = $dom->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'a:p');
            $rNode = $dom->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'a:r');
            $tNode = $dom->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'a:t');
            $tNode->nodeValue = (string) $line;
            $rNode->appendChild($tNode);
            $pNode->appendChild($rNode);
            $txBody->appendChild($pNode);
        }
    }
}
