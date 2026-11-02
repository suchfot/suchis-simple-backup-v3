<?php
/**
 * Streaming SQL export and SQL escaping helpers.
 *
 * @package SuchisSimpleBackup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dump the WordPress database to a SQL file.
 *
 * @param string $tmp_sql Path to the temporary SQL file.
 *
 * @return bool True if the dump was successful, false otherwise.
 */
function Ssbhf_Dump_db(string $tmp_sql): bool
{
    global $wpdb;

    $fh = @fopen($tmp_sql, 'wb');
    if (!$fh) {
        return false;
    }

    fwrite($fh, "-- Suchis Simple Backup DB Dump\n");
    fwrite($fh, "-- Site: " . home_url() . "\n");
    fwrite($fh, "-- Date: " . gmdate('c') . " (UTC)\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\n");
    fwrite($fh, "SET time_zone = '+00:00';\n");
    fwrite($fh, "SET foreign_key_checks = 0;\n\n");

    $tables = $wpdb->get_col('SHOW TABLES');
    if (!is_array($tables) || empty($tables)) {
        fwrite($fh, "\n-- No tables found.\nSET foreign_key_checks = 1;\n");
        fclose($fh);
        return true;
    }

    foreach ($tables as $table) {
        $tq = Ssbhf_bt($table);
        fwrite($fh, "\n-- Table: {$tq}\n");
        fwrite($fh, "DROP TABLE IF EXISTS {$tq};\n");

        $create = $wpdb->get_row("SHOW CREATE TABLE {$tq}", ARRAY_N);
        if (!is_array($create) || empty($create[1])) {
            fwrite($fh, "-- WARNING: SHOW CREATE TABLE failed for {$tq}\n");
            continue;
        }
        fwrite($fh, $create[1] . ";\n\n");

        // Stream rows in smaller chunks
        $offset = 0;
        $limit = 500;

        while (true) {
            $sql = $wpdb->prepare("SELECT * FROM {$tq} LIMIT %d OFFSET %d", $limit, $offset);
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows) || empty($rows)) {
                break;
            }

            $cols = array_keys($rows[0]);
            $col_list = implode(',', array_map('Ssbhf_bt', $cols));

            fwrite($fh, "INSERT INTO {$tq} ({$col_list}) VALUES\n");
            $n = count($rows);
            for ($i = 0; $i < $n; $i++) {
                $r = $rows[$i];
                $vals = [];
                foreach ($cols as $c) {
                    $vals[] = Ssbhf_Sql_value($wpdb, $r[$c] ?? null);
                }
                $line = '(' . implode(',', $vals) . ')';
                $line .= ($i < $n - 1) ? ",\n" : ";\n";
                fwrite($fh, $line);
            }

            $offset += $n;
        }
    }

    fwrite($fh, "\nSET foreign_key_checks = 1;\n");
    fclose($fh);
    return true;
}

/**
 * Export bounded row batches. The SQL offset is committed with the ZIP state.
 * A killed request is retried by truncating SQL to that committed offset.
 * This is not a transactional snapshot: keep writes to the source DB quiet.
 */
function Ssbhf_Dump_db_chunk(array &$state): void
{
    global $wpdb;
    $started = microtime(true);
    if (!isset($state['db'])) {
        $tables = $wpdb->get_col('SHOW TABLES');
        if (!is_array($tables) || !empty($wpdb->last_error)) {
            throw new RuntimeException('Datenbank: Tabellenliste konnte nicht gelesen werden.');
        }
        $state['db'] = ['tables' => $tables, 'table' => 0, 'row' => 0, 'offset' => 0, 'schema' => false];
    }
    $db = &$state['db'];
    $handle = Ssbhf_Zip_handle($state['tmp_sql'], $db['offset']);
    try {
        if ($db['offset'] === 0) {
            Ssbhf_Write_all($handle, "-- Suchis Simple Backup DB Dump\n-- Site: " . home_url() . "\nSET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET foreign_key_checks = 0;\n");
        }
        $batches = 0;
        while ($db['table'] < count($db['tables']) && $batches < 5 && microtime(true) - $started < 1.5 && ftell($handle) - $db['offset'] < 8 * 1024 * 1024) {
            $table = Ssbhf_bt($db['tables'][$db['table']]);
            if (!$db['schema']) {
                $create = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
                if (!is_array($create) || empty($create[1]) || !empty($wpdb->last_error)) {
                    throw new RuntimeException('Datenbank: Schema konnte nicht gelesen werden: ' . $table);
                }
                Ssbhf_Write_all($handle, "\nDROP TABLE IF EXISTS {$table};\n" . $create[1] . ";\n");
                $db['schema'] = true;
            }
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} LIMIT %d OFFSET %d", 100, $db['row']), ARRAY_A);
            if (!is_array($rows) || !empty($wpdb->last_error)) {
                throw new RuntimeException('Datenbank: Zeilen konnten nicht gelesen werden: ' . $table);
            }
            $batches++;
            foreach ($rows as $row) {
                $columns = implode(',', array_map('Ssbhf_bt', array_keys($row)));
                $values = [];
                foreach ($row as $value) {
                    $values[] = Ssbhf_Sql_value($wpdb, $value);
                }
                Ssbhf_Write_all($handle, "INSERT INTO {$table} ({$columns}) VALUES (" . implode(',', $values) . ");\n");
                $db['row']++;
            }
            if (count($rows) < 100) {
                $db['table']++;
                $db['row'] = 0;
                $db['schema'] = false;
            }
        }
        if ($db['table'] === count($db['tables'])) {
            Ssbhf_Write_all($handle, "\nSET foreign_key_checks = 1;\n");
            $state['db_complete'] = true;
        }
        if (!fflush($handle)) {
            throw new RuntimeException('Datenbank-Dump konnte nicht gespeichert werden.');
        }
        $db['offset'] = ftell($handle);
    } finally {
        fclose($handle);
    }
}

/**
 * Escape a database identifier (table or column name) with backticks.
 *
 * @param string $name The identifier to escape.
 *
 * @return string The escaped identifier.
 */
function Ssbhf_bt(string $name): string
{
    $name = str_replace('`', '``', $name);
    return '`' . $name . '`';
}

/**
 * Convert a value to its SQL representation.
 *
 * @param object $wpdb  The WordPress database object.
 * @param mixed  $value The value to convert.
 *
 * @return string The SQL-escaped value.
 */
function Ssbhf_Sql_value($wpdb, $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $s = (string)$value;

    if (isset($wpdb->dbh) && $wpdb->dbh instanceof mysqli) {
        $s = mysqli_real_escape_string($wpdb->dbh, $s);
    } else {
        $s = str_replace(["\\", "\0", "\n", "\r", "\x1a", "'", "\""], ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'", '\\"'], $s);
    }
    return "'" . $s . "'";
}
