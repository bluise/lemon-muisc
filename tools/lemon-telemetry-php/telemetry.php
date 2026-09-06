<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Browser open (GET): friendly page — real clients must POST JSON
if ($method === 'GET' || $method === 'HEAD') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    if ($method === 'HEAD') {
        exit;
    }
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>柠檬音乐 · 日活上报</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto;padding:0 1.25rem;line-height:1.6;color:#1a222c}';
    echo '@media(prefers-color-scheme:dark){body{background:#0f1419;color:#e7ecf1}}a{color:#3d9a6a}</style></head><body>';
    echo '<h1>日活上报接口</h1>';
    echo '<p>此地址仅供柠檬音乐服务端 <strong>POST</strong> 匿名统计，浏览器直接打开不会写入数据。</p>';
    echo '<p>查看看板请打开：<a href="stats.php">stats.php</a></p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

try {
    $cfg = lemon_telemetry_config();
    lemon_telemetry_check_secret($cfg);

    $data = lemon_telemetry_json_input();
    $installId = trim((string)($data['installId'] ?? ''));
    $day = trim((string)($data['day'] ?? ''));
    $app = trim((string)($data['app'] ?? 'lemon-music'));
    $version = trim((string)($data['version'] ?? ''));
    $activeMinutes = (int)($data['activeMinutes'] ?? 0);
    $onlineSessions = (int)($data['onlineSessions'] ?? 0);

    if ($installId === '' || !preg_match('/^[a-f0-9-]{36}$/i', $installId)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_install_id']);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_day']);
        exit;
    }
    if ($activeMinutes < 0) {
        $activeMinutes = 0;
    }
    if ($activeMinutes > 24 * 60) {
        $activeMinutes = 24 * 60;
    }
    if ($onlineSessions < 0) {
        $onlineSessions = 0;
    }
    if ($onlineSessions > 1000) {
        $onlineSessions = 1000;
    }
    if (strlen($version) > 32) {
        $version = substr($version, 0, 32);
    }
    if ($app === '') {
        $app = 'lemon-music';
    }
    if (strlen($app) > 64) {
        $app = substr($app, 0, 64);
    }

    $pdo = lemon_telemetry_db();
    $now = gmdate('c');

    // Idempotent upsert: keep the larger active_minutes (client sends day cumulative)
    $stmt = $pdo->prepare(
        'INSERT INTO daily_usage (day, install_id, app, version, active_minutes, online_sessions, updated_at)
         VALUES (:day, :install_id, :app, :version, :active_minutes, :online_sessions, :updated_at)
         ON CONFLICT(day, install_id) DO UPDATE SET
           app = excluded.app,
           version = excluded.version,
           active_minutes = CASE
             WHEN excluded.active_minutes > daily_usage.active_minutes THEN excluded.active_minutes
             ELSE daily_usage.active_minutes
           END,
           online_sessions = excluded.online_sessions,
           updated_at = excluded.updated_at'
    );
    $stmt->execute([
        ':day' => $day,
        ':install_id' => $installId,
        ':app' => $app,
        ':version' => $version,
        ':active_minutes' => $activeMinutes,
        ':online_sessions' => $onlineSessions,
        ':updated_at' => $now,
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
