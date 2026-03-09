<?php

namespace App\Providers;

use Aws\Credentials\CredentialProvider;
use Aws\Ses\SesClient;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Support\ServiceProvider;

class AwsSesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('aws.ses', function ($app) {
            // Provider chain that will attempt to load credentials from
            // environment variables, AWS credentials file, and IAM role
            $provider = CredentialProvider::defaultProvider();

            $config = [
                'region' => config('services.ses.region', 'us-east-1'),
                'version' => 'latest',
                'credentials' => $provider,
            ];

            // Add endpoint if specified (useful for local development)
            if ($endpoint = config('services.ses.endpoint')) {
                $config['endpoint'] = $endpoint;
            }

            return new SesClient($config);
        });

        // Register custom mailer transport for SES with IAM Role
        $this->app->afterResolving('mail.manager', function ($manager) {
            $manager->extend('ses-role', function () {
                return new SesTransport($this->app->make('aws.ses'));
            });
        });
    }
}
