<?php
/**
 * Kết nối Cơ sở dữ liệu PostgreSQL & Ghi Log Tập trung (Loki/Promtail)
 * Hệ thống Quản lý Thư viện - DTC241200004
 */

function write_app_log(string $level, string $action, string $message, array $context = []): void
{
    $logEntry = [
        'timestamp'  => date('c'),
        'student_id' => getenv('STUDENT_ID') ?: 'dtc241200004',
        'service'    => 'library-web-app',
        'level'      => strtoupper($level),
        'action'     => strtoupper($action),
        'message'    => $message,
        'client_ip'  => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        'context'    => $context,
    ];

    $jsonLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    // Ghi vào file log được mount với Promtail
    $logFile = '/var/log/app/library.log';
    if (is_dir('/var/log/app') && is_writable('/var/log/app')) {
        @file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);
    }

    // Đồng thời xuất ra stderr của container để Docker log driver thu thập
    @file_put_contents('php://stderr', $jsonLine);
}

function get_db_connection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'postgres';
    $port = getenv('DB_PORT') ?: '5432';
    $db   = getenv('POSTGRES_DB') ?: 'library_db';
    $user = getenv('POSTGRES_USER') ?: 'lib_admin_dtc241200004';
    $pass = getenv('POSTGRES_PASSWORD') ?: '';

    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        write_app_log('ERROR', 'DB_CONNECT_FAILED', 'Không thể kết nối PostgreSQL Database', [
            'error' => $e->getMessage(),
            'host'  => $host,
            'db'    => $db,
        ]);
        throw $e;
    }
}
