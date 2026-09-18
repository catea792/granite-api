<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('jwt:purge-expired-revocations')]
#[Description('Xóa các JWT revocation đã hết hạn')]
final class PurgeExpiredRevokedJwtTokens extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = DB::table('revoked_jwt_tokens')->where('expires_at', '<=', now('UTC'))->delete();
        $this->info("Đã xóa {$deleted} revocation hết hạn.");

        return self::SUCCESS;
    }
}
