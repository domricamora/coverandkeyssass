@if (session('success'))
    <div class="flash flash-success" role="status">{{ session('success') }}</div>
@endif

@if (session('warning'))
    <div class="flash flash-warning" role="status">{{ session('warning') }}</div>
@endif

@if (session('error'))
    <div class="flash flash-error" role="alert">{{ session('error') }}</div>
@endif
