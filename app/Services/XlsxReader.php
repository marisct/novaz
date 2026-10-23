<?php

namespace App\Services;

use RuntimeException;
use XMLReader;

/**
 * Reads the rows of one sheet of an Excel .xlsx workbook.
 * Needs only zlib and XMLReader, so it works on hosts without the PHP zip extension.
 */
class XlsxReader
{
    public static function isXlsx(string $path): bool
    {
        return file_get_contents($path, false, null, 0, 4) === "PK\x03\x04";
    }

    /**
     * Rows of the sheet called $preferredSheet, or of the first sheet when there is none.
     *
     * @return array<int, array<int, string|null>>
     */
    public function rows(string $path, string $preferredSheet = 'Questions'): array
    {
        $zip = file_get_contents($path);
        if ($zip === false) {
            throw new RuntimeException('The Excel file could not be read.');
        }
        $entries = $this->entries($zip);
        $sheetPath = $this->sheetPath($zip, $entries, $preferredSheet);
        $strings = isset($entries['xl/sharedStrings.xml'])
            ? $this->sharedStrings($this->extract($zip, $entries['xl/sharedStrings.xml']))
            : [];

        $rows = [];
        $reader = new XMLReader;
        $reader->XML($this->extract($zip, $entries[$sheetPath]), null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        unset($zip);
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $node = simplexml_load_string($reader->readOuterXml());
            $row = [];
            $next = 0;
            foreach ($node->c ?? [] as $cell) {
                $index = isset($cell['r']) ? $this->columnIndex((string) $cell['r']) : $next;
                $next = $index + 1;
                $type = (string) $cell['t'];
                if ($type === 's') {
                    $value = $strings[(int) $cell->v] ?? null;
                } elseif ($type === 'inlineStr') {
                    $value = $this->richText($cell->is);
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : null;
                }
                $row[$index] = $value;
            }
            if ($row === []) {
                continue;
            }
            $rows[] = array_replace(array_fill(0, max(array_keys($row)) + 1, null), $row);
        }
        $reader->close();

        return $rows;
    }

    /**
     * @return array<string, array{method:int, size:int, offset:int}>
     */
    private function entries(string $zip): array
    {
        $end = strrpos($zip, "PK\x05\x06");
        if ($end === false) {
            throw new RuntimeException('This is not a valid .xlsx file.');
        }
        $info = unpack('vcount/Vsize/Voffset', substr($zip, $end + 10, 10));
        $entries = [];
        $at = $info['offset'];
        for ($i = 0; $i < $info['count']; $i++) {
            if (substr($zip, $at, 4) !== "PK\x01\x02") {
                break;
            }
            $head = unpack('vmethod/vtime/vdate/Vcrc/Vsize/Vfull/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset', substr($zip, $at + 10, 36));
            $name = substr($zip, $at + 46, $head['name']);
            $entries[$name] = ['method' => $head['method'], 'size' => $head['size'], 'offset' => $head['offset']];
            $at += 46 + $head['name'] + $head['extra'] + $head['comment'];
        }

        return $entries;
    }

    /**
     * @param  array{method:int, size:int, offset:int}  $entry
     */
    private function extract(string $zip, array $entry): string
    {
        $local = unpack('vname/vextra', substr($zip, $entry['offset'] + 26, 4));
        $data = substr($zip, $entry['offset'] + 30 + $local['name'] + $local['extra'], $entry['size']);
        if ($entry['method'] === 0) {
            return $data;
        }
        $plain = $entry['method'] === 8 ? @gzinflate($data) : false;
        if ($plain === false) {
            throw new RuntimeException('The Excel file is damaged or uses an unsupported compression.');
        }

        return $plain;
    }

    /**
     * @param  array<string, array{method:int, size:int, offset:int}>  $entries
     */
    private function sheetPath(string $zip, array $entries, string $preferredSheet): string
    {
        $fallback = collect(array_keys($entries))
            ->filter(fn (string $name) => str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml'))
            ->sort(SORT_NATURAL)->first();
        if (! isset($entries['xl/workbook.xml'], $entries['xl/_rels/workbook.xml.rels'])) {
            return $fallback ?? throw new RuntimeException('The Excel file has no worksheet.');
        }

        $targets = [];
        $rels = simplexml_load_string($this->extract($zip, $entries['xl/_rels/workbook.xml.rels']));
        foreach ($rels->Relationship ?? [] as $rel) {
            $target = ltrim((string) $rel['Target'], '/');
            $targets[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        $first = null;
        $workbook = simplexml_load_string($this->extract($zip, $entries['xl/workbook.xml']));
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = $targets[$id] ?? null;
            if (! $target || ! isset($entries[$target])) {
                continue;
            }
            $first ??= $target;
            if (strcasecmp(trim((string) $sheet['name']), $preferredSheet) === 0) {
                return $target;
            }
        }

        return $first ?? $fallback ?? throw new RuntimeException('The Excel file has no worksheet.');
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(string $xml): array
    {
        $strings = [];
        $reader = new XMLReader;
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $strings[] = $this->richText(simplexml_load_string($reader->readOuterXml()));
            }
        }
        $reader->close();

        return $strings;
    }

    /** Text of a string item: plain <t>, or the joined <r><t> runs of formatted text. */
    private function richText(?\SimpleXMLElement $item): string
    {
        if ($item === null) {
            return '';
        }
        $text = (string) $item->t;
        foreach ($item->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function columnIndex(string $reference): int
    {
        $index = 0;
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z]/', '', $reference))) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }
}
