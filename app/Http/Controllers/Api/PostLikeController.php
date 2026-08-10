<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    public function store(Request $request, Post $post): JsonResponse
    {
        if ($post->status !== 'PUBLISHED') {
            return response()->json(['message' => 'Likes are only allowed on published posts.'], 422);
        }

        $like = PostLike::firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'liked' => true,
            'likes_count' => $post->likes()->count(),
            'like' => $like,
        ]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        PostLike::where('post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'liked' => false,
            'likes_count' => $post->likes()->count(),
        ]);
    }
}