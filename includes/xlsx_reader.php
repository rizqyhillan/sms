<?php
declare(strict_types=1);

/**
 * XLSX reader ringan untuk import tabel.
 * Mendukung Excel/LibreOffice, inlineStr, shared strings, dan hasil konversi
 * yang menyimpan beberapa kolom dalam satu sel. Tidak membutuhkan PhpSpreadsheet.
 */
function xlsx_read_rows(string $file): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif. Aktifkan ekstensi zip pada PHP/XAMPP lalu coba lagi.');
    }

    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        throw new RuntimeException('File XLSX tidak dapat dibuka. Pastikan file Excel valid.');
    }

    try {
        $shared = xlsx_read_shared_strings($zip);
        $sheetPath = xlsx_find_first_sheet_path($zip);
        if ($sheetPath === '') {
            throw new RuntimeException('Worksheet XLSX tidak ditemukan. Pastikan file benar-benar format .xlsx, bukan .xls.');
        }

        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false || trim($sheetXml) === '') {
            throw new RuntimeException('Worksheet XLSX tidak dapat dibaca: ' . $sheetPath);
        }

        // DOM/SimpleXML dipakai bila tersedia, tetapi hasil yang benar-benar
        // berisi sel diprioritaskan agar parser tidak berhenti pada baris kosong.
        $best = [];
        if (class_exists('DOMDocument')) {
            try { $best = xlsx_parse_sheet_dom($sheetXml, $shared); } catch (Throwable $e) { $best = []; }
            if (xlsx_rows_have_values($best)) return $best;
        }
        if (function_exists('simplexml_load_string')) {
            try { $best = xlsx_parse_sheet_simplexml($sheetXml, $shared); } catch (Throwable $e) { $best = []; }
            if (xlsx_rows_have_values($best)) return $best;
        }

        // Fallback tanpa ekstensi XML: cukup kuat untuk worksheet XLSX standar.
        $best = xlsx_parse_sheet_regex($sheetXml, $shared);
        if ($best !== []) return $best;

        return [];
    } finally {
        $zip->close();
    }
}

function xlsx_rows_have_values(array $rows): bool
{
    foreach ($rows as $row) {
        foreach ((array)$row as $value) {
            if (trim((string)$value) !== '') return true;
        }
    }
    return false;
}

function xlsx_read_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false || trim($xml) === '') return [];

    $shared = [];
    if (class_exists('DOMDocument')) {
        try {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = true;
            if (@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                $xp = new DOMXPath($dom);
                foreach ($xp->query('//*[local-name()="si"]') as $si) {
                    $text = '';
                    foreach ($xp->query('.//*[local-name()="t"]', $si) as $t) $text .= $t->textContent;
                    $shared[] = $text;
                }
                if ($shared !== []) return $shared;
            }
        } catch (Throwable $e) {}
    }
    if (function_exists('simplexml_load_string')) {
        try {
            $sx = @simplexml_load_string($xml);
            if ($sx !== false) {
                $ns = $sx->getDocNamespaces(true);
                $uri = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                foreach ($sx->children($uri)->si as $si) {
                    $text = '';
                    foreach ($si->children($uri)->t as $t) $text .= (string)$t;
                    foreach ($si->children($uri)->r as $r) foreach ($r->children($uri)->t as $t) $text .= (string)$t;
                    $shared[] = $text !== '' ? $text : trim((string)$si);
                }
                if ($shared !== []) return $shared;
            }
        } catch (Throwable $e) {}
    }

    // Fallback regex untuk sharedStrings.xml.
    if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/is', $xml, $items)) {
        foreach ($items[1] as $item) {
            $text = '';
            if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $item, $tm)) {
                foreach ($tm[1] as $part) $text .= xlsx_xml_decode($part);
            }
            $shared[] = $text;
        }
    }
    return $shared;
}

function xlsx_find_first_sheet_path(ZipArchive $zip): string
{
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

    if ($workbookXml !== false && $relsXml !== false) {
        $rid = '';
        if (preg_match('/<sheet\b[^>]*\br:id=["\']([^"\']+)["\'][^>]*>/i', $workbookXml, $m)) $rid = trim($m[1]);
        if ($rid !== '' && preg_match_all('/<Relationship\b[^>]*>/i', $relsXml, $rels)) {
            foreach ($rels[0] as $rel) {
                if (!preg_match('/\bId=["\']([^"\']+)["\']/i', $rel, $mi) || $mi[1] !== $rid) continue;
                if (!preg_match('/\bTarget=["\']([^"\']+)["\']/i', $rel, $mt)) continue;
                $target = str_replace('\\', '/', trim($mt[1]));
                $candidate = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . ltrim($target, '/');
                $parts = [];
                foreach (explode('/', $candidate) as $part) {
                    if ($part === '' || $part === '.') continue;
                    if ($part === '..') array_pop($parts); else $parts[] = $part;
                }
                $candidate = implode('/', $parts);
                if ($zip->locateName($candidate) !== false) return $candidate;
            }
        }
    }

    $candidates = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat || empty($stat['name'])) continue;
        $name = str_replace('\\', '/', $stat['name']);
        if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) $candidates[] = $name;
    }
    natsort($candidates);
    return $candidates ? (string)reset($candidates) : '';
}

function xlsx_parse_sheet_simplexml(string $xml, array $shared): array
{
    $sheet = @simplexml_load_string($xml);
    if ($sheet === false) return [];
    $ns = $sheet->getDocNamespaces(true);
    $uri = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $main = $sheet->children($uri);
    $rows = [];
    foreach ($main->sheetData->children($uri)->row as $rowNode) {
        $values = [];
        foreach ($rowNode->children($uri)->c as $cell) {
            $ref = strtoupper((string)$cell['r']);
            if (!preg_match('/([A-Z]+)\d+/', $ref, $m)) continue;
            $index = xlsx_column_index($m[1]);
            while (count($values) <= $index) $values[] = '';
            $type = (string)$cell['t'];
            $children = $cell->children($uri);
            $value = '';
            if ($type === 's') {
                $idx = (int)($children->v ?? 0);
                $value = $shared[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                foreach ($children->is->children($uri)->t as $t) $value .= (string)$t;
                foreach ($children->is->children($uri)->r as $r) foreach ($r->children($uri)->t as $t) $value .= (string)$t;
            } else {
                $value = (string)($children->v ?? '');
            }
            $values[$index] = trim($value);
        }
        $rows[] = $values;
    }
    return $rows;
}

function xlsx_parse_sheet_dom(string $xml, array $shared): array
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) return [];
    $xp = new DOMXPath($dom);
    $rows = [];
    foreach ($xp->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $rowNode) {
        $values = [];
        foreach ($xp->query('./*[local-name()="c"]', $rowNode) as $cell) {
            $ref = strtoupper($cell->getAttribute('r'));
            if (!preg_match('/([A-Z]+)\d+/', $ref, $m)) continue;
            $index = xlsx_column_index($m[1]);
            while (count($values) <= $index) $values[] = '';
            $type = $cell->getAttribute('t');
            $value = '';
            if ($type === 's') {
                $v = $xp->query('./*[local-name()="v"]', $cell)->item(0);
                $value = $v ? ($shared[(int)$v->textContent] ?? '') : '';
            } elseif ($type === 'inlineStr') {
                foreach ($xp->query('.//*[local-name()="t"]', $cell) as $t) $value .= $t->textContent;
            } else {
                $v = $xp->query('./*[local-name()="v"]', $cell)->item(0);
                $value = $v ? $v->textContent : '';
            }
            $values[$index] = trim($value);
        }
        $rows[] = $values;
    }
    return $rows;
}

function xlsx_parse_sheet_regex(string $xml, array $shared): array
{
    $rows = [];
    if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/is', $xml, $rowMatches)) return [];
    foreach ($rowMatches[1] as $rowXml) {
        $values = [];
        if (!preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/is', $rowXml, $cells, PREG_SET_ORDER)) {
            $rows[] = [];
            continue;
        }
        foreach ($cells as $cell) {
            $attrs = $cell[1] ?? '';
            $body = $cell[2] ?? '';
            if (!preg_match('/\br=["\']([A-Z]+)\d+["\']/i', $attrs, $rm)) continue;
            $index = xlsx_column_index(strtoupper($rm[1]));
            while (count($values) <= $index) $values[] = '';
            $type = '';
            if (preg_match('/\bt=["\']([^"\']+)["\']/i', $attrs, $tm)) $type = $tm[1];
            $value = '';
            if ($type === 's') {
                if (preg_match('/<v\b[^>]*>(.*?)<\/v>/is', $body, $vm)) $value = $shared[(int)trim($vm[1])] ?? '';
            } elseif ($type === 'inlineStr') {
                if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $body, $tm)) foreach ($tm[1] as $part) $value .= xlsx_xml_decode($part);
            } elseif (preg_match('/<v\b[^>]*>(.*?)<\/v>/is', $body, $vm)) {
                $value = xlsx_xml_decode($vm[1]);
            } elseif (preg_match('/<t\b[^>]*>(.*?)<\/t>/is', $body, $tm)) {
                $value = xlsx_xml_decode($tm[1]);
            }
            $values[$index] = trim($value);
        }
        $rows[] = $values;
    }
    return $rows;
}

function xlsx_xml_decode(string $value): string
{
    return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsx_column_index(string $col): int
{
    $index = 0;
    for ($i = 0, $len = strlen($col); $i < $len; $i++) $index = $index * 26 + (ord($col[$i]) - 64);
    return $index - 1;
}

function xlsx_normalize_header(array $row): array
{
    $row = array_map(static fn($v): string => trim((string)$v), $row);
    if (count($row) === 1 && strpbrk($row[0], ',;') !== false) {
        $row = preg_split('/\s*[;,]\s*/', $row[0]) ?: $row;
    }
    return array_map(static function ($v): string {
        $v = strtolower(trim((string)$v));
        $v = preg_replace('/^\xEF\xBB\xBF/', '', $v) ?? $v;
        return trim(preg_replace('/[^a-z0-9_]+/', '_', $v) ?? $v, '_');
    }, $row);
}

function xlsx_flexible_row(array $row, int $expectedColumns): array
{
    if (count($row) === 1 && strpbrk((string)$row[0], ',;') !== false) {
        $parts = preg_split('/\s*[;,]\s*/', (string)$row[0]);
        if ($parts !== false && count($parts) === $expectedColumns) return $parts;
    }
    return $row;
}
