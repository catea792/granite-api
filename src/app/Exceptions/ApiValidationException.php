<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ApiValidationException extends ApiException
{
    /** @param list<array{field: string, code: string, message: string}> $issues */
    public function __construct(private readonly array $issues)
    {
        parent::__construct(
            'VALIDATION_FAILED',
            'Dữ liệu gửi lên không hợp lệ.',
            422,
        );
    }

    /** @return array{issues: list<array{field: string, code: string, message: string}>} */
    public function responseMessage(): array
    {
        return ['issues' => $this->issues];
    }
}
