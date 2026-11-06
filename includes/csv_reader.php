<?php
declare(strict_types=1);

/**
 * Simple CSV reader for master-data imports.
 * Supports UTF-8 BOM and comma/semicolon/tab delimiters.
 */
function csv_read_rows(string $file): array
{
    $fh = @fopen($file, 'rb');
    if ($fh === false) {
        throw new RuntimeException('File CSV tidak dapat dibuka.');
    }

    try {
        $first = fgets($fh);
        if ($first === false) {
            throw new RuntimeException('File CSV kosong.');
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = csv_detect_delimiter($first);
        rewind($fh);

        $rows = [];
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            if ($row === [null]) continue;
            if (count($row) === 1 && trim((string)$row[0]) === '') continue;
            $row = array_map(static function ($value): string {
                $value = (string)($value ?? '');
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
            }, $row);
            $rows[] = $row;
        }
        return $rows;
    } finally {
        fclose($fh);
    }
}

function csv_detect_delimiter(string $line): string
{
    $candidates = [',', ';', "\t"];
    $best = ',';
    $bestCount = -1;
    foreach ($candidates as $delimiter) {
        $count = count(str_getcsv($line, $delimiter, '"', '\\'));
        if ($count > $bestCount) {
            $bestCount = $count;
            $best = $delimiter;
        }
    }
    return $best;
}

function csv_normalize_header(array $row): array
{
    $out = [];
    foreach ($row as $value) {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? $value;
        $out[] = trim($value, '_');
    }
    while ($out !== [] && end($out) === '') array_pop($out);
    return $out;
}
