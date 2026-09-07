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
            peak_online_sessions INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (day, install_id)
        )'
    );
    lemon_telemetry_ensure_columns($pdo);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_daily_usage_day ON daily_usage(day)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_daily_usage_version ON daily_usage(version)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_daily_usage_updated ON daily_usage(updated_at)');
    return $pdo;
}

function lemon_telemetry_ensure_columns(PDO $pdo): void
{
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(daily_usage)') as $row) {
        $cols[(string)$row['name']] = true;
    }
    if (empty($cols['peak_online_sessions'])) {
        $pdo->exec('ALTER TABLE daily_usage ADD COLUMN peak_online_sessions INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('UPDATE daily_usage SET peak_online_sessions = MAX(online_sessions, 0) WHERE peak_online_sessions = 0');
    }
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

function lemon_telemetry_today(): string
{
    return date('Y-m-d');
}

/** Heartbeat interval is 10 min; treat reports within 25 min as still online. */
function lemon_telemetry_online_cutoff_iso(int $minutes = 25): string
{
    return gmdate('c', time() - max(5, $minutes) * 60);
}

/**
 * @return array{
 *   daily: list<array<string,mixed>>,
 *   versions_30d: list<array<string,mixed>>,
 *   online_now: list<array<string,mixed>>,
 *   version_health: list<array<string,mixed>>,
 *   kpis: array<string,int|float|string>,
 *   suggestions: list<string>
 * }
 */
function lemon_telemetry_collect_stats(PDO $pdo): array
{
    $today = lemon_telemetry_today();
    $cutoff = lemon_telemetry_online_cutoff_iso(25);

    $daily = $pdo->query(
        "SELECT day,
                COUNT(*) AS dau,
                SUM(active_minutes) AS total_minutes,
                ROUND(AVG(active_minutes), 1) AS avg_minutes,
                SUM(CASE WHEN peak_online_sessions > 0 THEN 1 ELSE 0 END) AS online_installs,
                SUM(peak_online_sessions) AS peak_sessions,
                SUM(CASE WHEN online_sessions > 0 THEN 1 ELSE 0 END) AS last_report_online
         FROM daily_usage
         WHERE day >= date('now', '-29 days', 'localtime')
         GROUP BY day
         ORDER BY day DESC"
    )->fetchAll();

    $versions30d = $pdo->query(
        "SELECT version,
                COUNT(DISTINCT install_id) AS installs,
                SUM(active_minutes) AS total_minutes,
                MAX(day) AS last_day
         FROM daily_usage
         WHERE day >= date('now', '-29 days', 'localtime')
         GROUP BY version
         ORDER BY installs DESC
         LIMIT 30"
    )->fetchAll();

    $onlineStmt = $pdo->prepare(
        "SELECT version,
                COUNT(*) AS online_installs,
                SUM(online_sessions) AS online_sessions,
                SUM(active_minutes) AS active_minutes
         FROM daily_usage
         WHERE day = :day
           AND online_sessions > 0
           AND updated_at >= :cutoff
         GROUP BY version
         ORDER BY online_installs DESC, online_sessions DESC"
    );
    $onlineStmt->execute([':day' => $today, ':cutoff' => $cutoff]);
    $onlineNow = $onlineStmt->fetchAll();

    $onlineTotalsStmt = $pdo->prepare(
        "SELECT COUNT(*) AS online_installs,
                COALESCE(SUM(online_sessions), 0) AS online_sessions
         FROM daily_usage
         WHERE day = :day
           AND online_sessions > 0
           AND updated_at >= :cutoff"
    );
    $onlineTotalsStmt->execute([':day' => $today, ':cutoff' => $cutoff]);
    $onlineTotals = $onlineTotalsStmt->fetch() ?: ['online_installs' => 0, 'online_sessions' => 0];

    $todayDauStmt = $pdo->prepare('SELECT COUNT(*) AS dau FROM daily_usage WHERE day = :day');
    $todayDauStmt->execute([':day' => $today]);
    $todayDau = (int)(($todayDauStmt->fetch()['dau'] ?? 0));

    $versionHealth = $pdo->query(
        "SELECT version,
                COUNT(DISTINCT install_id) AS installs_all,
                COUNT(DISTINCT CASE WHEN day >= date('now', '-29 days', 'localtime') THEN install_id END) AS installs_30d,
                COUNT(DISTINCT CASE WHEN day >= date('now', '-6 days', 'localtime') THEN install_id END) AS installs_7d,
                MAX(day) AS last_day,
                MAX(updated_at) AS last_seen
         FROM daily_usage
         GROUP BY version
         ORDER BY last_day DESC, installs_30d DESC"
    )->fetchAll();

    foreach ($versionHealth as &$vh) {
        $lastDay = (string)($vh['last_day'] ?? '');
        $daysAgo = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastDay)) {
            $daysAgo = (int)floor((strtotime($today . ' 00:00:00') - strtotime($lastDay . ' 00:00:00')) / 86400);
        }
        $vh['days_ago'] = $daysAgo;
        $vh['status'] = lemon_telemetry_version_status($daysAgo);
    }
    unset($vh);

    $latestVersion = '';
    foreach ($versions30d as $v) {
        $ver = trim((string)($v['version'] ?? ''));
        if ($ver !== '' && $ver !== 'unknown') {
            if ($latestVersion === '' || version_compare($ver, $latestVersion, '>')) {
                $latestVersion = $ver;
            }
        }
    }

    $kpis = [
        'today' => $today,
        'today_dau' => $todayDau,
        'online_installs' => (int)$onlineTotals['online_installs'],
        'online_sessions' => (int)$onlineTotals['online_sessions'],
        'days_with_data' => count($daily),
        'total_minutes_30d' => 0,
        'unique_installs_30d' => 0,
        'latest_version' => $latestVersion,
    ];
    foreach ($daily as $r) {
        $kpis['total_minutes_30d'] += (int)$r['total_minutes'];
    }
    $uniq = $pdo->query(
        "SELECT COUNT(DISTINCT install_id) AS c
         FROM daily_usage
         WHERE day >= date('now', '-29 days', 'localtime')"
    )->fetch();
    $kpis['unique_installs_30d'] = (int)($uniq['c'] ?? 0);

    $suggestions = lemon_telemetry_build_suggestions($kpis, $onlineNow, $versionHealth, $versions30d, $daily);

    return [
        'daily' => $daily,
        'versions_30d' => $versions30d,
        'online_now' => $onlineNow,
        'version_health' => $versionHealth,
        'kpis' => $kpis,
        'suggestions' => $suggestions,
    ];
}

function lemon_telemetry_version_status(?int $daysAgo): string
{
    if ($daysAgo === null) {
        return 'unknown';
    }
    if ($daysAgo <= 0) {
        return 'active_today';
    }
    if ($daysAgo <= 3) {
        return 'recent';
    }
    if ($daysAgo <= 14) {
        return 'cooling';
    }
    if ($daysAgo <= 30) {
        return 'stale';
    }
    return 'dead';
}

/**
 * @param array<string,mixed> $kpis
 * @param list<array<string,mixed>> $onlineNow
 * @param list<array<string,mixed>> $versionHealth
 * @param list<array<string,mixed>> $versions30d
 * @param list<array<string,mixed>> $daily
 * @return list<string>
 */
function lemon_telemetry_build_suggestions(
    array $kpis,
    array $onlineNow,
    array $versionHealth,
    array $versions30d,
    array $daily
): array {
    $tips = [];
    $latest = (string)($kpis['latest_version'] ?? '');
    $onlineInstalls = (int)($kpis['online_installs'] ?? 0);
    $todayDau = (int)($kpis['today_dau'] ?? 0);

    if ($todayDau === 0 && $onlineInstalls === 0) {
        $tips[] = '今日尚无上报：确认安装包已注入日活地址，且用户未关闭「日活统计」。';
    }

    if ($onlineInstalls > 0 && $todayDau > 0) {
        $ratio = $onlineInstalls / max(1, $todayDau);
        if ($ratio < 0.15) {
            $tips[] = '当前在线约占今日 DAU 的 ' . round($ratio * 100) . '%，多数安装今日曾活跃但此刻离线，属正常离散使用。';
        } elseif ($ratio > 0.6) {
            $tips[] = '当前在线占今日 DAU 比例较高，用户使用时段较集中，发版/维护可避开高峰。';
        }
    }

    if ($latest !== '') {
        $latestOnline = 0;
        $latest30 = 0;
        foreach ($onlineNow as $row) {
            if ((string)$row['version'] === $latest) {
                $latestOnline = (int)$row['online_installs'];
            }
        }
        foreach ($versions30d as $row) {
            if ((string)$row['version'] === $latest) {
                $latest30 = (int)$row['installs'];
            }
        }
        $share = $kpis['unique_installs_30d'] > 0
            ? $latest30 / (int)$kpis['unique_installs_30d']
            : 0;
        if ($share < 0.4 && $latest30 > 0) {
            $tips[] = "近 30 日最新版 {$latest} 覆盖约 " . round($share * 100) . '% 安装，可在论坛/Release 提醒升级。';
        } elseif ($share >= 0.7) {
            $tips[] = "最新版 {$latest} 近 30 日覆盖较高（约 " . round($share * 100) . '%），旧版可逐步减少兼容投入。';
        }
        if ($onlineInstalls > 0 && $latestOnline === 0) {
            $tips[] = "当前在线用户都不在最新版 {$latest}，建议检查更新提示是否可达。";
        }
    }

    $stale = [];
    $dead = [];
    foreach ($versionHealth as $vh) {
        $ver = trim((string)($vh['version'] ?? '')) ?: 'unknown';
        $status = (string)($vh['status'] ?? '');
        $daysAgo = $vh['days_ago'];
        if ($status === 'stale') {
            $stale[] = "{$ver}（{$daysAgo} 天未上线）";
        } elseif ($status === 'dead') {
            $dead[] = "{$ver}（{$daysAgo} 天未上线）";
        }
    }
    if ($stale) {
        $tips[] = '较久未上线版本：' . implode('、', array_slice($stale, 0, 5)) . '。可观察是否仍有人升级回来。';
    }
    if ($dead) {
        $tips[] = '长期无人上线：' . implode('、', array_slice($dead, 0, 5)) . '。相关发行说明/兼容可归档，Release 资产可考虑保留但不主推。';
    }

    if (count($daily) >= 7) {
        $recent = array_slice($daily, 0, 3);
        $older = array_slice($daily, 3, 4);
        $avgRecent = 0;
        $avgOlder = 0;
        foreach ($recent as $r) {
            $avgRecent += (int)$r['dau'];
        }
        foreach ($older as $r) {
            $avgOlder += (int)$r['dau'];
        }
        $avgRecent = $avgRecent / max(1, count($recent));
        $avgOlder = $avgOlder / max(1, count($older));
        if ($avgOlder > 0 && $avgRecent > $avgOlder * 1.25) {
            $tips[] = '近 3 日 DAU 明显高于此前一周，增长期适合跟进反馈与热修。';
        } elseif ($avgOlder > 0 && $avgRecent < $avgOlder * 0.75) {
            $tips[] = '近 3 日 DAU 下滑，可对照发版/论坛公告节奏，或检查安装包与更新通道。';
        }
    }

    if (!$tips) {
        $tips[] = '数据量尚可，继续保持每日看一眼在线版本分布即可；大版本发布后重点盯「最新版在线占比」。';
    }

    return $tips;
}
