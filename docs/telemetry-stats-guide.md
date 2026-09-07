# 日活统计使用教程（作者侧）

本教程说明如何部署接收端、配置柠檬音乐上报，以及如何看看板。  
**真实域名、密钥只放在你自己的服务器 / 本机本地文件里，不要提交进 git。**

---

## 1. 这套东西是干什么的

各飞牛（或自建）上的柠檬音乐，在有人登录在线时，大约每 **10 分钟**向你的站点匿名上报一次：

| 会上报 | 不会上报 |
|--------|----------|
| 匿名安装 ID（UUID） | 歌单、歌曲名 |
| 应用版本号 | 本地路径、文件名 |
| 当天活跃分钟数 | 账号、邮箱、IP 归属业务数据 |
| 当前在线浏览器会话数 | 音源 Cookie / Token |

你在自己站点的 **stats.php** 看板里查看：今日有多少台在用、此刻谁在线、各版本占比、哪个版本多久没人上线，以及自动建议。

用户可在 **设置 → 邮件服务** 底部关闭「日活统计」。未配置上报地址时，应用**不会**真正发请求。

---

## 2. 整体流程

```text
[飞牛上的柠檬音乐]
        │  POST 匿名心跳（带密钥）
        ▼
[你的网站]/lemon-music/telemetry.php]
        │  写入 SQLite
        ▼
[你的网站]/lemon-music/stats.php]  ← 密码登录后看看板
```

分三步：

1. 把 PHP 接收端传到你的网站  
2. 本机配置上报地址与密钥（开发 / 打 FPK）  
3. 登录看板看数据  

---

## 3. 部署 PHP 接收端

### 3.1 上传文件

仓库目录：[`tools/lemon-telemetry-php/`](../tools/lemon-telemetry-php/)

上传到网站根目录下的某个文件夹，例如与站点首页同级：

```text
（网站根目录）/
  index.php                 ← 你原有站点入口
  lemon-music/
    telemetry.php           ← 上报接口
    stats.php               ← 看板
    lib.php
    config.example.php
    README.md
    data/                   ← 必须可写
```

目录名可自定，下文用 `lemon-music` 举例。上报 URL 最终类似：

```text
https://你的域名/lemon-music/telemetry.php
看板：
https://你的域名/lemon-music/stats.php
```

### 3.2 创建配置（服务器上）

在服务器上：

1. 复制 `config.example.php` → **`config.php`**（不要提交到公开仓库）  
2. 编辑 `config.php`：

```php
return [
    // 与应用侧 TELEMETRY_SECRET / telemetry.local.json 的 secret 完全一致
    'telemetry_secret' => '请换成足够长的随机字符串',

    // 看板登录密码
    'stats_password' => '请换成你自己的看板密码',
];
```

3. 确认 PHP 已启用 **PDO SQLite**  
4. 给 `data/` 写权限（常见 `755` 或 `775`；不要整站 `777`）

### 3.3 检查是否通

浏览器打开：

- `…/lemon-music/stats.php` → 应出现「看板密码」登录页  
- `…/lemon-music/telemetry.php` → 应是「此地址仅供 POST」说明页（不是 404）

若是伪静态站点（如 PbootCMS）整站 404，可在网站根 `.htaccess` **靠前**加：

```apache
RewriteRule ^lemon-music/ - [L]
```

（路径按你实际上传目录名改。）

---

## 4. 配置柠檬音乐上报

密钥、地址必须与服务器 `config.php` 一致。

### 4.1 本机开发 / 本机运行

任选其一：

**方式 A：本地文件（推荐）**

1. 复制 [`server/telemetry.local.json.example`](../server/telemetry.local.json.example)  
   → `server/telemetry.local.json`（已在 gitignore，勿 `git add`）  
2. 填入：

```json
{
  "url": "https://你的域名/lemon-music/telemetry.php",
  "secret": "与 config.php 里 telemetry_secret 相同"
}
```

也可用 `urlB64` / `secretB64` 代替明文。

**方式 B：环境变量**

```text
TELEMETRY_URL=https://你的域名/lemon-music/telemetry.php
TELEMETRY_SECRET=与 config.php 里 telemetry_secret 相同
```

启动服务后日志出现类似 `[telemetry] active` 即已启用；`inactive` 表示未配置。

### 4.2 打飞牛 FPK / 发 Release（重要）

公开仓库**不能**带真实上报地址。发布包在**你本机**注入：

1. 本机先配好上一节的 `telemetry.local.json` 或环境变量  
2. 执行：

```bash
npm run fpk:build
```

打包脚本会把配置写进安装包。  
仅做无上报测试包：

```bash
npm run fpk:build:no-telemetry
```

用户安装带注入的 FPK 后，有人打开并登录一段时间，就会开始有日活。

### 4.3 用户侧开关

管理员：**设置 → 邮件服务** → 「日活统计」  
默认开；关掉后该安装不再上报。

---

## 5. 使用看板

1. 打开 `https://你的域名/lemon-music/stats.php`  
2. 输入 `stats_password`  
3. 页面约每 **2 分钟**自动刷新  

### 5.1 顶部数字

| 指标 | 含义 |
|------|------|
| **今日在线安装（DAU）** | 今天至少上报过一次的安装台数 |
| **当前在线安装** | 今天有上报，且约 **25 分钟内**仍报「有人在线」的台数 |
| **当前在线会话** | 上述安装里浏览器连接数合计（一台可多人开） |
| **近 30 日独立安装** | 近一个月出现过的不重复安装 ID |

### 5.2 表格怎么读

- **当前在线 · 按版本**：此刻哪个版本还有人、多少台 / 多少会话  
- **版本分布（近 30 日）**：各版本装机与活跃分钟  
- **版本健康**：距该版本**最后一次有日活**过了几天（今日 / 降温 / 较久 / 长期）  
- **按日在线**：每天 DAU、曾在线安装、峰值会话合计、总分钟  

「峰值会话合计」是各安装当日峰值相加，**不是**同一秒钟的全局峰值。

### 5.3 「建议」栏

根据在线占比、最新版覆盖、旧版沉寂、近几日 DAU 涨跌自动生成几条提示，例如：

- 提醒用户升级到最新版  
- 旧版长期无人可降低维护优先级  
- DAU 明显上升或下滑时对照发版 / 论坛节奏  

---

## 6. 日常怎么用（建议节奏）

1. **发版当天 / 次日**：看「当前在线 · 按版本」和最新版近 30 日占比，确认更新通道是否生效  
2. **每周扫一眼**：版本健康里「较久 / 长期无人」的旧版，决定是否还在论坛主推  
3. **异常时**：今日 DAU 为 0 → 先查 FPK 是否注入、用户是否关统计、接口是否 401/404  

---

## 7. 更新接收端代码

仓库里优化了看板后，只需覆盖上传：

- `lib.php`  
- `telemetry.php`  
- `stats.php`  

**不要覆盖**你服务器上的 `config.php` 和 `data/telemetry.sqlite`。  
数据库会自动增加新列，一般**不用清库**。

---

## 8. 常见问题

| 现象 | 处理 |
|------|------|
| stats 404 | 路径是否与网站根同级；伪静态是否放行 |
| 读取数据库失败 | `data/` 是否可写；PDO SQLite 是否启用 |
| 上报后仍无数据 | 密钥是否一致；应用日志是否 `active`；是否关了日活开关；是否已有人登录在线满约 10 分钟 |
| 浏览器打开 telemetry.php 无数据 | 正常，必须由应用 **POST**，GET 只显示说明 |
| 看板「当前在线」总是 0 | 需有人正在用；离线后约 25 分钟内会从「当前在线」消失，但今日 DAU 仍保留 |

---

## 9. 隐私与仓库自检（提交前）

- [ ] 未提交 `server/telemetry.local.json`、`config/telemetry.local.json`  
- [ ] 未提交 `tools/lemon-telemetry-php/config.php`  
- [ ] diff 中无真实 `TELEMETRY_SECRET`、自有域名  
- [ ] 未对 gitignore 文件使用 `git add -f`  

更多约定见仓库规则与 README「日活统计」一节。

---

## 相关文件

| 路径 | 说明 |
|------|------|
| [`tools/lemon-telemetry-php/`](../tools/lemon-telemetry-php/) | 站点接收端 + 看板 |
| [`server/telemetry.local.json.example`](../server/telemetry.local.json.example) | 本机上报配置示例 |
| [`server/utils/telemetry.js`](../server/utils/telemetry.js) | 应用侧心跳逻辑 |
| [`docs/fpk-install.md`](fpk-install.md) | FPK 打包与注入说明 |
