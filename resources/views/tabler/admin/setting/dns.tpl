{include file='admin/header.tpl'}
<link rel="stylesheet" href="/css/mihomo-dns.css">
<div class="page-wrapper"><div class="container-xl"><div class="page-header"><h2 class="page-title">DNS 管理</h2><p class="text-muted">Mihomo 订阅 · <a href="/admin/setting/sub">返回订阅设置</a></p></div><div class="page-body" id="dns-manager">
<p id="dns-status" role="status">正在读取设置…</p>
<div class="card mb-3"><div class="card-body"><h3>全局设置</h3><p class="text-muted">首次显示默认预设，保存后启用 Google / 国内 / Cloudflare 分流。未保存时继续使用模板基线。</p><div class="row"><label class="col-md-4">DNS <input id="dns-enable" type="checkbox"></label><label class="col-md-4">默认 Provider <select id="dns-default" class="form-select"></select></label></div><details class="mt-3"><summary>高级设置</summary><label>IPv6 <input id="dns-ipv6" type="checkbox"></label><label class="ms-3">解析模式 <select id="dns-mode" class="form-select"><option>fake-ip</option><option>redir-host</option></select></label><p>Bootstrap、节点域名 DNS、直连 DNS 及 Fake-IP 参数继承模板，避免解析循环。</p></details></div></div>
<div class="card mb-3"><div class="card-body"><h3>DNS Provider</h3><p>多个地址可并发解析，不表示顺序主备。DNS 请求出口独立于业务流量出口。</p><div id="dns-providers"></div><button type="button" id="dns-add-provider" class="btn btn-secondary">添加 Provider</button></div></div>
<div class="card mb-3"><div class="card-body"><h3>DNS Policy</h3><p>private 固定使用 system。按优先级数字升序，同值按 ID 排序；连续域名条件由内核按精确域名 / 最长后缀匹配。细分覆盖请放在宽泛规则集之前。重复匹配会拒绝保存。</p><div id="dns-policies"></div><button type="button" id="dns-add-policy" class="btn btn-secondary">添加 Policy</button></div></div>
<div class="card mb-3"><div class="card-body"><h3>静态生成预览</h3><p>这是配置检查，不是客户端查询或测速。Cloudflare 用于未命中策略的默认解析。系统 DNS 接管仍需客户端配置。</p><button type="button" id="dns-preview" class="btn btn-secondary">检查并预览</button> <button type="button" id="dns-save" class="btn btn-primary">保存配置</button><pre id="dns-output" class="mt-3" style="white-space:pre-wrap"></pre></div></div>
</div></div></div>
<script src="/js/mihomo-dns.js"></script>
{include file='admin/footer.tpl'}
