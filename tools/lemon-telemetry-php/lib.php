<?php
declare(strict_types=1);

function lemon_telemetry_config(): array
{
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/config.example.php';
    }
    $cfg = require $path;
    if (!is_array($cfg)) {
        throw new RuntimeException('Invalid config.php');
    }
    return $cfg;
}

function lemon_telemetry_db(): PDO
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $dbPath = $dir . '/telemetry.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS daily_usage (
            day TEXT NOT NULL,
            install_id TEXT NOT NULL,
            app TEXT NOT NULL DEFAULT \'lemon-music\',
            version TEXT NOT NULL DEFAULT \'\',
            active_minutes INTEGER NOT NULL DEFAULT 0,
            online_sessions INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (day, install_id)
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_daily_usage_day ON daily_usage(day)');
    return $pdo;
}

/** @return array<string, mixed> */
function lemon_telemetry_json_input(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        $cached = [];
        return $cached;
    }
    $data = json_decode($raw, true);
    $cached = is_array($data) ? $data : [];
    return $cached;
}

function lemon_telemetry_check_secret(array $cfg): void
{
    $expected = (string)($cfg['telemetry_secret'] ?? '');
    $header = $_SERVER['HTTP_X_LEMON_TELEMETRY_KEY'] ?? '';
    $input = lemon_telemetry_json_input();
    $bodyKey = isset($input['secret']) ? (string)$input['secret'] : '';
    $got = $header !== '' ? $header : $bodyKey;
    if ($expected === '' || !hash_equals($expected, $got)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }
}
