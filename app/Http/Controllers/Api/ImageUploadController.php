<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Models\UploadedImage;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ImageUploadController extends Controller
{
    public function __construct(private readonly ImageStorageService $images) {}

    public function store(UploadImageRequest $request): JsonResponse
    {
        $file = $request->file('image');

        try {
            $stored = $this->images->store($file);
            UploadedImage::create([
                'user_id' => $request->user()->id,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => '이미지를 서버 저장소에 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.',
                'error_code' => 'IMAGE_STORAGE_UNAVAILABLE',
            ], 503);
        }

        return response()->json([
            'path' => $stored['path'],
            'url' => $stored['url'],
            'image_url' => $stored['url'],
        ], 201);
    }
}
