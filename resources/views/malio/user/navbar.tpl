<div class="navbar-bg"></div>
<nav class="navbar navbar-expand-lg main-navbar">
<button type="button" class="btn text-white" id="malio-sidebar-toggle" aria-label="切换导航" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
<details class="ef-account-menu ms-auto">
<summary><span class="ef-account-avatar"><i class="ti ti-user" aria-hidden="true"></i></span><span class="ef-account-name">Hi, {$user->user_name|escape}</span><i class="ti ti-chevron-down" aria-hidden="true"></i></summary>
<div class="ef-account-dropdown">
<a href="/user/profile"><i class="ti ti-user" aria-hidden="true"></i> 我的账户</a>
<a href="/user/code"><i class="ti ti-wallet" aria-hidden="true"></i> 我的钱包</a>
<a href="/user/edit"><i class="ti ti-settings" aria-hidden="true"></i> 账户设置</a>
<a href="/user/logout" class="text-danger"><i class="ti ti-logout" aria-hidden="true"></i> 登出</a>
</div>
</details>
</nav>
<div class="main-sidebar sidebar-style-2">
<aside id="sidebar-wrapper">
<div class="sidebar-brand"><a href="/user">{$config['appName']|escape}</a></div>
<div class="sidebar-brand sidebar-brand-sm"><a href="/user" aria-label="{$config['appName']|escape}">火</a></div>
<ul class="sidebar-menu">
<li><a class="nav-link" href="/user"><i class="fab fa-fort-awesome" aria-hidden="true"></i><span>首页</span></a></li>
<li><a class="nav-link" href="/user/product"><i class="fas fa-store" aria-hidden="true"></i><span>套餐展示</span></a></li>
<li class="menu-header">我的</li>
<li><a class="nav-link" href="/user/profile"><i class="fas fa-user" aria-hidden="true"></i><span>我的账户</span></a></li>
<li><a class="nav-link" href="/user/code"><i class="fas fa-wallet" aria-hidden="true"></i><span>我的钱包</span></a></li>
<li><a class="nav-link" href="/user/edit"><i class="fas fa-cog" aria-hidden="true"></i><span>账户设置</span></a></li>
<li class="menu-header">使用</li>
<li><a class="nav-link" href="/user/server"><i class="fas fa-server" aria-hidden="true"></i><span>节点列表</span></a></li>
<li><a class="nav-link" href="/user/tutorial"><i class="fas fa-book" aria-hidden="true"></i><span>下载和教程</span></a></li>
<li><a class="nav-link" href="/user/ticket"><i class="fas fa-headset" aria-hidden="true"></i><span>工单支持</span></a></li>
{if $user->is_admin}<li><a class="nav-link" href="/admin"><i class="fas fa-cogs" aria-hidden="true"></i><span>站点管理</span></a></li>{/if}
</ul>
</aside>
</div>
