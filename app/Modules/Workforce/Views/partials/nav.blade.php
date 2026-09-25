<nav class="flex gap-2 mt-2 mb-4" aria-label="Staff">
    @foreach ([['staff.index', 'Employees'], ['staff.schedule', 'Schedule'], ['staff.attendance', 'Attendance'], ['staff.leave', 'Leave'], ['my-work.index', 'My work']] as [$name, $label])
        <a href="{{ route($name) }}" class="btn btn-sm {{ request()->routeIs($name) ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
    @endforeach
</nav>
