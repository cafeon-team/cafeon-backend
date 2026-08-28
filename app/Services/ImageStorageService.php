<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageStorageService
{
    /** @return array{disk: string, path: string, url: string} */
    public function store(UploadedFile $image, string $directory = 'blog'): array
    {
        $disks = array_values(array_unique([
            (string) config('filesystems.upload_disk', 'public'),
            'public',
            'uploads',
        ]));

        foreach ($disks as $diskName) {
            if (! config("filesystems.disks.{$diskName}")) {
                continue;
            }

            try {
                $disk = Storage::disk($diskName);
                $path = $disk->putFile($directory, $image);

                if (is_string($path) && $path !== '' && $disk->exists($path)) {
                    $url = $disk->url($path);

                    return [
                        'disk' => $diskName,
                        'path' => $path,
                        'url' => Str::startsWith($url, ['http://', 'https://']) ? $url : url($url),
                    ];
                }
            } catch (\Throwable $exception) {
                Log::warning('Image upload disk failed.', [
                    'disk' => $diskName,
                    'directory' => $directory,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        throw new RuntimeException('No writable image upload disk is available.');
    }

    public function deleteLocalUrls(iterable $urls): void
    {
        foreach ($urls as $url) {
            $location = $this->localDiskLocation($url);
            if ($location !== null) {
                Storage::disk($location['disk'])->delete($location['path']);
            }
        }
    }

    public function publicDiskPath(?string $url): ?string
    {
        $location = $this->localDiskLocation($url);

        return $location !== null && $location['disk'] === 'public' ? $location['path'] : null;
    }

    /** @return array{disk: string, path: string}|null */
    private function localDiskLocation(?string $url): ?array
    {
        if (! $url) {
            return null;
        }

        $urlPath = parse_url($url, PHP_URL_PATH) ?: $url;
        foreach (['/uploads/' => 'public', '/storage/' => 'public'] as $prefix => $disk) {
            if (! Str::contains($urlPath, $prefix)) {
                continue;
            }

            $path = ltrim(Str::after($urlPath, $prefix), '/');

            return Str::startsWith($path, ['blog/', 'profiles/']) ? compact('disk', 'path') : null;
        }

        return null;
    }
}
