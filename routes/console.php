<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (App::environment() === 'production') {
    // migrate:fresh - Refresh Entire Database
    // migrate:refresh - Remove All Data From Database
    // migrate:rollback - Rollback Migration
    // migrate:reset -
    // db:seed - Seeding Database
    // down - Maintenance Mode On
    // up - Maintenance Mode Off
    // db:wipe - Drop all tables, views, and types
    $commands = ['migrate:fresh', 'migrate:refresh', 'migrate:rollback', 'migrate:reset', 'db:seed', 'down', 'up', 'db:wipe'];
    foreach ($commands as $command) {
        Artisan::command($command, function () {
            $this->comment('You are not allowed to do this in production!');
        })->describe('Override default command in production.');
    }
}
