<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Yayasan Tanwiriyyah</title>
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/brand/favicon.png" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="/brand/tanwiriyyah-logo.jpg">
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
