@if (session('success'))
    <div class="mb-4 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800 dark:border-brand-700 dark:bg-brand-900 dark:text-brand-100" role="status">
        {{ session('success') }}
    </div>
@endif

@if (session('warning'))
    <div class="mb-4 rounded-lg border border-brass-300 bg-brass-50 px-4 py-3 text-sm font-medium text-brass-800 dark:border-brass-700 dark:bg-brass-900/40 dark:text-brass-200" role="status">
        {{ session('warning') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-200" role="alert">
        {{ session('error') }}
    </div>
@endif
