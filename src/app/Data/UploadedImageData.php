<?php

declare(strict_types=1);

namespace App\Data;

final readonly class UploadedImageData
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public int $byteSize,
        public string $url,
    ) {}
}
