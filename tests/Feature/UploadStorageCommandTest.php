<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UploadStorageCommandTest extends TestCase
{
    public function test_prepare_command_creates_and_verifies_upload_directory(): void
    {
        $root = storage_path('framework/testing/upload-command');
        File::deleteDirectory($root);

        config([
            'filesystems.upload_disk' => 'command-test-uploads',
            'filesystems.disks.command-test-uploads' => [
                'driver' => 'local',
                'root' => $root,
                'url' => 'http://localhost/uploads',
                'visibility' => 'public',
                'throw' => false,
            ],
        ]);

        $this->artisan('uploads:prepare')
            ->expectsOutputToContain('is ready')
            ->assertSuccessful();

        $this->assertDirectoryExists($root);
        $this->assertSame([], array_values(array_diff(scandir($root), ['.', '..'])));

        File::deleteDirectory($root);
    }
}
