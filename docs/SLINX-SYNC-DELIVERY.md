# SLINX 与 EverFlame 同步交付记录

日期：2026-11-08，时区 Asia/Shanghai。正式面板：https://ef.reelfires.com 。

本次交付已在真实的 Debian AMD64 节点和本机 Docker Compose 面板上运行，不是模拟数据演示。
正式计量起点：**2026-11-08 21:41:13 +08:00**。此前是影子账目，不扣用户额度。

## 九项任务与验收

| 任务 | 实现和实际证据 |
|---|---|
| 1 通信链路 | 独立 Agent/观察器 Bearer 凭据；节点通过正式 HTTPS 域名拉配置、发送心跳和用量；错误凭据返回 401。修复程序客户端标识与 Cloudflare Tunnel 连接问题，隧道使用 QUIC、真实边缘地址及显式 DNS。 |
| 2 稳定绑定 | Agent `ef-hk-01`，节点展示记录与线路绑定分离；1/3 两个 binding 共用 443 物理入站，通过独立认证身份选择出口；原重复展示记录 3、4 停用。 |
| 3 独立心跳 | 每 30 秒心跳，90 秒过期；核心 stopped 与 Agent 失联分开判断；配置/采集故障显示黄灯及原因。真实核心停机检查与隔离库健康状态测试均有验证。 |
| 4 真实探测 | 独立 Docker observer 经公网 SS 认证、生产路由访问两个 HTTPS 204 目标；2 次成功恢复，3 次失败判故障，180 秒结果过期。香港及新增 ISP 线路绿色；用户故意配置的 SSH 失败线路保持红色。 |
| 5 独立用户 | 两个原账号分别使用独立逐线路密码；Clash、sing-box、SIP008、V2Ray JSON、SS、SIP002 六种导出的密码与 Agent 一致；两个账号均通过实际代理连接。 |
| 6 可靠采集 | 单采集器每秒读取不清零累计统计；SQLite 事务保存 checkpoint/outbox/本地消费总额；带核心 epoch、不可变事件 ID、序号和小时区间。面板停止期间产生 3 条共 408,756 字节待报记录；Agent 重启后完整保留。 |
| 7 正式账目 | 面板事务验权、去重、保存原始上下行和费率版本，更新当前周期额度、历史和节点用量；恢复后 outbox 清零；真实已入账事件经正式 HTTPS API 重放，ACK 成功且不重复扣量。两个原账号及验收账号的当前周期上下行均与正式账本相等。 |
| 8 本地执行 | 本地合并所有线路消费；缓存授权 300 秒；额度、到期和停用都实际阻断。验收账号在两条线路各下载 64 KiB，合计耗尽 90,000 字节剩余额度，两条线路随后均拒绝访问。面板离线时，到期时间仍终止了正在下载的长连接；停用验收账号后另一个原账号仍可连接。 |
| 9 迁移与恢复 | 先验证影子用量再开启正式扣量；备份原镜像、数据库和节点；首次启动未拿到配置时拒绝回落旧共享身份，恢复旧版排障后再迁移成功；此后验证了离线队列随 Agent 重启保留。旧共享 SS 密码在 443/1443 均实测失效。旧共享身份的既往流量无法补拆给用户。 |

## 实际线路

| 展示记录 | binding | SLINX 入站 | 入口 | 路由 | 期望状态 |
|---|---|---|---|---|---|
| 1 香港 01 | 1 | 1 | 38.55.195.102:443 | 原 direct 路由 | 绿 |
| 2 新加坡 01 | 2 | 4 | 38.55.195.102:1443 | 原 ipfoxy-ssh | 红；用户确认这是故意保留的认证失败测试 |
| 5 亚太 ISP 02（美国出口） | 3 | 1 | 38.55.195.102:443 | 按 `EF_3_<user>` / `EFProbe_3` 身份到 rayobyte-sg-http | 绿 |

新线路由用户提供的 HTTP CONNECT 账号接入。实测出口包括 64.16.139.187、158.62.215.92、158.62.215.86，查询的地理库均返回美国；`-country-SG` 请求返回 551，因此没有把它当成实际新加坡出口交付。供应商也明确说明亚太接入网关不决定出口国家：[接入点说明](https://docs.rayobyte.com/proxies/connecting/entry-points/)、[国家定位说明](https://docs.rayobyte.com/proxies/targeting/country/)。凭据只存于 SLINX 服务端数据库和核心配置。

新端口 2443 的监听在服务器正常，但从本机公网连接超时；曾经的 8443 也有同类问题。已改为共用实测可达的 443，停用未使用的 2443 入站，避免引入一个无法连接的订阅入口。

香港：两账号分别完成 HTTPS、131,072 字节下载及 65,536 字节上传，并检查回传 SHA256。
新增 ISP：两账号分别完成 HTTPS、65,536 字节下载及 65,536 字节上传回显；出口不同于香港服务器。

## 计量边界

- 一个 Agent 管理当前全部线路。服务端部署脚本拒绝添加第二个独立 Agent，以免重复发放完整预算；多 Agent 预算租赁尚未实现。
- 当前支持固定费率，费率变更生成新快照。动态费率节点会明确拒绝迁移/续租，不会默默套错倍率。
- 本地用量包含已入账和待补报部分；面板返回自己的已入账累计值，避免将 ACK 后的用量扣两次。
- 每秒采样，跨小时按采样区间比例分桶；边界归属有采样精度误差。补报依据发生周期，不从新月份额度扣旧周期流量。
- 核心突然崩溃、强制断电或落盘失败可能丢失最后一次成功采样之后的少量内存计数。持久化队列不因 HTTP 失败丢失，不承诺核心内存统计绝对零损失。
- 额度耗尽存在采样和应用延迟，不是精确到最后一个字节的硬限额。测试的小额度可能在一个采样间隔内超出。
- 为确保旧长连接失效，身份权限变化使用受控核心重载；当前会使该核心的其他连接短暂重连。单独关闭某一身份的已有连接尚未提供。
- 公网原生 UDP 本轮请求超时，未把它当作公网可用能力交付；当前三条线路的 Clash 配置均不宣告 UDP。服务器内部经同一认证 SS 入站的真实 UDP DNS 请求成功并进入逐用户计量；HTTP/SSH 上游仍是 TCP 能力。
- 原始账本保留去重标识，不随小时统计清理；持续生产需要监控 MySQL/SQLite 空间。待报队列超过 100,000 条会在健康中报告，持久化失败会停止放行。

## 代码、构建及运行

EverFlame 主要文件：`src/Services/EfSync.php`、`src/Controllers/EfAgentController.php`、`db/ef-agent.sql`、`scripts/ef-{provision,control,verify}.php`。
SLINX 主要文件：`internal/efagent/`、核心计量/配置/生命周期 hook、`cmd/ef-observer/`。

```powershell
# 从正式地址查看当前状态（仅输出不含凭据的信息）
docker compose exec -T app php scripts/ef-control.php ef-hk-01 status
docker compose exec -T app php scripts/ef-verify.php ef-hk-01

# 按源代码正常构建；observer 的 context 指向相邻 SLINX-EF 仓库
docker compose build app
docker compose -f compose.yaml -f compose.cloudflare.yaml -f compose.ef-sync.yaml build observer
docker compose -f compose.yaml -f compose.cloudflare.yaml -f compose.ef-sync.yaml up -d
```

当前机器首次构建遇到 Composer 下载中断，已使用 `deploy/docker/Dockerfile.sync` 从备份镜像复用完全相同的 composer.lock 依赖层，并在镜像内核对 lock 文件。
完整 Dockerfile 已将 Composer 安装移到源码 COPY 之前，后续单纯改代码不会重复下载依赖。

观察器也提供 `Dockerfile.ef-observer.runtime`：使用本机已验证 Go 1.26.3 构建的 Linux 二进制和固定 sing-box 1.13.12，构建时验证 SHA256。`.dockerignore` 排除临时凭据、备份、`.ci` 和运行数据。

节点配置 `/etc/slinx/data/ef-agent.json`；面板专用凭据在持久化 storage 卷的 `ef-ef-hk-01.json`；外部观察器只挂载 `storage/ef-observer.json`，没有面板管理员权限。真实角色验证：观察器读取探测配置返回 200、不含用户凭据；同一观察器请求用户 desired-state 返回 401。统计 API 仍只监听 127.0.0.1:2048/9090。

发布工作流的 BUILDINFO 增加 `feature_ef_agent_v1=1`；安装器和内置更新器都验证带校验和的能力标记，启用 EF Agent 后拒绝被无计量能力的旧 Release 覆盖。此次已部署源码构建，未冒称 GitHub 已有新的公开 Release。

## 验证命令

```powershell
# SLINX：指定实际完整核心，运行全量 Go 测试
$env:EF_CORE_PATH='G:\Project\SLINX-EF\.ci\full-core-gvisor\sing-box.exe'
go test -tags nomsgpack ./...

# 面板事务测试必须使用隔离的 *_sync_test 数据库
docker compose exec -T -e DB_DATABASE=everflame_sync_test app php scripts/test-ef-sync.php
```

隔离库验证：首次入账、丢失 ACK 重放、事件内容冲突、整批回滚、未授权绑定、旧周期补报、未来时间拒绝、过期探测及核心 stopped。
Go 验证：队列重试与部分 ACK、两线路共享额度、租约/禁用、本地时区边界、同监听多线路认证路由、禁用入站、旧版降级保护，以及原有全量回归。

## 备份和回滚约束

- 首次备份镜像：`everflame-app:before-ef-sync-20261108`。
- 首次面板 SQL：`storage/ef-backups/20261108-sync/panel.sql`。
- 首次节点备份：`/etc/slinx/backups/ef-sync-20261108`，包含原二进制、SQLite 一致性快照及原配置。
- 正式扣量后只能恢复支持 EF Agent 的构建，并保留当前 `ef_usage`、`ef_credentials`、`ef_agents`、`ef_bindings`、`ef_tariffs` 和节点 ef_* 表。不要直接恢复扣量前 SQL/SQLite，也不要把旧共享密码重新开放。
- 紧急冻结：`php scripts/ef-control.php ef-hk-01 freeze` 拒绝新的授权通信；已缓存授权最长 300 秒失效。需要立即断流时，在节点停止 slinx。恢复：`resume`，保留 outbox，先补报再取得新预算。
- 回滚二进制前先停止节点并用 SQLite backup API 保存现有库；只切换兼容二进制/配置，禁止覆盖当前账本与队列。源码与部署哈希及最终快照另见 `storage/ef-backups/20261108-sync/` 的本地记录。

验收账号 3 最终停用，仅保留其真实测试账目；临时测试服务会在交付结束前停止。

## 最终版本核对

- 实际停止 Agent 后，心跳年龄 93 秒时面板返回红色、原因“Agent 心跳已过期”；恢复后香港及 ISP 线路重新绿色。
- 真实停止核心后，面板在下一次独立心跳中显示红色；恢复核心后正常。
- 正式扣量后的回滚故障注入：用立即失败的候选程序启动，检测到 systemd 非 active，随后恢复兼容快照的二进制。恢复 SHA256 一致，服务 active，SQLite 未被覆盖，outbox 前后均为 0。
- 节点版本：`SLINX-EF ef-sync-20261108 56f576c29068+920ffa7a70d4f2ba`。
- 节点二进制 SHA256：`5e79c404c66c90c773c3bf3347332ccae29b5f417ae4f574aac95450bad19ea1`。
- 最终兼容节点快照：`/etc/slinx/backups/ef-sync-final-20261108`。
- 面板 11 个关键文件与工作区 SHA256 全部一致；observer 二进制及固定核心哈希一致。
- Docker app 镜像：`79b58532ffbb6cd6b60572aa7705d21876847b3f359a42a0d1fc8ca999fb0805`；observer 镜像：`793b23954af22026bc3d61bbb5bb6ea741daaeb1ac34fba6e8de6473bbb1278c`。
- 运行记录：`storage/ef-backups/20261108-sync/final-artifacts.json`、`container-source-check.json`、`panel-final.sql`。记录和 SQL 均在 Git/Docker 构建上下文之外。
- 临时公网测试服务已经停止，验收账号已经停用。提供的代理凭据在两个仓库的已跟踪及未忽略文件中均未发现。

用户客户端需要刷新原面板订阅，获取新的独立 SS 身份；旧共享密码已撤销。
