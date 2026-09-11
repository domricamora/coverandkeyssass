<x-app-layout>
    <div class="dash-row-head">
        <h1>Profile</h1>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card p-6">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <div class="card p-6 mt-6">
        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>