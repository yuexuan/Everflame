{include file="user/header.tpl"}
<link rel="stylesheet" href="/theme/malio/assets/modules/vanilla-calendar-pro/styles/index.css">
<link rel="stylesheet" href="/theme/malio/css/traffic.css?v=20261109c">

<div class="page-wrapper ef-traffic-page">
    <div class="container-xl">
        <header class="ef-traffic-heading">
            <div><h1>流量监控</h1><p>查看流量用量与历史趋势</p></div>
            <div class="ef-traffic-date"><i class="ti ti-calendar" aria-hidden="true"></i> {$traffic_date} <span>{$traffic_timezone|escape}</span></div>
        </header>
        <div class="ef-traffic-summary">
            <section class="card"><div class="card-body">
                <h2><i class="ti ti-download" aria-hidden="true"></i> 本期已用</h2>
                <p class="ef-traffic-value">{$used_traffic}</p>
                <p class="ef-traffic-meta">上传 {$upload_traffic} / 下载 {$download_traffic}</p>
            </div></section>
            <section class="card"><div class="card-body">
                <h2><i class="ti ti-stack-2" aria-hidden="true"></i> 剩余流量</h2>
                <p class="ef-traffic-value">{$remaining_traffic}</p>
                <div class="ef-traffic-allowance"><span>套餐总量 {$total_traffic}</span><strong>已用 {$usage_percent}%</strong></div>
                <progress class="ef-traffic-progress" max="100" value="{$progress_percent}" aria-label="套餐流量已用比例">{$usage_percent}%</progress>
            </div></section>
            <section class="card"><div class="card-body">
                <h2><i class="ti ti-calendar-stats" aria-hidden="true"></i> 今日用量</h2>
                <p class="ef-traffic-value">{$today_traffic}</p>
                <p class="ef-traffic-meta">按节点倍率计费后的用量</p>
            </div></section>
        </div>
        {if $traffic_error}<p class="ef-traffic-notice" role="alert">{$traffic_error|escape}</p>{/if}
        <section class="card ef-traffic-trend" aria-labelledby="traffic-trend-title">
            <div class="card-body">
                <div class="ef-traffic-section-heading">
                    <div><h2 id="traffic-trend-title">流量趋势</h2><p>{if $traffic['has_records']}期间传输 {$range_total}{else}等待节点上报流量{/if} · {$traffic_window['label']|escape} · 按{if $traffic_window['hourly']}小时{else}天{/if}统计</p></div>
                    <nav class="ef-traffic-ranges" aria-label="流量统计时间范围">
                        <a href="/user/traffic?range=24h" {if $traffic['range'] === '24h'}aria-current="page"{/if}>近24小时</a>
                        <a href="/user/traffic?range=7d" {if $traffic['range'] === '7d'}aria-current="page"{/if}>近7天</a>
                        <a href="/user/traffic?range=30d" {if $traffic['range'] === '30d'}aria-current="page"{/if}>近30天</a>
                        <button type="button" id="traffic-custom-toggle" aria-haspopup="dialog" {if !in_array($traffic['range'], ['24h', '7d', '30d'])}class="is-active"{/if}><i class="ti ti-calendar" aria-hidden="true"></i> {if in_array($traffic['range'], ['24h', '7d', '30d'])}自定义{else}{$traffic_window['label']|escape}{/if}</button>
                    </nav>
                </div>
                <p class="ef-traffic-window">{$traffic_window['description']|escape} <span>{$traffic_timezone|escape}</span></p>
                <div class="ef-traffic-chart-wrap"><canvas id="user-traffic-chart" role="img" aria-label="所选期间上传与下载合计的传输流量趋势">所选期间传输 {$range_total}；各节点合计见下方节点用量。</canvas></div>
                <p id="traffic-chart-error" class="ef-traffic-notice" hidden>图表暂时无法加载，请查看下方用量明细或刷新页面。</p>
                {if !$traffic['has_records']}<p class="ef-traffic-notice" role="status">此时间范围内暂无流量记录，节点上报后将在这里显示。</p>{/if}
                <p class="ef-traffic-footnote">折线图显示上传与下载合计的传输流量；上方账户用量按节点倍率计费。</p>
                {if !$traffic_enabled}<p class="ef-traffic-notice">普通节点流量记录当前未开启；Agent 记录及已保存的历史仍可查看。</p>{/if}
            </div>
        </section>
        <section class="card ef-traffic-details" aria-labelledby="traffic-details-title">
            <div class="card-body">
                <div class="ef-traffic-section-heading"><div><h2 id="traffic-details-title">节点用量</h2><p>与流量趋势使用相同时间范围 · {$traffic_window['label']|escape}</p></div><span class="ef-traffic-meta">合计 {$range_total}</span></div>
                <div class="table-responsive">
                    <table class="table ef-traffic-table"><thead><tr><th scope="col">节点名称</th><th scope="col">传输流量</th><th scope="col">占比</th><th scope="col"><span class="visually-hidden">用量占比</span></th></tr></thead>
                    <tbody>{foreach $traffic['nodes'] as $row}<tr><td><strong>{$row['name']|escape}</strong></td><td>{$row['formatted']}</td><td>{$row['percent']}%</td><td><progress class="ef-traffic-progress" max="100" value="{$row['percent']}" aria-label="{$row['name']|escape} 的用量占比">{$row['percent']}%</progress></td></tr>{foreachelse}<tr><td colspan="4" class="ef-traffic-empty">所选期间暂无可归属到节点的流量记录</td></tr>{/foreach}</tbody></table>
                </div>
                <p class="ef-traffic-footnote">按实际上传与下载合计，依用量降序排列；占比以所选期间节点总传输量计算。套餐扣费按节点倍率计算。</p>
                <p class="ef-traffic-footnote">仅统计已采集且可归属到节点的记录；未采集或已清理的历史无法恢复。普通节点明细至少保留 {$retention_days} 天。</p>
            </div>
        </section>
    </div>
    <dialog id="traffic-range-dialog" class="ef-range-dialog" aria-labelledby="traffic-range-title">
        <form id="traffic-range-form" action="/user/traffic" method="get">
            <header class="ef-range-heading"><div><h2 id="traffic-range-title">自定义时间范围</h2><p>同时更新流量趋势和节点用量</p></div><button type="button" data-range-close class="btn-close" aria-label="关闭"></button></header>
            <div class="ef-range-presets" aria-label="快捷时间范围"><button type="button" data-preset="today">今天</button><button type="button" data-preset="yesterday">昨天</button><button type="button" data-preset="3m">近3个月</button><button type="button" data-preset="6m">近半年</button></div>
            <input type="hidden" name="range" id="traffic-range-kind" value="custom">
            <div class="ef-range-inputs"><label>开始日期<input class="form-control" type="text" inputmode="numeric" pattern="[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]" required name="start" id="traffic-range-start" placeholder="YYYY-MM-DD" value="{$traffic_window['start_date']}"></label><span>至</span><label>结束日期<input class="form-control" type="text" inputmode="numeric" pattern="[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]" required name="end" id="traffic-range-end" placeholder="YYYY-MM-DD" value="{$traffic_window['end_date']}"></label></div>
            <div id="traffic-range-calendar" aria-label="选择开始和结束日期"></div>
            <p id="traffic-range-hint" class="ef-range-hint" role="status">单次最多6个自然月，不含未来日期。</p>
            <p class="ef-range-definition">今天：00:00 至现在；昨天：00:00 至24:00。两者按小时展示，区别于滚动的近24小时。</p>
            <footer class="ef-range-footer"><span id="traffic-range-preview"></span><div><button type="button" data-range-close class="btn">取消</button><button type="submit" class="btn btn-primary">应用范围</button></div></footer>
        </form>
    </dialog>
    <script id="user-traffic-data" type="application/json">{$traffic_json nofilter}</script>
    <script id="traffic-calendar-data" type="application/json">{$traffic_calendar_json nofilter}</script>
    <script defer src="/theme/malio/assets/modules/vanilla-calendar-pro/index.js"></script>
    <script defer src="/theme/malio/js/traffic-range.js?v=20261109b"></script>
    <script defer src="/theme/malio/assets/modules/chartjs/chart.umd.min.js"></script>
    <script defer src="/theme/malio/js/traffic.js?v=20261109b"></script>
    {include file="user/footer.tpl"}
