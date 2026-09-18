<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\UploadedImageData;
use Illuminate\Http\UploadedFile;

interface ImageStorage
{
    public function upload(UploadedFile $image, string $directory = 'images'): UploadedImageData;

    public function delete(string $path): void;

    public function url(string $path): string;
}
