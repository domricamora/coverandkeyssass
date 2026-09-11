<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'superadmin:create
        {name : Full name}
        {email : Email address}
        {--password= : Password (prompted securely when omitted)}';

    protected $description = 'Create or promote a user to platform Super Admin';

    public function handle(AuditLogger $audit): int
    {
        $name = (string) $this->argument('name');
        $email = \strtolower((string) $this->argument('email'));

        $password = $this->option('password');
        if ($password === null) {
            if ($this->input->isInteractive() === false) {
                $this->error('Non-interactive runs must pass --password=');

                return self::FAILURE;
            }
            $password = $this->secret('Password (min 12 characters)');
        }

        $validated = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'min:12'],
            ],
        );

        if ($validated->fails()) {
            foreach ($validated->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->update(['status' => 'active', 'name' => $name]);
            $this->info("Existing user [{$email}] promoted to Super Admin.");
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $this->info("Super Admin [{$email}] created.");
        }

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => null]]);

        $audit->log('platform.superadmin.created', $user, null, ['email' => $email], null, $user->id);

        $this->warn('Store the password securely — it is not displayed again.');

        return self::SUCCESS;
    }
}
