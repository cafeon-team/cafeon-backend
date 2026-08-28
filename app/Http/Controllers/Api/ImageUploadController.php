<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Models\UploadedImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImageUploadController extends Controller
{
    public function store(UploadImageRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $path = null;

        try {
            $path = $file->store('blog', 'public');
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Public disk returned an empty upload path.');
            }

            UploadedImage::create([
                'user_id' => $request->user()->id,
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }

            report($exception);

            return response()->json([
                'message' => '이미지를 서버 저장소에 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.',
                'error_code' => 'IMAGE_STORAGE_UNAVAILABLE',
            ], 503);
        }

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'path' => $path,
            'url' => Str::startsWith($url, ['http://', 'https://']) ? $url : url($url),
            'image_url' => Str::startsWith($url, ['http://', 'https://']) ? $url : url($url),
        ], 201);
    }
}
