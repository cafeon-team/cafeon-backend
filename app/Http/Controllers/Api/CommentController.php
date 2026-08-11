<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Post $post): JsonResponse
    {
        $comments = $post->comments()
            ->with([
                'user:id,name',
                'replies' => fn ($query) => $query
                    ->where('status', 'VISIBLE')
                    ->with('user:id,name')
                    ->oldest(),
            ])
            ->whereNull('parent_id')
            ->where('status', 'VISIBLE')
            ->oldest()
            ->get();

        return response()->json(CommentResource::collection($comments)->resolve());
    }

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $validated = $request->validated();

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

        return response()->json(CommentResource::make($comment->load('user:id,name'))->resolve(), 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        $validated = $request->validated();

        $comment->update($validated);

        return response()->json(CommentResource::make($comment->fresh()->load('user:id,name'))->resolve());
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);
        $comment->delete();

        return response()->json(status: 204);
    }

    public function updateStatus(Request $request, Comment $comment): JsonResponse
    {
        $comment->loadMissing('post.store');
        $this->authorize('manageBlog', [Post::class, $comment->post->store]);
        $validated = $request->validate([
            'status' => ['required', 'in:VISIBLE,HIDDEN,SPAM'],
        ]);
        $comment->update($validated);

        return response()->json([
            'message' => '댓글 상태를 변경했습니다.',
            'comment' => CommentResource::make($comment->fresh())->resolve(),
        ]);
    }
}
