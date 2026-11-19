<?php
/**
 * Koneksi MySQLi + helper prepared statement.
 * SEMUA query yang membawa data dari pengguna wajib lewat helper ini
 * (parameter di-bind, bukan digabung ke string SQL) -> mencegah SQL Injection.
 */
declare(strict_types=1);
if (!defined('APP_BOOT')) { http_response_code(403); exit; }

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        // Error MySQLi menjadi exception -> ditangkap handler global (detail masuk log saja)
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $c = config('db');
        $conn = new mysqli(
            (string) ($c['host'] ?? 'localhost'),
            (string) ($c['user'] ?? ''),
            (string) ($c['pass'] ?? ''),
            (string) ($c['name'] ?? ''),
            (int) ($c['port'] ?? 3306)
        );
        $conn->set_charset('utf8mb4');
        $tz = (string) ($c['timezone'] ?? '+07:00');
        if (preg_match('/^[+-]\d{2}:\d{2}$/', $tz) === 1) {
            $conn->query("SET time_zone = '" . $tz . "'");
        }
    }
    return $conn;
}

/** Jalankan prepared statement; kembalikan mysqli_stmt yang sudah di-execute. */
function db_query(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** SELECT banyak baris. */
function db_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_query($sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** SELECT satu baris (null bila tidak ada). */
function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}

/** INSERT/UPDATE/DELETE; kembalikan jumlah baris terpengaruh. */
function db_exec(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_query($sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return (int) $affected;
}

/** INSERT; kembalikan ID baris baru. */
function db_insert(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_query($sql, $types, $params);
    $id = $stmt->insert_id;
    $stmt->close();
    return (int) $id;
}
