# SG 01 出口迁移

2026-11-10（Asia/Shanghai）。

- 保留香港入口 `38.55.195.102:1443`、SG 01 展示记录、binding 2 和逐用户认证身份。
- 新出口 `163.61.199.85:443`，香港至新加坡采用 Shadowsocks 2022，`2022-blake3-aes-256-gcm`，独立随机密钥。SSH 30022 仅用于运维。
- SG 服务为 `ef-sg.service`，程序和配置位于 `/opt/ef-sg/`，配置权限 0600，开机启动。
- 为保持已有路由引用，endpoint 11 的内部 tag 暂保留 `ipfoxy-ssh`；实际协议已改为 shadowsocks。
- 两个现有用户均通过 SG 01 完成 HTTPS 204 与公网出口 IP 验证，出口均为 `163.61.199.85`。
- 订阅六种格式身份一致，用户身份独立，当前周期计数与正式账本一致。
- SLINX 前端构建、类型检查和全量 `go test -tags nomsgpack ./...` 通过。
- SLINX 部署版本 `ef-sync-20261110 56f576c-workspace`，包含当前未提交工作区功能；两个仓库 HEAD 均与各自 origin/main 一致。未将其他来源的上游变更合并进来。
- EverFlame app 和 quota 重新构建并部署当前工作区代码。备份镜像 `everflame-app:before-sg-20261110`。
- 节点旧二进制、SQLite 一致性快照和原 endpoint 位于 `/etc/slinx/backups/sg-20261110/`。正式计量已运行，回滚时只恢复兼容程序和目标 endpoint，禁止恢复旧账本覆盖新用量。
- sing-box 仍使用已验证的固定版本 1.13.12；此次没有宣称将第三方依赖升级到最新发行版。
- 公网 UDP 尚未验收，客户端继续保持原有 TCP 能力声明。
