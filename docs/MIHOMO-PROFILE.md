# EverFlame Mihomo 订阅模板

第一版针对 Mihomo；`/sub/<token>/clash` 使用当前用户通过节点等级、分组、流量条件筛选后的节点生成配置。sing-box 订阅保持原有配置。

当前本地面板已接通 SLINX 导出 → EverFlame 数据库导入 → 面板订阅 HTTP 导出，新模板已经在运行中的应用启用。Admin 账号下实际保存「香港 01」「新加坡 01」两个节点；本轮最终验收见文末。

## 配置与启用

- `config/profiles/mihomo.php`：DNS、公共规则源、地区匹配、服务分组及自定义规则。
- `src/Services/Subscribe/MihomoProfile.php`：将本次订阅的节点组合成完整配置。没有节点时代理组使用 REJECT，不自动降级为 DIRECT；地区组只在有匹配节点时出现。
- `config/appprofile.example.php`：新安装默认启用。已有 `config/appprofile.php` 不会自动覆盖，保留原 Clash 配置路径。

已有部署在 `config/appprofile.php` 的旧 Clash 配置后加入以下内容即可切换；先备份该文件，删除新增的两行即可恢复旧模板。不要覆盖自己的 sing-box 或其他自定义配置。

```php
$_ENV['Clash_Mihomo_Profile'] = require __DIR__ . '/profiles/mihomo.php';
$_ENV['Clash_Mihomo_Profile']['base']['tcp-concurrent'] = $_ENV['tcp_concurrent'] ?? true;
```

需要同时发布新版 PHP 类和 `config/profiles/`。Docker 代码在镜像内，验证后重建应用镜像生效；数据库不需迁移。

## 策略与更新

采用 ACL4SSR 常见的服务/地区分组思路，独立编排配置，公共数据来自 [MetaCubeX/meta-rules-dat](https://github.com/MetaCubeX/meta-rules-dat)。没有直接加载或执行 ACL4SSR `.ini`。

- 每个 HTTP Rule Provider 的 `interval=86400`，由运行中的 Mihomo 每 24 小时拉取独立 MRS 数据；不再依赖整份 GeoSite/GeoIP 文件。首次运行需要能下载规则，后续使用客户端本地缓存。面板不为每个订阅请求下载规则，也不需要每天重写模板。
- 规则顺序：局域网域名/IP → 自定义规则 → 可选广告规则 → 国内游戏下载 → 细分服务 → 国内域名 → 国外域名 → 国内 IP → 默认代理。
- ChatGPT、Claude、Gemini、TikTok 默认使用手动固定组。它们共享固定组的初始选择，但可在各服务组中分别选择不同节点组。固定组第一项取本次节点顺序中的第一项；用户手动指定后由客户端持久化，节点被删除时需重新指定。
- GitHub 直接手动选择具体节点，独立于全局选择和共享固定节点；无节点时 REJECT，所选节点故障时不自动切换。首次使用默认节点列表第一项，客户端更新后需手动确认专属出口。
- YouTube、Netflix、Disney+、Spotify、Telegram、Discord、X 独立选择；OneDrive 在 Microsoft 规则之前，Gemini 在 Google 规则之前。
- 国内游戏下载规则单独走“游戏下载”，Steam/Epic 平台服务另行分流。不会按端口或进程把全部游戏下载器流量放行。
- 广告组默认 PASS，继续匹配后续规则；选择 REJECT 才启用域名广告拦截。
- 境外 DNS 经节点选择组，节点域名使用国内 DoH 启动解析；局域网域名使用系统 DNS，国内域名使用国内 DoH。桌面客户端负责自己的 TUN 开关，模板不强制开启系统路由。
- 节点选择默认使用固定节点；所有节点组和地区组均手动选择。模板不提供故障转移或自动测速切换，所选节点故障时连接失败，不自动换节点、换地区或降级直连。
- 地区按节点名称匹配，并非出口 IP 检测，也不代表 AI/流媒体解锁。没有预设任何节点已经解锁。

2026-11-09 起移除自动切换策略。更新客户端订阅后，请核对各服务和固定节点的实际选择；首次使用或所选节点被删除时，客户端可能选中组内第一项，需要手动确认出口。下方验收中的故障转移行为是旧版历史记录。

细分规则在 `custom-rules` 数组添加，例如 `DOMAIN-SUFFIX,example.com,🧠 Claude`。局域网保护规则优先于这些覆盖项；其余公共规则排在后面。不要添加中途的 MATCH 规则，否则后面的规则将不可达。修改模板后需要更新订阅；修改上游名单则等待 Rule Provider 更新即可。

### GitHub 覆盖补充（2026-11-09）

- 保留 MetaCubeX `github` MRS Provider，每 24 小时更新；其中已有网页/API/Git/SSH、GitHub Pages、Raw/Release 下载、GHCR、Copilot 核心域名，也包含 npmjs.com/npmjs.org，npm 仍走 GitHub 组。
- `service-rules` 补充 `default.exp-tas.com`，以及 Maven/npm/NuGet/RubyGems 的四个 `*registryv2prod.blob.core.windows.net` 具体端点。
- 使用首尾锚定的正则补充 `productionresultssa<数字>.blob.core.windows.net`、`copilot-reports-<标识>.b01.azurefd.net`、`usagereports<标识>.blob.core.windows.net`。不接管整个 Azure/AWS 公共域名；这些规则在 Microsoft/国内/兜底规则前匹配，用户的 `custom-rules` 仍优先。
- 来源：[GitHub 官方 Meta API](https://api.github.com/meta)、[Copilot allowlist reference](https://docs.github.com/en/enterprise-cloud@latest/copilot/reference/copilot-allowlist-reference)、[社区 GitHub 清单](https://raw.githubusercontent.com/MetaCubeX/meta-rules-dat/meta/geo/geosite/github.list)。这是已核对端点的补充，不承诺未来新增服务、自定义 Pages 域名或外部 Actions 依赖自动归入 GitHub。
- Git/SSH 流量需要客户端实际接入代理（例如 TUN 或 SSH ProxyCommand）；单独开启浏览器系统代理不等于终端 SSH 已接入。

## SLINX-EF 节点与本地验收

从 SLINX-EF 用户订阅导出 Clash/Mihomo YAML，将其中的具体 `proxies` 交给离线组合工具。工具保留节点传输/TLS/Reality 字段，丢弃输入中的分流与 DNS，仅使用 EverFlame 模板；远程 `proxy-providers` 需要先展开，避免误认为已经导入节点。不要导出服务端 Reality 私钥或 ISP 出口凭据。

```sh
php scripts/render-mihomo.php /private/slinx-nodes.yaml /private/everflame.yaml
mihomo -t -d /private/mihomo-check -f /private/everflame.yaml
```

源文件与产物都可能含 UUID、密码和公钥等连接信息，应留在仓库外。这个工具用于个人配置和验收，不向机场数据库导入节点，不更改 SLINX 服务端。

### 导入面板再导出

`scripts/import-slinx.php` 将当前 SLINX 的 AEAD Shadowsocks 节点实际写入 `node` 表。保留每个节点的地址、端口、密码、加密方式、UDP 和插件设置；导入数据存储在节点 `custom_config.external_shadowsocks`，不修改面板用户的节点密码或登录密码。

```sh
# 在已配置数据库连接的应用环境执行；不加 --apply 时仅预览。
php scripts/import-slinx.php /private/slinx-nodes.yaml 1
php scripts/import-slinx.php /private/slinx-nodes.yaml 1 --apply
```

- 第二个参数是凭据所属的 EverFlame 用户 ID。所有用户节点列表和订阅共用的筛选层会过滤其他用户的导入节点，包括其他管理员的订阅。
- 使用来源、所属用户、服务器地址和端口识别已有导入节点，再次导入更新原记录，不重复创建。保留源订阅的节点优先顺序，避免按中文名称排序改变默认固定节点。
- 全部输入验证后在数据库事务中写入；不同来源的同名节点会拒绝覆盖。当前导入器只接受明确支持的 AEAD Shadowsocks 字段，不将 Reality 或其他协议伪装成 Shadowsocks。
- SS 的 Clash、sing-box、SIP008、SIP002、SS、V2Ray JSON 导出共用导入凭据解析器；原生面板节点继续使用原来的用户凭据。
- 登录面板的节点管理即可查看已导入记录；用户首页原来的 Clash 订阅按钮输出的就是新模板，无需用离线组合工具绕过面板。

本次是将已有 SLINX 凭据绑定给指定面板用户；不包含两个面板之间持续的用户/计费同步。需要扩展为多人独立账号时，再增加 SLINX 逐用户认证同步。Mihomo 模板本身不存储共享凭据。

## 验证

`tests/Unit/Services/MihomoProfileTest.php` 覆盖节点凭据保留、用户节点隔离、空节点阻断、地区组、策略引用、服务覆盖顺序、每日更新及 DNS 启动解析。可由项目 Pest/PHPUnit 执行，测试本身不依赖数据库。

### 手动选择策略更新（2026-11-09）

- 移除故障转移、自动测速组及其引用；节点选择默认指向固定节点，地区组保留手动选择。现有国内直连分流规则不变。
- PHP 8.3.36 / PHPUnit 11.5.57：8 项测试、842 次断言通过，包含所有策略组仅使用 select、无自动切换引用及空节点 REJECT 检查；修改的 PHP 文件语法检查通过。
- 有节点、空节点配置以及运行面板的真实 HTTP 订阅均通过 Mihomo 原生配置检查；HTTP 导出确认节点选择默认使用固定节点，所有策略组均为手动选择。
- 基于正在运行的应用镜像仅替换模板类和配置文件，已更新本地 app 服务。回滚镜像标签：`everflame-app:before-manual-20261109`。客户端需更新订阅并确认固定节点选择后生效。

### GitHub 专属节点与补充规则验证（2026-11-09）

- 10 项模板测试、888 次断言通过，PHP 语法检查通过；有节点、空节点配置通过 Mihomo 原生配置检查。
- 受控本地 HTTP 代理验证 15 个 GitHub/Raw/Release/GHCR/npm/Copilot/Packages/Actions 域名的真实规则命中，均走 GitHub 专属节点，不跟随全局节点。关闭专属代理监听后，连接失败，所选节点保持不变且未连接全局代理。这是路由行为验证，不代表生产节点连通或平台解锁。
- 对照当日官方 Meta API 的 website/copilot/packages/actions 共 220 条域名项，社区清单加补充规则全部覆盖。
- 已使用仅替换模板类和配置文件的增量镜像更新本地 app。真实 HTTP 订阅返回 200，3 个节点及其凭据与更新前一致，GitHub 组直接列出这 3 个节点，8 条补充规则齐全，订阅通过 Mihomo 原生检查。回滚镜像：`everflame-app:before-github-20261109`。

规则格式与字段参考：[Rule Provider](https://wiki.metacubex.one/config/rule-providers/)、[DNS](https://wiki.metacubex.one/config/dns/)、[策略组](https://wiki.metacubex.one/config/proxy-groups/)。发布前还需对生成的完整配置运行实际 Mihomo 检查，并用有效节点验证真实网络与规则命中。

### 本轮结果（2026-11-08）

- PHP 8.3.36 + PHPUnit 11.5.57：7 项测试、855 次断言通过；修改的 PHP 文件语法检查通过。
- 28 个 MRS 远程地址返回 HTTP 200，并实际下载、由 Mihomo 1.19.33 加载编译成功。
- 含既有 Reality 导出的完整配置、空节点配置均通过 `mihomo -t`。导出保存在仓库外，未加入机场数据库或模板。
- 使用受控本地 HTTP 节点，仅替换测试配置中的节点，保留模板的规则和策略组；原生连接跟踪验证 ChatGPT、Claude、Gemini、YouTube、Netflix、Discord、GitHub、Steam、未知域名兜底共 9 项命中通过。这证明规则编排，不代表生产出口或平台解锁可用。
- 本轮生产 SSH 连接在协议握手前被关闭；此前保存的 Reality 节点 HTTPS 请求被远端重置。因此尚未完成生产最新节点导出、生产真实连通、用户身份同步或上线。需要恢复访问后继续验收。
- 现有运行中的应用容器未被重建，已有自定义 appprofile 未被覆盖。本轮交付为仓库实现和隔离验证。

### 恢复连接后的生产复验（2026-11-08 16:28，Asia/Shanghai）

- SSH 已恢复，SLINX 服务 active。通过服务器本地订阅接口，以当前唯一启用用户重新导出，读取的是现有生产订阅；未创建用户、修改节点或重载服务器配置。
- 最新节点为 Shadowsocks「香港 01」（443）和「新加坡 01」（1443），已经替代此前验收使用的旧 Reality 导出。节点凭据及生成的个人 YAML 均保存在仓库外。
- 两节点组合 EverFlame 模板后通过 Mihomo 1.19.33 原生检查，并按名称生成香港、新加坡地区组。
- 香港节点：通过生成模板实际请求 ipify 和 Cloudflare Trace，均为 HTTPS 200；两处确认出口 IP `38.55.195.102`，Cloudflare 地区为 HK。
- 新加坡节点：两个 HTTPS 请求均失败。服务器日志对应请求明确显示 `outbound/ssh[ipfoxy-ssh]` 的 `ssh: unable to authenticate, attempted methods [none password], no supported methods remain`。上游 SSH TCP 连接与 banner 正常，故障定位在当前上游认证；需核对供应商账号/密码或账号状态，不把它归为模板规则故障，也未更改为直连来绕过失败。
- 本次导出是个人静态节点配置，公共 MRS 规则仍自动更新；以后节点或连接凭据改变，需要重新导出。机场逐用户认证同步及应用容器上线仍未执行。

### 面板完整链路验收（2026-11-08，后续完成）

- 用户明确要求从面板导出后，已将两条最新 SLINX 订阅节点导入本地 EverFlame 的 Admin（ID=1），数据库节点 ID 为 1、2。重复执行导入后仍为两条记录，导入为更新而不是追加。
- 在仓库外备份了导入前数据库和活动 appprofile，并保留旧应用镜像。使用仅包含本次 PHP/配置文件的增量镜像更新应用，保留原有 appprofile 其他设置和数据库、Redis、storage 卷。
- 新版实际订阅接口返回 HTTP 200 / application/yaml，产物包含 2 个节点、28 个策略组、28 个 MRS Provider、29 条规则。源 SLINX 与面板输出逐字段核对地址、端口、密码、加密方式和 UDP，一致。
- 用真实数据库的用户筛选路径验证其他用户无法获得导入节点；面板账户信息未改动。补充的凭据解析测试与原模板测试合计 12 项、865 次断言通过。
- 面板 HTTP 导出的配置通过 Mihomo 1.19.33 原生检查；按其默认故障转移组实际 HTTPS 请求成功，出口 `38.55.195.102`。单独选择香港节点的 ipify 和 Cloudflare HTTPS 请求均成功。
- 新加坡节点已完整导入并出现在面板订阅中；其上游 SSH 认证失败仍导致真实连接失败，故障与之前一致，未用直连替换源路由。
- 修正订阅入口将 Host 固定拼为 HTTPS 的检查，改为核对配置中的主机和端口，支持当前 localhost HTTP 部署以及由反向代理终止 TLS 的部署。
- 本次从用户主页复制的 Clash 订阅是 EverFlame 自己的链接：更新订阅时从面板数据库重新生成节点和模板，公共规则仍由客户端独立更新。

### 订阅名称编码修复（2026-11-08）

客户端显示 `\xe7\x81...` 的原因是原 `Content-Disposition` 直接包含中文 UTF-8 字节。已改成 ASCII 回退名 `EverFlame` 加 `filename*=UTF-8''%E7%81%AB%E6%9C%AA%E5%A4%AE`，支持扩展文件名的客户端解析为「火未央」。真实 HTTP 验证响应头全部为 ASCII、UTF-8 名称解码正确，修复前后订阅正文逐字节一致。

[Clash Verge Rev 的导入逻辑](https://github.com/clash-verge-rev/clash-verge-rev/blob/dev/src-tauri/src/config/prfitem.rs) 优先读取 `filename*`；已缓存的订阅名称可在客户端编辑为「火未央」，无需删除节点或重置配置。

### 月度 300G 与普通用户验收（2026-11-08）

- 复查确认此前仅配置 `reg_traffic=300`，注册有效期、免费用户月度重置日/额度均仍为 0；Admin 创建命令也不继承注册额度，数据库保留 0 流量及 1989 年默认日期。
- 已更新运行库及配置默认值：`reg_class_time=30`、`reg_traffic=300`、`free_user_reset_day=1`、`free_user_reset_bandwidth=300`。采用现有面板“30 天一期”的有效期，每月 1 日重置额度；额度重置不自动续期。
- Admin（ID=1）修正为 300G、2026-12-08 到期，保留其登录信息和订阅地址。通过正常管理员创建用户所用的 `registerHelper` 创建普通账号「月度订阅测试」（ID=2），自动继承同样配置；没有为它事后手动伪造订阅响应。
- 已为测试用户导入两条指定凭据记录，Admin 的节点 ID 为 1/2、测试用户为 3/4，按 owner 隔离。不同用户可以持有同名源节点，导入器不会因此覆盖其他用户的记录。
- 两个账号真实 HTTP 订阅均返回 `total=322122547200`（面板的 300G）及 2026-12-08 的有效到期时间。测试用户真实密码登录、已认证用户首页和节点列表均通过，导出的配置通过 Mihomo 1.19.33 检查。
- 新增独立 Docker `quota` 服务，按 Asia/Shanghai 时间每五分钟检查周期。首次观察只记录周期，不清空现有用量；跨月后在事务中重置，使用数据库锁和逐用户周期记录防止重复执行，并能在短月/月末及停机恢复后处理。不会发送邮件或其他通知。
- 实际数据库事务验证：同月重复运行保留已用流量，下个月重置为 300G，同周期重复跳过。未来日期验证全部回滚，真实账号未被提前重置或续期。单元测试合计 15 项、874 次断言通过。
- 300G 是当前面板账户额度。现有 SLINX 导入采用指定用户的固定连接凭据，尚无 SLINX → EverFlame 的逐用户流量回传；本轮不把额度展示与月度重置宣称为已完成服务端逐用户计量/断流。测试账号的登录信息及订阅保存在仓库外。


### 后台 DNS Provider / Policy（2026-11-09）

数据流：`/sub/<token>/clash` → `SubController` → `Subscribe::getContent` → `Clash::getContent` → `MihomoDnsSettings::load` → `MihomoProfile::build` / `MihomoDns::compile`。每次订阅请求读取 config 表 `mihomo_dns` JSON 字符串；不用 `Config::obtain(type=array)`，避免对象/数组类型问题。后台通过 updateOrCreate 创建或更新记录，不依赖仅更新已有行的 Config::set。配置归类 mihomo_dns，避免旧订阅设置保存接口覆盖。

先按项目现有迁移流程运行 `db/migrations/2026110900-mihomo_dns.php`，将 config.value 扩为 MEDIUMTEXT（不自动执行迁移、不修改运行库）。启用 `Clash_Mihomo_Profile` 后，从管理员「订阅设置 → DNS 管理」进入。首次未保存仍使用部署者的模板 DNS 基线；保存页面默认预设后启用 Google / YouTube / Gemini → Google，cn-domain → 阿里，未匹配 → Cloudflare，private-domain → system。四个 Provider 预设包括腾讯，支持额外自定义 Provider。修改下次订阅请求生效，客户端需更新订阅。

只编译 DNS 开关、DNS IPv6、解析模式、nameserver 和 nameserver-policy；其余 DNS/bootstrap/direct 参数继承当前模板。未知额外字段不会覆盖节点、rules 或 services。数据库缺记录、读取失败、JSON 错误或当前用户最终配置中引用失效时使用模板基线，并记录不含配置内容的错误类型；后台读取非法保存记录会报错。不存在于空节点配置的地区组不开放给后台，确保设置适用于无节点用户；运行时再次按用户实际分组校验。

出口仅支持 DIRECT 或已有稳定代理组；不开放跟随路由或 DoQ。地址出口后缀由编译层生成，拒绝用户手填后缀、凭据、查询参数、不支持协议。停用或删除仍被默认或任何 Policy（包括停用 Policy）引用的 Provider 会拒绝保存，须先解除引用。Provider ID 是稳定引用，修改 ID 时须同步调整引用。

优先级：private 固定第一，其余按 priority 升序、同值 ID 排序。Mihomo 将连续 domain/domain-suffix 条目合为域名树，其内部按精确域名/最长后缀匹配；规则集按输出顺序处理。细分域名覆盖请设较小数字，放到宽泛规则集前；完全重复匹配及重复 ID 拒绝。rule-set 必须是最终生成的 domain 类型，禁止 ipcidr/GEOIP/进程条件。规则集内容沿用原公共源，本期不复制公共域名列表。

Cloudflare 仅是未命中的默认，不是 Google 请求失败后的严格后备；同 Provider 多地址不保证主备顺序。DNS 解析不能保证流媒体解锁。订阅的 127.0.0.1:1053 DNS 入口不会自动接管系统 DNS，仍需客户端 DNS/TUN 接管条件。未开发客户端或服务端 DNS 管理，未加入 AKDNS。

验证记录：独立 Docker PHP 8.3 容器，Profile 与 ShadowsocksCredentials 共 18 tests / 940 assertions 通过，PHP lint 和共享 JS 语法检查通过。隔离 SQLite HTTP fixture 调用真实管理员控制器和真实 SubController（不包含生产认证中间件），自定义 DoH 保存、刷新一致；fixture-nodes / fixture-empty 均返回含后台 DNS 的 YAML，Mihomo Meta v1.19.33 `-t` 均通过。两个主题的实际表单 body 用相同 Tabler CSS 在隔离浏览器检查，预览反馈成功；这不等于生产登录后的整页导航验收。

复验（独立容器，不挂载生产配置、不连接生产数据库）：

```powershell
docker run -d --name ef-dns-isolated-test -p 127.0.0.1:18891:18891 -e MIHOMO_DNS_TEST=1 -v G:/Project/EverFlame:/workspace:ro -w /workspace --entrypoint php everflame-app -S 0.0.0.0:18891 scripts/test-mihomo-dns-http.php
python scripts/test-mihomo-dns-core.py G:/Project/SLINX-EF/.ci/mihomo/mihomo-windows-amd64-compatible.exe
```

core 脚本需要 PyYAML；脚本自动在隔离 fixture 中添加 custom Provider 与 example.com Policy。受控测试将 Provider 地址替换为本地 TCP DNS、使用 SOCKS 测试节点及代表性 inline rule-set，验证 google.com、youtube.com、gmail.com、gemini.google.com、baidu.com、random.invalid、example.com 的响应与 SOCKS/DIRECT 实际出口。此项证明编译与出口绑定，不证明公共 DNS 可用性或公共列表完整性。公网节点真实出口与完整生产页面验收未做；禁止将本地受控测试描述为公网 Google DNS 测速成功。

本轮新增：MihomoDns / MihomoDnsSettings / DnsController、DNS 页面（两主题）、共享 JS/CSS、迁移、隔离 HTTP/core 测试脚本；增量修改 Clash/MihomoProfile、routes、两主题订阅入口和 Profile 测试。保留原有工作区修改，没有部署、提交、重置或生产数据改写。
