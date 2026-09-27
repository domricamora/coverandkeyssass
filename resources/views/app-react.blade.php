<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="currency-symbol" content="{{ \App\Support\Currency::symbol() }}">
        <meta name="robots" content="noindex">
        <link rel="icon" type="image/svg+xml" href="{{ asset('img/favicon.svg') }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        <title>{{ config('app.name') }}</title>
        @viteReactRefresh
        @vite(['resources/css/react.css', 'resources/js/react/app.jsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
