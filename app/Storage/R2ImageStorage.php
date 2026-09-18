<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\ImageStorage;
use App\Data\UploadedImageData;
use App\Exceptions\ImageStorageException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

final class R2ImageStorage implements ImageStorage
{
    private readonly Filesystem $disk;

    public function __construct(FilesystemManager $filesystems)
    {
        $this->disk = $filesystems->disk('r2');
    }

    public function upload(UploadedFile $image, string $directory = 'images'): UploadedImageData
    {
        $directory = $this->normalizeDirectory($directory);
        $mimeType = (string) $image->getMimeType();
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new ImageStorageException('Định dạng ảnh không được hỗ trợ.'),
        };
        $byteSize = (int) $image->getSize();

        if ($byteSize < 1) {
            throw new ImageStorageException('Tệp ảnh rỗng hoặc không thể đọc.');
        }

        $path = sprintf('%s/%s.%s', $directory, Str::ulid(), $extension);

        try {
            $stored = $this->disk->putFileAs(
                '',
                $image,
                $path,
                ['ContentType' => $mimeType],
            );
        } catch (Throwable $exception) {
            throw new ImageStorageException('Không thể lưu ảnh vào object storage.', previous: $exception);
        }

        if ($stored === false) {
            throw new ImageStorageException('Không thể lưu ảnh vào object storage.');
        }

        return new UploadedImageData($path, $mimeType, $byteSize, $this->url($path));
    }

    public function delete(string $path): void
    {
        $path = $this->normalizePath($path);

        try {
            if (! $this->disk->delete($path)) {
                throw new ImageStorageException('Không thể xóa ảnh khỏi object storage.');
            }
        } catch (ImageStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ImageStorageException('Không thể xóa ảnh khỏi object storage.', previous: $exception);
        }
    }

    public function url(string $path): string
    {
        $path = $this->normalizePath($path);
        $baseUrl = rtrim((string) config('filesystems.disks.r2.url'), '/');

        if ($baseUrl === '') {
            throw new ImageStorageException('R2_URL chưa được cấu hình.');
        }

        $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $path)));

        return $baseUrl.'/'.$encodedPath;
    }

    private function normalizeDirectory(string $directory): string
    {
        $directory = trim($directory, '/');

        if ($directory === '' || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9\/_-]*\z/', $directory) || str_contains($directory, '..')) {
            throw new ImageStorageException('Thư mục lưu ảnh không hợp lệ.');
        }

        return $directory;
    }

    private function normalizePath(string $path): string
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new ImageStorageException('Đường dẫn ảnh không hợp lệ.');
        }

        return $path;
    }
}
