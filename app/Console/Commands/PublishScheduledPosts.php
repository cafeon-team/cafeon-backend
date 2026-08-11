<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish scheduled blog posts whose publication time has arrived';

    public function handle(): int
    {
        $publishedCount = Post::query()
            ->where('status', 'SCHEDULED')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->update([
                'status' => 'PUBLISHED',
                'published_at' => now(),
                'scheduled_at' => null,
                'updated_at' => now(),
            ]);

        $this->info("{$publishedCount} scheduled post(s) published.");

        return self::SUCCESS;
    }
}
