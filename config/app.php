<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

// Compute frontend base URL with proper fallback chain
$frontendBaseUrl = env('FRONTEND_BASE_URL', env('APP_URL', 'http://localhost'));

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Sequifi') === 'Laravel' ? 'Sequifi' : env('APP_NAME', 'Sequifi'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),
    'base_url' => env('BASE_URL', 'http://localhost'),
    'frontend_base_url' => $frontendBaseUrl,
    'login_link' => env('LOGIN_LINK', $frontendBaseUrl),

    'asset_url' => env('ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | This value is used for S3 storage paths and domain-specific logic
    | throughout the application.
    |
    */

    'domain_name' => env('DOMAIN_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Tiered Sales Configuration
    |--------------------------------------------------------------------------
    |
    | Enable or disable recalculation of tiered sales. Set to 1 to enable
    | tiered sales recalculation, or 0 to disable.
    |
    */

    'recalculate_tiered_sales' => env('RECALCULATE_TIERED_SALES', 0),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default number of items per page for paginated results.
    |
    */

    'paginate' => env('PAGINATE', 15),

    /*
    |--------------------------------------------------------------------------
    | Document Signing
    |--------------------------------------------------------------------------
    |
    | URL for the document signing screen.
    |
    */

    'sign_screen_url' => env('SIGN_SCREEN_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Admin User Passwords
    |--------------------------------------------------------------------------
    |
    | These are the passwords for CS Admin and Dev Admin users. Set these
    | in your .env file for security. Never commit actual passwords to git.
    |
    | SECURITY: These values MUST be set in .env file. No default fallbacks
    | are provided to prevent hardcoded passwords in the codebase.
    |
    */
    'super_admin_password' => env('SUPER_ADMIN_PASSWORD'),
    'cs_admin_password' => env('CS_ADMIN_PASSWORD'),
    'dev_admin_password' => env('DEV_ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    // 'timezone' => 'UTC',
    'timezone' => 'America/New_York',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'aws_s3bucket_url' => env('AWS_S3BUCKET_URL'),
    'aws_s3bucket_old_url' => env('AWS_S3BUCKET_OLD_URL'),

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        Barryvdh\DomPDF\ServiceProvider::class,

        /*
         * Package Service Providers...
         */
        L5Swagger\L5SwaggerServiceProvider::class,

        Sentry\Laravel\ServiceProvider::class,

        App\Providers\FieldRoutesServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\BoostProductionServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\AwsSesServiceProvider::class,
        Barryvdh\DomPDF\ServiceProvider::class,
        Maatwebsite\Excel\ExcelServiceProvider::class,
        App\Providers\HighLevelServiceProvider::class,
        App\Providers\EspQuickBaseServiceProvider::class,
        App\Providers\OnyxRepDataPushServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        'Excel' => Maatwebsite\Excel\Facades\Excel::class,
        'PDF' => Barryvdh\DomPDF\Facade::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | This value determines the "version" your application is currently running
    | in. You may want to follow the "semantic versioning" please
    |
    */

    'version' => env('APP_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring
    |--------------------------------------------------------------------------
    |
    | Used by the health check endpoint to monitor queue worker processes.
    |
    */

    'expected_queues' => env('EXPECTED_QUEUES') ? explode(',', env('EXPECTED_QUEUES')) : [],
    'minimum_workers' => env('MINIMUM_WORKERS', 1),
    'stuck_job_threshold_hours' => env('STUCK_JOB_THRESHOLD_HOURS', 4),

    /*
    |--------------------------------------------------------------------------
    | System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for system-wide functionality including admin user
    | and encryption settings used by the application.
    |
    */
    'admin_user_id' => 1,
    'encryption_cipher_algo' => env('ENCRYPTION_CIPHER_ALGO', 'AES-256-CBC'),
    'encryption_key' => env('ENCRYPTION_KEY'),
    'encryption_iv' => env('ENCRYPTION_IV'),

];
