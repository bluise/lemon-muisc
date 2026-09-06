# 柠檬音乐日活统计（PHP 接收端）

将本目录上传到你自己的网站某个路径（例如 `/lemon-music/`）。**真实域名和密钥只放在服务器本地 `config.php`，不要写进公开仓库。**

## 配置

1. 复制 `config.example.php` → `config.php`
2. 填写 `telemetry_secret`（与应用侧 `TELEMETRY_SECRET` / `telemetry.local.json` 一致）
3. 填写 `stats_password`（看板登录密码）
4. 确认 PHP 启用 PDO SQLite，且 `data/` 可写

- 上报接口：`…/telemetry.php`
- 看板：`…/stats.php`

`config.php` 已加入 `.gitignore`，请勿提交。应用仓库侧请用环境变量或本地 json 配置，FPK 发布时由本机 `npm run fpk:build` 注入。
