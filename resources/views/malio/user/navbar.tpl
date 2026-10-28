<div class="navbar-bg"></div>
<nav class="navbar navbar-expand-lg main-navbar">
<button type="button" class="btn text-white" id="malio-sidebar-toggle" aria-label="切换导航" aria-expanded="false">☰</button>
<div class="ms-auto text-white">{$user->user_name|escape} · <a class="text-white" href="/user/logout">登出</a></div>
</nav>
<div class="main-sidebar sidebar-style-2">
<aside id="sidebar-wrapper">
<div class="sidebar-brand"><a href="/user">{$config['appName']|escape}</a></div>
<ul class="sidebar-menu">
<li><a class="nav-link" href="/user"><span>用户中心</span></a></li>
<li><a class="nav-link" href="/user/product"><span>套餐展示</span></a></li>
<li class="menu-header">我的</li>
<li><a class="nav-link" href="/user/profile"><span>我的账户</span></a></li>
<li><a class="nav-link" href="/user/edit"><span>连接设置</span></a></li>
<li class="menu-header">使用</li>
<li><a class="nav-link" href="/user/server"><span>节点列表</span></a></li>
<li><a class="nav-link" href="/user/docs"><span>使用文档</span></a></li>
<li><a class="nav-link" href="/user/ticket"><span>工单支持</span></a></li>
{if $user->is_admin}<li><a class="nav-link" href="/admin"><span>站点管理</span></a></li>{/if}
</ul>
</aside>
</div>
