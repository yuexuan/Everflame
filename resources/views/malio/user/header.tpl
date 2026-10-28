<!doctype html>
<html lang="zh">

<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="referrer" content="never">
    <title>{$config['appName']}</title>
    <!-- CSS files -->
    <link href="//{$config['jsdelivr_url']}/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="//{$config['jsdelivr_url']}/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet"/>
    <!-- JS files -->
    <script src="/assets/js/fuck.min.js"></script>
    <script src="//{$config['jsdelivr_url']}/npm/qrcode_js@latest/qrcode.min.js"></script>
    <script src="//{$config['jsdelivr_url']}/npm/clipboard@latest/dist/clipboard.min.js"></script>
    <script src="//{$config['jsdelivr_url']}/npm/htmx.org@latest/dist/htmx.min.js"></script>
    <style>
        .home-subtitle {
            font-size: 14px;
        }

        .home-title {
            font-size: 36px;
        }
    </style>
<link rel="stylesheet" href="/theme/malio/css/style-purple.css">
<link rel="stylesheet" href="/theme/malio/assets/css/components.css">
<link rel="stylesheet" href="/theme/malio/css/malio.css">
<link rel="stylesheet" href="/theme/malio/css/everflame.css">
</head>

{if $user->is_dark_mode}
<body data-bs-theme="dark">
{else}
<body>
{/if}
<div class="page everflame-user">
{include file='user/navbar.tpl'}
