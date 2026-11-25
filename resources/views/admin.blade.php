<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-app="admin">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="site-url" content="{{ config('services.frontend.url') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('icon.png') }}" type="image/png" sizes="512x512">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>Admin · {{ config('app.name') }}</title>
    @viteReactRefresh
    @vite(['resources/js/admin/main.tsx'])
</head>
<body>
    {{-- The admin console SPA. Blade renders no domain data. --}}
    <div id="app"></div>
</body>
</html>
