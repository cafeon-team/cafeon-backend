<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Models\UploadedImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    public function store(UploadImageRequest $request): JsonResponse
    {
        $path = $request->file('image')->store('blog', 'public');

        UploadedImage::create([
            'user_id' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $request->file('image')->getMimeType(),
            'size' => $request->file('image')->getSize(),
        ]);

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'path' => $path,
            'url' => Str::startsWith($url, ['http://', 'https://']) ? $url : url($url),
        ], 201);
    }
}
