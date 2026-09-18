<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Contracts\ImageStorage;
use App\Exceptions\ImageStorageException;
use App\Storage\R2ImageStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class R2ImageStorageTest extends TestCase
{
    public function test_upload_uses_unique_safe_key_writes_metadata_resolves_url_and_deletes(): void
    {
        config()->set('filesystems.disks.r2.url', 'https://media.example.com/base');
        Storage::fake('r2');
        $storage = app(ImageStorage::class);

        $first = $storage->upload(UploadedFile::fake()->image('unsafe original name.jpg', 20, 20), 'products');
        $second = $storage->upload(UploadedFile::fake()->image('unsafe original name.jpg', 20, 20), 'products');

        $this->assertInstanceOf(R2ImageStorage::class, $storage);
        $this->assertNotSame($first->path, $second->path);
        $this->assertMatchesRegularExpression('/^products\/[0-9A-HJKMNP-TV-Z]{26}\.jpg$/', $first->path);
        $this->assertStringNotContainsString('unsafe', $first->path);
        $this->assertSame('image/jpeg', $first->mimeType);
        $this->assertGreaterThan(0, $first->byteSize);
        $this->assertSame('https://media.example.com/base/'.$first->path, $first->url);
        Storage::disk('r2')->assertExists($first->path);

        $storage->delete($first->path);
        Storage::disk('r2')->assertMissing($first->path);
    }

    public function test_unsupported_file_and_unsafe_directory_are_rejected(): void
    {
        Storage::fake('r2');
        config()->set('filesystems.disks.r2.url', 'https://media.example.com');
        $storage = app(ImageStorage::class);

        $this->expectException(ImageStorageException::class);
        $storage->upload(UploadedFile::fake()->create('payload.txt', 1, 'text/plain'), '../escape');
    }

    public function test_delete_false_is_reported_as_storage_failure(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->with('images/file.jpg')->andReturnFalse();
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->once()->with('r2')->andReturn($disk);
        $storage = new R2ImageStorage($manager);

        $this->expectException(ImageStorageException::class);
        $storage->delete('images/file.jpg');
    }

    public function test_upload_passes_detected_content_type_metadata_to_disk(): void
    {
        config()->set('filesystems.disks.r2.url', 'https://media.example.com');
        $image = UploadedFile::fake()->image('source.jpg', 10, 10);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')
            ->once()
            ->with(
                '',
                $image,
                Mockery::on(static fn (string $path): bool => preg_match('/^images\/[0-9A-HJKMNP-TV-Z]{26}\.jpg$/', $path) === 1),
                ['ContentType' => 'image/jpeg'],
            )
            ->andReturnUsing(static fn (string $directory, UploadedFile $file, string $path): string => $path);
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->once()->with('r2')->andReturn($disk);

        $uploaded = (new R2ImageStorage($manager))->upload($image);

        $this->assertSame('image/jpeg', $uploaded->mimeType);
    }
}
