{* 平台布局及安装、导入和连接流程参考 theme-sources/malio/templates/user/tutorial*. *}
{if $tutorial_os == ''}
<div class="row g-4 mb-4 ef-tutorial-grid">
    <div class="col-md-6"><a href="/user/tutorial?os=windows" class="ef-os-card ef-os-windows"><i class="ti ti-brand-windows" aria-hidden="true"></i><div><h3>Windows</h3><p>Windows 客户端安装与订阅导入</p></div><span aria-hidden="true">→</span></a></div>
    <div class="col-md-6"><a href="/user/tutorial?os=android" class="ef-os-card ef-os-android"><i class="ti ti-brand-android" aria-hidden="true"></i><div><h3>Android</h3><p>Android 手机与平板使用教程</p></div><span aria-hidden="true">→</span></a></div>
    <div class="col-md-6"><a href="/user/tutorial?os=ios" class="ef-os-card ef-os-ios"><i class="ti ti-brand-apple" aria-hidden="true"></i><div><h3>Apple</h3><p>iPhone / iPad 客户端与连接设置</p></div><span aria-hidden="true">→</span></a></div>
    <div class="col-md-6"><a href="/user/tutorial?os=mac" class="ef-os-card ef-os-mac"><i class="ti ti-device-desktop" aria-hidden="true"></i><div><h3>Mac</h3><p>macOS 客户端安装与系统代理设置</p></div><span aria-hidden="true">→</span></a></div>
    <div class="col-md-6"><a href="/user/tutorial?os=linux" class="ef-os-card ef-os-linux"><i class="ti ti-terminal-2" aria-hidden="true"></i><div><h3>Linux</h3><p>Linux 客户端与订阅配置</p></div><span aria-hidden="true">→</span></a></div>
    <div class="col-md-6"><a href="/user/tutorial?os=faq" class="ef-os-card ef-os-faq"><i class="ti ti-help-circle" aria-hidden="true"></i><div><h3>FAQ</h3><p>常见问题解答</p></div><span aria-hidden="true">→</span></a></div>
</div>
{else}
<div class="mb-4"><a href="/user/tutorial" class="btn btn-outline-primary">← 返回平台选择</a></div>
<div class="card mb-4"><div class="card-body ef-tutorial-content">
{if $tutorial_os == 'faq'}
<h3 class="card-title">常见问题解答</h3>
<details open><summary>订阅导入失败怎么办？</summary><p>先确认客户端支持所选订阅格式。回到用户中心，复制该客户端对应的订阅链接，在客户端的订阅或配置页面手动添加，再执行更新。不要把通用订阅地址当作单个节点导入。</p></details>
<details><summary>连接后仍然无法访问？</summary><p>确认账户套餐有效、流量充足，再更新订阅、切换节点并检查客户端是否已启用系统代理或 VPN。手机需要允许客户端创建 VPN 连接。</p></details>
<details><summary>没有可用节点？</summary><p>到节点列表检查当前账户可见的线路，并确认套餐有效期。如果更新订阅后仍然为空，请通过工单联系管理员。</p></details>
<details><summary>更换设备后如何使用？</summary><p>在新设备登录用户中心，为对应平台安装客户端并重新导入订阅。使用时请遵守套餐的设备数量限制。</p></details>
<details><summary>提交工单时提供什么信息？</summary><p>请附上操作系统、客户端名称及版本、出错时间、节点名称和错误提示。截图时遮盖订阅链接、密码等账户信息。</p></details>
<a href="/user/ticket" class="btn btn-primary mt-3">联系工单支持</a>
{else}
<h3 class="card-title">{if $tutorial_os == 'windows'}Windows{elseif $tutorial_os == 'android'}Android{elseif $tutorial_os == 'ios'}Apple · iOS / iPadOS{elseif $tutorial_os == 'mac'}Mac · macOS{else}Linux{/if} 使用教程</h3>
<ol class="ef-tutorial-steps">
<li><h4>安装客户端</h4>
<p>前往用户中心的客户端区域，展开“全部平台”，选择当前设备对应的客户端。使用其中的下载或商店入口完成安装。</p>
{if $tutorial_os == 'windows'}<p>按照安装向导完成安装，然后启动客户端。</p>
{elseif $tutorial_os == 'android'}<p>下载完成后打开安装包。如果系统询问安装权限，只允许本次下载所使用的应用安装该客户端。</p>
{elseif $tutorial_os == 'ios'}<p>通过客户端对应的 App Store 入口安装，下载前请查看商店中的系统要求及价格。</p>
{elseif $tutorial_os == 'mac'}<p>选择适合 Mac 芯片架构的安装包，打开后按提示安装并启动。</p>
{else}<p>选择适合发行版和处理器架构的安装包，安装后启动图形客户端。</p>{/if}
<a class="btn btn-primary" href="/user#recommended-clients">打开客户端下载与订阅</a></li>
<li><h4>导入订阅配置</h4><p>在用户中心找到已安装的客户端，点击其导入按钮，允许浏览器打开客户端并确认添加配置。更新订阅后，客户端会显示可用节点。</p><p>如果无法一键导入，请复制该客户端对应的订阅链接，在客户端的“订阅”或“配置”页面新建远程订阅，粘贴链接并保存、更新。</p></li>
<li><h4>选择节点并开启连接</h4><p>选中刚添加的配置，选择可用节点。</p>
{if $tutorial_os == 'android' || $tutorial_os == 'ios'}<p>点击连接开关，首次使用时允许系统创建 VPN 连接。连接成功后再打开网页验证。</p>
{else}<p>开启客户端的“系统代理”开关，并使用规则模式。保持客户端运行，再打开网页验证连接。</p>{/if}
</li>
<li><h4>更新与排查</h4><p>线路有变化时，在客户端中更新订阅。如果连接异常，先尝试切换节点，检查套餐及流量，再查看常见问题。</p><a href="/user/tutorial?os=faq">查看常见问题 →</a></li>
</ol>
{/if}
</div></div>
{/if}
