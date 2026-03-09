<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncSwaggerPublicCommand extends Command
{
    protected $signature = 'swagger:sync-public
                            {--generate : Run l5-swagger:generate first if api-docs.json is missing}';

    protected $description = 'Copy api-docs.json from storage to public for swagger-ui.html and static HTML files';

    public function handle(): int
    {
        $source = storage_path('api-docs/api-docs.json');
        $publicDir = public_path('swagger-json');
        $dest = $publicDir.'/api-docs.json';

        if (! File::exists($source)) {
            if ($this->option('generate')) {
                $this->info('api-docs.json not found. Running l5-swagger:generate...');
                $this->call('l5-swagger:generate');
            } else {
                $this->error('api-docs.json not found in storage.');
                $this->line('Run: php artisan l5-swagger:generate');
                $this->line('Or: php artisan swagger:sync-public --generate');

                return self::FAILURE;
            }
        }

        if (! File::exists($source)) {
            $this->error('Failed to generate api-docs.json.');

            return self::FAILURE;
        }

        if (! File::exists($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }

        File::copy($source, $dest);
        $this->info("Synced api-docs.json to {$dest}");

        return self::SUCCESS;
    }
}
