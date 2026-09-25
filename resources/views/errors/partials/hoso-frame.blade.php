<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
    <title>{{ $code }} | {{ config('app.name') }}</title>
    <meta name="robots" content="noindex">
</head>
<body class="dash-body">
    <div class="auth-wrap">
        <div class="auth-card card text-center">
            <p class="pill-badge pill-badge--amber">{{ $code }}</p>
            <h1 class="mt-4">{{ $title }}</h1>
            <p class="auth-card__sub">{{ $message }}</p>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary mt-4">Back to dashboard</a>
            @else
                <a href="{{ url('/') }}" class="btn btn-primary mt-4">Back to home</a>
            @endauth
        </div>
    </div>
</body>
</html>
