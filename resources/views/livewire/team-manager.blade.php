<div class="mx-auto max-w-4xl">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-brand-950 dark:text-sand-50">Team</h1>
            <p class="mt-1 text-sm text-brand-600 dark:text-brand-300">People with access to {{ $tenant->name }}.</p>
        </div>
    </div>

    <div class="hoso-card mt-6 overflow-hidden">
        <table class="hoso-table">
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
                        <td class="font-semibold">{{ $member->user->name }}</td>
                        <td class="text-brand-500 dark:text-brand-400">{{ $member->user->email }}</td>
                        <td>
                            <select wire:change="changeRole({{ $member->user_id }}, $event.target.value)"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg border-sand-300 bg-white text-sm dark:border-brand-700 dark:bg-brand-950 dark:text-sand-50"
                                    aria-label="Role for {{ $member->user->name }}">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->slug }}" @selected($member->role?->slug === $role->slug)>{{ $role->display_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="text-right">
                            <button wire:click="removeMember({{ $member->user_id }})"
                                    wire:confirm="Remove {{ $member->user->name }} from this business?"
                                    class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400">
                                Remove
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-sm text-brand-500 dark:text-brand-400">No members yet — invite your first teammate below.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="hoso-card mt-6 p-6">
        <h2 class="font-display text-lg font-semibold text-brand-900 dark:text-sand-50">Add a team member</h2>
        <form wire:submit="addMember" class="mt-4 grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <label class="hoso-label" for="member-name">Name</label>
                <input id="member-name" type="text" wire:model="name" class="hoso-input" placeholder="Juan Dela Cruz" />
                @error('name') <span class="hoso-error">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <label class="hoso-label" for="member-email">Email</label>
                <input id="member-email" type="email" wire:model="email" class="hoso-input" placeholder="juan@example.com" />
                @error('email') <span class="hoso-error">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <label class="hoso-label" for="member-role">Role</label>
                <select id="member-role" wire:model="roleSlug" class="hoso-input">
                    @foreach ($roles as $role)
                        <option value="{{ $role->slug }}">{{ $role->display_name }}</option>
                    @endforeach
                </select>
                @error('roleSlug') <span class="hoso-error">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-end sm:col-span-1">
                <button type="submit" class="hoso-btn-primary w-full">Add member</button>
            </div>
        </form>
        <p class="mt-3 text-xs text-brand-500 dark:text-brand-400">
            New members get a platform account with a generated password — they can set a new one through “Forgot password”.
        </p>
    </div>
</div>
