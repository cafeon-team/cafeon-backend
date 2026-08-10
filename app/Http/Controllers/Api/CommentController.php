<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        if ($post->status !== 'PUBLISHED') {
            return response()->json(['message' => 'Comments are only allowed on published posts.'], 422);
        }

        if (isset($validated['parent_id'])) {
            $parent = Comment::findOrFail($validated['parent_id']);

            if ($parent->post_id !== $post->id) {
                return response()->json(['message' => 'The parent comment belongs to another post.'], 422);
            }

            $isStoreManager = $request->user()->role === 'ADMIN'
                || $request->user()->storeMemberships()
                    ->where('store_id', $post->store_id)
                    ->where('is_active', true)
                    ->whereIn('role', ['OWNER', 'MANAGER'])
                    ->exists();

            if (! $isStoreManager) {
                return response()->json(['message' => 'Only the store owner or manager can write replies.'], 403);
            }
        }

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'],
            'status' => 'VISIBLE',
        ]);

        return response()->json($comment->load('user:id,name'), 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $comment->update($validated);

        return response()->json($comment->fresh()->load('user:id,name'));
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);
        $comment->delete();

        return response()->json(status: 204);
    }
}