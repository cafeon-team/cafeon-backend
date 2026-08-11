<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStorageService
{
    public function deleteLocalUrls(iterable $urls): void
    {
        foreach ($urls as $url) {
            $path = $this->publicDiskPath($url);
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function publicDiskPath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $urlPath = parse_url($url, PHP_URL_PATH) ?: $url;
        if (! Str::contains($urlPath, '/storage/')) {
            return null;
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');

        return Str::startsWith($path, 'blog/') ? $path : null;
    }
}
