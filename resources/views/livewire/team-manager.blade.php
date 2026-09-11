<div>
    <div class="dash-row-head">
        <div>
            <h1>Team</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">People with access to {{ $tenant->name }}.</p>
        </div>
    </div>

    <div class="table-wrap card mt-6">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr wire:key="member-{{ $member->user_id }}">
                        <td class="font-semibold" style="color:var(--text)">{{ $member->user->name }}</td>
                        <td style="color:var(--text-3)">{{ $member->user->email }}</td>
                        <td>
                            <select wire:change="changeRole({{ $member->user_id }}, $event.target.value)"
                                    wire:loading.attr="disabled"
                                    class="form-input form-input--sm"
                                    aria-label="Role for {{ $member->user->name }}">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->slug }}" @selected($member->role?->slug === $role->slug)>{{ $role->display_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="text-right">
                            <button wire:click="removeMember({{ $member->user_id }})"
                                    wire:confirm="Remove {{ $member->user->name }} from this business?"
                                    class="btn btn-sm btn-danger">
                                Remove
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-sm" style="color:var(--text-3)">No members yet — invite your first teammate below.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="dash-h2" style="margin-top:0">Add a team member</h2>
        <form wire:submit="addMember" class="mt-4 grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <label class="form-label" for="member-name">Name</label>
                <input id="member-name" type="text" wire:model="name" class="form-input" placeholder="Juan Dela Cruz" />
                @error('name') <span class="text-xs" style="color:var(--red)">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <label class="form-label" for="member-email">Email</label>
                <input id="member-email" type="email" wire:model="email" class="form-input" placeholder="juan@example.com" />
                @error('email') <span class="text-xs" style="color:var(--red)">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <label class="form-label" for="member-role">Role</label>
                <select id="member-role" wire:model="roleSlug" class="form-input">
                    @foreach ($roles as $role)
                        <option value="{{ $role->slug }}">{{ $role->display_name }}</option>
                    @endforeach
                </select>
                @error('roleSlug') <span class="text-xs" style="color:var(--red)">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-end sm:col-span-1">
                <button type="submit" class="btn btn-primary w-full">Add member</button>
            </div>
        </form>
        <p class="mt-3 text-xs" style="color:var(--text-3)">
            New members get a platform account with a generated password — they can set a new one through "Forgot password".
        </p>
    </div>
</div>