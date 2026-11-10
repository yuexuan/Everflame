/* Malio 与 Tabler 管理主题共用；使用 DOM 文本 API 避免执行管理员输入内容。 */
(async () => {
    const el = id => document.getElementById(id);
    let state, groups, rulesets;
    const status = message => { el('dns-status').textContent = message; };
    const input = (label, value, change, type = 'text') => {
        const wrap = document.createElement('label'); wrap.className = 'd-block mb-2';
        wrap.textContent = label;
        const field = document.createElement('input'); field.className = 'form-control'; field.type = type;
        if (type === 'checkbox') { field.checked = value; field.className = 'ms-2'; } else field.value = value;
        field.onchange = () => change(type === 'checkbox' ? field.checked : type === 'number' ? Number(field.value) : field.value);
        wrap.append(field); return wrap;
    };
    const select = (label, value, options, change) => {
        const wrap = document.createElement('label'); wrap.className = 'd-block mb-2'; wrap.textContent = label;
        const field = document.createElement('select'); field.className = 'form-select';
        for (const [id, name] of options) { const option = new Option(name, id); field.add(option); }
        field.value = value; field.onchange = () => change(field.value); wrap.append(field); return wrap;
    };
    const button = (label, action) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-outline-danger mb-2'; b.textContent = label; b.onclick = action; return b; };
    const providerOptions = () => state.providers.map(p => [p.id, p.name + (p.enabled ? '' : '（停用）')]);
    function render() {
        el('dns-enable').checked = state.enable; el('dns-ipv6').checked = state.ipv6; el('dns-mode').value = state['enhanced-mode'];
        el('dns-default').replaceChildren(...providerOptions().map(([id, name]) => new Option(name, id)));
        el('dns-default').value = state.defaultProviderId;
        el('dns-providers').replaceChildren(); el('dns-policies').replaceChildren();
        for (const p of state.providers) {
            const card = document.createElement('div'); card.className = 'border rounded p-3 mb-3 dns-editor-row';
            card.append(input('ID（稳定标识）', p.id, v => { p.id = v; render(); }), input('名称', p.name, v => { p.name = v; render(); }),
                input('启用', p.enabled, v => { p.enabled = v; render(); }, 'checkbox'),
                input('服务器地址（多个地址用空格分隔）', p.servers.join(' '), v => p.servers = v.trim().split(/\s+/)),
                select('DNS 请求出口', p.egress, groups.map(g => [g, g]), v => p.egress = v),
                input('备注', p.note || '', v => p.note = v), button('删除 Provider', () => {
                    if (state.defaultProviderId === p.id || state.policies.some(r => r.providerId === p.id)) { status('该 Provider 仍被引用，请先调整默认或 Policy'); return; }
                    state.providers = state.providers.filter(x => x !== p); render();
                })); el('dns-providers').append(card);
        }
        for (const p of state.policies) {
            const card = document.createElement('div'); card.className = 'border rounded p-3 mb-3 dns-editor-row';
            card.append(input('ID', p.id, v => p.id = v), input('名称', p.name, v => p.name = v), input('启用', p.enabled, v => p.enabled = v, 'checkbox'),
                input('优先级（数字越小越优先）', p.priority, v => p.priority = v, 'number'),
                select('匹配类型', p.match.type, [['rule-set', '域名规则集'], ['domain', '精确域名'], ['domain-suffix', '域名后缀']], v => { p.match = {type:v, value:v === 'rule-set' ? rulesets[0] : ''}; render(); }),
                p.match.type === 'rule-set' ? select('域名规则集', p.match.value, rulesets.map(r => [r,r]), v => p.match.value = v) : input('域名（小写，不带通配符）', p.match.value, v => p.match.value = v),
                select('Provider', p.providerId, providerOptions(), v => p.providerId = v),
                button('删除 Policy', () => { state.policies = state.policies.filter(x => x !== p); render(); })); el('dns-policies').append(card);
        }
    }
    async function submit(preview) {
        state.enable = el('dns-enable').checked; state.ipv6 = el('dns-ipv6').checked;
        state['enhanced-mode'] = el('dns-mode').value; state.defaultProviderId = el('dns-default').value;
        const buttons = [el('dns-save'), el('dns-preview')]; buttons.forEach(b => b.disabled = true);
        try {
            const response = await fetch('/admin/setting/dns', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({settings:JSON.stringify(state), preview:preview ? '1':'0'})});
            const data = await response.json(); if (!data.ret) throw new Error(data.msg);
            el('dns-output').textContent = JSON.stringify(data.preview, null, 2); status(preview ? '检查通过（尚未保存）' : '保存成功；后续订阅请求生效');
        } catch (error) { status(error.message); } finally { buttons.forEach(b => b.disabled = false); }
    }
    try {
        const response = await fetch('/admin/setting/dns/data'); const data = await response.json(); if (!data.ret) throw new Error(data.msg);
        state = data.settings;
        el('dns-enable').onchange = () => state.enable = el('dns-enable').checked;
        el('dns-ipv6').onchange = () => state.ipv6 = el('dns-ipv6').checked;
        el('dns-mode').onchange = () => state['enhanced-mode'] = el('dns-mode').value;
        el('dns-default').onchange = () => state.defaultProviderId = el('dns-default').value;
        groups = data.groups; rulesets = data.rulesets.filter(r => r !== 'private-domain'); render();
        status(data.saved ? '已加载保存配置' : '当前使用模板基线；可保存下方默认分流预设');
        el('dns-output').textContent = JSON.stringify(data.preview, null, 2);
        el('dns-add-provider').onclick = () => { state.providers.push({id:'custom-' + Date.now(), name:'自定义 DNS', enabled:true, servers:[], egress:'DIRECT', note:''}); render(); };
        el('dns-add-policy').onclick = () => { state.policies.push({id:'policy-' + Date.now(), name:'自定义策略', enabled:true, priority:10, match:{type:'domain',value:''}, providerId:state.defaultProviderId}); render(); };
        el('dns-preview').onclick = () => submit(true); el('dns-save').onclick = () => submit(false);
    } catch (error) { status(error.message); }
})();
