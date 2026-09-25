<div class="dash-row-head">
    <div>
        <span class="badge badge-amber">Super Admin</span>
        <h1 class="mt-2">{{ $title }}</h1>
        @isset($sub)<p class="mt-1 text-sm" style="color:var(--text-3)">{{ $sub }}</p>@endisset
    </div>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">Platform overview</a>
</div>
@if (session('success'))<div class="card mt-4 p-4" role="status">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="card mt-4 p-4" role="alert" style="color:var(--danger, #b91c1c)">{{ $errors->first() }}</div>@endif
