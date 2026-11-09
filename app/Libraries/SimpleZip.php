<?php

namespace App\Libraries;

/**
 * Minimal read-only zip reader (stored and deflated entries) for .xlsx files, so the PHP "zip" extension is not required.
 * Same two calls as \ZipArchive that the importers use: open() and getFromName().
 */
class SimpleZip
{
    private string $data = '';

    /** @var array<string, array{method: int, csize: int, size: int, offset: int}> */
    private array $entries = [];

    public function open(string $path): bool
    {
        $raw = @file_get_contents($path);
        if ($raw === false || strlen($raw) < 22) {
            return false;
        }
        $this->data    = $raw;
        $this->entries = [];

        // end-of-central-directory record: signature 0x06054b50, the last one in the file
        $eocd = strrpos($raw, "PK\x05\x06");
        if ($eocd === false) {
            return false;
        }
        $count  = unpack('v', substr($raw, $eocd + 10, 2))[1];
        $offset = unpack('V', substr($raw, $eocd + 16, 4))[1];

        for ($i = 0; $i < $count; $i++) {
            if (substr($raw, $offset, 4) !== "PK\x01\x02") {
                return false;
            }
            $h = unpack('vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen', substr($raw, $offset + 10, 24));
            $local = unpack('V', substr($raw, $offset + 42, 4))[1];
            $name  = str_replace('\\', '/', substr($raw, $offset + 46, $h['nlen']));   // some tools write backslashes
            $this->entries[$name] = ['method' => $h['method'], 'csize' => $h['csize'], 'size' => $h['size'], 'offset' => $local];
            $offset += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
        }

        return true;
    }

    /** @return string|false the file's bytes, or false when the entry is missing or unreadable */
    public function getFromName(string $name): string|false
    {
        $e = $this->entries[$name] ?? null;
        if ($e === null || substr($this->data, $e['offset'], 4) !== "PK\x03\x04") {
            return false;
        }
        $l     = unpack('vnlen/velen', substr($this->data, $e['offset'] + 26, 4));
        $start = $e['offset'] + 30 + $l['nlen'] + $l['elen'];
        $bytes = substr($this->data, $start, $e['csize']);

        if ($e['method'] === 0) {
            return $bytes;
        }
        if ($e['method'] === 8) {
            $out = @gzinflate($bytes);

            return $out === false ? false : $out;
        }

        return false;
    }

    public function close(): void
    {
        $this->data    = '';
        $this->entries = [];
    }
}
