<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('admin:create {email? : Email của quản trị viên}')]
#[Description('Tạo tài khoản quản trị viên Granite')]
final class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $emailInput = $this->argument('email') ?? $this->ask('Email');
        $email = mb_strtolower(trim((string) $emailInput));
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email:filter', 'max:255']]);

        if ($validator->fails()) {
            $this->error('Email không hợp lệ.');

            return self::FAILURE;
        }

        if (Admin::query()->where('email', $email)->exists()) {
            $this->error('Email này đã tồn tại.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Mật khẩu (tối thiểu 12 ký tự)');
        $confirmation = (string) $this->secret('Nhập lại mật khẩu');

        if (mb_strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Mật khẩu phải có ít nhất 12 ký tự và hai lần nhập phải trùng nhau.');

            return self::FAILURE;
        }

        Admin::query()->create([
            'email' => $email,
            'password_hash' => Hash::make($password),
        ]);

        $this->info("Đã tạo quản trị viên {$email}.");

        return self::SUCCESS;
    }
}
