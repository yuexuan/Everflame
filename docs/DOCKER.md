# Docker / MySQL 部署

## 本地架构

- app：PHP/Apache，代码在镜像内，storage 使用独立卷。
- db：MySQL 8.4，数据在 `everflame_mysql_data`，不发布数据库端口。
- redis：独立容器和数据卷，仅内部网络访问。
- `.env` 保存连接参数及密钥，已忽略提交，镜像中不包含它。

本轮按用户要求删除 Everflame 原 MariaDB 数据并重建 MySQL，不保留旧数据。其他 Docker 项目未改动。

```powershell
docker compose up -d --build
# 第一次创建数据库后，导入当前核心配置默认值：
docker compose exec app php xcat Tool importSetting
# 创建管理员（本机已创建，无需重复）：
docker compose exec -it app php xcat Tool createAdmin
```

本地入口 http://localhost:18087。更新代码：`docker compose up -d --build --no-deps app`，不会重建数据库。

## 为什么有独立 MySQL 表结构

当前上游虽然声明支持 MySQL，历史迁移实际上包含 MariaDB 专用语法和类型。`deploy/docker/mysql-schema.sql` 是从上游 c2ab3003cc4bf70237eb6ae82b3c65f31fb1f051 的完整迁移结果（2025073100）导出的纯表结构，无用户、密码或业务数据。

MySQL 必要转换：INET4/INET6 改为 varchar(15)/varchar(45)，UUID 改为 char(36)；TEXT 默认值使用表达式；JSON CHECK 从列级移动到表级（保留原引用）；去除计数器和 MariaDB 客户端指令。IP/UUID 仍以当前模型使用的字符串形式读写，MySQL 不提供原 MariaDB 类型的自动规范化与类型校验。未改用户/套餐/节点/订阅业务实现。字段含义、索引及默认值保留。

空的本地 MySQL 卷会由官方镜像自动导入该结构，末尾写入真实对应的 db_version。不要在这个数据库上再执行 `Migration new` 或倒退到旧迁移。未来 `Migration latest` 前需审查新增迁移的 MySQL 兼容性，备份后再运行；不能假定上游未来所有 MariaDB SQL 都能运行。上游历史迁移文件保持原样。

核心仅在 `src/Services/DB.php` 两处将连接驱动改为可配置值，默认仍为 mariadb；Docker 配置选择 mysql。连接端口也通过独立部署配置读取。

## 迁到 1Panel 的 MySQL

目标使用 MySQL 8.4，字符集 utf8mb4 / utf8mb4_unicode_ci，SQL mode 按核心要求不启用严格模式（本地为 NO_ENGINE_SUBSTITUTION）。创建应用专属数据库与用户。目标地址必须能从 app 容器访问，不能填容器自身的 localhost。可使用受限内网地址及发布端口，或明确将 app 接入 1Panel 的数据库网络。

**改连接不会自动搬运数据。** 空目标库先导入 `deploy/docker/mysql-schema.sql`，然后对目标运行 `Tool importSetting`、创建管理员。若要保留届时的用户/节点/套餐数据，则从本地 MySQL 导出完整库并导入目标；不要同时再次导入初始化 schema。

编辑 `.env`：

```dotenv
DB_HOST=你的MySQL可达地址
DB_PORT=3306
DB_DATABASE=你的数据库名
DB_USERNAME=你的数据库用户
DB_PASSWORD=你的数据库密码
DB_DRIVER=mysql
```

使用现成外部数据库覆盖配置，移除 app 对本地 db 的启动依赖：

```powershell
docker compose -f compose.yaml -f compose.external-db.yaml up -d app redis
```

覆盖配置采用 Docker Compose 的 `!override` 标签，要求 Compose 2.24.4+。以后保持使用这两个 `-f` 参数管理应用。本地 db 服务被移入可选 profile；已运行的本地 db 不会自动被删除，需要时可单独停止。不要用普通默认配置重新启动 app，否则会恢复对本地 db 的启动依赖。

同一套代码/镜像支持本地或外部 MySQL，未来只需导入数据、修改上述连接参数并重建 app 容器，无需重新改 PHP 文件。迁移到新域名还需更新 APP_URL 并配置 HTTPS；迁移已有账号时保留 APP_KEY 和 WEBAPI_KEY。

## 运维

`docker compose down` 保留卷；`down -v` 会删除数据，不用于日常更新。管理员密码不写入代码或镜像。计划任务仍需按上游要求每五分钟执行 `php xcat Cron`，本地不自动启动后台业务任务。

备份在 db 容器内运行 mysqldump，导出文件存仓库外并限制访问；不要将备份、数据库文件或 `.env` 提交 Git。

## 本轮验证（2026-10-28）

本地 MySQL 实际版本 8.4.13。初始化结构包含 26 张表，配置版本 2025073100。使用上游 Tool importSetting 导入配置，使用 createAdmin 重建管理员（沿用此前提供的账号密码）；未配置支付渠道。

实际登录 POST 成功并返回 HX-Redirect=/user。携带登录 Cookie 请求用户首页、套餐、节点和管理员首页均返回 200。独立检查 Malio 侧栏已在用户页渲染。PHP 语法、Composer 运行依赖和默认/外部数据库两套 Compose 配置检查通过；在外部数据库覆盖模式下也验证了已登录页面。没有模拟认证，也没有用静态页面替代模板。

必要的登录修正：Cookie 域移除端口，对 localhost/IP 使用 host-only Cookie；未设置重定向时回到 /user（密码登录、注册、WebAuthn 共用相同回退规则）。Secure/HttpOnly 保持不变，正式部署仍需 HTTPS。这两处位于 src/Utils/Cookie.php、src/Controllers/AuthController.php，后续同步上游时需保留或核对上游是否已修复。
