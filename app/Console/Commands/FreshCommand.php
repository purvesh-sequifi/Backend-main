<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sequifi:fresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs migration:fresh, Runs seeder, Clears Config Cache, Clears Route Cache, Reloads Autoload Class.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        exec('composer dump-autoload');
        $this->info('Reloaded Autoload class.');
        exec('php artisan cache:clear');
        $this->info('Cache cleared successfully.');
        exec('php artisan route:clear');
        $this->info('Route cleared successfully.');
        exec('php artisan config:clear');
        $this->info('Config cleared successfully.');
        exec('php artisan migrate:fresh');
        $this->info('Deleted all tables successfully.');
        $this->info('Re-Migrated all tables successfully.');
        exec('php artisan db:seed');
        $this->info('Seeder executed successfully.');
        exec('composer dump-autoload');
        $this->info('Reloaded Autoload class.');
        $this->info('Hello Gorakh All Done...');

        return Command::SUCCESS;
    }
}
