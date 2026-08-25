<?php

namespace App\Console\Commands;

use App\Jobs\GenerateProfileThumbnail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackfillProfileThumbnails extends Command
{
    protected $signature = 'profiles:generate-thumbnails';

    protected $description = 'Generate missing thumbnails for locally stored profile images';

    public function handle(): int
    {
        $queued = 0;
        $skipped = 0;

        User::query()
            ->whereNotNull('profile_image_url')
            ->whereNull('profile_thumbnail_url')
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$queued, &$skipped) {
                foreach ($users as $user) {
                    $path = $this->publicDiskPath($user->profile_image_url);
                    if (! $path || ! Storage::disk('public')->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    GenerateProfileThumbnail::dispatch($user->id, $path, $user->profile_image_url);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} profile thumbnail(s); skipped {$skipped} external or missing image(s).");

        return self::SUCCESS;
    }

    private function publicDiskPath(string $url): ?string
    {
        $urlPath = parse_url($url, PHP_URL_PATH) ?: $url;
        if (! Str::contains($urlPath, '/storage/')) {
            return null;
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');

        return Str::startsWith($path, ['blog/', 'profiles/']) ? $path : null;
    }
}
