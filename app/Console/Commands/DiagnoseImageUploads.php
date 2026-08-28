<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DiagnoseImageUploads extends Command
{
    protected $signature = 'images:diagnose';

    protected $description = 'Check image upload storage, database metadata table, and public storage link';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $probe = '.upload-diagnostic-'.uniqid().'.tmp';
        $checks = [];

        try {
            $checks['public disk write/delete'] = $disk->put($probe, 'ok') && $disk->exists($probe);
            $disk->delete($probe);
        } catch (Throwable $exception) {
            $checks['public disk write/delete'] = false;
            $this->error('Storage error: '.$exception->getMessage());
        }

        $checks['uploaded_images table'] = Schema::hasTable('uploaded_images');
        $checks['uploaded_images DB query'] = $checks['uploaded_images table']
            && DB::table('uploaded_images')->limit(1)->get() !== null;
        $checks['public/storage link or directory'] = is_dir(public_path('storage'));
        $checks['fileinfo extension'] = extension_loaded('fileinfo');

        foreach ($checks as $name => $passed) {
            $this->line(sprintf('[%s] %s', $passed ? 'OK' : 'FAIL', $name));
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
