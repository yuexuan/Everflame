# Mihomo DNS 链路审计（2026-11-10）

## 结论与故障范围

不是站点整体下线。历史日志直接记录过滤 DNS 返回零地址：

```
2026-11-09T22:30:15.667946100+08:00 [DNS] gator.volces.com --> [0.0.0.0] A from udp://94.140.14.14:53
2026-11-09T22:34:37.216586100+08:00 [DNS] cache hit gator.volces.com --> [0.0.0.0] A, expire at 2026-11-09 23:30:15
```

此日志文件共有 3 条零地址上游回答、2391 条零地址缓存命中。首次已记录来源是 UDP 94.140.14.14，后续缓存和 DIRECT 拨号使用该结果。该地址属于 AdGuard 过滤 DNS；本轮保留过滤意图，不删除服务器、不加域名白名单。UDP 来源标识不能排除链路劫持；未抓包验证服务端身份。原始报错中 cn-domain 那一次的对应完整时间线尚未逐条关联，不能把所有零地址故障一概认定相同来源。

另一个通用问题是客户端 DNS 覆盖，1053 未监听是独立现象。当前内核 1.19.33，PID 36584；TCP/UDP 1053 均未发现此进程监听，实际混合入口 4651、控制入口 63922。内核 DNS API 可解析，说明没有本地监听不等于内部解析不可用。

## 配置来源和覆盖关系

1. SLINX-EF `internal/core/node_export.go` 导出节点，面板导入/EF 同步维护用户节点；SLINX 不是本机 CFW 的覆写来源。
2. 面板 `/sub/<token>/clash` → SubController → Subscribe → `src/Services/Subscribe/Clash.php:183`。启用 Clash_Mihomo_Profile 后，从 `config/profiles/mihomo.php` 和当前用户节点调用 MihomoProfile::build。
3. `MihomoProfile.php` 使用 base，再替换 proxies/proxy-groups/rule-providers/rules；custom-rules 位于广告及业务公共规则之前。private 优先，广告 PASS/REJECT 保留手选，业务规则在 cn-domain 前，最后 cn-ip/no-resolve 和 MATCH。
4. 数据库 config.item=mihomo_dns 存在且有效时，`MihomoDns::compile` 以 profile.dns 为基础替换 enable/ipv6/enhanced-mode/nameserver/nameserver-policy。保留 bootstrap、代理节点 DNS、DIRECT DNS、follow-policy、监听和 fake-ip-filter。未保存时不自动应用管理页面的默认草稿。
5. DNS Policy：private→system 固定；其余按 priority/id 排序；rule-set 必须存在且 behavior=domain。精确/后缀最终受内核域名树的精确/最长匹配语义影响，priority 不是所有匹配类型统一的逐条路由顺序。Provider 出口显式 #DIRECT 或现有代理组。DIRECT follow-policy=true，所以命中 Policy 时可以覆盖 direct-nameserver；这属于原设计。
6. 规则 Provider 是 HTTP MRS，使用节点选择下载，独立周期更新，不调用面板 DNS 编译器。离线首次启动且无缓存时规则下载仍依赖所选节点；未更改策略。
7. 管理后台保存只写数据库；下一次订阅生成生效，不推送内核、不清客户端 DNS 缓存。规则定时更新也不更新订阅。
8. 本机 CFW 的订阅文件保留阿里 /dns-query、cn-domain Policy、127.0.0.1:1053、Fake-IP；没有 hosts。启动 config.yaml 是控制端口基础配置，不等于最终运行配置。
9. 读取当前安装包 `C:/Program Files/Clash for Windows/resources/app.asar` 验证：`dist/electron/features/profiles/prepare-profile.js` 的顺序是订阅 parse → `{...config,...tunConfig}` → YAML Mixin `{...config,...mixin}` 或 JS Mixin → 规范化及 Provider 安全路径转换。这里是顶层浅合并，整块 dns 可被覆盖。
10. `dist/electron/features/tun/build-tun-config.js` 默认 nameServers 为 8.8.8.8、1.1.1.1、94.140.14.14；生成 DNS 不包含 listen、direct-nameserver、proxy-server-nameserver 等订阅字段。profile-application.js 随后序列化，通过 clashApi.putConfig 下发，ipv6/log-level 另取客户端全局设置。

运行日志确实使用上述三个 UDP 上游，与订阅阿里 DoH 不同；这证明订阅与当时运行解析不一致，且安装包存在能产生同样结果的覆写路径。尚未成功读取客户端当前 TUN/Mixin 持久化设置和完整 API 提交体，不能唯一判定当前是 TUN、Mixin 还是两者组合。UI 只能确认当前火未央订阅；后续控件交互因截图捕获超时失败。没有修改客户端安装包或设置。

## 最小修复

`MihomoDnsSettings::load(bool $strict=false)` 新增严格读取模式，后台 DnsController 使用严格模式：数据库异常/坏 JSON 返回 HTTP 422，避免当成未保存状态显示默认值。订阅保留原有记录错误并退回模板的兼容行为，未引入门禁或改变流量策略。

`scripts/audit-mihomo-runtime.py` 是只读诊断：版本、关键规则集计数、内部 DNS API、1053 UDP/TCP 查询和有界零地址日志证据；不打印 token/secret/节点配置。需要 PyYAML。

`scripts/test-mihomo-dns-core.py` 扩展现有隔离测试，增加热重载、TCP/UDP Fake-IP 一致性、cn Policy DIRECT 二次解析后真实 HTTP 请求。使用固定测试端口，须先启动隔离 PHP fixture；不能对生产数据库运行 fixture。

## 实际验证结果

- PHP 8.3.36 / PHPUnit：MihomoProfileTest 13 tests、930 assertions，通过（构建后的镜像再验通过）。全目录尝试包含 Pest 的 beforeEach，单独 PHPUnit 无法执行；不宣称全仓测试通过。
- 受控 Mihomo 1.19.33：google.com/youtube.com/gmail.com/gemini.google.com→Google/SOCKS；baidu.com→Ali/DIRECT；未匹配→Cloudflare/SOCKS；自定义 example.com→custom/DIRECT，共 7 项通过。
- PUT /configs?force=true 实际热重载 204；Fake-IP UDP/TCP 都返回 198.18.0.4；gator 测试域名经 cn Policy 二次解析，访问受控 HTTP 目标成功。测试使用代表性 inline 规则，不能代替真实公网业务健康或所有公共规则更新验收。
- 故意破坏隔离 SQLite 的 JSON：后台 ret=0、HTTP 422，提示 DNS 设置读取失败；订阅仍 HTTP 200 回退基线。未修改生产记录。
- 公网 Ali DoH 实际 POST /dns-query（wire DNS）HTTP 200、RCODE 0、8 answers，未含零 A 记录；不再用 /resolve 代替。
- 本机内核 API：gator 返回 163.181.87.x；baidu.com、example.com 均有非零 A；cn-domain 110177 条，private-domain 132 条。
- curl --proxy http://127.0.0.1:4651 -I --max-time 12 https://gator.volces.com/：CONNECT 200，TLS 后 HTTP 404，说明当次无零地址拨号；不是业务 API 健康证明。
- SLINX-EF Go：首次 CGO=0 测试因 sqlite stub 失败；正确设置 CGO_ENABLED=1 和既有 MinGW GCC 后 go test ./... 全部通过。

## 发布、恢复与未闭环事项

面板使用现有 Dockerfile.sync 构建，composer.lock 一致性校验通过；保留 `everflame-app:before-dns-audit-20261110`。新版与旧版 appprofile.php SHA256 一致；只更新 app 服务，不重建数据库或改节点。恢复：将备份镜像重新 tag 为 everflame-app:latest，再用相同 compose 文件 up -d --no-deps app。

SLINX-EF v0.0.9 使用仓库现有双架构 Release workflow；旧上游 v0.0.2 到 v0.0.8 标签保留。本次发布不是远端 VPS 安装升级。

尚未闭环：客户端覆写设置及完整提交体、公共规则实际更新后 DNS 保留、公网代理 DNS 与完整业务 API 健康、过滤服务器当前策略确认。当前 1053 故障仍在；项目代码无法强制客户端保留订阅字段。没有通过删除过滤、关闭 Fake-IP 或合并所有 DNS 来掩盖这些问题。应在客户端配置归属明确后选择保留订阅 DNS 或明确配置覆盖，备份后再执行与复验；本报告不宣称整个验收标准已经达成。

参考：Mihomo DNS 官方规范 https://wiki.metacubex.one/config/dns/ （2026-11-10 阅读）。
