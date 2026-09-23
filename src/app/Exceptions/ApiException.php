<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function responseMessage(): mixed
    {
        return $this->getMessage();
    }

    /** @return array{error_code: string, http_status: int} */
    public function context(): array
    {
        return [
            'error_code' => $this->errorCode,
            'http_status' => $this->status,
        ];
    }
}
