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

function status_label(string $status): string
{
    if ($status === 'active_today') return '今日有活跃';
    if ($status === 'recent') return '近几日有活跃';
    if ($status === 'cooling') return '降温中';
    if ($status === 'stale') return '较久无人';
    if ($status === 'dead') return '长期无人';
    return '未知';
}

$stats = null;
if ($authed) {
    try {
        $pdo = lemon_telemetry_db();
        $stats = lemon_telemetry_collect_stats($pdo);
    } catch (Throwable $e) {
        $error = '读取数据库失败：请确认 data/ 可写且已有上报数据';
    }
}

$kpis = $stats['kpis'] ?? [];
$daily = $stats['daily'] ?? [];
$onlineNow = $stats['online_now'] ?? [];
$versions30d = $stats['versions_30d'] ?? [];
$versionHealth = $stats['version_health'] ?? [];
$suggestions = $stats['suggestions'] ?? [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="refresh" content="120" />
  <title>柠檬音乐 · 使用统计</title>
  <style>
    :root { color-scheme: light dark; --bg: #0f1419; --card: #1a222c; --text: #e7ecf1; --muted: #9aa7b5; --accent: #3d9a6a; --border: #2a3542; --warn: #c9a227; --danger: #c45c5c; --ok: #3d9a6a; }
    @media (prefers-color-scheme: light) {
      :root { --bg: #f4f6f8; --card: #fff; --text: #1a222c; --muted: #667788; --border: #dde3ea; }
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
    main { max-width: 980px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
    h1 { font-size: 1.35rem; margin: 0 0 0.35rem; }
    h2 { margin: 0 0 0.75rem; font-size: 1.05rem; }
    .sub { color: var(--muted); margin: 0 0 1.5rem; font-size: 0.9rem; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem; margin-bottom: 1rem; }
    label { display: block; margin-bottom: 0.4rem; font-size: 0.9rem; }
    input[type=password] { width: 100%; max-width: 280px; padding: 0.55rem 0.7rem; border-radius: 6px; border: 1px solid var(--border); background: transparent; color: var(--text); }
    button { margin-top: 0.75rem; padding: 0.5rem 1rem; border: 0; border-radius: 6px; background: var(--accent); color: #fff; cursor: pointer; }
    .err { color: #e07070; margin-top: 0.75rem; font-size: 0.9rem; }
    .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 800px) {
      .grid { grid-template-columns: 1fr 1fr; }
      .grid-2 { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) { .grid { grid-template-columns: 1fr; } }
    .stat strong { display: block; font-size: 1.45rem; font-variant-numeric: tabular-nums; }
    .stat span { color: var(--muted); font-size: 0.8rem; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th, td { text-align: left; padding: 0.5rem 0.4rem; border-bottom: 1px solid var(--border); vertical-align: top; }
    th { color: var(--muted); font-weight: 600; }
    .num { font-variant-numeric: tabular-nums; }
    .top { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap; }
    a { color: var(--accent); }
    .badge { display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px; font-size: 0.75rem; border: 1px solid var(--border); color: var(--muted); white-space: nowrap; }
    .badge.ok { color: var(--ok); border-color: color-mix(in srgb, var(--ok) 40%, var(--border)); }
    .badge.warn { color: var(--warn); border-color: color-mix(in srgb, var(--warn) 40%, var(--border)); }
    .badge.danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); }
    .tips { margin: 0; padding-left: 1.1rem; }
    .tips li { margin: 0.4rem 0; color: var(--text); }
    .muted { color: var(--muted); font-size: 0.85rem; }
    .note { margin: 0.35rem 0 0; font-size: 0.8rem; color: var(--muted); }
  </style>
</head>
<body>
<main>
  <div class="top">
    <div>
      <h1>柠檬音乐 · 匿名使用统计</h1>
      <p class="sub">每日在线安装、当前在线、版本分布与久未上线提醒（约每 2 分钟自动刷新）</p>
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
        <strong class="num"><?= (int)($kpis['today_dau'] ?? 0) ?></strong>
        <span>今日在线安装（DAU）<?= isset($kpis['today']) ? ' · ' . h((string)$kpis['today']) : '' ?></span>
      </div>
      <div class="card stat">
        <strong class="num"><?= (int)($kpis['online_installs'] ?? 0) ?></strong>
        <span>当前在线安装</span>
      </div>
      <div class="card stat">
        <strong class="num"><?= (int)($kpis['online_sessions'] ?? 0) ?></strong>
        <span>当前在线会话（浏览器）</span>
      </div>
      <div class="card stat">
        <strong class="num"><?= (int)($kpis['unique_installs_30d'] ?? 0) ?></strong>
        <span>近 30 日独立安装</span>
      </div>
    </div>

    <div class="card">
      <h2>建议</h2>
      <?php if (!$suggestions): ?>
        <p class="sub" style="margin:0">暂无</p>
      <?php else: ?>
        <ul class="tips">
          <?php foreach ($suggestions as $tip): ?>
            <li><?= h($tip) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="note">
        「当前在线」= 今日有上报且最近约 25 分钟内仍有会话（心跳 10 分钟一次）。
        <?php if (!empty($kpis['latest_version'])): ?>
          推断最新版本：<?= h((string)$kpis['latest_version']) ?>。
        <?php endif; ?>
      </p>
    </div>

    <div class="grid-2">
      <div class="card">
        <h2>当前在线 · 按版本</h2>
        <?php if (!$onlineNow): ?>
          <p class="sub" style="margin:0">此刻没有检测到在线安装。</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>版本</th>
                <th>在线安装</th>
                <th>在线会话</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($onlineNow as $row): ?>
                <tr>
                  <td><?= h((string)($row['version'] ?: 'unknown')) ?></td>
                  <td class="num"><?= (int)$row['online_installs'] ?></td>
                  <td class="num"><?= (int)$row['online_sessions'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>版本分布（近 30 日）</h2>
        <?php if (!$versions30d): ?>
          <p class="sub" style="margin:0">暂无</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>版本</th>
                <th>安装数</th>
                <th>总分钟</th>
                <th>最近活跃日</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($versions30d as $v): ?>
                <tr>
                  <td><?= h((string)($v['version'] ?: 'unknown')) ?></td>
                  <td class="num"><?= (int)$v['installs'] ?></td>
                  <td class="num"><?= (int)$v['total_minutes'] ?></td>
                  <td class="num"><?= h((string)$v['last_day']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h2>版本健康 · 多久没人上线</h2>
      <?php if (!$versionHealth): ?>
        <p class="sub" style="margin:0">暂无</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>版本</th>
              <th>状态</th>
              <th>距上次上线</th>
              <th>近 7 日</th>
              <th>近 30 日</th>
              <th>历史安装</th>
              <th>上次活跃日</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($versionHealth as $vh): ?>
              <?php
                $status = (string)($vh['status'] ?? 'unknown');
                $badgeClass = '';
                if ($status === 'active_today' || $status === 'recent') $badgeClass = 'ok';
                elseif ($status === 'cooling') $badgeClass = 'warn';
                elseif ($status === 'stale' || $status === 'dead') $badgeClass = 'danger';
                $daysAgo = $vh['days_ago'];
                $daysText = $daysAgo === null ? '—' : ($daysAgo <= 0 ? '今天' : $daysAgo . ' 天');
              ?>
              <tr>
                <td><?= h((string)($vh['version'] ?: 'unknown')) ?></td>
                <td><span class="badge <?= h($badgeClass) ?>"><?= h(status_label($status)) ?></span></td>
                <td class="num"><?= h($daysText) ?></td>
                <td class="num"><?= (int)$vh['installs_7d'] ?></td>
                <td class="num"><?= (int)$vh['installs_30d'] ?></td>
                <td class="num"><?= (int)$vh['installs_all'] ?></td>
                <td class="num"><?= h((string)$vh['last_day']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="note">「距上次上线」按该版本最后一次有日活记录的日期计算；长期无人的旧版可降低维护优先级。</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>按日在线</h2>
      <?php if (!$daily): ?>
        <p class="sub" style="margin:0">暂无数据。确认应用已开启统计且本接口可被 NAS 访问。</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>日期</th>
              <th>DAU</th>
              <th>曾在线安装</th>
              <th>峰值会话合计</th>
              <th>总分钟</th>
              <th>平均分钟/安装</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daily as $r): ?>
              <tr>
                <td><?= h((string)$r['day']) ?></td>
                <td class="num"><?= (int)$r['dau'] ?></td>
                <td class="num"><?= (int)$r['online_installs'] ?></td>
                <td class="num"><?= (int)$r['peak_sessions'] ?></td>
                <td class="num"><?= (int)$r['total_minutes'] ?></td>
                <td class="num"><?= h((string)$r['avg_minutes']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="note">「曾在线安装」= 当日 peak_online_sessions &gt; 0 的安装数；「峰值会话合计」为各安装当日峰值相加（非同一时刻全局峰值）。</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
