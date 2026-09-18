<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_normalizes_email_hashes_password_and_does_not_print_plaintext(): void
    {
        $password = 'very-secret-password';

        $this->artisan('admin:create', ['email' => ' ADMIN@EXAMPLE.COM '])
            ->expectsQuestion('Mật khẩu (tối thiểu 12 ký tự)', $password)
            ->expectsQuestion('Nhập lại mật khẩu', $password)
            ->expectsOutput('Đã tạo quản trị viên admin@example.com.')
            ->doesntExpectOutput($password)
            ->assertSuccessful();

        $admin = Admin::query()->sole();
        $this->assertSame('admin@example.com', $admin->email);
        $this->assertNotSame($password, $admin->password_hash);
        $this->assertTrue(Hash::check($password, $admin->password_hash));
    }

    public function test_command_rejects_duplicate_email(): void
    {
        Admin::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('admin:create', ['email' => ' ADMIN@EXAMPLE.COM '])
            ->expectsOutput('Email này đã tồn tại.')
            ->assertFailed();

        $this->assertSame(1, Admin::query()->count());
    }
}
