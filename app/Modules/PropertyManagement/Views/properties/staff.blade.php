<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Staff — {{ $property->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Assign business members to this property's daily operations.</p>
        </div>
        <a href="{{ route('properties.show', $property) }}" class="btn btn-ghost">Back to property</a>
    </div>

    <div class="card mt-6 p-6" style="max-width:560px">
        <h2 class="text-lg">Assign a member</h2>
        <form method="POST" action="{{ route('properties.staff.store', $property) }}" class="mt-3 space-y-3">
            @csrf
            <div>
                <label for="email" class="form-label">Member email (must be on the business team)</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="form-input" />
                <x-input-error :messages="$errors->get('email')" />
            </div>
            <div>
                <label for="role" class="form-label">Property role</label>
                <select id="role" name="role" class="form-input">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" />
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">Assign</button>
            </div>
        </form>
    </div>

    <div class="table-wrap card mt-6">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Property role</th>
                    <th>Assigned</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td>
                            <strong style="color:var(--text)">{{ $member->user?->name ?? '—' }}</strong>
                            <br><small style="color:var(--text-3)">{{ $member->user?->email }}</small>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('properties.staff.update', [$property, $member]) }}" class="flex gap-2 items-center">
                                @csrf
                                @method('PATCH')
                                <select name="role" class="form-input" style="max-width:180px">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected($member->role === $role)>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-dark">Save</button>
                            </form>
                        </td>
                        <td style="color:var(--text-2)">{{ $member->created_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('properties.staff.destroy', [$property, $member]) }}" onsubmit="return confirm('Remove this member from the property?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm" style="color:var(--text-3)">No staff assigned to this property yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
