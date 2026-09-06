<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

session_start();

$cfg = lemon_telemetry_config();
$password = (string)($cfg['stats_password'] ?? '');
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['lemon_stats_ok']);
    header('Location: stats.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $got = (string)($_POST['password'] ?? '');
    if ($password !== '' && hash_equals($password, $got)) {
        $_SESSION['lemon_stats_ok'] = true;
        header('Location: stats.php');
        exit;
    }
    $error = '密码错误';
}

$authed = !empty($_SESSION['lemon_stats_ok']);

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$rows = [];
$summary = [
    'days' => 0,
    'total_dau' => 0,
    'total_minutes' => 0,
];
$versions = [];

if ($authed) {
    try {
        $pdo = lemon_telemetry_db();
        $stmt = $pdo->query(
            "SELECT day,
                    COUNT(*) AS dau,
                    SUM(active_minutes) AS total_minutes,
                    ROUND(AVG(active_minutes), 1) AS avg_minutes
             FROM daily_usage
             WHERE day >= date('now', '-29 days', 'localtime')
             GROUP BY day
             ORDER BY day DESC"
        );
        $rows = $stmt->fetchAll();
        $summary['days'] = count($rows);
        foreach ($rows as $r) {
            $summary['total_dau'] += (int)$r['dau'];
            $summary['total_minutes'] += (int)$r['total_minutes'];
        }
        $vstmt = $pdo->query(
            "SELECT version, COUNT(DISTINCT install_id) AS installs
             FROM daily_usage
             WHERE day >= date('now', '-29 days', 'localtime')
             GROUP BY version
             ORDER BY installs DESC
             LIMIT 20"
        );
        $versions = $vstmt->fetchAll();
    } catch (Throwable $e) {
        $error = '读取数据库失败：请确认 data/ 可写且已有上报数据';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>柠檬音乐 · 使用统计</title>
  <style>
    :root { color-scheme: light dark; --bg: #0f1419; --card: #1a222c; --text: #e7ecf1; --muted: #9aa7b5; --accent: #3d9a6a; --border: #2a3542; }
    @media (prefers-color-scheme: light) {
      :root { --bg: #f4f6f8; --card: #fff; --text: #1a222c; --muted: #667788; --border: #dde3ea; }
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
    main { max-width: 880px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
    h1 { font-size: 1.35rem; margin: 0 0 0.35rem; }
    .sub { color: var(--muted); margin: 0 0 1.5rem; font-size: 0.9rem; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem; margin-bottom: 1rem; }
    label { display: block; margin-bottom: 0.4rem; font-size: 0.9rem; }
    input[type=password] { width: 100%; max-width: 280px; padding: 0.55rem 0.7rem; border-radius: 6px; border: 1px solid var(--border); background: transparent; color: var(--text); }
    button { margin-top: 0.75rem; padding: 0.5rem 1rem; border: 0; border-radius: 6px; background: var(--accent); color: #fff; cursor: pointer; }
    .err { color: #e07070; margin-top: 0.75rem; font-size: 0.9rem; }
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
    @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
    .stat strong { display: block; font-size: 1.4rem; }
    .stat span { color: var(--muted); font-size: 0.8rem; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th, td { text-align: left; padding: 0.5rem 0.4rem; border-bottom: 1px solid var(--border); }
    th { color: var(--muted); font-weight: 600; }
    .top { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap; }
    a { color: var(--accent); }
  </style>
</head>
<body>
<main>
  <div class="top">
    <div>
      <h1>柠檬音乐 · 匿名使用统计</h1>
      <p class="sub">近 30 日日活安装数与累计在线时长（分钟）</p>
    </div>
    <?php if ($authed): ?>
      <a href="?logout=1">退出</a>
    <?php endif; ?>
  </div>

  <?php if (!$authed): ?>
    <div class="card">
      <form method="post" action="">
        <label for="password">看板密码</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required />
        <div><button type="submit">登录</button></div>
        <?php if ($error !== ''): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
      </form>
    </div>
  <?php else: ?>
    <?php if ($error !== ''): ?><p class="err"><?= h($error) ?></p><?php endif; ?>

    <div class="grid">
      <div class="card stat">
        <strong><?= (int)$summary['days'] ?></strong>
        <span>有数据的天数</span>
      </div>
      <div class="card stat">
        <strong><?= count($rows) ? (int)$rows[0]['dau'] : 0 ?></strong>
        <span>最近一日 DAU<?= count($rows) ? '（' . h((string)$rows[0]['day']) . '）' : '' ?></span>
      </div>
      <div class="card stat">
        <strong><?= (int)$summary['total_minutes'] ?></strong>
        <span>近 30 日总活跃分钟</span>
      </div>
    </div>

    <div class="card">
      <h2 style="margin:0 0 0.75rem;font-size:1.05rem">按日</h2>
      <?php if (!$rows): ?>
        <p class="sub" style="margin:0">暂无数据。确认应用已开启统计且本接口可被 NAS 访问。</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>日期</th>
              <th>DAU</th>
              <th>总分钟</th>
              <th>平均分钟/安装</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= h((string)$r['day']) ?></td>
                <td><?= (int)$r['dau'] ?></td>
                <td><?= (int)$r['total_minutes'] ?></td>
                <td><?= h((string)$r['avg_minutes']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="margin:0 0 0.75rem;font-size:1.05rem">版本分布（近 30 日）</h2>
      <?php if (!$versions): ?>
        <p class="sub" style="margin:0">暂无</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>版本</th><th>安装数</th></tr>
          </thead>
          <tbody>
            <?php foreach ($versions as $v): ?>
              <tr>
                <td><?= h((string)($v['version'] ?: 'unknown')) ?></td>
                <td><?= (int)$v['installs'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
