<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

        'endpoint' => env('AWS_SES_ENDPOINT'),

        'use_iam_role' => env('AWS_USE_IAM_ROLE', false),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/redirect/google',
        'maps_api_key' => env('GOOGLE_MAPS_KEY'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => '/auth/redirect/facebook',
    ],

    'field_routes' => [
        'max_retries' => 3,
        'retry_delay' => 2,
        'timeout' => 30,
        'concurrent_processing' => true,
        'concurrency_limit' => 3,
    ],

    'highlevel' => [
        'token' => env('HIGHLEVEL_API_TOKEN'),
        'location_id' => env('HIGHLEVEL_LOCATION_ID'),
        'api_version' => env('HIGHLEVEL_API_VERSION', '2021-07-28'),
    ],

    'ssldotcom' => [
        'token_base_url' => env('SSLDOTCOM_TOKEN_BASE_URL', 'https://login.ssl.com'),
        'base_url' => env('SSLDOTCOM_BASE_URL', 'https://ds.ssl.com'),
        'client_id' => env('SSLDOTCOM_CLIENT_ID'),
        'client_secret' => env('SSLDOTCOM_CLIENT_SECRET'),
        'username' => env('SSLDOTCOM_USERNAME'),
        'password' => env('SSLDOTCOM_PASSWORD'),
        'springboot_app_url' => env('SSLDOTCOM_SPRINGBOOT_APP_URL'),
        'credential_id' => env('SSLDOTCOM_CREDENTIAL_ID'),
    ],

    'turnai' => [
        'url' => env('TURN_AI_URL'),
        'jwt_token' => env('TURN_AI_JWT_TOKEN'),
    ],

    'quickbooks' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'base_url' => env('QUICKBOOKS_BASE_URL'),
        'oauth_scope' => env('QUICKBOOKS_OAUTH_SCOPE'),
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
        'api_url' => env('QUICKBOOKS_API_URL'),
    ],

    'jira' => [
        'email' => env('JIRA_EMAIL'),
        'secret_key' => env('JIRA_SECRET_KEY'),
        'base_url' => env('JIRA_API_BASE_URL'),
    ],

    'stripe' => [
        'type' => env('STRIPE_TYPE', 'test'),
        'key_live' => env('STRIPE_KEY_LIVE'),
        'key_test' => env('STRIPE_KEY_TEST'),
    ],

    'aws' => [
        's3_bucket_url' => env('AWS_S3BUCKET_URL'),
    ],

    'chatgpt' => [
        'token_key' => env('CHAT_GPT_TOKEN_KEY'),
    ],

];
