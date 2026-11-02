<?php
/**
 * Resumable ZIP64 writer using STORE. No compression state crosses requests.
 * All offsets require 64-bit PHP. A committed checkpoint is authoritative;
 * uncommitted bytes are truncated on retry (including after a hard timeout).
 */
if (!defined('ABSPATH')) {
    exit;
}

function Ssbhf_Write_all($handle, string $bytes): void
{
    $offset = 0;
    while ($offset < strlen($bytes)) {
        $written = fwrite($handle, substr($bytes, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Schreiben fehlgeschlagen; Speicherplatz und Dateisystem prüfen.');
        }
        $offset += $written;
    }
}

function Ssbhf_Zip_u64(int $value): string
{
    return pack('V2', $value & 0xffffffff, $value >> 32);
}

/** Combine two CRC32 values without retaining or rereading earlier file data. */
function Ssbhf_Crc_matrix(array $matrix, int $vector): int
{
    $sum = 0;
    $i = 0;
    while ($vector) {
        if ($vector & 1) {
            $sum ^= $matrix[$i];
        }
        $vector >>= 1;
        $i++;
    }
    return $sum;
}

function Ssbhf_Crc_square(array $matrix): array
{
    $result = [];
    for ($i = 0; $i < 32; $i++) {
        $result[$i] = Ssbhf_Crc_matrix($matrix, $matrix[$i]);
    }
    return $result;
}

function Ssbhf_Crc_combine(int $first, int $second, int $length): int
{
    if ($length === 0) {
        return $first;
    }
    $odd = [0xedb88320];
    for ($i = 1; $i < 32; $i++) {
        $odd[$i] = 1 << ($i - 1);
    }
    $even = Ssbhf_Crc_square($odd);
    $odd = Ssbhf_Crc_square($even);
    do {
        $even = Ssbhf_Crc_square($odd);
        if ($length & 1) {
            $first = Ssbhf_Crc_matrix($even, $first);
        }
        $length >>= 1;
        if (!$length) {
            break;
        }
        $odd = Ssbhf_Crc_square($even);
        if ($length & 1) {
            $first = Ssbhf_Crc_matrix($odd, $first);
        }
        $length >>= 1;
    } while ($length);
    return ($first ^ $second) & 0xffffffff;
}

/** Reopen at the last persisted boundary, discarding an interrupted write. */
function Ssbhf_Zip_handle(string $path, int $offset)
{
    if (!is_file($path) && $offset !== 0) {
        throw new RuntimeException('Die unvollständige ZIP-Datei fehlt.');
    }
    $handle = fopen($path, 'c+b');
    if (!$handle || fstat($handle)['size'] < $offset || !ftruncate($handle, $offset) || fseek($handle, $offset) !== 0) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('ZIP-Checkpoint konnte nicht wiederhergestellt werden.');
    }
    return $handle;
}

/** Write at most $budget payload bytes, even in the middle of a large file. */
function Ssbhf_Zip_entry(array &$state, array $entry, $zip, $central, int &$budget): bool
{
    if (is_link($entry['path']) || !is_file($entry['path'])) {
        throw new RuntimeException('Quelldatei fehlt oder ist kein reguläres Dokument: ' . $entry['relative_path']);
    }
    if (empty($state['entry'])) {
        $source = fopen($entry['path'], 'rb');
        if (!$source) {
            throw new RuntimeException('Datei nicht lesbar: ' . $entry['relative_path']);
        }
        $stat = fstat($source);
        fclose($source);
        $name = str_replace('\\', '/', $entry['relative_path']);
        if ($name === '' || strlen($name) > 65535 || strpos($name, "\0") !== false || $name[0] === '/' || in_array('..', explode('/', $name), true)) {
            throw new RuntimeException('Ungültiger ZIP-Dateiname.');
        }
        $stamp = getdate($stat['mtime']);
        $time = ($stamp['hours'] << 11) | ($stamp['minutes'] << 5) | (int) floor($stamp['seconds'] / 2);
        $date = ((max(1980, min(2107, $stamp['year'])) - 1980) << 9) | ($stamp['mon'] << 5) | $stamp['mday'];
        $state['entry'] = [
            'time' => $time, 'date' => $date,
            'path' => $entry['path'], 'name' => $name, 'size' => $stat['size'],
            'mtime' => $stat['mtime'], 'offset' => 0, 'crc' => 0,
            'local' => $state['zip_offset'],
        ];
        // UTF-8, STORE and ZIP64 sizes; CRC is patched on completion.
        $header = pack('VvvvvvVVVvv', 0x04034b50, 45, 0x800, 0, $time, $date, 0, 0xffffffff, 0xffffffff, strlen($name), 20)
            . $name . pack('vv', 1, 16) . Ssbhf_Zip_u64($stat['size']) . Ssbhf_Zip_u64($stat['size']);
        Ssbhf_Write_all($zip, $header);
        $state['zip_offset'] += strlen($header);
    }
    $current = &$state['entry'];
    $source = fopen($current['path'], 'rb');
    if (!$source) {
        throw new RuntimeException('Quelldatei konnte nicht geöffnet werden: ' . $current['name']);
    }
    try {
        $stat = fstat($source);
        if ($stat['size'] !== $current['size'] || $stat['mtime'] !== $current['mtime'] || fseek($source, $current['offset']) !== 0) {
            throw new RuntimeException('Datei wurde während der Sicherung verändert: ' . $current['name']);
        }
        while ($budget > 0 && $current['offset'] < $current['size']) {
            $data = fread($source, min(1048576, $budget, $current['size'] - $current['offset']));
            if ($data === false || $data === '') {
                throw new RuntimeException('Lesefehler: ' . $current['name']);
            }
            Ssbhf_Write_all($zip, $data);
            $length = strlen($data);
            $current['crc'] = Ssbhf_Crc_combine($current['crc'], crc32($data), $length);
            $current['offset'] += $length;
            $state['zip_offset'] += $length;
            $budget -= $length;
        }
    } finally {
        fclose($source);
    }
    if ($current['offset'] !== $current['size']) {
        return false;
    }
    // Patch CRC; retrying from an earlier checkpoint safely patches it again.
    if (fseek($zip, $current['local'] + 14) !== 0) {
        throw new RuntimeException('ZIP-Header konnte nicht aktualisiert werden.');
    }
    Ssbhf_Write_all($zip, pack('V', $current['crc']));
    if (fseek($zip, $state['zip_offset']) !== 0) {
        throw new RuntimeException('ZIP-Schreibposition konnte nicht wiederhergestellt werden.');
    }
    $record = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 45, 45, 0x800, 0, $current['time'] ?? 0, $current['date'] ?? 33, $current['crc'], 0xffffffff, 0xffffffff,
        strlen($current['name']), 28, 0, 0, 0, 0, 0xffffffff)
        . $current['name'] . pack('vv', 1, 24) . Ssbhf_Zip_u64($current['size'])
        . Ssbhf_Zip_u64($current['size']) . Ssbhf_Zip_u64($current['local']);
    Ssbhf_Write_all($central, $record);
    $state['central_size'] += strlen($record);
    $state['entry_count']++;
    $state['entry'] = null;
    return true;
}

/** Copy the central directory in bounded packets, then write ZIP64 end records. */
function Ssbhf_Zip_finish(array &$state, $zip, $central, int $budget): void
{
    if (!isset($state['central_start'])) {
        $state['central_start'] = $state['zip_offset'];
        $state['central_copied'] = 0;
    }
    fseek($central, $state['central_copied']);
    $remaining = min($budget, $state['central_size'] - $state['central_copied']);
    while ($remaining > 0) {
        $data = fread($central, min(1048576, $remaining));
        if ($data === false || $data === '') {
            throw new RuntimeException('ZIP-Verzeichnis konnte nicht gelesen werden.');
        }
        Ssbhf_Write_all($zip, $data);
        $state['central_copied'] += strlen($data);
        $state['zip_offset'] += strlen($data);
        $remaining -= strlen($data);
    }
    if ($state['central_copied'] !== $state['central_size']) {
        return;
    }
    $end_offset = $state['zip_offset'];
    $end = pack('V', 0x06064b50) . Ssbhf_Zip_u64(44) . pack('vvVV', 45, 45, 0, 0)
        . Ssbhf_Zip_u64($state['entry_count']) . Ssbhf_Zip_u64($state['entry_count'])
        . Ssbhf_Zip_u64($state['central_size']) . Ssbhf_Zip_u64($state['central_start'])
        . pack('VV', 0x07064b50, 0) . Ssbhf_Zip_u64($end_offset) . pack('V', 1)
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 0xffff, 0xffff, 0xffffffff, 0xffffffff, 0);
    Ssbhf_Write_all($zip, $end);
    $state['zip_offset'] += strlen($end);
    $state['phase'] = 'verify';
    $state['verify_cursor'] = 0;
    $state['verified_entries'] = 0;
}

/** Verify stored payload CRCs incrementally, independently of original sources. */
function Ssbhf_Zip_verify(array &$state, $zip, $central, int $budget): void
{
    while ($budget > 0 && $state['verify_cursor'] < $state['central_size']) {
        if (empty($state['verify_entry'])) {
            fseek($central, $state['verify_cursor']);
            $header = fread($central, 46);
            if (strlen($header) !== 46 || unpack('V', $header)[1] !== 0x02014b50) {
                throw new RuntimeException('Ungültiges ZIP-Verzeichnis.');
            }
            $fields = unpack('Vcrc', substr($header, 16, 4));
            $length = unpack('vname/vextra/vcomment', substr($header, 28, 6));
            $name = fread($central, $length['name']);
            $extra = fread($central, $length['extra']);
            $sizes = unpack('Psize/Pcompressed/Plocal', substr($extra, 4));
            $state['verify_entry'] = [
                'name' => $name, 'size' => $sizes['size'], 'crc' => $fields['crc'],
                'data' => $sizes['local'] + 30 + strlen($name) + 20,
                'offset' => 0, 'actual_crc' => 0,
                'next' => $state['verify_cursor'] + 46 + $length['name'] + $length['extra'] + $length['comment'],
            ];
        }
        $entry = &$state['verify_entry'];
        fseek($zip, $entry['data'] + $entry['offset']);
        while ($budget > 0 && $entry['offset'] < $entry['size']) {
            $data = fread($zip, min(1048576, $budget, $entry['size'] - $entry['offset']));
            if ($data === false || $data === '') {
                throw new RuntimeException('ZIP-Prüfung: Lesefehler bei ' . $entry['name']);
            }
            $entry['actual_crc'] = Ssbhf_Crc_combine($entry['actual_crc'], crc32($data), strlen($data));
            $entry['offset'] += strlen($data);
            $budget -= strlen($data);
        }
        if ($entry['offset'] < $entry['size']) {
            return;
        }
        if ($entry['crc'] !== $entry['actual_crc']) {
            throw new RuntimeException('ZIP-Prüfung: CRC-Fehler bei ' . $entry['name']);
        }
        $state['verify_cursor'] = $entry['next'];
        $state['verified_entries']++;
        $state['verify_entry'] = null;
    }
    if ($state['verify_cursor'] === $state['central_size']) {
        $state['phase'] = 'publish';
    }
}
