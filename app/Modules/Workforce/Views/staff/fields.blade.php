{{-- Shared employee assignment fields. $employee (nullable), option lists from the controller. --}}
<div class="grid grid-cols-2 gap-2">
    <select name="department_id" class="form-input" aria-label="Department">
        <option value="">No department</option>
        @foreach ($allDepartments as $d)
            <option value="{{ $d->id }}" @selected(old('department_id', $employee?->department_id) == $d->id)>{{ $d->name }}</option>
        @endforeach
    </select>
    <select name="position_id" class="form-input" aria-label="Position">
        <option value="">No position</option>
        @foreach ($allPositions as $p)
            <option value="{{ $p->id }}" @selected(old('position_id', $employee?->position_id) == $p->id)>{{ $p->name }}</option>
        @endforeach
    </select>
    <select name="employment_type" class="form-input" aria-label="Employment type">
        @foreach (\App\Modules\Workforce\Models\Employee::TYPES as $type)
            <option value="{{ $type }}" @selected(old('employment_type', $employee?->employment_type ?? 'full_time') === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
        @endforeach
    </select>
    <input name="hire_date" type="date" value="{{ old('hire_date', $employee?->hire_date?->toDateString()) }}" class="form-input" aria-label="Hire date" />
    <select name="property_id" class="form-input" aria-label="Home property">
        <option value="">Any property</option>
        @foreach ($properties as $property)
            <option value="{{ $property->id }}" @selected(old('property_id', $employee?->property_id) == $property->id)>{{ $property->name }}</option>
        @endforeach
    </select>
    <select name="user_id" class="form-input" aria-label="Linked account">
        <option value="">No login account</option>
        @foreach ($members as $member)
            <option value="{{ $member->id }}" @selected(old('user_id', $employee?->user_id) == $member->id)>{{ $member->name }}</option>
        @endforeach
    </select>
</div>
