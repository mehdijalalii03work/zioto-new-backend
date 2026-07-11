<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-staff';

    protected $description = 'Create a staff user with a role for admin panel access';

    public function handle(): int
    {
        $name = $this->ask('نام کاربر');
        $email = $this->ask('ایمیل کاربر');

        if (User::where('email', $email)->exists()) {
            $this->error("کاربری با ایمیل {$email} از قبل وجود دارد.");

            return static::FAILURE;
        }

        $password = $this->secret('رمز عبور');

        if (strlen($password) < 8) {
            $this->error('رمز عبور باید حداقل ۸ کاراکتر باشد.');

            return static::FAILURE;
        }

        $roles = Role::pluck('name', 'id')->toArray();

        if ($roles === []) {
            $this->error('هیچ نقشی تعریف نشده است. ابتدا RolePermissionSeeder را اجرا کنید.');

            return static::FAILURE;
        }

        $this->newLine();
        $this->info('نقش‌های موجود:');

        $index = 1;
        $roleMap = [];
        foreach ($roles as $id => $roleName) {
            $this->line("  <info>{$index}</info>. {$roleName}");
            $roleMap[$index] = $roleName;
            $index++;
        }

        $this->newLine();
        $roleChoice = $this->choice('نقش مورد نظر را انتخاب کنید', array_values($roleMap));

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $user->assignRole($roleChoice);

        $this->newLine();
        $this->info('کاربر با موفقیت ساخته شد:');
        $this->table(
            ['فیلد', 'مقدار'],
            [
                ['نام', $name],
                ['ایمیل', $email],
                ['نقش', $roleChoice],
            ]
        );

        return static::SUCCESS;
    }
}
