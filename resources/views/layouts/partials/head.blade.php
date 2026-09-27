<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('img/favicon.svg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/bnb-app.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/bnb-components.css') }}?v=1">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>[x-cloak]{display:none!important}</style>
{{-- Optimistic preloading: the browser fetches a same-origin page while the pointer rests on its link (Chrome/Edge; others ignore this).
     Never links whose GET changes state: sign-out, sign-in links, unsubscribe, verification, payment returns, CSV exports. --}}
<script type="speculationrules">
{"prefetch": [{"where": {"and": [
    {"href_matches": "/*"},
    {"not": {"href_matches": "/*\\?*"}},
    {"not": {"href_matches": "*/logout*"}},
    {"not": {"href_matches": "*/login-link/*"}},
    {"not": {"href_matches": "*/unsubscribe/*"}},
    {"not": {"href_matches": "*/verify-email*"}},
    {"not": {"href_matches": "*/continue*"}},
    {"not": {"href_matches": "*/payment-return*"}},
    {"not": {"href_matches": "*/return"}},
    {"not": {"href_matches": "*.csv*"}},
    {"not": {"selector_matches": "[rel~=nofollow], [download], [data-no-prefetch]"}}
]}, "eagerness": "moderate"}]}
</script>
