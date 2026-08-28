<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PrepareUploadStorage extends Command
{
    protected $signature = 'uploads:prepare';

    protected $description = 'Create and verify a writable image upload directory';

    public function handle(): int
    {
        $diskName = (string) config('filesystems.upload_disk', 'uploads');
        $diskConfig = config("filesystems.disks.{$diskName}");

        if (! is_array($diskConfig)) {
            $this->error("Upload disk [{$diskName}] is not configured.");

            return self::FAILURE;
        }

        $root = $diskConfig['root'] ?? null;
        if (($diskConfig['driver'] ?? null) === 'local' && is_string($root)) {
            File::ensureDirectoryExists($root, 0755, true);
        }

        $probe = '.cafeon-upload-probe-'.bin2hex(random_bytes(8));

        try {
            $disk = Storage::disk($diskName);
            if (! $disk->put($probe, 'ok') || ! $disk->exists($probe)) {
                throw new \RuntimeException('Write verification failed.');
            }
            $disk->delete($probe);
        } catch (Throwable $exception) {
            $this->error("Upload disk [{$diskName}] is not writable: {$exception->getMessage()}");
            if (is_string($root)) {
                $this->line("Configured path: {$root}");
            }

            return self::FAILURE;
        }

        $this->info("Upload disk [{$diskName}] is ready.");
        if (is_string($root)) {
            $this->line("Path: {$root}");
        }

        return self::SUCCESS;
    }
}
