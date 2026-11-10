{include file="user/header.tpl"}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none">
            <h1 class="page-title"><span class="home-title">节点列表</span></h1>
            <div class="page-pretitle my-3"><span class="home-subtitle">查看节点在线情况、流量倍率与限速</span></div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl ef-node-list">
            {foreach $server_groups as $node_class => $group}
                <section class="ef-node-group" aria-labelledby="node-group-{$node_class}">
                    <h2 class="ef-node-group-title" id="node-group-{$node_class}">{if $node_class === 0}免费节点{else}VIP {$node_class} 节点{/if}</h2>
                    <div class="ef-node-grid">
                        {foreach $group as $server}
                            <article class="card ef-node-card">
                                <div class="card-body">
                                    {if $server['region_flag'] !== ''}
                                        <img class="ef-node-flag" src="/theme/malio/assets/modules/flag-icon-css/flags/1x1/{$server['region_flag']}.svg" alt="" width="54" height="54">
                                    {else}
                                        <span class="ef-node-flag ef-node-flag-fallback"><i class="ti ti-world" aria-hidden="true"></i></span>
                                    {/if}
                                    <div class="ef-node-info">
                                        <h3 class="ef-node-name"><span class="ef-node-status ef-node-status-{$server['color']|escape}" role="img" aria-label="{if $server['online'] === 1}在线{elseif $server['online'] === 0}待上报{else}离线{/if}" title="{$server['health_reason']|escape}"></span><span>{$server['name']|escape}</span></h3>
                                        <p class="ef-node-description">{$server['sort']|escape}{if $server['connection_type'] !== 0} · IPv6{/if}{if $user->class < $server['class']} · <a href="/user/product">升级套餐解锁</a>{/if}</p>
                                    </div>
                                    <dl class="ef-node-metrics">
                                        <div><dt>倍率{if $server['is_dynamic_rate']}（动态）{/if}</dt><dd>×{$server['traffic_rate']|string_format:'%.2f'}</dd></div>
                                        <div><dt>限速</dt><dd>{if $server['speed_limit'] > 0}{$server['speed_limit']}<small> Mbps</small>{else}不限速{/if}</dd></div>
                                    </dl>
                                </div>
                            </article>
                        {/foreach}
                    </div>
                </section>
            {foreachelse}
                <div class="card"><div class="card-body text-center text-secondary py-5">暂无可用节点，请稍后再来查看。</div></div>
            {/foreach}
        </div>
    </div>

    {include file="user/footer.tpl"}
