# EverFlame 机场 DNS 管理与 SLINX-EF 传统 Clash 导出清理任务

> 日期：2026-11-09。基于本地仓库实际代码制定；本文是 **Codex 开发任务**，不是已完成的实现报告。
> 本文存放于：`G:\Project\EverFlame\docs\MIHOMO-DNS-TASKS.md`。

## 一、范围及明确的纠正

**EverFlame（机场面板）**：统一托管节点，生成给用户的最终订阅；本期增量开发 Mihomo DNS Provider、DNS Policy、DNS 请求出口配置和后台管理界面，使客户端导入机场订阅就获得默认完整策略。**不要删除机场已有其他订阅格式/客户端兼容。**

**SLINX-EF（节点面板）**：仅做轻量节点管理、入站/出站/中继/健康检测和节点导出；本期独立清理 **SLINX-EF 的传统 Clash 导出兼容**（`/clash-legacy` 及专属逻辑），保留 `/clash` 现代 Mihomo 导出、原始 URI、Sing-box、Surge 等现有功能。不要删 Shadowsocks、VMess、Trojan 协议本身；不要因客户端 UI 使用旧风格就限制客户端内核。

**客户端**：用户可能继续使用传统风格 UI，但替换为现代 Mihomo 内核。本次 **不开发/改写客户端，不统一删掉机场的传统 Clash 相关实现**，客户端兼容不是验收重点。

**本期不做**：AKDNS/Smart DNS 专门集成、流媒体解锁检测和保证、定制本地 DNS 代理服务器、客户端 UI 改造、服务端 SLINX DNS 策略管理、权限/门禁重构、整个订阅系统重写。

## 二、已核对的本地代码位置（Codex 先复查）

### EverFlame：`G:\Project\EverFlame`

- `config/profiles/mihomo.php`：已有 DNS 静态配置；默认 CF DoH 经“🚀 节点选择”，国内域名阿里 DNS；带 `rule-set:private-domain`、`rule-set:cn-domain`、`direct-nameserver-follow-policy`。
- `src/Services/Subscribe/MihomoProfile.php`：`build($proxies, $options)` 从 `base` 和现有规则集组合最终配置；已有 Google、YouTube、Gemini、CN 等 `rule-providers`；手动节点组不可擅自改成自动切换。
- `src/Services/Subscribe/Clash.php`：当 `$_ENV['Clash_Mihomo_Profile']` 已设置时调用 `MihomoProfile::build` 输出 YAML；否则仍有历史生成路径。**保留后者，勿作为本期删除对象。**
- `src/Controllers/SubController.php`：现有 `/sub/<token>/clash` 用户订阅入口及响应头；URL 不能破坏。其他订阅格式同样保持。
- `config/appprofile.example.php`：加载 Mihomo 模板配置；现有自定义 `appprofile.php` 不宜覆盖。
- `src/Controllers/Admin/Setting/SubController.php`、`app/routes.php`：既有订阅设置路由 `GET/POST /admin/setting/sub`。
- `resources/views/malio/admin/setting/sub.tpl`、`resources/views/tabler/admin/setting/sub.tpl`：后台订阅设置有两个主题模板；新增界面遵循现有风格，避免重复两套业务逻辑。
- `src/Models/Config.php`：已有 config 表读写能力，注意 `Config::set` **只更新存在的行**，`type=array` 的读取可能得到 JSON 对象，不能假设一定是 PHP 关联数组。
- `tests/Unit/Services/MihomoProfileTest.php`、`docs/MIHOMO-PROFILE.md`：现有回归覆盖代理组、规则顺序、凭据、空节点 REJECT 等行为；必须保持。

### SLINX-EF：`G:\Project\SLINX-EF`

- `internal/sub/sub.go`：`ClashLegacy`、`legacyUnsupportedReason` 和 `Url` 返回 `/clash-legacy`。
- `internal/api/sub.go`、`internal/api/router.go`：`GetClashLegacySubscription` 和 legacy 路由。
- `internal/sub/template/clash.go`、`internal/sub/template/template.go`：双模板 `clash.yaml` / `clash-legacy.yaml` 与 `RenderClashLegacy`。
- `internal/job/ruleset.go`：刷新逻辑检查 legacy 模板存在。
- `web/src/view/sub/widget/Info.vue`：订阅 UI 中有“Clash 传统”和兼容说明，URL 标签顺序依赖当前四条链接。
- `internal/sub/clash_legacy_test.go`、`internal/sub/template/clash_test.go`、`internal/sub/shadowsocks_test.go`：传统兼容相关测试（不要删除现代 SS 协议测试）。
- `doc/CLASH-COMPATIBILITY.md`：包含传统导出说明；按实际变化修订，不误删现代协议的有效说明。

**注意**：当前两个工作区均存在大量未提交改动，任务执行时不得覆盖、重置、清理这些改动，不得做 `git reset --hard` 或自动提交。

## 三、目标模型：最小可维护方案

### 全局配置（机场管理员）

- DNS 开关、默认 Provider（Cloudflare）、IPv6/Fake-IP 等现有参数；默认值不应因新页面初始化而丢失。
- 本期不改变现有自动/手动节点策略；保留国内直连和未配置节点时 REJECT 的行为。

### DNS Provider（机场管理员）

最小字段：
- `id`（稳定标识）、`name`、`enabled`、`servers`（一个或多个合法地址，UDP/TCP/DoH/DoT；DoQ 仅在确认内核支持及校验后开放）。
- `egress`：`DIRECT` / 使用既有代理组名称 / 跟随既有路由（是否支持以 Mihomo 实际行为验证，不能仅 UI 宣称）。
- 可选备注。预设 Google Public DNS、Cloudflare、阿里 DNS、腾讯 DNS；用户可添加自定义 DNS 服务。
- Provider 不等于新节点，不存代理密码；停用/删除被引用 Provider 时必须提示，不能悄悄丢失策略。
- DNS 地址的 `#代理组` 后缀由生成层统一编译，管理界面不应强迫用户手写 URL 后缀。

### DNS Policy（机场管理员）

- `id`、`name`、`enabled`、`priority`、`match`（规则集引用或指定域名/后缀）、`providerId`。
- 规则匹配优先引用现有 Mihomo domain rule provider，例如 `google`、`youtube`、`google-gemini`、`cn-domain`；若需要附加域名，使用显式 domain/domain-suffix，而不是复制一整套公共列表。
- 不能用 IP-CIDR/GEOIP/进程规则当普通域名 DNS 匹配条件。
- 匹配重叠时清楚说明优先级、验证生成结果；不引入无法落地的“智能自动检测”。
- **默认策略**：Google 生态（Google / YouTube / Gemini 可用现有规则集组合）→ Google DNS；国内 → 阿里 DNS；其它未命中 → Cloudflare DNS。
- **Cloudflare 兜底的精确定义**：本期指“未命中专用规则的全局默认”。同一 `nameserver-policy` 内列两台服务器并不代表严格 Google 失败才回退 CF；不得在 UI 和文档宣称已支持主备故障顺序。
- 代理节点域名解析仍用独立 `proxy-server-nameserver`，保留 bootstrap 防循环策略，不让 Google/CF DNS 依赖自己尚未解析出的代理节点。

### 未来 Smart DNS 兼容预留

仅使用上述通用 Provider + Policy 机制：未来添加 AKDNS 地址、为 Netflix / Disney+ 域名指定该 Provider，同时选择正确 DNS 请求出口。**本期不预置或启用 AKDNS**；后续应在服务商后台完成源公网 IP 登记/鉴权及地区策略。流媒体专用 DNS 失败后回退 CF 只能恢复普通解析，不意味着仍可解锁。

## 四、任务拆分与验收

### EF-DNS-01｜审查现有生成路径，确定数据加载点（P0）

- 从 `/sub/<token>/clash` → `Subscribe::getContent` → `Clash::getContent` → `MihomoProfile::build` 跟踪真实数据流。
- 确认管理员修改 DNS 设置后订阅请求何时读取新数据；选择当前代码最简单的持久化方式（建议基于既有 config 表 JSON 配置记录或已有同等能力），不另建复杂控制面。
- 对 `config/profiles/mihomo.php` 保留可回退的默认基线，运行时仅覆盖明确可编辑 DNS 部分，不让数据库配置覆盖 nodes、services、rules。
- 明确未配置、非法配置、数据库无新记录时的回退行为；旧订阅链接继续存在。
- **验收**：落地数据流说明；无 DNS 自定义配置时旧配置继续可生成；不更改非 DNS 部分。

### EF-DNS-02｜Provider 存储与配置校验（P0）

- 在机场后台实现 Provider 的获取、创建、更新、停用、删除；预置 Google、Cloudflare、阿里、腾讯；自定义地址合法性校验；允许同 Provider 多个服务器地址但**不承诺有序故障回退**。
- 地址、协议、引用和出口合法性校验在服务器端执行；未知代理组、空列表、循环/非法字段不得写进有效配置。
- 修改保存必须落地并被后续订阅读取，勿只做前端状态。
- **验收**：新增自定义 DoH 可保存、刷新后仍在、可由 DNS Policy 引用；非法地址被拒绝；误删引用得到解释。

### EF-DNS-03｜Policy 规则与默认预设（P0）

- 新增 Policy 管理；复用已存在的域名 Rule Provider，支持精确域名和 DOMAIN-SUFFIX 自定义。
- 预设 Google（包含 Google/YouTube/Gemini 的必要域名规则集）→ Google DNS；CN → 阿里 DNS；全局默认 → Cloudflare DNS；private → system。
- 优先级：本地/private 优先；管理员明确配置的细分策略优先于宽泛规则；无匹配走默认 Provider。
- 复用 `MihomoProfile` 实际生成的 rule-provider key；引用不存在的 rule-set 时拒绝或有明确错误，不生成无法工作的 YAML。
- **验收**：Google、YouTube、Gmail、Gemini、国内域名、随机国际域名分别命中预期 DNS；定制域名覆盖可验证；路由策略组原有顺序不变。

### EF-DNS-04｜Mihomo DNS 编译及接入订阅（P0）

- 从存储的 Provider/Policy 编译 Mihomo DNS 结构，至少 `nameserver`、`nameserver-policy`、`proxy-server-nameserver`；保留 `default-nameserver`、`direct-nameserver`、`direct-nameserver-follow-policy`、Fake-IP 和 IPv6 等既有参数，必要调整以真实内核验证为准。
- DNS 请求出口与普通业务流量出口分开；动态校验导出的 `proxy-groups` 中确实有被引用的组；节点为空时不能暗自 DIRECT、自动换节点或绕过原本 REJECT 原则。
- 检查客户端端的 DNS 入口/TUN 依赖，不把机场订阅“开箱即用”误写成“自动夺取所有系统 DNS”；系统 DNS 未被接管的应用属于客户端运行条件。
- **验收**：实际 `/sub/<token>/clash` 输出包含后台设置的 DNS；不影响节点、凭据、用户隔离、原 Rule Provider、服务策略组；`mihomo -t` 成功。

### EF-DNS-05｜管理员 DNS 页面（P1）

- 在机场管理员 **订阅设置附近**增加“DNS 管理”入口（页面或现有设置子页，按 UI 最少改动选）；按现有 Tabler/Malio 组件与布局做一致交互。
- 四块内容：全局设置、Provider 列表、Policy 列表、只读生成预览；高级项折叠显示。不要复制 Clash Verge/第三方客户端整套 UI。
- 交互重点：选择已存在域名规则集、选择 Provider、选择 DNS 请求出口、保存后预览生效结果；来源于服务端的设置应为真实值。
- 预览要区分“生成静态配置”与“真实客户端 DNS 查询”，不能把静态预览伪装成网络测速成功。
- **验收**：管理员不编辑 YAML 就能完成 Google/国内/Cloudflare 分流配置，并通过订阅查看变化；两种后台主题的导航和功能均可用。

### EF-DNS-06｜回归与真实内核验证（P0）

- 扩展 `tests/Unit/Services/MihomoProfileTest.php`：默认配置、Google/YouTube/Gemini/CN/国际默认命中、Rule Provider 引用存在、非法 Provider、出口组引用、重复/冲突域名、空节点、已有代理组、节点凭据和用户隔离。
- PHP 语法及现有测试通过；两种真实 HTTP 订阅：有节点、无节点均校验。
- 利用本地现有 Mihomo 内核执行配置检查；有条件时通过受控 DNS/代理出口做真实网络查询验证，不可仅凭 YAML 字符串判断 Google DNS 的真实出口。
- 更新 `docs/MIHOMO-PROFILE.md`，写清默认与异常行为、如何配置、测试命令、限制。
- **验收**：新老测试通过，实际订阅能由 Mihomo 加载，运营人员可从后台维护 DNS，无需用户本地编辑配置。

### SLINX-LEGACY-01｜仅 SLINX-EF 清理传统 Clash 导出（独立任务）

在 `G:\Project\SLINX-EF` 实施，**不要在 EverFlame 里执行同名删除**。

- 删除 `/clash-legacy` 服务端路由、API handler、`ClashLegacy`/`legacyUnsupportedReason`、`RenderClashLegacy`、旧模板常量与生成/检测逻辑，保留 `/clash` 现代导出。
- 调整 `sub.Url` 列表及 `web/src/view/sub/widget/Info.vue` 的标签/说明，**不能仅删除数组元素导致标签与 URL 错位**。清理传统专属测试与文档，保留或改写仍覆盖现代 Shadowsocks 等协议的测试。
- 检查 `internal/sub/template/clash_test.go`、`internal/sub/shadowsocks_test.go` 中对 legacy 的断言，不能因为清理测试误伤现代协议验证。
- **验收**：`/clash` 现代导出正常；`/clash-legacy` 不再对外提供；通用订阅、Surge、Sing-box 未回归；Go 测试与前端构建通过。旧 `/clash-legacy` 链接会失效，属于有意识移除，但不影响机场 `/sub/<token>/clash`。

## 五、执行顺序及防偏离

1. 机场先完成 `EF-DNS-01`，然后 `02→03→04→05→06`，每项结束做最小验收。
2. `SLINX-LEGACY-01` 是**独立仓库任务**，可在机场 DNS 完成后执行；同一个 Codex 会话需明确切换工作区并阅读该仓库指令，不得把“旧 Clash 兼容”扩展到机场/客户端。
3. 优先复用 `config/profiles/mihomo.php` 和 `MihomoProfile::build`，不新造配置合并引擎、代理组框架或规则集下载器。
4. 只完成本轮目标；不加入自动测速、故障转移、自研 Smart DNS、ACL、审批/授权系统。
5. 不修改现有生产密钥、用户订阅 token、节点密码、数据库消费/计量账本；不直接修改生产运行实例；不自动提交或强制覆盖未提交改动。
6. 任何测试因外部网络、DNS 或核心缺失无法进行时，记录准确原因和可执行复验命令，不能宣称完成。

## 六、给 Codex 的执行指令

请在本地 `G:\Project\EverFlame` 打开 `docs/MIHOMO-DNS-TASKS.md`，严格按本文件实施机场 `EF-DNS-01` 到 `EF-DNS-06`。先复查实际代码和所有未提交改动，复用现有 Mihomo Profile 构建器、规则集、后台样式和订阅 URL，完成 DNS Provider/Policy 管理、Google/国内/CF 默认分流、DNS 请求出口绑定和最终订阅输出，做真实 Mihomo 校验。不要移除机场的历史 Clash 兼容及其他客户端订阅功能；不要修改 SLINX-EF、客户端或生产服务。

完成机场后，再**单独**打开 `G:\Project\SLINX-EF`，实施 `SLINX-LEGACY-01`：只删除该节点面板的 `/clash-legacy` 专属兼容，保留现代 `/clash` 与其他现有导出格式。两仓库分别记录修改文件、测试结果、未完成项，保留所有既有未提交变更，不自动部署、重置或提交。