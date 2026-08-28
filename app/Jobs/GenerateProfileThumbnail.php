<?php

namespace App\Jobs;

use App\Models\UploadedImage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateProfileThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $userId,
        public string $sourcePath,
        public string $sourceUrl,
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($this->sourcePath)) {
            throw new RuntimeException('프로필 원본 이미지를 찾을 수 없습니다.');
        }

        $sourceBytes = $disk->get($this->sourcePath);
        $source = @imagecreatefromstring($sourceBytes);
        if ($source === false) {
            throw new RuntimeException('프로필 이미지를 읽을 수 없습니다.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $sourceX = (int) floor(($width - $side) / 2);
        $sourceY = (int) floor(($height - $side) / 2);
        $thumbnail = imagecreatetruecolor(128, 128);

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $source, 0, 0, $sourceX, $sourceY, 128, 128, $side, $side);

        ob_start();
        $encoded = imagewebp($thumbnail, null, 80);
        $thumbnailBytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumbnail);

        if (! $encoded || ! is_string($thumbnailBytes)) {
            throw new RuntimeException('프로필 썸네일을 생성할 수 없습니다.');
        }

        $thumbnailPath = 'profile-thumbnails/'.Str::uuid().'.webp';
        $disk->put($thumbnailPath, $thumbnailBytes, ['visibility' => 'public']);
        $thumbnailUrl = rtrim(config('filesystems.disks.public.url'), '/').'/'.$thumbnailPath;

        $updated = User::query()
            ->whereKey($this->userId)
            ->where('profile_image_url', $this->sourceUrl)
            ->update(['profile_thumbnail_url' => $thumbnailUrl]);

        if ($updated === 0) {
            $disk->delete($thumbnailPath);

            return;
        }

        UploadedImage::create([
            'user_id' => $this->userId,
            'disk' => 'public',
            'path' => $thumbnailPath,
            'mime_type' => 'image/webp',
            'size' => strlen($thumbnailBytes),
            'attached_type' => 'PROFILE_THUMBNAIL',
            'attached_id' => $this->userId,
        ]);
    }
}
