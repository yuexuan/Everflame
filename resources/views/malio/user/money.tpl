{include file='user/header.tpl'}
<div class="page-wrapper">
    <div class="container-xl"><div class="page-header"><h2 class="page-title"><span class="home-title">我的钱包</span></h2></div></div>
    <div class="page-body"><div class="container-xl">
        <div class="card ef-wallet-balance mb-4"><div class="card-body">
            <div class="ef-wallet-watermark" aria-hidden="true">¥</div>
            <h3 class="ef-wallet-amount">¥ {$user->money|string_format:'%.2f'}</h3>
            <p>钱包余额</p>
            <a href="/user/ticket" class="btn btn-outline-light mt-2">联系管理员充值</a>
        </div></div>
        <div class="card mb-4"><div class="card-header"><h3 class="card-title mb-0">余额变动记录</h3></div>
            <div class="table-responsive"><table class="table card-table table-vcenter text-nowrap mb-0">
                <thead><tr><th>类型 / 备注</th><th>变动金额</th><th>变动后余额</th><th>日期</th></tr></thead>
                <tbody>{foreach $moneylogs as $moneylog}<tr>
                    <td>{$moneylog->remark|escape}</td><td>¥ {$moneylog->amount|string_format:'%.2f'}</td><td>¥ {$moneylog->after|string_format:'%.2f'}</td><td>{$moneylog->create_time|escape}</td>
                </tr>{foreachelse}<tr><td colspan="4" class="text-secondary py-4">暂无余额变动记录</td></tr>{/foreach}</tbody>
            </table></div>
        </div>
        <div class="card"><div class="card-body d-flex flex-wrap align-items-center gap-3"><div class="me-auto"><h3 class="card-title mb-2">购买记录</h3><p class="text-secondary mb-0">在订单中查看购买详情与状态。</p></div><a href="/user/order" class="btn btn-outline-primary">查看订单</a></div></div>
    </div></div>
{include file='user/footer.tpl'}
