# 柠檬音乐日活统计（PHP 接收端）

将本目录上传到你自己的网站某个路径（例如 `/lemon-music/`）。**真实域名和密钥只放在服务器本地 `config.php`，不要写进公开仓库。**

## 配置

1. 复制 `config.example.php` → `config.php`
2. 填写 `telemetry_secret`（与应用侧 `TELEMETRY_SECRET` / `telemetry.local.json` 一致）
3. 填写 `stats_password`（看板登录密码）
4. 确认 PHP 启用 PDO SQLite，且 `data/` 可写

- 上报接口：`…/telemetry.php`
- 看板：`…/stats.php`

## 看板看什么

- **今日 DAU / 当前在线安装 / 当前在线会话**：按心跳（约 10 分钟）估算，25 分钟内有会话视为仍在线
- **当前在线 · 按版本**：哪个版本此刻还有人
- **版本健康**：哪个版本多久没人上线（今日 / 降温 / 较久 / 长期）
- **按日在线**：近 30 日 DAU、曾在线安装、峰值会话合计、活跃分钟
- **建议**：根据在线占比、最新版覆盖、旧版沉寂自动给几条运维提示

`config.php` 已加入 `.gitignore`，请勿提交。应用仓库侧请用环境变量或本地 json 配置，FPK 发布时由本机 `npm run fpk:build` 注入。

上传更新时覆盖 `lib.php`、`telemetry.php`、`stats.php` 即可；SQLite 会自动补 `peak_online_sessions` 列，无需清库。

**图文步骤教程：** 仓库 [`docs/telemetry-stats-guide.md`](../../docs/telemetry-stats-guide.md)
