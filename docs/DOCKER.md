# Docker 部署：代码与数据分离

`compose.yaml` 将 app（PHP/Apache）、db（MariaDB）、redis 分开运行。不修改 SSPanel 业务层；数据库迁移仍属于版本化代码，真实数据存储在 Docker 卷中。

- app：代码构建进镜像；仅 `storage` 使用 `everflame_app_storage` 卷。
- db：数据写入 `everflame_db_data`，不映射宿主机端口。
- redis：AOF 持久化写入 `everflame_redis_data`，不映射宿主机端口。
- db/redis 位于内部 data 网络，app 同时连接 web/data 网络。其他现有 Docker 项目不受影响。
- 运行配置通过 `deploy/docker/config.php` 从环境变量读取，覆盖上游示例配置的连接参数；密钥保存在忽略提交的 `.env`，不进入镜像。

## 启动与首次初始化

复制 `.env.example` 为 `.env` 并填入四项不同的随机密钥。本机已生成 `.env`，不要重新覆盖。默认地址 http://localhost:18087，仅供本机验证。正式部署需在反向代理配置 HTTPS 并更新 APP_URL。

```powershell
docker compose up -d --build
# 仅对全新空数据库执行 new；已有数据升级使用 latest，先备份。
docker compose exec app php xcat Migration new
docker compose exec app php xcat Migration latest
docker compose exec app php xcat Tool importSetting
# 按提示创建自己的管理员；不预置共享密码。
docker compose exec -it app php xcat Tool createAdmin
```

数据库初始化不放在 app 启动脚本中，避免应用重启时隐式迁移或覆盖数据。首次初始化完成后，将上游要求的 Cron 命令接入部署环境的计划任务；本配置不会自动启动后台业务任务。

## 更新代码 / 重启

```powershell
docker compose up -d --build --no-deps app
# 若此次上游更新需要迁移，备份后执行：
docker compose exec app php xcat Migration latest
docker compose exec app php xcat Tool importSetting
```

普通 `docker compose down` 保留数据卷。`down -v` 会删除数据库、Redis 和 storage 数据，不作为日常更新命令。应用容器可以删除重建；不要将数据库文件复制进代码仓库或镜像。

## 检查与备份

```powershell
docker compose ps
docker compose logs --tail 50 app
# 使用容器内部数据库环境变量，避免在命令行写明密码。
# 导出时通过二进制安全的 shell 重定向保存，不在聊天输出中打印 SQL。
docker compose exec db sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --single-transaction "$MARIADB_DATABASE" > /tmp/everflame.sql'
docker compose cp db:/tmp/everflame.sql ./everflame-backup.sql
```

备份包含用户敏感数据，存放于仓库外并控制访问。恢复前核对目标数据库，禁止直接对已有业务库覆盖导入。

## 本轮验证（2026-10-28）

本机三个服务已运行；MariaDB/Redis 健康检查通过。通过上游命令在独立空数据库执行 `Migration new`、`Migration latest`、`Tool importSetting`，数据库版本为 `2025073100`，配置记录 202 条。未设置支付商户或网关。

Composer 非开发依赖平台检查及部署配置 PHP 语法检查通过。真实数据库连接下 `/auth/login`、`/auth/register` 与 Malio CSS 请求均返回 HTTP 200，登录/注册响应包含实际表单，登录页包含 Malio 样式引用。这不等同于账号登录/注册提交或全部主题页面验收。

执行 `up -d --force-recreate --no-deps app` 后，数据库容器 ID 未变化，数据库版本和配置数量保持不变，主题资源仍可访问。尚未创建管理员账号；使用上方交互命令自行设置账号密码。
