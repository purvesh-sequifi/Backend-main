<?php

use App\Core\Traits\EditSaleTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Events\sendEventToPusher;
use App\Http\Controllers\API\ApiMissingDataController;
use App\Jobs\GenerateAlertJob;
use App\Jobs\Sales\SaleMasterJob;
use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Crmcustomfields;
use App\Models\EmailConfiguration;
use App\Models\ExcelImportHistory;
use App\Models\ExternalSaleWorker;
use App\Models\FrequencyType;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRowData;
use App\Models\MonthlyPayFrequency;
use App\Models\Payroll;
use App\Models\paystubEmployee;
use App\Models\PositionPayFrequency;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleDataUpdateLogs;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\Settings;
use App\Models\State;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
use App\Models\UsersAdditionalEmail;
use App\Models\WeeklyPayFrequency;
use App\Traits\EmailNotificationTrait;
use Aws\S3\S3Client;
use Barryvdh\Reflection\DocBlock\Type\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash; // added in pstage
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

if (! function_exists('getS3Credentials')) {
    /**
     * Get S3 credentials with three-tier fallback:
     * 1. Settings table (database)
     * 2. Config file (encrypted .env values)
     * 3. IAM role (EC2 instance profile - returns null credentials to use default provider)
     *
     * @param string $bucketType 'private' or 'public'
     * @return array Contains 'region', 'bucket', 'key', 'secret', and 'use_iam_role'
     */
    function getS3Credentials(string $bucketType = 'private'): array
    {
        $suffix = $bucketType === 'public' ? '_PUBLIC' : '_PRIVATE';

        // Tier 1: Try Settings table (database)
        try {
            $settings = Settings::get();
            if (! empty($settings)) {
                $data = [];
                foreach ($settings as $row) {
                    $data[$row->key] = $row->value;
                }

                $regionKey = 'AWS_DEFAULT_REGION' . $suffix;
                $accessKeyKey = 'AWS_ACCESS_KEY_ID' . $suffix;
                $secretKeyKey = 'AWS_SECRET_ACCESS_KEY' . $suffix;
                $bucketKey = 'AWS_BUCKET' . $suffix;

                if (! empty($data[$regionKey]) && ! empty($data[$accessKeyKey]) && ! empty($data[$secretKeyKey]) && ! empty($data[$bucketKey])) {
                    return [
                        'region' => $data[$regionKey],
                        'bucket' => $data[$bucketKey],
                        'key' => $data[$accessKeyKey],
                        'secret' => $data[$secretKeyKey],
                        'use_iam_role' => false,
                        'source' => 'settings_table',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Database error, fall through to next tier
            Log::warning('S3 credentials: Database unavailable, falling back to config/IAM', ['error' => $e->getMessage()]);
        }

        // Tier 2: Try Config file (encrypted credentials from .env)
        // Tier 2: Try Config file (encrypted credentials from .env)
        $regionConfig = $bucketType === 'public' ? 'aws.region_public' : 'aws.region_private';
        $bucketConfig = $bucketType === 'public' ? 'aws.bucket_public' : 'aws.bucket_private';
        $keyConfig = $bucketType === 'public' ? 'aws.key_encrypted_public' : 'aws.key_encrypted_private';
        $secretConfig = $bucketType === 'public' ? 'aws.secret_encrypted_public' : 'aws.secret_encrypted_private';

        $region = config($regionConfig);
        $bucket = config($bucketConfig);
        $encryptedKey = config($keyConfig);
        $encryptedSecret = config($secretConfig);

        if (! empty($encryptedKey) && ! empty($encryptedSecret)) {
            $accessKey = openssl_decrypt(
                $encryptedKey,
                config('app.encryption_cipher_algo'),
                config('app.encryption_key'),
                0,
                config('app.encryption_iv')
            );
            $secretKey = openssl_decrypt(
                $encryptedSecret,
                config('app.encryption_cipher_algo'),
                config('app.encryption_key'),
                0,
                config('app.encryption_iv')
            );

            // Validate bucket is not null before returning Tier 2 credentials
            $defaultBucket = $bucketType === 'public' ? 'sequifi' : 'sequifi-private-files';
            $validatedBucket = $bucket ?? env('AWS_BUCKET'.$suffix) ?? $defaultBucket;

            if (! empty($accessKey) && ! empty($secretKey) && ! empty($validatedBucket)) {
                return [
                    'region' => $region ?? 'us-west-1',
                    'bucket' => $validatedBucket,
                    'key' => $accessKey,
                    'secret' => $secretKey,
                    'use_iam_role' => false,
                    'source' => 'config_encrypted',
                ];
            }
        }

        // Tier 3: Fall back to IAM role (EC2 instance profile)
        // Return empty credentials - S3Client will use default credential provider chain
        $defaultRegion = $bucketType === 'public' ? 'us-west-1' : 'us-west-1';
        $defaultBucket = $bucketType === 'public' ? 'sequifi' : 'sequifi-private-files';

        return [
            'region' => $region ?? env('AWS_DEFAULT_REGION' . $suffix) ?? $defaultRegion,
            'bucket' => $bucket ?? env('AWS_BUCKET' . $suffix) ?? $defaultBucket,
            'key' => null,
            'secret' => null,
            'use_iam_role' => true,
            'source' => 'iam_role',
        ];
    }
}

if (! function_exists('createS3Client')) {
    /**
     * Create an S3 client using the centralized credential provider.
     * Supports IAM role authentication when explicit credentials are not available.
     *
     * @param string $bucketType 'private' or 'public'
     * @param string|null $regionOverride Override the region from credentials
     * @return array Contains 's3' (S3Client instance) and 'bucket' (string)
     */
    function createS3Client(string $bucketType = 'private', ?string $regionOverride = null): array
    {
        $credentials = getS3Credentials($bucketType);

        $config = [
            'version' => 'latest',
            'region' => $regionOverride ?? $credentials['region'],
        ];

        // Only add explicit credentials if they exist (otherwise S3Client uses IAM role)
        if (! $credentials['use_iam_role'] && ! empty($credentials['key']) && ! empty($credentials['secret'])) {
            $config['credentials'] = [
                'key' => $credentials['key'],
                'secret' => $credentials['secret'],
            ];
        }

        return [
            's3' => new S3Client($config),
            'bucket' => $credentials['bucket'],
            'credentials_source' => $credentials['source'],
        ];
    }
}

if (! function_exists('s3_upload')) {
    function s3_upload($file_name, $content, $isFile = false, $stored_bucket = 'private')
    {
        // function s3_upload($file_name,$content,$isFile=false,$stored_bucket='public'){

        // //s3 bucket in upload file -----
        // $filePath =  '123.pdf';
        // Storage::disk("s3_private")->put($filePath, 'hello world 123 123');
        // //s3 bucket in upload file End--------

        // exit();
        // $file_name = str_replace(' ', '_',$file_name);
        $file_name_arr = explode('.', $file_name);
        switch (strtolower($file_name_arr[1])) {
            case 'png':
                $content_type = 'image/png';
                break;
            case 'jpg' :
                $content_type = 'image/jpg';
                break;
            case 'jpeg' :
                $content_type = 'image/jpeg';
                break;
            case 'gif' :
                $content_type = 'image/gif';
                break;
            case 'msword' :
                $content_type = 'application/msword';
                break;
            case 'zip' :
                $content_type = 'application/zip';
                break;
            case 'pdf' :
                $content_type = 'application/pdf';
                break;
            default:
                $content_type = 'binary/octet-stream';
                break;
        }

        // Use centralized S3 client factory with IAM role fallback
        $s3Client = createS3Client($stored_bucket);
        $s3 = $s3Client['s3'];
        $bucket = $s3Client['bucket'];

        // Upload file to S3 bucket
        try {
            if ($isFile) {
                $result = $s3->putObject([
                    'Bucket' => $bucket,
                    'Key' => $file_name,
                    // 'ACL'    => 'public-read',
                    'SourceFile' => $content,
                    'ContentDisposition' => 'inline',
                    'ContentType' => $content_type,
                ]);
            } else {
                $result = $s3->putObject([
                    'Bucket' => $bucket,
                    'Key' => $file_name,
                    // 'ACL'    => 'public-read',
                    'Body' => $content,
                    'ContentDisposition' => 'inline',
                    'ContentType' => $content_type,
                ]);
            }

            $result_arr = $result->toArray();

            if (! empty($result_arr['ObjectURL'])) {
                return $s3_file_link =
                [
                    'ObjectURL' => $result_arr['ObjectURL'],
                    'status' => true,
                ];

                // Log::info($s3_file_link);
            } else {
                return $api_error = 'Upload Failed! S3 Object URL not found.';
                // Log::info($api_error);
            }
        } catch (Aws\S3\Exception\S3Exception $e) {
            return $api_error = $e->getMessage();
            // Log::info($api_error);
        }
    }
}

if (! function_exists('s3_getTempUrl')) {
    function s3_getTempUrl($filepath, $stored_bucket = 'private', $duration = 30)
    {
        // Note: DO NOT strip leading slash - S3 keys are case and character sensitive
        // Some files may be stored with leading slash due to config('app.domain_name') being null
        // $filepath = ltrim($filepath, '/');

        // //s3 bucket in upload file -----
        // $filePath =  '123.pdf';
        // Storage::disk("s3_private")->put($filePath, 'hello world 123 123');
        // Storage::disk('s3_private')->temporaryUrl(config('app.domain_name').'/'.'legacy-raw-data-files/'.$row['log_file_name'] , now()->addMinutes(30));
        //
        // //s3 bucket in upload file End--------
        // s3 bucket in upload image private folder
        // $imagePath = "document/" . time() . $file->getClientOriginalName();
        // \Storage::disk("s3_private")->put($imagePath, file_get_contents($file));
        // $getImage .=  Storage::disk('s3_private')->temporaryUrl($imagePath , now()->addMinutes(10));
        // s3 bucket in upload image private folder End----
        // $filepath = 'flex/signed_documents/06dddbce-c3d7-4ba7-8743-903b6451b8ad_Offer_Letter-_10-18-20231697612636.pdf';

        // exit();

        $check_s3_getTempUrl = check_s3_getTempUrl($filepath, $stored_bucket, $duration);

        if (isset($check_s3_getTempUrl['status']) && $check_s3_getTempUrl['status'] == true) {
            return $file_link = $check_s3_getTempUrl['presignedUrl'];
        } else {
            return null;
        }
    }
}

if (! function_exists('getAwsS3Client')) {
    /**
     * Get AWS S3 client configuration using centralized credential provider with IAM role fallback.
     *
     * @param string $bucketType 'private' or 'public'
     * @return array Contains 'client' (S3Client) and 'bucket' (string)
     */
    function getAwsS3Client(string $bucketType = 'private'): array
    {
        // Use centralized S3 client factory with IAM role fallback
        $s3Client = createS3Client($bucketType);

        return [
            'client' => $s3Client['s3'],
            'bucket' => $s3Client['bucket'],
        ];
    }
}


if (! function_exists('check_s3_getTempPresignedUrl')) {
    /**
     * Generate S3 presigned URL with pre-initialized client for better performance
     *
     * @param  string  $filepath  The file path in S3 bucket
     * @param  S3Client  $s3Client  Pre-initialized S3 client
     * @param  string  $bucket  S3 bucket name
     * @param  int  $duration  URL expiration time in minutes (default: 30)
     * @return array|null Returns array with status and presignedUrl, or null on failure
     */
    function check_s3_getTempPresignedUrl($filepath, $s3Client, $bucket, $duration = 30)
    {
        // Strip leading slash from filepath to fix S3 key issues
        $filepath = ltrim($filepath, '/');
        
        try {
            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $filepath,
            ]);

            $request = $s3Client->createPresignedRequest($cmd, '+'.$duration.' minutes');
            $presignedUrl = (string) $request->getUri();

            return [
                'status' => true,
                'presignedUrl' => $presignedUrl,
            ];
        } catch (Aws\S3\Exception\S3Exception $e) {
            // Log the error for debugging
            \Log::warning('S3 presigned URL generation failed', [
                'file' => $filepath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

if (! function_exists('check_s3_getTempUrl')) {
    function check_s3_getTempUrl($filepath, $stored_bucket = 'private', $duration = 30)
    {
        // Note: DO NOT strip leading slash - S3 keys are case and character sensitive
        // Some files may be stored with leading slash due to config('app.domain_name') being null
        // $filepath = ltrim($filepath, '/' );

        // Use centralized S3 client factory with IAM role fallback
        $s3Client = createS3Client($stored_bucket);
        $s3 = $s3Client['s3'];
        $bucket = $s3Client['bucket'];

        // Generate presigned URL for file
        try {
            $cmd = $s3->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $filepath,
            ]);

            $request = $s3->createPresignedRequest($cmd, '+'.$duration.' minutes');

            // Get the actual presigned-url
            $presignedUrl = (string) $request->getUri();

            if (! empty($presignedUrl)) {
                return [
                    'status' => true,
                    'presignedUrl' => $presignedUrl,
                ];
            } else {
                return '';
            }
        } catch (Aws\S3\Exception\S3Exception $e) {
            return '';  // $e->getMessage();
        }
    }
}

/*** change date format ***/
if (! function_exists('dateToMDY')) {
    function dateToMDY($date)
    {
        // dd($date);
        if (empty($date)) {
            return '';
        }

        return Carbon::createFromFormat('d/m/Y', $date)->format('m-d-Y');

    }
}

/*** change date format ***/
if (! function_exists('dateToYMD')) {
    function dateToYMD($date)
    {
        // dd($date);
        if (empty($date)) {
            return '';
        }

        return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');

    }
}

if (! function_exists('dateToDMY')) {
    function dateToDMY($date)
    {
        // dd($date);
        // "20/12/2022";
        if (empty($date)) {
            return '';
        }

        return Carbon::createFromFormat('d/m/Y', $date)->format('m/d/Y');

    }
}

if (! function_exists('get_growth_percentage')) {
    function get_growth_percentage($old_value, $new_value)
    {
        if ($old_value == 0 && $new_value >= 0) {
            return $percentage = '100%';
        }
        if ($old_value > 0 && $new_value >= 0) {
            $diff = $new_value - $old_value;

            return $percentage = round(($diff / $old_value) * 100, 2).'%';
        }
    }
}

if (! function_exists('createDateToDMY')) {
    function createDateToDMY($date)
    {
        // dd($date);
        // "20/12/2022";
        return Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('m/d/Y');
    }
}

if (! function_exists('get_svg_icon')) {
    function get_svg_icon($path, $class = null, $svgClass = null)
    {
        if (strpos($path, 'media') === false) {
            $path = theme()->getMediaUrlPath().$path;
        }

        $file_path = public_path($path);

        if (! file_exists($file_path)) {
            return '';
        }

        $svg_content = file_get_contents($file_path);

        if (empty($svg_content)) {
            return '';
        }

        $dom = new DOMDocument;
        $dom->loadXML($svg_content);

        // remove unwanted comments
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//comment()') as $comment) {
            $comment->parentNode->removeChild($comment);
        }

        // add class to svg
        if (! empty($svgClass)) {
            foreach ($dom->getElementsByTagName('svg') as $element) {
                $element->setAttribute('class', $svgClass);
            }
        }

        // remove unwanted tags
        $title = $dom->getElementsByTagName('title');
        if ($title['length']) {
            $dom->documentElement->removeChild($title[0]);
        }
        $desc = $dom->getElementsByTagName('desc');
        if ($desc['length']) {
            $dom->documentElement->removeChild($desc[0]);
        }
        $defs = $dom->getElementsByTagName('defs');
        if ($defs['length']) {
            $dom->documentElement->removeChild($defs[0]);
        }

        // remove unwanted id attribute in g tag
        $g = $dom->getElementsByTagName('g');
        foreach ($g as $el) {
            $el->removeAttribute('id');
        }
        $mask = $dom->getElementsByTagName('mask');
        foreach ($mask as $el) {
            $el->removeAttribute('id');
        }
        $rect = $dom->getElementsByTagName('rect');
        foreach ($rect as $el) {
            $el->removeAttribute('id');
        }
        $xpath = $dom->getElementsByTagName('path');
        foreach ($xpath as $el) {
            $el->removeAttribute('id');
        }
        $circle = $dom->getElementsByTagName('circle');
        foreach ($circle as $el) {
            $el->removeAttribute('id');
        }
        $use = $dom->getElementsByTagName('use');
        foreach ($use as $el) {
            $el->removeAttribute('id');
        }
        $polygon = $dom->getElementsByTagName('polygon');
        foreach ($polygon as $el) {
            $el->removeAttribute('id');
        }
        $ellipse = $dom->getElementsByTagName('ellipse');
        foreach ($ellipse as $el) {
            $el->removeAttribute('id');
        }

        $string = $dom->saveXML($dom->documentElement);

        // remove empty lines
        $string = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $string);

        $cls = ['svg-icon'];

        if (! empty($class)) {
            $cls = array_merge($cls, explode(' ', $class));
        }

        $asd = explode('/media/', $path);
        if (isset($asd[1])) {
            $path = 'assets/media/'.$asd[1];
        }

        $output = "<!--begin::Svg Icon | path: $path-->\n";
        $output .= '<span class="'.implode(' ', $cls).'">'.$string.'</span>';
        $output .= "\n<!--end::Svg Icon-->";

        return $output;
    }
}

if (! function_exists('theme')) {
    /**
     * Get the instance of Theme class core
     *
     * @return \App\Core\Adapters\Theme|\Illuminate\Contracts\Foundation\Application|mixed
     */
    function theme()
    {
        return app(\App\Core\Adapters\Theme::class);
    }
}

if (! function_exists('util')) {
    /**
     * Get the instance of Util class core
     *
     * @return \App\Core\Adapters\Util|\Illuminate\Contracts\Foundation\Application|mixed
     */
    function util()
    {
        return app(\App\Core\Adapters\Util::class);
    }
}

if (! function_exists('bootstrap')) {
    /**
     * Get the instance of Util class core
     *
     * @return \App\Core\Adapters\Util|\Illuminate\Contracts\Foundation\Application|mixed
     *
     * @throws Throwable
     */
    function bootstrap()
    {
        $demo = ucwords(theme()->getDemo());
        $bootstrap = "\App\Core\Bootstraps\Bootstrap$demo";

        if (! class_exists($bootstrap)) {
            abort(404, 'Demo has not been set or '.$bootstrap.' file is not found.');
        }

        return app($bootstrap);
    }
}

if (! function_exists('assetCustom')) {
    /**
     * Get the asset path of RTL if this is an RTL request
     *
     * @param  null  $secure
     * @return string
     */
    function assetCustom($path)
    {
        // Include rtl css file
        if (isRTL()) {
            return asset(theme()->getDemo().'/'.dirname($path).'/'.basename($path, '.css').'.rtl.css');
        }

        // Include dark style css file
        if (theme()->isDarkModeEnabled() && theme()->getCurrentMode() !== 'light') {
            $darkPath = str_replace('.bundle', '.'.theme()->getCurrentMode().'.bundle', $path);
            if (file_exists(public_path(theme()->getDemo().'/'.$darkPath))) {
                return asset(theme()->getDemo().'/'.$darkPath);
            }
        }

        // Include default css file
        return asset(theme()->getDemo().'/'.$path);
    }
}

if (! function_exists('isRTL')) {
    /**
     * Check if the request has RTL param
     *
     * @return bool
     */
    function isRTL()
    {
        return isset($_REQUEST['rtl']) && $_REQUEST['rtl'] || (isset($_COOKIE['rtl']) && $_COOKIE['rtl']);
    }
}

if (! function_exists('preloadCss')) {
    /**
     * Preload CSS file
     *
     * @return bool
     */
    function preloadCss($url)
    {
        return '<link rel="preload" href="'.$url.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" type="text/css"><noscript><link rel="stylesheet" href="'.$url.'"></noscript>';
    }
}

if (! function_exists('isDarkSidebar')) {
    function isDarkSidebar()
    {
        if (isset($_COOKIE['layout'])) {
            if ($_COOKIE['layout'] === 'dark-sidebar') {
                return true;
            }
            if ($_COOKIE['layout'] === 'light-sidebar') {
                return false;
            }
        } else {
            return theme()->getOption('layout', 'aside/theme') === 'dark';
        }

        return true;
    }
}

if (! function_exists('curlRequest')) {
    function curlRequest($url, $data, $headers, $method = 'POST')
    {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,

        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}

if (!function_exists('curlRequestWithStatusCode')) {
    function curlRequestWithStatusCode($url,$data,$headers,$method='POST'){

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL =>  $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30, // 30 second timeout to prevent hanging
          CURLOPT_CONNECTTIMEOUT => 10, // 10 second connection timeout
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => $method,
          CURLOPT_POSTFIELDS => $data,
          CURLOPT_HTTPHEADER => $headers,

        ));

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        // Handle cURL errors
        if ($response === false) {
            throw new \Exception('cURL Error: ' . $curl_error);
        }

        return [
            'body' => $response,
            'statusCode' => $http_code,
        ];
    }
}

if (! function_exists('removeMultiSpace')) {
    function removeMultiSpace($text)
    {
        $text = preg_replace('/[\t\n\r\0\x0B]/', '', $text);
        $text = preg_replace('/([\s])\1+/', ' ', $text);
        $text = trim($text);

        return $text;
    }
}
if (! function_exists('paginate')) {
    function paginate($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }
}

if (! function_exists('custom_decrypt')) {
    function custom_decrypt($encryptedBase64)
    {
        $key = base64_decode(config('app.encryption_key'));
        $iv = config('app.encryption_iv');
        $cipher = config('app.encryption_cipher_algo', 'AES-256-CBC');

        $encrypted = base64_decode($encryptedBase64, true);

        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }
}
if (! function_exists('custom_encrypt')) {
    function custom_encrypt($plaintext)
    {
        $key = base64_decode(config('app.encryption_key'));
        $iv = config('app.encryption_iv');
        $cipher = config('app.encryption_cipher_algo', 'AES-256-CBC');

        $encrypted = openssl_encrypt($plaintext, $cipher, $key, 0, $iv);

        return base64_encode($encrypted); // Safe to store in DB
    }
}

if (! function_exists('insert_update_sale_master')) {
    function insert_update_sale_master($pid = '')
    {
        try {
            DB::beginTransaction();
            if (empty($pid)) {
                $newData = LegacyApiNullData::with('userDetail', 'userAdditionalEmail')->whereNotNull('data_source_type')->orderBy('id', 'desc')->get();
            } else {
                $newData = LegacyApiNullData::with('userDetail', 'userAdditionalEmail')->where('pid', $pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->get();
            }

            // Update data by previous comparison in Sales_Master
            foreach ($newData as $checked) {
                // Skip Termite Inspection products for WhiteKnight
                if ($checked->product === 'Termite Inspection' && ($checked->data_source_type === 'WhiteKnight' || $checked->data_source_type === 'whiteknight')) {
                    continue;
                }

                $check = 0;
                $salesMaster = SalesMaster::where('pid', $checked->pid)->first();
                if (! empty($salesMaster)) {
                    $check_kw = ($checked['kw'] == $salesMaster->kw) ? 0 : 1;
                    $check_net_epc = ($checked['net_epc'] == $salesMaster->net_epc) ? 0 : 1;
                    $check_date_cancelled = ($checked['date_cancelled'] == $salesMaster->date_cancelled) ? 0 : 1;
                    $check_return_sales_date = ($checked['return_sales_date'] == $salesMaster->return_sales_date) ? 0 : 1;
                    $check_customer_state = ($checked['customer_state'] == $salesMaster->customer_state) ? 0 : 1;
                    $check_m1_date = ($checked['m1_date'] == $salesMaster->m1_date) ? 0 : 1;
                    $check_m2_date = ($checked['m2_date'] == $salesMaster->m2_date) ? 0 : 1;
                    // $check = ($check_kw+$check_net_epc+$check_date_cancelled+$check_return_sales_date+$check_customer_state+$check_m1_date+$check_m2_date);
                    $check = ($check_kw + $check_net_epc + $check_date_cancelled + $check_customer_state + $check_m1_date + $check_m2_date);
                }
                $val = [
                    'pid' => $checked->pid,
                    'weekly_sheet_id' => null, // $checked->weekly_sheet_id,//!empty($salesMaster->weekly_sheet_id)?$salesMaster->weekly_sheet_id:
                    'install_partner' => check_null_and_matching_data($salesMaster, 'install_partner', $checked, 'install_partner'), // $checked->install_partner, //!empty($salesMaster->install_partner)?$salesMaster->install_partner:
                    'install_partner_id' => check_null_and_matching_data($salesMaster, 'install_partner_id', $checked, 'install_partner_id'), // $checked->install_partner_id, //!empty($salesMaster->install_partner_id)?$salesMaster->install_partner_id:
                    'customer_name' => check_null_and_matching_data($salesMaster, 'customer_name', $checked, 'customer_name'), // $checked->customer_name, //!empty($salesMaster->customer_name)?$salesMaster->customer_name:
                    'customer_address' => check_null_and_matching_data($salesMaster, 'customer_address', $checked, 'customer_address'), // $checked->customer_address, //!empty($salesMaster->customer_address)?$salesMaster->customer_address:
                    'customer_address_2' => check_null_and_matching_data($salesMaster, 'customer_address_2', $checked, 'customer_address_2'), // $checked->customer_address_2, //!empty($salesMaster->customer_address_2)?$salesMaster->customer_address_2:
                    'customer_city' => check_null_and_matching_data($salesMaster, 'customer_city', $checked, 'customer_city'), // $checked->customer_city, //!empty($salesMaster->customer_city)?$salesMaster->customer_city:
                    'customer_state' => check_null_and_matching_data($salesMaster, 'customer_state', $checked, 'customer_state'), // $checked->customer_state, //!empty($salesMaster->customer_state)?$salesMaster->customer_state:
                    'customer_zip' => check_null_and_matching_data($salesMaster, 'customer_zip', $checked, 'customer_zip'), // $checked->customer_zip, //!empty($salesMaster->customer_zip)?$salesMaster->customer_zip:
                    'customer_email' => check_null_and_matching_data($salesMaster, 'customer_email', $checked, 'customer_email'), // $checked->customer_email, //!empty($salesMaster->customer_email)?$salesMaster->customer_email:
                    'customer_phone' => check_null_and_matching_data($salesMaster, 'customer_phone', $checked, 'customer_phone'), // $checked->customer_phone, //!empty($salesMaster->customer_phone)?$salesMaster->customer_phone:
                    'homeowner_id' => check_null_and_matching_data($salesMaster, 'homeowner_id', $checked, 'homeowner_id'), // $checked->homeowner_id, //!empty($salesMaster->homeowner_id)?$salesMaster->homeowner_id:
                    'proposal_id' => check_null_and_matching_data($salesMaster, 'proposal_id', $checked, 'proposal_id'), // $checked->proposal_id, //!empty($salesMaster->proposal_id)?$salesMaster->proposal_id:
                    'sales_rep_name' => check_null_and_matching_data($salesMaster, 'sales_rep_name', $checked, 'sales_rep_name'), // $checked->sales_rep_name, //!empty($salesMaster->sales_rep_name)?$salesMaster->sales_rep_name:
                    'employee_id' => check_null_and_matching_data($salesMaster, 'employee_id', $checked, 'employee_id'), // $checked->employee_id, //!empty($salesMaster->employee_id)?$salesMaster->employee_id:
                    'sales_rep_email' => check_null_and_matching_data($salesMaster, 'sales_rep_email', $checked, 'sales_rep_email'), // $checked->sales_rep_email, //!empty($salesMaster->sales_rep_email)?$salesMaster->sales_rep_email:
                    'kw' => check_null_and_matching_data($salesMaster, 'kw', $checked, 'kw'), // $checked->kw, //!empty($salesMaster->kw)?$salesMaster->kw:
                    'date_cancelled' => $checked->date_cancelled, // check_null_and_matching_data($salesMaster,'date_cancelled',$checked,'date_cancelled'),// $checked->date_cancelled, //!empty($salesMaster->date_cancelled)?$salesMaster->date_cancelled:
                    'customer_signoff' => check_null_and_matching_data($salesMaster, 'customer_signoff', $checked, 'customer_signoff'), // !empty($salesMaster->customer_signoff)?$salesMaster->customer_signoff:$checked->customer_signoff,
                    'm1_date' => $checked->m1_date, // check_null_and_matching_data($salesMaster,'m1_date',$checked,'m1_date'),// $checked->m1_date, //!empty($salesMaster->m1_date)?$salesMaster->m1_date:
                    'm2_date' => $checked->m2_date, // check_null_and_matching_data($salesMaster,'m2_date',$checked,'m2_date'),// $checked->m2_date, //!empty($salesMaster->m2_date)?$salesMaster->m2_date:
                    'product' => check_null_and_matching_data($salesMaster, 'product', $checked, 'product'), // $checked->product, //!empty($salesMaster->product)?$salesMaster->product:
                    'epc' => check_null_and_matching_data($salesMaster, 'epc', $checked, 'epc'), // $checked->epc, //!empty($salesMaster->epc)?$salesMaster->epc:
                    'net_epc' => check_null_and_matching_data($salesMaster, 'net_epc', $checked, 'net_epc'), // $checked->net_epc, //!empty($salesMaster->net_epc)?$salesMaster->net_epc:
                    'gross_account_value' => check_null_and_matching_data($salesMaster, 'gross_account_value', $checked, 'gross_account_value'), // $checked->gross_account_value, //!empty($salesMaster->gross_account_value)?$salesMaster->gross_account_value:
                    'dealer_fee_percentage' => check_null_and_matching_data($salesMaster, 'dealer_fee_percentage', $checked, 'dealer_fee_percentage'), // $checked->dealer_fee_percentage, //!empty($salesMaster->dealer_fee_percentage)?$salesMaster->dealer_fee_percentage:
                    'adders' => check_null_and_matching_data($salesMaster, 'adders', $checked, 'adders'), // $checked->adders, //!empty($salesMaster->adders)?$salesMaster->adders:
                    'adders_description' => check_null_and_matching_data($salesMaster, 'adders_description', $checked, 'adders_description'), // $checked->adders_description, //!empty($salesMaster->adders_description)?$salesMaster->adders_description:
                    'funding_source' => check_null_and_matching_data($salesMaster, 'funding_source', $checked, 'funding_source'), // $checked->funding_source, //!empty($salesMaster->funding_source)?$salesMaster->funding_source:
                    'financing_rate' => check_null_and_matching_data($salesMaster, 'financing_rate', $checked, 'financing_rate'), // $checked->financing_rate, //!empty($salesMaster->financing_rate)?$salesMaster->financing_rate:
                    'financing_term' => check_null_and_matching_data($salesMaster, 'financing_term', $checked, 'financing_term'), // $checked->financing_term, //!empty($salesMaster->financing_term)?$salesMaster->financing_term:
                    'scheduled_install' => check_null_and_matching_data($salesMaster, 'scheduled_install', $checked, 'scheduled_install'), // $checked->scheduled_install, //!empty($salesMaster->scheduled_install)?$salesMaster->scheduled_install:
                    'install_complete_date' => check_null_and_matching_data($salesMaster, 'install_complete_date', $checked, 'install_complete_date'), // $checked->install_complete_date, //!empty($salesMaster->install_complete_date)?$salesMaster->install_complete_date:
                    // 'return_sales_date' =>  $checked->return_sales_date,
                    'return_sales_date' => null,
                    // 'return_sales_date' => check_null_and_matching_data($salesMaster,'return_sales_date',$checked,'return_sales_date'),// $checked->return_sales_date, //!empty($salesMaster->return_sales_date)?$salesMaster->return_sales_date:
                    // 'return_sales_date' => $checked->return_sales_date,// $checked->return_sales_date, //!empty($salesMaster->return_sales_date)?$salesMaster->return_sales_date:
                    'cash_amount' => check_null_and_matching_data($salesMaster, 'cash_amount', $checked, 'cash_amount'), // $checked->cash_amount, //!empty($salesMaster->cash_amount)?$salesMaster->cash_amount:
                    'loan_amount' => check_null_and_matching_data($salesMaster, 'loan_amount', $checked, 'loan_amount'), // $checked->loan_amount, //!empty($salesMaster->loan_amount)?$salesMaster->loan_amount:
                    // 'dealer_fee_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->dealer_fee_amount)?$salesMaster->dealer_fee_amount:$checked->dealer_fee_dollar,
                    'redline' => check_null_and_matching_data($salesMaster, 'redline', $checked, 'redline'), // $checked->redline, //!empty($salesMaster->redline)?$salesMaster->redline:
                    // 'total_amount_for_acct' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->total_amount_for_acct)?$salesMaster->total_amount_for_acct:$checked->total_for_acct,
                    // 'prev_amount_paid' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->prev_amount_paid)?$salesMaster->prev_amount_paid:$checked->prev_paid,
                    // 'last_date_pd' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->last_date_pd)?$salesMaster->last_date_pd:$checked->last_date_pd,
                    // 'm1_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->m1_amount)?$salesMaster->m1_amount:$checked->m1_this_week,
                    // 'm2_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->m2_amount)?$salesMaster->m2_amount:$checked->m2_this_week,
                    // 'prev_deducted_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->prev_deducted_amount)?$salesMaster->prev_deducted_amount:$checked->prev_deducted,
                    'cancel_fee' => check_null_and_matching_data($salesMaster, 'cancel_fee', $checked, 'cancel_fee'), // $checked->cancel_fee, //!empty($salesMaster->cancel_fee)?$salesMaster->cancel_fee:
                    // 'cancel_deduction' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->cancel_deduction)?$salesMaster->cancel_deduction:$checked->cancel_deduction,
                    // 'lead_cost_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->lead_cost_amount)?$salesMaster->lead_cost_amount:$checked->lead_cost,
                    // 'adv_pay_back_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->adv_pay_back_amount)?$salesMaster->adv_pay_back_amount:$checked->adv_pay_back_amount,
                    // 'total_amount_in_period' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->total_amount_in_period)?$salesMaster->total_amount_in_period:$checked->total_in_period
                    'data_source_type' => $checked->data_source_type,
                    'job_status' => $checked->job_status,
                ];

                if (empty($val['dealer_fee_percentage'])) {
                    $val['dealer_fee_percentage'] = 0;
                }

                if (empty($salesMaster)) {
                    if (! empty($checked->pid) && ! empty($checked->customer_signoff) && ! empty($checked->epc) && ! empty($checked->net_epc) && ! empty($checked->customer_name) && ! empty($checked->kw) && ! empty($checked->customer_state) && ! empty($checked->sales_rep_name) && ! empty($checked->sales_rep_email) && (! empty($checked->userDetail) || ! empty($checked->userAdditionalEmail))) {
                        // && !empty($checked->setter_id)
                        // && !empty($checked->dealer_fee_percentage)
                        $insertData = '';
                        // $val['data_source_type'] = 'api';
                        $insertData = SalesMaster::create($val);
                        // Added by Gorakah
                        $user_detail = User::where('email', $checked->sales_rep_email)->first();
                        $usersAdditionalEmailDetail = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->first();
                        if ($user_detail) {
                            $closer1_id = $user_detail->id;
                        } elseif ($usersAdditionalEmailDetail) {
                            $closer1_id = $usersAdditionalEmailDetail->user_id;
                        } else {
                            $closer1_id = null;
                        }
                        // End by Gorakah

                        $data = [
                            'sale_master_id' => $insertData->id,
                            'weekly_sheet_id' => $insertData->weekly_sheet_id,
                            'pid' => $checked->pid,
                            // 'closer1_id' => isset($checked->userDetail->id) ? $checked->userDetail->id : null,
                            'closer1_id' => $closer1_id,
                            'job_status' => $checked->job_status,
                        ];
                        SaleMasterProcess::create($data);
                        (new ApiMissingDataController)->subroutine_process_api_excel($checked->pid);
                    }
                } else {
                    // Added by Gorakh
                    $user_detail = User::where('email', $checked->sales_rep_email)->first();
                    $usersAdditionalEmailDetail = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->first();
                    if ($user_detail) {
                        $closer1_id = $user_detail->id;
                    } elseif ($usersAdditionalEmailDetail) {
                        $closer1_id = $usersAdditionalEmailDetail->user_id;
                    } else {
                        $closer1_id = null;
                    }

                    $data = [
                        'closer1_id' => $closer1_id,
                        'job_status' => $checked->job_status,
                    ];

                    $saleMasterProcessCheck = SaleMasterProcess::where('pid', $checked->pid)->first();
                    salesDataChangesClawback($saleMasterProcessCheck->pid);
                    if (empty($saleMasterProcessCheck->closer1_id)) {

                        SaleMasterProcess::where('pid', $checked->pid)->update($data);
                    }
                    // end by Gorakh

                    if (! empty($salesMaster->m1_date) && ! empty($checked->m1_date) && ($salesMaster->m1_date != $checked->m1_date)) {
                        m1datePayrollData($checked->pid, $checked->m1_date);
                    }
                    if (! empty($salesMaster->m2_date) && ! empty($checked->m2_date) && ($salesMaster->m2_date != $checked->m2_date)) {
                        m2datePayrollData($checked->pid, $checked->m2_date);
                    }

                    if (! empty($salesMaster->m1_date) && empty($checked->m1_date)) {
                        m1dateSalesData($checked->pid);
                    }
                    if (! empty($salesMaster->m2_date) && empty($checked->m2_date)) {
                        m2dateSalesData($checked->pid, $salesMaster->m2_date);
                    }
                    if (! empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                        // When Clawback Is Paid, Sale Should Act As It's New Therefore
                        salesDataChangesBasedOnClawback($salesMaster->pid);
                    }

                    $updateData = SalesMaster::where('pid', $checked->pid)->update($val);
                    if ($check > 0) {
                        (new ApiMissingDataController)->subroutine_process_api_excel($checked->pid);
                    }
                    // (new ApiMissingDataController())->subroutine_process_api_excel($checked->pid);
                }
                $val = [];
            }

            /* Send event to pusher */
            $pusherMsg = 'Legacy Sales imported successfully';
            $pusherEvent = 'legacy-sale-import';
            $domainName = config('app.domain_name');
            $dataForPusherEvent = '';
            event(new sendEventToPusher($domainName, $pusherEvent, $pusherMsg, $dataForPusherEvent));
            /* Send event to pusher */

            DB::commit();
        } catch (\Exception $e) {
            Log::info($e);
            // DB::rollBack();
        }
    }
}

if (! function_exists('m1datePayrollData')) {
    function m1datePayrollData($pid, $m1_date_new)
    {
        $commissions = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1'])->where('status', 1)->get();
        if (count($commissions) > 0) {
            foreach ($commissions as $key => $commission) {
                $positionFrequency = PositionPayFrequency::where('position_id', $commission->position_id)->first();
                $count = 0;
                if (@$positionFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                    $count = WeeklyPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0])->count();
                } elseif (@$positionFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                    $count = MonthlyPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0])->count();
                } elseif (@$positionFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                    $count = AdditionalPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0, 'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE])->count();
                } elseif (@$positionFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                    $count = AdditionalPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0, 'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE])->count();
                }
                if ($count > 0) {
                    UserCommission::where(['user_id' => $commission->user_id, 'pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to])->update(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'payroll_id' => 0]);
                    UserOverrides::where(['sale_user_id' => $commission->user_id, 'pid' => $pid, 'pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to])->update(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'payroll_id' => 0]);
                    Payroll::where(['user_id' => $commission->user_id, 'pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to])->update(['is_mark_paid' => 0, 'is_next_payroll' => 0]);
                }
            }
        }
    }
}

if (! function_exists('m2datePayrollData')) {
    function m2datePayrollData($pid, $m2_date_new)
    {
        $commissions = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2'])->where('status', 1)->get();
        if (count($commissions) > 0) {
            foreach ($commissions as $commission) {
                $positionFrequency = PositionPayFrequency::where('position_id', $commission->position_id)->first();
                $count = 0;
                if (@$positionFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                    $count = WeeklyPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0])->count();
                } elseif (@$positionFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                    $count = MonthlyPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0])->count();
                } elseif (@$positionFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                    $count = AdditionalPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0, 'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE])->count();
                } elseif (@$positionFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                    $count = AdditionalPayFrequency::where(['pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to, 'closed_status' => 0, 'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE])->count();
                }
                if ($count > 0) {
                    UserCommission::where(['user_id' => $commission->user_id, 'pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to])->update(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'payroll_id' => 0]);
                    UserOverrides::where(['sale_user_id' => $commission->user_id, 'pid' => $pid, 'pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to])->update(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'payroll_id' => 0]);
                    Payroll::where(['user_id' => $commission->user_id, 'pay_period_from' => $commission->pay_period_from, 'pay_period_to' => $commission->pay_period_to])->update(['is_mark_paid' => 0, 'is_next_payroll' => 0]);
                }
            }
        }
    }
}

if (! function_exists('m1dateSalesData')) {
    function m1dateSalesData($pid)
    {
        $m1comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 1])->first();
        if ($m1comm) {
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                $saleMasters->closer1_m1 = 0;
                $saleMasters->closer2_m1 = 0;
                $saleMasters->setter1_m1 = 0;
                $saleMasters->setter2_m1 = 0;
                $saleMasters->closer1_m1_paid_status = null;
                $saleMasters->closer2_m1_paid_status = null;
                $saleMasters->setter1_m1_paid_status = null;
                $saleMasters->setter2_m1_paid_status = null;
                $saleMasters->save();
            }

            $cdelete = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 1])->delete();
        }

    }
}

if (! function_exists('m2dateSalesData')) {
    function m2dateSalesData($pid, $m2date)
    {
        $m2comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 1])->first();
        if ($m2comm) {
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                $saleMasters->closer1_m2 = 0;
                $saleMasters->closer2_m2 = 0;
                $saleMasters->setter1_m2 = 0;
                $saleMasters->setter2_m2 = 0;
                $saleMasters->closer1_m2_paid_status = null;
                $saleMasters->closer2_m2_paid_status = null;
                $saleMasters->setter1_m2_paid_status = null;
                $saleMasters->setter2_m2_paid_status = null;
                $saleMasters->closer1_commission = 0;
                $saleMasters->closer2_commission = 0;
                $saleMasters->setter1_commission = 0;
                $saleMasters->setter2_commission = 0;
                $saleMasters->mark_account_status_id = null;
                $saleMasters->save();
            }

            $cdelete = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 1])->delete();
            $odelete = UserOverrides::where(['pid' => $pid, 'status' => 1])->delete();
            $wdelete = UserReconciliationWithholding::where(['pid' => $pid, 'status' => 'unpaid'])->delete();
        }

    }
}

if (! function_exists('check_null_and_matching_data')) {
    function check_null_and_matching_data($old, $old_key, $new, $new_key)
    {
        // $old->$old_key;
        if (empty($old->$old_key)) {
            if (empty($new->$new_key)) {
                return null;
            } else {
                if ($new_key == 'm1_date' || $new_key == 'm2_date' || $new_key == 'date_cancelled') {
                    return get_date_only($new->$new_key);
                } else {
                    return $new->$new_key;
                }
            }
        } else {
            if (empty($new->$new_key)) {
                if ($old_key == 'm1_date' || $old_key == 'm2_date' || $old_key == 'date_cancelled') {
                    return get_date_only($old->$old_key);
                } else {
                    return $old->$old_key;
                }
            } else {
                if ($new_key == 'm1_date' || $new_key == 'm2_date' || $new_key == 'date_cancelled') {
                    $old_dt = get_date_only($old->$old_key);

                    // $new_dt = get_date_only($new->$new_key);
                    return $old_dt;
                } else {
                    return $new->$new_key;
                }
            }
        }

    }
}

if (! function_exists('get_date_only')) {
    function get_date_only($date)
    {
        $d = explode('T', $date);

        return isset($d[0]) ? $d[0] : null;
    }
}

if (! function_exists('create_raw_data_history_api')) {
    function create_raw_data_history_api($val)
    {
        $new_pid_null = '';
        $updated_pid_null = '';

        $netEPC = $val->net_epc;

        $data = [
            'legacy_id' => $val->id,
            'pid' => $val->prospect_id,
            'homeowner_id' => $val->homeowner_id,
            'proposal_id' => $val->proposal_id,
            'customer_name' => $val->customer_name,
            'customer_address' => $val->customer_address,
            'customer_address_2' => $val->customer_address_2,
            'customer_city' => $val->customer_city,
            'customer_state' => $val->customer_state,
            'location_code' => $val->customer_state,
            'customer_zip' => $val->customer_zip,
            'customer_email' => $val->customer_email,
            'customer_phone' => $val->customer_phone,
            //    'setter_id'  => null, //$val->setter_id,
            'sales_rep_name' => $val->rep_name,
            'sales_rep_email' => $val->rep_email,
            'employee_id' => $val->employee_id,
            'install_partner' => $val->install_partner,
            'install_partner_id' => $val->install_partner_id,
            'customer_signoff' => $val->customer_signoff,
            'm1_date' => $val->m1,
            'm2_date' => $val->m2,
            'scheduled_install' => $val->scheduled_install,
            'install_complete_date' => $val->install_complete,
            'date_cancelled' => $val->date_cancelled,
            // 'return_sales_date' => $val->return_sales_date,
            'return_sales_date' => null,
            'gross_account_value' => $val->gross_account_value,
            'cash_amount' => $val->cash_amount,
            'loan_amount' => $val->loan_amount,
            'kw' => $val->kw,
            'dealer_fee_percentage' => (! empty($val->dealer_fee_percentage)) ? $val->dealer_fee_percentage : 0,
            'adders' => $val->adders,
            'cancel_fee' => $val->cancel_fee,
            'adders_description' => $val->adders_description,
            'funding_source' => $val->funding_source,
            'financing_rate' => $val->financing_rate,
            'financing_term' => $val->financing_term,
            'product' => $val->product,
            'epc' => $val->epc,
            'net_epc' => $netEPC, // $val->net_epc,
            'data_source_type' => 'api',
            'source_created_at' => $val->created,
            'source_updated_at' => $val->modified,
            'job_status' => isset($val->job_status) ? $val->job_status : null,
        ];
        $history = LegacyApiRawDataHistory::where('pid', $val->prospect_id)->orderBy('id', 'DESC')->first();
        if (empty($history)) {
            $create_raw_data_history = LegacyApiRawDataHistory::create($data);
            $new_pid_null = $val->prospect_id;
        } else {
            $history_data = LegacyApiRawDataHistory::where('pid', $history->pid)->where($data)->first();
            if (empty($history_data)) {
                $data['created_at'] = $history->created_at;
                $create_raw_data_history = LegacyApiRawDataHistory::create($data);
                $updated_pid_null = $val->prospect_id;
            }
        }

        return ['new_pid_null' => $new_pid_null, 'updated_pid_null' => $updated_pid_null];
    }
}

if (! function_exists('create_raw_data_history_excel')) {
    function create_raw_data_history_excel($val)
    {
        $data = [
            'legacy_id' => $val->legacy_data_id,
            'pid' => isset($val->prospect_id) ? $val->prospect_id : null,
            'weekly_sheet_id' => null,
            'homeowner_id' => isset($val->homeowner_id) ? $val->homeowner_id : null,
            'proposal_id' => isset($val->proposal_id) ? $val->proposal_id : null,
            'customer_name' => isset($val->customer_name) ? $val->customer_name : null,
            'customer_address' => isset($val->customer_address) ? $val->customer_address : null,
            'customer_address_2' => isset($val->customer_address_2) ? $val->customer_address_2 : null,
            'customer_city' => isset($val->customer_city) ? $val->customer_city : null,
            'customer_state' => isset($val->customer_state) ? $val->customer_state : null,
            'customer_zip' => isset($val->customer_zip) ? $val->customer_zip : null,
            'customer_email' => isset($val->customer_email) ? $val->customer_email : null,
            'customer_phone' => isset($val->customer_phone) ? $val->customer_phone : null,
            'setter_id' => isset($val->setter_id) ? $val->setter_id : null,
            'employee_id' => isset($val->employee_id) ? $val->employee_id : null,
            'sales_rep_name' => isset($val->rep_name) ? $val->rep_name : null,
            'sales_rep_email' => isset($val->rep_email) ? $val->rep_email : null,
            'install_partner' => isset($val->install_partner) ? $val->install_partner : null,
            'install_partner_id' => isset($val->install_partner_id) ? $val->install_partner_id : null,
            'customer_signoff' => isset($val->customer_signoff) ? $val->customer_signoff : null,
            'm1_date' => isset($val->m1) ? $val->m1 : null,
            'm2_date' => isset($val->m2) ? $val->m2 : null,
            'scheduled_install' => isset($val->scheduled_install) ? $val->scheduled_install : null,
            'install_complete_date' => isset($val->install_complete) ? $val->install_complete : null,
            'date_cancelled' => isset($val->date_cancelled) ? $val->date_cancelled : null,
            // 'return_sales_date' => isset($val->return_sales_date)?$val->return_sales_date:NULL,
            'return_sales_date' => null,
            'gross_account_value' => isset($val->gross_account_value) ? $val->gross_account_value : null,
            'cash_amount' => isset($val->cash_amount) ? $val->cash_amount : null,
            'loan_amount' => isset($val->loan_amount) ? $val->loan_amount : null,
            'kw' => isset($val->kw) ? $val->kw : null,
            'dealer_fee_percentage' => isset($val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null,
            'dealer_fee_amount' => isset($val->dealer_fee_amount) ? $val->dealer_fee_amount : null,
            'adders' => isset($val->adders) ? $val->adders : null,
            'cancel_fee' => isset($val->cancel_fee) ? $val->cancel_fee : null,
            'adders_description' => isset($val->adders_description) ? $val->adders_description : null,
            'redline' => isset($val->redline) ? $val->redline : null,
            'total_amount_for_acct' => isset($val->total_amount_for_acct) ? $val->total_amount_for_acct : null,
            'prev_amount_paid' => isset($val->prev_amount_paid) ? $val->prev_amount_paid : null,
            'last_date_pd' => isset($val->last_date_pd) ? $val->last_date_pd : null,
            'm1_amount' => isset($val->m1_amount) ? $val->m1_amount : null,
            'm2_amount' => isset($val->m2_amount) ? $val->m2_amount : null,
            'prev_deducted_amount' => isset($val->prev_deducted_amount) ? $val->prev_deducted_amount : null,
            'cancel_deduction' => isset($val->cancel_deduction) ? $val->cancel_deduction : null,
            'lead_cost_amount' => isset($val->lead_cost_amount) ? $val->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($val->adv_pay_back_amount) ? $val->adv_pay_back_amount : null,
            'total_amount_in_period' => isset($val->total_amount_in_period) ? $val->total_amount_in_period : null,
            'funding_source' => isset($val->funding_source) ? $val->funding_source : null,
            'financing_rate' => isset($val->financing_rate) ? $val->financing_rate : null,
            'financing_term' => isset($val->financing_term) ? $val->financing_term : null,
            'product' => isset($val->product) ? $val->product : null,
            'epc' => isset($val->epc) ? $val->epc : null,
            'net_epc' => isset($val->net_epc) ? $val->net_epc : null,
            'data_source_type' => isset($val->data_source_type) ? $val->data_source_type : null,
        ];
        $history = LegacyApiRawDataHistory::where('pid', $val->prospect_id)->orderBy('id', 'DESC')->first();
        if (empty($history)) {
            $create_raw_data_history = LegacyApiRawDataHistory::create($data);
        } else {
            $history_data = LegacyApiRawDataHistory::where('pid', $history->pid)->where($data)->first();
            if (empty($history_data)) {
                $data['created_at'] = $history->created_at;
                $create_raw_data_history = LegacyApiRawDataHistory::create($data);
            }
        }
    }
}

if (! function_exists('update_rawdata_and_salemaster')) {
    function update_rawdata_and_salemaster($val)
    {
        if (! empty($val->m1)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('m1_date')->update(['m1_date' => $val->m1]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('m1_date')->update(['m1_date' => $val->m1]);
        }
        if (! empty($val->m2)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('m2_date')->update(['m2_date' => $val->m2]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('m2_date')->update(['m2_date' => $val->m2]);
        }
        if (! empty($val->date_cancelled)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('date_cancelled')->update(['date_cancelled' => $val->date_cancelled]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('date_cancelled')->update(['date_cancelled' => $val->date_cancelled]);
        }

        if (! empty($val->homeowner_id)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('homeowner_id')->update(['homeowner_id' => $val->homeowner_id]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('homeowner_id')->update(['homeowner_id' => $val->homeowner_id]);
        }
        if (! empty($val->proposal_id)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('proposal_id')->update(['proposal_id' => $val->proposal_id]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('proposal_id')->update(['proposal_id' => $val->proposal_id]);
        }
        if (! empty($val->customer_name)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_name')->update(['customer_name' => $val->customer_name]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_name')->update(['customer_name' => $val->customer_name]);
        }
        if (! empty($val->customer_address)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_address')->update(['customer_address' => $val->customer_address]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_address')->update(['customer_address' => $val->customer_address]);
        }
        if (! empty($val->customer_address_2)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_address_2')->update(['customer_address_2' => $val->customer_address_2]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_address_2')->update(['customer_address_2' => $val->customer_address_2]);
        }
        if (! empty($val->customer_city)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_city')->update(['customer_city' => $val->customer_city]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_city')->update(['customer_city' => $val->customer_city]);
        }
        if (! empty($val->customer_state)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_state')->update(['customer_state' => $val->customer_state]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_state')->update(['customer_state' => $val->customer_state]);
        }
        if (! empty($val->customer_zip)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_zip')->update(['customer_zip' => $val->customer_zip]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_zip')->update(['customer_zip' => $val->customer_zip]);
        }
        if (! empty($val->customer_email)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_email')->update(['customer_email' => $val->customer_email]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_email')->update(['customer_email' => $val->customer_email]);
        }
        if (! empty($val->customer_phone)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_phone')->update(['customer_phone' => $val->customer_phone]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_phone')->update(['customer_phone' => $val->customer_phone]);
        }
        // if(!empty($val->setter_id)){
        //     $raw_update = LegacyApiRowData::where('pid',$val->prospect_id)->whereNull('setter_id')->update(['setter_id'=>$val->setter_id]);
        //     // $sale_process_update = SaleMasterProcess::where('pid',$val->prospect_id)->whereNull('setter1_id')->update(['setter1_id'=>$val->setter_id]);
        // }
        if (! empty($val->rep_name)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('sales_rep_name')->update(['sales_rep_name' => $val->rep_name]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('sales_rep_name')->update(['sales_rep_name' => $val->rep_name]);
        }
        if (! empty($val->rep_email)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('sales_rep_email')->update(['sales_rep_email' => $val->rep_email]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('sales_rep_email')->update(['sales_rep_email' => $val->rep_email]);
            // $closer_id = User::where('email',$val->sales_rep_email'])->first();
            // if(!empty($closer_id)){
            //     $sale_process_update = SaleMasterProcess::where('pid',$val->prospect_id)->whereNull('closer1_id')->update(['closer1_id'=>$closer_id->id]);
            // }
        }
        if (! empty($val->employee_id)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('employee_id')->update(['employee_id' => $val->employee_id]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('employee_id')->update(['employee_id' => $val->employee_id]);
        }
        if (! empty($val->install_partner)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('install_partner')->update(['install_partner' => $val->install_partner]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('install_partner')->update(['install_partner' => $val->install_partner]);
        }
        if (! empty($val->install_partner_id)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('install_partner_id')->update(['install_partner_id' => $val->install_partner_id]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('install_partner_id')->update(['install_partner_id' => $val->install_partner_id]);
        }
        if (! empty($val->customer_signoff)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('customer_signoff')->update(['customer_signoff' => $val->customer_signoff]); //
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('customer_signoff')->update(['customer_signoff' => $val->customer_signoff]); //
        }
        if (! empty($val->scheduled_install)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('scheduled_install')->update(['scheduled_install' => $val->scheduled_install]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('scheduled_install')->update(['scheduled_install' => $val->scheduled_install]);
        }
        if (! empty($val->install_complete)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('install_complete_date')->update(['install_complete_date' => $val->install_complete]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('install_complete_date')->update(['install_complete_date' => $val->install_complete]);
        }
        if (! empty($val->return_sales_date)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('return_sales_date')->update(['return_sales_date' => $val->return_sales_date]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('return_sales_date')->update(['return_sales_date' => $val->return_sales_date]);
        }
        if (! empty($val->gross_account_value)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('gross_account_value')->update(['gross_account_value' => $val->gross_account_value]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('gross_account_value')->update(['gross_account_value' => $val->gross_account_value]);
        }
        if (! empty($val->cash_amount)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('cash_amount')->update(['cash_amount' => $val->cash_amount]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('cash_amount')->update(['cash_amount' => $val->cash_amount]);
        }
        if (! empty($val->loan_amount)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('loan_amount')->update(['loan_amount' => $val->loan_amount]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('loan_amount')->update(['loan_amount' => $val->loan_amount]);
        }
        if (! empty($val->kw)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('kw')->update(['kw' => $val->kw]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('kw')->update(['kw' => $val->kw]);
        }
        if (! empty($val->dealer_fee_percentage)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('dealer_fee_percentage')->update(['dealer_fee_percentage' => $val->dealer_fee_percentage]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('dealer_fee_percentage')->update(['dealer_fee_percentage' => $val->dealer_fee_percentage]);
        }
        if (! empty($val->adders)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('adders')->update(['adders' => $val->adders]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('adders')->update(['adders' => $val->adders]);
        }
        if (! empty($val->cancel_fee)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('cancel_fee')->update(['cancel_fee' => $val->cancel_fee]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('cancel_fee')->update(['cancel_fee' => $val->cancel_fee]);
        }
        if (! empty($val->adders_description)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('adders_description')->update(['adders_description' => $val->adders_description]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('adders_description')->update(['adders_description' => $val->adders_description]);
        }
        if (! empty($val->funding_source)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('funding_source')->update(['funding_source' => $val->funding_source]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('funding_source')->update(['funding_source' => $val->funding_source]);
        }
        if (! empty($val->financing_rate)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('financing_rate')->update(['financing_rate' => $val->financing_rate]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('financing_rate')->update(['financing_rate' => $val->financing_rate]);
        }
        if (! empty($val->financing_term)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('financing_term')->update(['financing_term' => $val->financing_term]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('financing_term')->update(['financing_term' => $val->financing_term]);
        }
        if (! empty($val->product)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('product')->update(['product' => $val->product]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('product')->update(['product' => $val->product]);
        }
        if (! empty($val->epc)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('epc')->update(['epc' => $val->epc]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('epc')->update(['epc' => $val->epc]);
        }
        if (! empty($val->net_epc)) {
            $raw_update = LegacyApiRowData::where('pid', $val->prospect_id)->whereNull('net_epc')->update(['net_epc' => $val->net_epc]);
            $sale_update = SalesMaster::where('pid', $val->prospect_id)->whereNull('net_epc')->update(['net_epc' => $val->net_epc]);
        }
    }
}

// if(!function_exists('insert_update_legacy_raw_data')){
//     function insert_update_legacy_raw_data($val){
//         $status = '';
//         $response = [];
//         $updated_pid_raw = '';
//         $new_pid_raw = '';
//         $updated_pid_null = '';
//         $new_pid_null = '';

//         $updated = '';
//                 $inserted = '';
//                 $checkPid = LegacyApiRowData::where('pid',$val->prospect_id)->first();
//                 $data['legacy_data_id'] = check_null_and_matching_data($checkPid,'legacy_data_id',$val,'id');
//                 // $data['weekly_sheet_id'] = isset($lid['weekid']) ? $lid['weekid'] : null;
//                 // $data['page'] = isset($lid['pageid']) ? $lid['pageid'] : null;
//                 $data['pid'] = check_null_and_matching_data($checkPid,'pid',$val,'prospect_id'); //isset($val->prospect_id) ? $val->prospect_id : null;
//                 $data['homeowner_id'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//isset($val->homeowner_id) ? $val->homeowner_id : null;
//                 $data['proposal_id'] = check_null_and_matching_data($checkPid,'proposal_id',$val,'proposal_id');//isset($val->proposal_id) ? $val->proposal_id : null;
//                 $data['customer_name'] = check_null_and_matching_data($checkPid,'customer_name',$val,'customer_name');//isset($val->customer_name) ? $val->customer_name : null;
//                 $data['customer_address'] = check_null_and_matching_data($checkPid,'customer_address',$val,'customer_address');//isset($val->customer_address) ? $val->customer_address : null;
//                 $data['customer_address_2'] = check_null_and_matching_data($checkPid,'customer_address_2',$val,'customer_address_2');//isset($val->customer_address_2) ? $val->customer_address_2 : null;
//                 $data['customer_city'] = check_null_and_matching_data($checkPid,'customer_city',$val,'customer_city');//isset($val->customer_city) ? $val->customer_city : null;
//                 $data['customer_state'] = check_null_and_matching_data($checkPid,'customer_state',$val,'customer_state');//isset($val->customer_state) ? $val->customer_state : null;
//                 $data['customer_zip'] = check_null_and_matching_data($checkPid,'customer_zip',$val,'customer_zip');//isset($val->customer_zip) ? $val->customer_zip : null;
//                 $data['customer_email'] = check_null_and_matching_data($checkPid,'customer_email',$val,'customer_email');//$val->customer_email) ? $val->customer_email : null;
//                 $data['customer_phone'] = check_null_and_matching_data($checkPid,'customer_phone',$val,'customer_phone');//$val->customer_phone) ? $val->customer_phone : null;
//                 $data['setter_id'] = check_null_and_matching_data($checkPid,'setter_id',$val,'setter_id');//$val->setter_id) ? $val->setter_id : null;
//                 $data['employee_id'] = check_null_and_matching_data($checkPid,'employee_id',$val,'employee_id');//$val->employee_id) ? $val->employee_id : null;
//                 $data['sales_rep_name'] = check_null_and_matching_data($checkPid,'sales_rep_name',$val,'rep_name');//$val->rep_name) ? $val->rep_name : null;
//                 $data['sales_rep_email'] = check_null_and_matching_data($checkPid,'sales_rep_email',$val,'rep_email');//$val->rep_email) ? $val->rep_email : null;
//                 $data['install_partner'] = check_null_and_matching_data($checkPid,'install_partner',$val,'install_partner');//$val->install_partner) ? $val->install_partner : null;
//                 $data['install_partner_id'] = check_null_and_matching_data($checkPid,'install_partner_id',$val,'install_partner_id');//$val->install_partner_id) ? $val->install_partner_id : null;
//                 // $data['customer_signoff'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
//                 $data['customer_signoff'] = check_null_and_matching_data($checkPid,'customer_signoff',$val,'customer_signoff');//$val->customer_signoff) && $val->customer_signoff != null ? $val->customer_signoff : null;
//                 //$data['m1_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
//                 $data['m1_date'] = check_null_and_matching_data($checkPid,'m1_date',$val,'m1');//$val->m1) ? $val->m1 : null;
//                 //$data['scheduled_install'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
//                 $data['scheduled_install'] = check_null_and_matching_data($checkPid,'scheduled_install',$val,'scheduled_install');//$val->scheduled_install) ? $val->scheduled_install : null;
//                 $data['install_complete_date'] = check_null_and_matching_data($checkPid,'install_complete_date',$val,'install_complete');//$val->install_complete)?$val->install_complete:null;
//                 //$data['m2_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
//                 $data['m2_date'] = check_null_and_matching_data($checkPid,'m2_date',$val,'m2');//$val->m2) ? $val->m2 : null;
//                 // $data['date_cancelled'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
//                 $date_cancelled = check_null_and_matching_data($checkPid,'date_cancelled',$val,'date_cancelled');
//                 $data['date_cancelled'] = empty($date_cancelled)?null:$date_cancelled; //$val->date_cancelled) ? $val->date_cancelled : null;
//                 // $data['return_sales_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
//                 $data['return_sales_date'] = check_null_and_matching_data($checkPid,'return_sales_date',$val,'return_sales_date');//$val->return_sales_date) ? $val->return_sales_date : null;
//                 $data['gross_account_value'] = check_null_and_matching_data($checkPid,'gross_account_value',$val,'gross_account_value');//$val->gross_account_value) ? $val->gross_account_value : null;
//                 $data['cash_amount'] = check_null_and_matching_data($checkPid,'cash_amount',$val,'cash_amount');//$val->cash_amount) ? $val->cash_amount : null;
//                 $data['loan_amount'] = check_null_and_matching_data($checkPid,'loan_amount',$val,'loan_amount');//$val->loan_amount) ? $val->loan_amount : null;
//                 $data['kw'] = check_null_and_matching_data($checkPid,'kw',$val,'kw');//$val->kw) ? $val->kw : null;
//                 $data['dealer_fee_percentage'] = check_null_and_matching_data($checkPid,'dealer_fee_percentage',$val,'dealer_fee_percentage');//$val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
//                 $data['adders'] = check_null_and_matching_data($checkPid,'adders',$val,'adders');//$val->adders) ? $val->adders : null;
//                 $data['cancel_fee'] = check_null_and_matching_data($checkPid,'cancel_fee',$val,'cancel_fee');//$val->cancel_fee) ? $val->cancel_fee : null;
//                 $data['adders_description'] = check_null_and_matching_data($checkPid,'adders_description',$val,'adders_description');//$val->adders_description) ? $val->adders_description : null;
//                 $data['funding_source'] = check_null_and_matching_data($checkPid,'funding_source',$val,'funding_source');//$val->funding_source) ? $val->funding_source : null;
//                 $data['financing_rate'] = check_null_and_matching_data($checkPid,'financing_rate',$val,'financing_rate');//$val->financing_rate) ? $val->financing_rate : 0.00;
//                 $data['financing_term'] = check_null_and_matching_data($checkPid,'financing_term',$val,'financing_term');//$val->financing_term) ? $val->financing_term : null;
//                 $data['product'] = check_null_and_matching_data($checkPid,'product',$val,'product');//$val->product) ? $val->product : null;
//                 $data['epc'] = check_null_and_matching_data($checkPid,'epc',$val,'epc');//$val->epc) ? $val->epc : null;
//                 $data['net_epc'] = check_null_and_matching_data($checkPid,'net_epc',$val,'net_epc');//$val->net_epc) ? $val->net_epc : null;
//                 $data['source_created_at'] = check_null_and_matching_data($checkPid,'source_created_at',$val,'created');
//                 $data['source_updated_at'] = check_null_and_matching_data($checkPid,'source_updated_at',$val,'modified');
//                 $data['data_source_type'] = 'api';
//                 // Log::info("UPDATE:".$data['date_cancelled']);

//                 if(!empty($checkPid))
//                 {
//                     //m1 m2 kw epc ,net_epc, rep_email, type =api
//                     $check_keys = ['m1_date','m2_date','epc','net_epc', 'sales_rep_email','date_cancelled'];
//                     $val_check_keys = ['m1','m2','epc','net_epc', 'rep_email','date_cancelled'];

//                     $create_history = false;
//                     $old_data_arr= [];
//                     $new_data_arr= [];
//                     $checkPid_arr =  $checkPid->toArray();
//                     $message_str = [];
//                     foreach($val_check_keys as $postion => $check_key){
//                         if(($checkPid_arr[$check_keys[$postion]] == null && $val->$check_key != null) || ($checkPid_arr[$check_keys[$postion]] != null && $val->$check_key != $checkPid_arr[$check_keys[$postion]])){
//                             $old_data_arr[$check_keys[$postion]] = $checkPid_arr[$check_keys[$postion]];
//                             $new_data_arr[$check_keys[$postion]] = $val->$check_key;
//                             $create_history =true;
//                             if($check_keys[$postion] == 'm1_date' || $check_keys[$postion] == 'm2_date' || $check_keys[$postion] == 'date_cancelled'){
//                                 $date_new = get_date_only($val->$check_key);
//                                 $date_old = get_date_only($checkPid_arr[$check_keys[$postion]]);
//                                 if($date_new != $date_old){
//                                     $payroll_data = Payroll::join('user_commission','user_commission.user_id','=','payrolls.user_id')->where('user_commission.pid',$val->prospect_id)->where('user_commission.amount_type',$check_key)->get();
//                                     foreach($payroll_data as $payroll){
//                                         if($payroll['is_mark_paid']==1){
//                                             $data[$check_keys[$postion]] = $checkPid_arr[$check_keys[$postion]];
//                                         }
//                                     }
//                                     $message_str[] = $check_keys[$postion].'_old:'.$date_old.','.$check_keys[$postion].'_new:'.$date_new;
//                                 }
//                             }else{
//                                 $message_str[] = $check_keys[$postion].'_old:'.$checkPid_arr[$check_keys[$postion]].','.$check_keys[$postion].'_new:'.$val->$check_key;
//                             }
//                         }
//                     }
//                     // Log::info($message_str);
//                     if(!empty($message_str)){
//                         $message = join('|',$message_str);
//                         $log_data = ['pid'=>$val->prospect_id,'message_text'=>$message];
//                         SaleDataUpdateLogs::create($log_data);
//                     }

//                     $updated = LegacyApiRowData::where('pid',$val->prospect_id)->update($data);
//                     if(!empty($updated)){
//                         $updated_pid_raw = $val->prospect_id;
//                         $status = 'LegacyApiRowData_update';
//                     }
//                 }else{
//                     $inserted = LegacyApiRowData::create($data);
//                     if(!empty($inserted)){
//                         $new_pid_raw = $val->prospect_id;
//                         $status = 'LegacyApiRowData_insert';
//                     }
//                 }
//         return ['status'=>$status,'new_pid_null'=>$new_pid_null,'updated_pid_null'=>$updated_pid_null,'updated_pid_raw'=>$updated_pid_raw,'new_pid_raw'=>$new_pid_raw];
//     }
// }

if (! function_exists('insert_update_legacy_null_data')) {
    function insert_update_legacy_null_data($val)
    {
        try {
            DB::beginTransaction();
            // $data['ct'] = null;
            $status = '';
            $response = [];
            $updated_pid_null = '';
            $new_pid_null = '';
            $updated = '';
            $inserted = '';
            // Insert null data in table for alert admin...............................................
            $checkPid = LegacyApiNullData::where('pid', $val->prospect_id)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
            $m1_date = $val->m1;
            $m2_date = $val->m2;
            if (! empty($checkPid)) {
                $salesMaster = SalesMaster::where('pid', $checkPid->pid)->first();
                if (! empty($salesMaster->m1_date)) {
                    $commission = UserCommission::where(['pid' => $checkPid->pid, 'amount_type' => 'm1', 'status' => '3'])->first();
                    if (! empty($commission)) {
                        $m1_date = $salesMaster->m1_date;
                    }
                }
                if (! empty($salesMaster->m2_date)) {
                    $commission = UserCommission::where(['pid' => $checkPid->pid, 'amount_type' => 'm2', 'status' => '3'])->first();
                    if (! empty($commission)) {
                        $m2_date = $salesMaster->m2_date;
                    }
                }
            }

            $netEPC = check_null_and_matching_data($checkPid, 'net_epc', $val, 'net_epc'); // $val->net_epc) ? $val->net_epc : null;

            // $data['weekly_sheet_id'] = isset($lid['weekid']) ? $lid['weekid'] : null;
            $data['legacy_data_id'] = check_null_and_matching_data($checkPid, 'legacy_data_id', $val, 'id');
            $data['pid'] = check_null_and_matching_data($checkPid, 'pid', $val, 'prospect_id'); // isset($val->prospect_id) ? $val->prospect_id : null;
            $data['homeowner_id'] = check_null_and_matching_data($checkPid, 'homeowner_id', $val, 'homeowner_id'); // isset($val->homeowner_id) ? $val->homeowner_id : null;
            $data['proposal_id'] = check_null_and_matching_data($checkPid, 'proposal_id', $val, 'proposal_id'); // isset($val->proposal_id) ? $val->proposal_id : null;
            $data['customer_name'] = check_null_and_matching_data($checkPid, 'customer_name', $val, 'customer_name'); // isset($val->customer_name) ? $val->customer_name : null;
            $data['customer_address'] = check_null_and_matching_data($checkPid, 'customer_address', $val, 'customer_address'); // isset($val->customer_address) ? $val->customer_address : null;
            $data['customer_address_2'] = check_null_and_matching_data($checkPid, 'customer_address_2', $val, 'customer_address_2'); // isset($val->customer_address_2) ? $val->customer_address_2 : null;
            $data['customer_city'] = check_null_and_matching_data($checkPid, 'customer_city', $val, 'customer_city'); // isset($val->customer_city) ? $val->customer_city : null;
            $data['customer_state'] = check_null_and_matching_data($checkPid, 'customer_state', $val, 'customer_state'); // isset($val->customer_state) ? $val->customer_state : null;
            $data['location_code'] = check_null_and_matching_data($checkPid, 'location_code', $val, 'customer_state'); // isset($val->customer_state) ? $val->customer_state : null;
            $data['customer_zip'] = check_null_and_matching_data($checkPid, 'customer_zip', $val, 'customer_zip'); // isset($val->customer_zip) ? $val->customer_zip : null;
            $data['customer_email'] = check_null_and_matching_data($checkPid, 'customer_email', $val, 'customer_email'); // $val->customer_email) ? $val->customer_email : null;

            $data['customer_email'] = strtolower($data['customer_email']);
            $data['customer_phone'] = check_null_and_matching_data($checkPid, 'customer_phone', $val, 'customer_phone'); // $val->customer_phone) ? $val->customer_phone : null;
            // $data['setter_id'] = check_null_and_matching_data($checkPid,'setter_id',$val,'setter_id');//$val->setter_id) ? $val->setter_id : null;
            $data['employee_id'] = check_null_and_matching_data($checkPid, 'employee_id', $val, 'employee_id'); // $val->employee_id) ? $val->employee_id : null;
            $data['sales_rep_name'] = check_null_and_matching_data($checkPid, 'sales_rep_name', $val, 'rep_name'); // $val->rep_name) ? $val->rep_name : null;
            $data['sales_rep_email'] = check_null_and_matching_data($checkPid, 'sales_rep_email', $val, 'rep_email'); // $val->rep_email) ? $val->rep_email : null;
            $data['sales_rep_email'] = strtolower($data['sales_rep_email']);
            $data['install_partner'] = check_null_and_matching_data($checkPid, 'install_partner', $val, 'install_partner'); // $val->install_partner) ? $val->install_partner : null;
            $data['install_partner_id'] = check_null_and_matching_data($checkPid, 'install_partner_id', $val, 'install_partner_id'); // $val->install_partner_id) ? $val->install_partner_id : null;
            // $data['customer_signoff'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
            $data['customer_signoff'] = check_null_and_matching_data($checkPid, 'customer_signoff', $val, 'customer_signoff'); // $val->customer_signoff) && $val->customer_signoff != null ? $val->customer_signoff : null;
            // $data['m1_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
            $data['m1_date'] = $m1_date; // check_null_and_matching_data($checkPid,'m1_date',$val,'m1');//$val->m1) ? $val->m1 : null;
            // $data['scheduled_install'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
            $data['scheduled_install'] = check_null_and_matching_data($checkPid, 'scheduled_install', $val, 'scheduled_install'); // $val->scheduled_install) ? $val->scheduled_install : null;
            $data['install_complete_date'] = check_null_and_matching_data($checkPid, 'install_complete_date', $val, 'install_complete'); // $val->install_complete)?$val->install_complete:null;
            // $data['m2_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
            $data['m2_date'] = $m2_date; // check_null_and_matching_data($checkPid,'m2_date',$val,'m2');//$val->m2) ? $val->m2 : null;
            // $data['date_cancelled'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
            $data['date_cancelled'] = $val->date_cancelled; // check_null_and_matching_data($checkPid,'date_cancelled',$val,'date_cancelled');//$val->date_cancelled) ? $val->date_cancelled : null;
            // $data['return_sales_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
            // $data['return_sales_date'] = $val->return_sales_date; //check_null_and_matching_data($checkPid,'return_sales_date',$val,'return_sales_date');//$val->return_sales_date) ? $val->return_sales_date : null;
            $data['return_sales_date'] = null;
            $data['gross_account_value'] = check_null_and_matching_data($checkPid, 'gross_account_value', $val, 'gross_account_value'); // $val->gross_account_value) ? $val->gross_account_value : null;
            $data['cash_amount'] = check_null_and_matching_data($checkPid, 'cash_amount', $val, 'cash_amount'); // $val->cash_amount) ? $val->cash_amount : null;
            $data['loan_amount'] = check_null_and_matching_data($checkPid, 'loan_amount', $val, 'loan_amount'); // $val->loan_amount) ? $val->loan_amount : null;
            $data['kw'] = check_null_and_matching_data($checkPid, 'kw', $val, 'kw'); // $val->kw) ? $val->kw : null;
            $data['dealer_fee_percentage'] = check_null_and_matching_data($checkPid, 'dealer_fee_percentage', $val, 'dealer_fee_percentage'); // $val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
            if (empty($data['dealer_fee_percentage'])) {
                $data['dealer_fee_percentage'] = 0;
            }
            $data['adders'] = check_null_and_matching_data($checkPid, 'adders', $val, 'adders'); // $val->adders) ? $val->adders : null;
            $data['cancel_fee'] = check_null_and_matching_data($checkPid, 'cancel_fee', $val, 'cancel_fee'); // $val->cancel_fee) ? $val->cancel_fee : null;
            $data['adders_description'] = check_null_and_matching_data($checkPid, 'adders_description', $val, 'adders_description'); // $val->adders_description) ? $val->adders_description : null;
            $data['funding_source'] = check_null_and_matching_data($checkPid, 'funding_source', $val, 'funding_source'); // $val->funding_source) ? $val->funding_source : null;
            $data['financing_rate'] = check_null_and_matching_data($checkPid, 'financing_rate', $val, 'financing_rate'); // $val->financing_rate) ? $val->financing_rate : 0.00;
            $data['financing_term'] = check_null_and_matching_data($checkPid, 'financing_term', $val, 'financing_term'); // $val->financing_term) ? $val->financing_term : null;
            $data['product'] = check_null_and_matching_data($checkPid, 'product', $val, 'product'); // $val->product) ? $val->product : null;
            $data['epc'] = check_null_and_matching_data($checkPid, 'epc', $val, 'epc'); // $val->epc) ? $val->epc : null;
            $data['net_epc'] = $netEPC; // check_null_and_matching_data($checkPid,'net_epc',$val,'net_epc');//$val->net_epc) ? $val->net_epc : null;
            $data['source_created_at'] = check_null_and_matching_data($checkPid, 'source_created_at', $val, 'created');
            $data['source_updated_at'] = check_null_and_matching_data($checkPid, 'source_updated_at', $val, 'modified');
            $data['email_status'] = 0;
            $data['data_source_type'] = 'api';
            $data['job_status'] = isset($val->job_status) ? $val->job_status : null;
            // Log::info('LegacyApiNullData :'.json_encode($data));

            // // $data['weekly_sheet_id'] = isset($lid['weekid']) ? $lid['weekid'] : null;
            // $data['legacy_data_id'] = $val->id;
            // $data['pid'] = $val->prospect_id; //isset($val->prospect_id) ? $val->prospect_id : null;
            // $data['homeowner_id'] = $val->homeowner_id;//isset($val->homeowner_id) ? $val->homeowner_id : null;
            // $data['proposal_id'] = $val->proposal_id;//isset($val->proposal_id) ? $val->proposal_id : null;
            // $data['customer_name'] = $val->customer_name;//isset($val->customer_name) ? $val->customer_name : null;
            // $data['customer_address'] = $val->customer_address;//isset($val->customer_address) ? $val->customer_address : null;
            // $data['customer_address_2'] = $val->customer_address_2;//isset($val->customer_address_2) ? $val->customer_address_2 : null;
            // $data['customer_city'] = $val->customer_city;//isset($val->customer_city) ? $val->customer_city : null;
            // $data['customer_state'] = $val->customer_state;//isset($val->customer_state) ? $val->customer_state : null;
            // $data['customer_zip'] = $val->customer_zip;//isset($val->customer_zip) ? $val->customer_zip : null;
            // $data['customer_email'] = $val->customer_email;//$val->customer_email) ? $val->customer_email : null;
            // $data['customer_phone'] = $val->customer_phone;//$val->customer_phone) ? $val->customer_phone : null;
            // $data['setter_id'] = $val->setter_id;//$val->setter_id) ? $val->setter_id : null;
            // $data['employee_id'] = $val->employee_id;//$val->employee_id) ? $val->employee_id : null;
            // $data['sales_rep_name'] = $val->rep_name;//$val->rep_name) ? $val->rep_name : null;
            // $data['sales_rep_email'] = $val->rep_email;//$val->rep_email) ? $val->rep_email : null;
            // $data['install_partner'] = $val->install_partner;//$val->install_partner) ? $val->install_partner : null;
            // $data['install_partner_id'] = $val->install_partner_id;//$val->install_partner_id) ? $val->install_partner_id : null;
            // $data['customer_signoff'] = isset($val->customer_signoff)?$val->customer_signoff:null;//$val->customer_signoff) && $val->customer_signoff != null ? $val->customer_signoff : null;
            // $data['m1_date'] = $val->m1;//$val->m1) ? $val->m1 : null;
            // $data['scheduled_install'] = $val->scheduled_install;//$val->scheduled_install) ? $val->scheduled_install : null;
            // $data['install_complete_date'] = $val->install_complete;//$val->install_complete)?$val->install_complete:null;
            // $data['m2_date'] = $val->m2;//$val->m2) ? $val->m2 : null;
            // $data['date_cancelled'] = $val->date_cancelled;//$val->date_cancelled) ? $val->date_cancelled : null;
            // $data['return_sales_date'] = $val->return_sales_date;//$val->return_sales_date) ? $val->return_sales_date : null;
            // $data['gross_account_value'] = $val->gross_account_value;//$val->gross_account_value) ? $val->gross_account_value : null;
            // $data['cash_amount'] = $val->cash_amount;//$val->cash_amount) ? $val->cash_amount : null;
            // $data['loan_amount'] = $val->loan_amount;//$val->loan_amount) ? $val->loan_amount : null;
            // $data['kw'] = $val->kw;//$val->kw) ? $val->kw : null;
            // $data['dealer_fee_percentage'] = $val->dealer_fee_percentage;//$val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
            // $data['adders'] = $val->adders;//$val->adders) ? $val->adders : null;
            // $data['cancel_fee'] = $val->cancel_fee;//$val->cancel_fee) ? $val->cancel_fee : null;
            // $data['adders_description'] = $val->adders_description;//$val->adders_description) ? $val->adders_description : null;
            // $data['funding_source'] = $val->funding_source;//$val->funding_source) ? $val->funding_source : null;
            // $data['financing_rate'] = $val->financing_rate;//$val->financing_rate) ? $val->financing_rate : 0.00;
            // $data['financing_term'] = $val->financing_term;//$val->financing_term) ? $val->financing_term : null;
            // $data['product'] = $val->product;//$val->product) ? $val->product : null;
            // $data['epc'] = $val->epc;//$val->epc) ? $val->epc : null;
            // $data['net_epc'] = $val->net_epc;//$val->net_epc) ? $val->net_epc : null;
            // $data['source_created_at'] = $val->created;
            // $data['source_updated_at'] = $val->modified;
            // $data['email_status'] = 0;
            // $data['data_source_type'] = 'api';

            if (! empty($checkPid)) {
                $updated = LegacyApiNullData::where('id', $checkPid->id)->update($data);
                if (! empty($updated)) {
                    $updated_pid_null = $val->prospect_id;
                    $status = 'LegacyApiNullData_update';
                }
            } else {
                $inserted = LegacyApiNullData::create($data);
                if (! empty($inserted)) {
                    $new_pid_null = $val->prospect_id;
                    $status = 'LegacyApiNullData_insert';
                }
            }
            DB::commit();

            return ['status' => $status, 'new_pid_null' => $new_pid_null, 'updated_pid_null' => $updated_pid_null];

        } catch (Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
        }
    }
}

if (! function_exists('DynamicEmailConfig')) {
    function DynamicEmailConfig()
    {
        $mailSetting = EmailConfiguration::first();
        if ($mailSetting != '') {
            // Decrypt the password from database
            $decryptedPassword = $mailSetting->password;
            if (! empty($mailSetting->password)) {
                try {
                    $decryptedPassword = custom_decrypt($mailSetting->password);
                } catch (\Exception $e) {
                    \Log::error('Error decrypting email password: '.$e->getMessage());
                    // Fallback to the encrypted password if decryption fails
                    $decryptedPassword = $mailSetting->password;
                }
            }

            // Set the data in an array variable from settings table
            $mailConfig = [
                'transport' => 'smtp',
                'host' => $mailSetting->host_name,
                'port' => $mailSetting->smtp_port,
                'encryption' => $mailSetting->security_protocol,
                'username' => $mailSetting->user_name,
                'password' => $decryptedPassword,
                'timeout' => null,
            ];
            // To set configuration values at runtime, pass an array to the config helper
            config(['mail.mailers.smtp' => $mailConfig]);
            config(['mail.mailers.from.address' => $mailSetting->email_from_address]);
            config(['mail.mailers.from.name' => $mailSetting->email_from_name]);
        }
    }
}

if (! function_exists('hs_create_raw_data_history_api')) {
    function hs_create_raw_data_history_api($data)
    {

        $new_pid_null = '';
        $updated_pid_null = '';
        // $dataCreate = $data1;
        // $dataCreate['aveyo_hs_id'] = $data1['pid'];
        $dataCreate = [

            'pid' => isset($data['hs_object_id']['value']) ? $data['hs_object_id']['value'] : null,
            'install_partner' => isset($data['install_team']['value']) ? $data['install_team']['value'] : null,
            'homeowner_id' => isset($data['hubspot_owner_id']['value']) ? $data['hubspot_owner_id']['value'] : null,
            'customer_name' => isset($data['borrower_name']['value']) ? $data['borrower_name']['value'] : null,
            'customer_address' => isset($data['full_address']['value']) ? $data['full_address']['value'] : null,
            'customer_address_2' => isset($data['address']['value']) ? $data['address']['value'] : null,
            'customer_city' => isset($data['city']['value']) ? $data['city']['value'] : null,
            'customer_state' => isset($data['state']['value']) ? $data['state']['value'] : null,
            'customer_zip' => isset($data['postal_code']['value']) ? $data['postal_code']['value'] : null,
            'customer_email' => isset($data['email']['value']) ? $data['email']['value'] : null,
            'customer_phone' => isset($data['phone']['value']) ? $data['phone']['value'] : null,
            'sales_rep_email' => isset($data['setter']['value']) ? $data['setter']['value'] : null,
            'm1_date' => isset($data['m1_com_approved']['value']) ? $data['m1_com_approved']['value'] : null,
            'm2_date' => isset($data['m2_com_date']['value']) ? $data['m2_com_date']['value'] : null,
            'date_cancelled' => isset($data['cancelation_date']['value']) ? $data['cancelation_date']['value'] : null,
            'kw' => isset($data['system_size']['value']) ? $data['system_size']['value'] : null,
            'dealer_fee_percentage' => isset($data['dealer_fee____']['value']) ? $data['dealer_fee____']['value'] : null,
            'dealer_fee_amount' => isset($data['dealer_fee_amount']['value']) ? $data['dealer_fee_amount']['value'] : null,
            'adders' => isset($data['sow_total_adder_cost']['value']) ? $data['sow_total_adder_cost']['value'] : null,
            'adders_description' => isset($data['adders_description']['value']) ? $data['adders_description']['value'] : null,
            'epc' => isset($data['gross_ppw']['value']) ? number_format($data['gross_ppw']['value'], 4, '.', '') : null,
            'net_epc' => isset($data['net_ppw_calc']['value']) ? number_format($data['net_ppw_calc']['value'], 4, '.', '') : null,
            'gross_account_value' => isset($data['total_cost']['value']) ? number_format($data['total_cost']['value'], 3, '.', '') : null,
            'product' => isset($data['project_type']['value']) ? $data['project_type']['value'] : null,
            //    'setter1_id'=> isset($data['setter_id']['value'])? $data['setter_id']['value']:null,
            //    'setter2_id'=> isset($data['setter_2_id']['value'])? $data['setter_2_id']['value']:null,
            //    'closer1_id'=> isset($data['closer_id']['value'])? $data['closer_id']['value']:null,
            //    'closer2_id'=> isset($data['closer_2_id']['value'])? $data['closer_2_id']['value']:null,
            'contract_sign_date' => isset($data['contract_sign_date']['value']) ? date('Y-m-d', strtotime($data['contract_sign_date']['value'])) : null,
        ];

        $history = LegacyApiRawDataHistory::where('pid', $dataCreate['pid'])->orderBy('id', 'DESC')->first();
        if (empty($history)) {
            $create_raw_data_history = LegacyApiRawDataHistory::create($dataCreate);
            $new_pid_null = $dataCreate['pid'];
        } else {
            $history_data = LegacyApiRawDataHistory::where('pid', $history->pid)->where($dataCreate)->first();
            if (empty($history_data)) {
                // $data['created_at'] = $history->created_at;
                $create_raw_data_history = LegacyApiRawDataHistory::create($dataCreate);
                $updated_pid_null = $dataCreate['pid'];
            }
        }

        return ['new_pid_null' => $new_pid_null, 'updated_pid_null' => $updated_pid_null];
    }
}

if (! function_exists('jobnimbus_create_raw_data_history_api')) {
    function jobnimbus_create_raw_data_history_api($data)
    {

        $new_pid_null = '';
        $updated_pid_null = '';

        $history = LegacyApiRawDataHistory::where('pid', $data['pid'])->orderBy('id', 'DESC')->first();
        if (empty($history)) {
            $create_raw_data_history = LegacyApiRawDataHistory::create($data);
            $new_pid_null = $data['pid'];
        } else {

            $history_data = LegacyApiRawDataHistory::where('pid', $history->pid)->where($data)->first();
            if (empty($history_data)) {
                // $data['created_at'] = $history->created_at;
                $create_raw_data_history = LegacyApiRawDataHistory::create($data);
                $updated_pid_null = $data['pid'];
            }
        }

        return ['new_pid_null' => $new_pid_null, 'updated_pid_null' => $updated_pid_null];
    }
}

if (! function_exists('jobnimbus_create_update_legacy_api_data_null')) {
    function jobnimbus_create_update_legacy_api_data_null($data)
    {
        // Assuming $yourArray is your input array

        // Check if there's an existing record with the given pid
        $existingRecord = LegacyApiNullData::where('pid', $data['pid'])->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
        // print_r($existingRecord->toArray());

        if ($existingRecord) {
            // Update the existing record and only those column which have value
            $updateData = array_filter($data, function ($value) {
                return $value != null;
            });
            $existingRecord->update($updateData);
        } else {
            // Insert a new record
            LegacyApiNullData::create($data);
        }
    }
}

if (! function_exists('jobnimbus_insert_update_sale_master')) {
    function jobnimbus_insert_update_sale_master($pid = '')
    {
        try {
            DB::beginTransaction();
            if (empty($pid)) {
                $newData = LegacyApiNullData::with('userDetail')->whereNotNull('data_source_type')->orderBy('id', 'desc')->get();
            } else {
                $newData = LegacyApiNullData::with('userDetail')->where('pid', $pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->get();
            }

            // Update data by previous comparison in Sales_Master
            foreach ($newData as $checked) {
                $check = 0;
                $salesMaster = SalesMaster::where('pid', $checked->pid)->first();
                if ($salesMaster) {
                    $salesMaster->install_partner = strtolower(trim($salesMaster->install_partner));
                    $check_kw = ($checked['kw'] == $salesMaster->kw) ? 0 : 1;
                    $check_net_epc = ($checked['net_epc'] == $salesMaster->net_epc) ? 0 : 1;
                    $check_date_cancelled = ($checked['date_cancelled'] == $salesMaster->date_cancelled) ? 0 : 1;
                    $check_return_sales_date = ($checked['return_sales_date'] == $salesMaster->return_sales_date) ? 0 : 1;
                    $check_customer_state = ($checked['customer_state'] == $salesMaster->customer_state) ? 0 : 1;
                    $check_m1_date = ($checked['m1_date'] == $salesMaster->m1_date) ? 0 : 1;
                    $check_m2_date = ($checked['m2_date'] == $salesMaster->m2_date) ? 0 : 1;
                    $check = ($check_kw + $check_net_epc + $check_date_cancelled + $check_return_sales_date + $check_customer_state + $check_m1_date + $check_m2_date);
                }

                $val = [
                    'pid' => $checked->pid,
                    'weekly_sheet_id' => null, // $checked->weekly_sheet_id,//!empty($salesMaster->weekly_sheet_id)?$salesMaster->weekly_sheet_id:
                    'install_partner' => check_null_and_matching_data($salesMaster, 'install_partner', $checked, 'install_partner'), // $checked->install_partner, //!empty($salesMaster->install_partner)?$salesMaster->install_partner:
                    'install_partner_id' => check_null_and_matching_data($salesMaster, 'install_partner_id', $checked, 'install_partner_id'), // $checked->install_partner_id, //!empty($salesMaster->install_partner_id)?$salesMaster->install_partner_id:
                    'customer_name' => check_null_and_matching_data($salesMaster, 'customer_name', $checked, 'customer_name'), // $checked->customer_name, //!empty($salesMaster->customer_name)?$salesMaster->customer_name:
                    'customer_address' => check_null_and_matching_data($salesMaster, 'customer_address', $checked, 'customer_address'), // $checked->customer_address, //!empty($salesMaster->customer_address)?$salesMaster->customer_address:
                    'customer_address_2' => check_null_and_matching_data($salesMaster, 'customer_address_2', $checked, 'customer_address_2'), // $checked->customer_address_2, //!empty($salesMaster->customer_address_2)?$salesMaster->customer_address_2:
                    'customer_city' => check_null_and_matching_data($salesMaster, 'customer_city', $checked, 'customer_city'), // $checked->customer_city, //!empty($salesMaster->customer_city)?$salesMaster->customer_city:
                    'customer_state' => check_null_and_matching_data($salesMaster, 'customer_state', $checked, 'customer_state'), // $checked->customer_state, //!empty($salesMaster->customer_state)?$salesMaster->customer_state:
                    'location_code' => check_null_and_matching_data($salesMaster, 'location_code', $checked, 'location_code'), // $checked->customer_state, //!empty($salesMaster->customer_state)?$salesMaster->customer_state:
                    'customer_zip' => check_null_and_matching_data($salesMaster, 'customer_zip', $checked, 'customer_zip'), // $checked->customer_zip, //!empty($salesMaster->customer_zip)?$salesMaster->customer_zip:
                    'customer_email' => check_null_and_matching_data($salesMaster, 'customer_email', $checked, 'customer_email'), // $checked->customer_email, //!empty($salesMaster->customer_email)?$salesMaster->customer_email:
                    'customer_phone' => check_null_and_matching_data($salesMaster, 'customer_phone', $checked, 'customer_phone'), // $checked->customer_phone, //!empty($salesMaster->customer_phone)?$salesMaster->customer_phone:
                    'homeowner_id' => check_null_and_matching_data($salesMaster, 'homeowner_id', $checked, 'homeowner_id'), // $checked->homeowner_id, //!empty($salesMaster->homeowner_id)?$salesMaster->homeowner_id:
                    'proposal_id' => check_null_and_matching_data($salesMaster, 'proposal_id', $checked, 'proposal_id'), // $checked->proposal_id, //!empty($salesMaster->proposal_id)?$salesMaster->proposal_id:
                    'sales_rep_name' => check_null_and_matching_data($salesMaster, 'sales_rep_name', $checked, 'sales_rep_name'), // $checked->sales_rep_name, //!empty($salesMaster->sales_rep_name)?$salesMaster->sales_rep_name:
                    'employee_id' => check_null_and_matching_data($salesMaster, 'employee_id', $checked, 'employee_id'), // $checked->employee_id, //!empty($salesMaster->employee_id)?$salesMaster->employee_id:
                    'sales_rep_email' => check_null_and_matching_data($salesMaster, 'sales_rep_email', $checked, 'sales_rep_email'), // $checked->sales_rep_email, //!empty($salesMaster->sales_rep_email)?$salesMaster->sales_rep_email:
                    'kw' => check_null_and_matching_data($salesMaster, 'kw', $checked, 'kw'), // $checked->kw, //!empty($salesMaster->kw)?$salesMaster->kw:
                    // 'date_cancelled' => $checked->date_cancelled,// check_null_and_matching_data($salesMaster,'date_cancelled',$checked,'date_cancelled'),// $checked->date_cancelled, //!empty($salesMaster->date_cancelled)?$salesMaster->date_cancelled:
                    'customer_signoff' => check_null_and_matching_data($salesMaster, 'customer_signoff', $checked, 'customer_signoff'), // !empty($salesMaster->customer_signoff)?$salesMaster->customer_signoff:$checked->customer_signoff,
                    'm1_date' => $checked->m1_date, // check_null_and_matching_data($salesMaster,'m1_date',$checked,'m1_date'),// $checked->m1_date, //!empty($salesMaster->m1_date)?$salesMaster->m1_date:
                    'm2_date' => $checked->m2_date, // check_null_and_matching_data($salesMaster,'m2_date',$checked,'m2_date'),// $checked->m2_date, //!empty($salesMaster->m2_date)?$salesMaster->m2_date:
                    'product' => check_null_and_matching_data($salesMaster, 'product', $checked, 'product'), // $checked->product, //!empty($salesMaster->product)?$salesMaster->product:
                    'epc' => check_null_and_matching_data($salesMaster, 'epc', $checked, 'epc'), // $checked->epc, //!empty($salesMaster->epc)?$salesMaster->epc:
                    'net_epc' => check_null_and_matching_data($salesMaster, 'net_epc', $checked, 'net_epc'), // $checked->net_epc, //!empty($salesMaster->net_epc)?$salesMaster->net_epc:
                    'gross_account_value' => check_null_and_matching_data($salesMaster, 'gross_account_value', $checked, 'gross_account_value'), // $checked->gross_account_value, //!empty($salesMaster->gross_account_value)?$salesMaster->gross_account_value:
                    'dealer_fee_percentage' => check_null_and_matching_data($salesMaster, 'dealer_fee_percentage', $checked, 'dealer_fee_percentage'), // $checked->dealer_fee_percentage, //!empty($salesMaster->dealer_fee_percentage)?$salesMaster->dealer_fee_percentage:
                    'adders' => check_null_and_matching_data($salesMaster, 'adders', $checked, 'adders'), // $checked->adders, //!empty($salesMaster->adders)?$salesMaster->adders:
                    'adders_description' => check_null_and_matching_data($salesMaster, 'adders_description', $checked, 'adders_description'), // $checked->adders_description, //!empty($salesMaster->adders_description)?$salesMaster->adders_description:
                    'funding_source' => check_null_and_matching_data($salesMaster, 'funding_source', $checked, 'funding_source'), // $checked->funding_source, //!empty($salesMaster->funding_source)?$salesMaster->funding_source:
                    'financing_rate' => check_null_and_matching_data($salesMaster, 'financing_rate', $checked, 'financing_rate'), // $checked->financing_rate, //!empty($salesMaster->financing_rate)?$salesMaster->financing_rate:
                    'financing_term' => check_null_and_matching_data($salesMaster, 'financing_term', $checked, 'financing_term'), // $checked->financing_term, //!empty($salesMaster->financing_term)?$salesMaster->financing_term:
                    'scheduled_install' => check_null_and_matching_data($salesMaster, 'scheduled_install', $checked, 'scheduled_install'), // $checked->scheduled_install, //!empty($salesMaster->scheduled_install)?$salesMaster->scheduled_install:
                    'install_complete_date' => check_null_and_matching_data($salesMaster, 'install_complete_date', $checked, 'install_complete_date'), // $checked->install_complete_date, //!empty($salesMaster->install_complete_date)?$salesMaster->install_complete_date:
                    // 'return_sales_date' => check_null_and_matching_data($salesMaster,'return_sales_date',$checked,'return_sales_date'),// $checked->return_sales_date, //!empty($salesMaster->return_sales_date)?$salesMaster->return_sales_date:
                    // 'return_sales_date' => $checked->return_sales_date,// $checked->return_sales_date, //!empty($salesMaster->return_sales_date)?$salesMaster->return_sales_date:
                    'cash_amount' => check_null_and_matching_data($salesMaster, 'cash_amount', $checked, 'cash_amount'), // $checked->cash_amount, //!empty($salesMaster->cash_amount)?$salesMaster->cash_amount:
                    'loan_amount' => check_null_and_matching_data($salesMaster, 'loan_amount', $checked, 'loan_amount'), // $checked->loan_amount, //!empty($salesMaster->loan_amount)?$salesMaster->loan_amount:
                    // 'dealer_fee_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->dealer_fee_amount)?$salesMaster->dealer_fee_amount:$checked->dealer_fee_dollar,
                    'redline' => check_null_and_matching_data($salesMaster, 'redline', $checked, 'redline'), // $checked->redline, //!empty($salesMaster->redline)?$salesMaster->redline:
                    // 'total_amount_for_acct' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->total_amount_for_acct)?$salesMaster->total_amount_for_acct:$checked->total_for_acct,
                    // 'prev_amount_paid' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->prev_amount_paid)?$salesMaster->prev_amount_paid:$checked->prev_paid,
                    // 'last_date_pd' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->last_date_pd)?$salesMaster->last_date_pd:$checked->last_date_pd,
                    // 'm1_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->m1_amount)?$salesMaster->m1_amount:$checked->m1_this_week,
                    // 'm2_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->m2_amount)?$salesMaster->m2_amount:$checked->m2_this_week,
                    // 'prev_deducted_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->prev_deducted_amount)?$salesMaster->prev_deducted_amount:$checked->prev_deducted,
                    'cancel_fee' => check_null_and_matching_data($salesMaster, 'cancel_fee', $checked, 'cancel_fee'), // $checked->cancel_fee, //!empty($salesMaster->cancel_fee)?$salesMaster->cancel_fee:
                    // 'cancel_deduction' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->cancel_deduction)?$salesMaster->cancel_deduction:$checked->cancel_deduction,
                    // 'lead_cost_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->lead_cost_amount)?$salesMaster->lead_cost_amount:$checked->lead_cost,
                    // 'adv_pay_back_amount' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->adv_pay_back_amount)?$salesMaster->adv_pay_back_amount:$checked->adv_pay_back_amount,
                    // 'total_amount_in_period' => check_null_and_matching_data($salesMaster,'weekly_sheet_id',$checked,'id') !empty($salesMaster->total_amount_in_period)?$salesMaster->total_amount_in_period:$checked->total_in_period
                    'data_source_type' => $checked->data_source_type,
                ];

                if (empty($val['dealer_fee_percentage'])) {
                    $val['dealer_fee_percentage'] = 0;
                }

                // print_r($val);
                if (empty($salesMaster)) {
                    if (! empty($checked->pid) && ! empty($checked->customer_signoff) && ! empty($checked->net_epc) && ! empty($checked->customer_name) && ! empty($checked->kw) && ! empty($checked->customer_state) && ! empty($checked->location_code) && ! empty($checked->setter_id) && ! empty($checked->userDetail) &&
                    ! empty($checked->sales_rep_email)) {
                        // )
                        // !empty($checked->epc) &&
                        // !empty($checked->dealer_fee_percentage)  &&
                        // !empty($checked->sales_rep_name) &&
                        // print_r($val);
                        $insertData = '';
                        // $val['data_source_type'] = 'api';
                        $insertData = SalesMaster::create($val);
                        $data = [
                            'sale_master_id' => $insertData->id,
                            'weekly_sheet_id' => $insertData->weekly_sheet_id,
                            'pid' => $checked->pid,
                            'closer1_id' => $checked->userDetail->id,
                            'setter1_id' => isset($checked->setter_id) ? $checked->setter_id : null,
                        ];
                        SaleMasterProcess::create($data);
                        (new ApiMissingDataController)->subroutine_process_api_excel($checked->pid);
                    } else {
                        $response = ['data' => $checked->toArray(), 'message' => 'Condition not matched. '];
                        // print_r($response);
                    }
                } else {
                    $updateData = SalesMaster::where('pid', $checked->pid)->update($val);
                    $checkedCloser1_id = isset($checked->userDetail->id) ? $checked->userDetail->id : null;
                    if ($checkedCloser1_id != null) {
                        SaleMasterProcess::where(['pid' => $checked->pid])->update([
                            'closer1_id' => $checkedCloser1_id,
                            // 'setter1_id' => isset($checked->setter_id) ? $checked->setter_id : null,
                        ]);
                    }

                    if (! empty($salesMaster->m1_date) && ! empty($checked->m1_date) && ($salesMaster->m1_date != $checked->m1_date)) {
                        m1datePayrollData($checked->pid, $checked->m1_date);
                    }
                    if (! empty($salesMaster->m2_date) && ! empty($checked->m2_date) && ($salesMaster->m2_date != $checked->m2_date)) {
                        m2datePayrollData($checked->pid, $checked->m2_date);
                    }

                    if (! empty($salesMaster->m1_date) && empty($checked->m1_date)) {
                        m1dateSalesData($checked->pid);
                    }
                    if (! empty($salesMaster->m2_date) && empty($checked->m2_date)) {
                        m2dateSalesData($checked->pid, $salesMaster->m2_date);
                    }
                    if ($check > 0) {
                        (new ApiMissingDataController)->subroutine_process_api_excel($checked->pid);
                    }
                }
                $val = [];
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            log::info([
                'message: ' => $e->getMessage(),
                'file: ' => $e->getFile(),
                'line: ' => $e->getLine(),
            ]
            );
        }
    }
}

if (! function_exists('pest_excel_insert_update_sale_master')) {
    function pest_excel_insert_update_sale_master($user)
    {
        $successPID = [];
        $excelId = '';
        // try {
        User::query()->update(['email' => \DB::raw('LOWER(email)')]);
        LegacyApiRawDataHistory::query()->update(['sales_rep_email' => \DB::raw('LOWER(sales_rep_email)')]);
        $newData = LegacyApiRawDataHistory::with('userDetail')->where(['data_source_type' => 'excel', 'import_to_sales' => '0'])->groupBy('pid')->get();

        if (! class_exists('NewClass')) {
            class NewClass
            {
                use EditSaleTrait, SetterSubroutineListTrait;
            }
        }
        $editSaleTrait = new NewClass;

        $excelId = @$newData[0]->excel_import_id;
        $salesSuccessReport = [];
        $salesErrorReport = [];
        foreach ($newData as $checked) {
            // DB::beginTransaction();
            $check = 0;
            $salesMaster = SalesMaster::where('pid', $checked->pid)->first();

            $state = State::where('state_code', $checked->customer_state)->first();
            $saleMasterData = [
                'pid' => $checked->pid,
                'customer_name' => $checked->customer_name,
                'customer_address' => $checked->customer_address,
                'customer_address_2' => $checked->customer_address_2,
                'customer_city' => $checked->customer_city,
                'state_id' => isset($state->id) ? $state->id : null,
                'customer_state' => strtoupper(trim($checked->customer_state)),
                'location_code' => strtolower(trim($checked->location_code)),
                'customer_zip' => $checked->customer_zip,
                'customer_email' => $checked->customer_email,
                'customer_phone' => $checked->customer_phone,
                'sales_rep_email' => $checked->sales_rep_email,
                'install_partner' => $checked->install_partner,
                'customer_signoff' => $checked->customer_signoff,
                'm1_date' => $checked->m1_date,
                'm2_date' => $checked->m2_date,
                'install_complete_date' => $checked->install_complete_date,
                'date_cancelled' => $checked->date_cancelled,
                'gross_account_value' => $checked->gross_account_value,
                'product' => $checked->product,
                'length_of_agreement' => $checked->length_of_agreement,
                'service_schedule' => $checked->service_schedule,
                'initial_service_cost' => $checked->initial_service_cost,
                'subscription_payment' => $checked->subscription_payment,
                'card_on_file' => $checked->card_on_file,
                'auto_pay' => $checked->auto_pay,
                'service_completed' => $checked->service_completed,
                'last_service_date' => $checked->last_service_date,
                'bill_status' => $checked->bill_status,
                'data_source_type' => 'excel',
                'job_status' => $checked->job_status,
            ];

            $closer = User::where('id', $checked->closer1_id)->first();
            $isImportStatus = 1;
            if (! $salesMaster) {
                $null_table_val = $saleMasterData;
                $null_table_val['closer_id'] = $checked->closer1_id;
                $null_table_val['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : null;
                $null_table_val['sales_rep_email'] = isset($closer->email) ? $closer->email : null;
                $null_table_val['job_status'] = $checked->job_status;
                LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $null_table_val);

                $saleMaster = SalesMaster::create($saleMasterData);
                $saleMasterProcessData = [
                    'sale_master_id' => $saleMaster->id,
                    'pid' => $checked->pid,
                    'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : null,
                    'job_status' => $checked->job_status,
                ];
                SaleMasterProcess::create($saleMasterProcessData);

                // try {
                (new ApiMissingDataController)->pestSubroutineForExcel($saleMaster->pid);
                $salesSuccessReport[] = [
                    'is_error' => false,
                    'pid' => $checked->pid,
                    'message' => 'Success',
                    'realMessage' => 'Success',
                    'file' => '',
                    'line' => '',
                    'name' => '-',
                ];
                $excel = ExcelImportHistory::where('id', $excelId)->first();
                if ($excel) {
                    $excel->new_records = $excel->new_records + 1;
                    $excel->save();
                }
                // } catch (\Throwable $e) {
                //     $isImportStatus = 2;
                //     $salesErrorReport[] = [
                //         'is_error' => true,
                //         'pid' => $checked->pid,
                //         'message' => 'Error During Subroutine Process',
                //         'realMessage' => $e->getMessage(),
                //         'file' => $e->getFile(),
                //         'line' => $e->getLine(),
                //         'name' => '-'
                //     ];
                //     DB::rollBack();
                //     $excel = ExcelImportHistory::where('id', $excelId)->first();
                //     if ($excel) {
                //         $excel->error_records = $excel->error_records + 1;
                //         $excel->save();
                //     }
                // }
            } else {
                // try {
                $grossAmount = ($checked->gross_account_value == $salesMaster->gross_account_value) ? 0 : 1;
                $check_date_cancelled = ($checked->date_cancelled == $salesMaster->date_cancelled) ? 0 : 1;
                $check_customer_state = ($checked->customer_state == $salesMaster->customer_state) ? 0 : 1;
                $check_m1_date = ($checked->m1_date == $salesMaster->m1_date) ? 0 : 1;
                $check_m2_date = ($checked->m2_date == $salesMaster->m2_date) ? 0 : 1;

                $salesMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                salesDataChangesClawback($salesMasterProcess->pid);
                $check_closer = 0;
                if ($salesMasterProcess) {
                    $check_closer = ($checked->closer1_id == $salesMasterProcess->closer1_id) ? 0 : 1;
                }
                $check = ($grossAmount + $check_date_cancelled + $check_customer_state + $check_m1_date + $check_m2_date + $check_closer);

                $success = true;
                if ($check > 0) {
                    // M1 IS PAID & M1 DATE GETS REMOVED
                    if (! empty($salesMaster->m1_date) && empty($checked->m1_date)) {
                        if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $isImportStatus = 2;
                            $success = false;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Apologies, the M1 date cannot be removed because the upfront amount has already been paid',
                                'realMessage' => 'Apologies, the M1 date cannot be removed because the upfront amount has already been paid',
                                'file' => '',
                                'line' => '',
                                'name' => '-',
                            ];
                        } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                            $isImportStatus = 2;
                            $success = false;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Apologies, the M1 date cannot be removed because the M1 amount has finalized or executed from reconciliation',
                                'realMessage' => 'Apologies, the M1 date cannot be removed because the M1 amount has finalized or executed from reconciliation',
                                'file' => '',
                                'line' => '',
                                'name' => '-',
                            ];
                        } else {
                            $editSaleTrait->m1dateSalesData($checked->pid);
                        }
                    }

                    // M1 DATE GETS CHANGED
                    if (! empty($salesMaster->m1_date) && ! empty($checked->m1_date) && $salesMaster->m1_date != $checked->m1_date) {
                        if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $isImportStatus = 2;
                            $success = false;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Apologies, the M1 date cannot be changed because the upfront amount has already been paid',
                                'realMessage' => 'Apologies, the M1 date cannot be changed because the upfront amount has already been paid',
                                'file' => '',
                                'line' => '',
                                'name' => '-',
                            ];
                        } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                            $isImportStatus = 2;
                            $success = false;
                            $salesErrorReport[] = [
                                'is_error' => true,
                                'pid' => $checked->pid,
                                'message' => 'Apologies, the M1 date cannot be removed because the M1 amount has finalized or executed from reconciliation',
                                'realMessage' => 'Apologies, the M1 date cannot be removed because the M1 amount has finalized or executed from reconciliation',
                                'file' => '',
                                'line' => '',
                                'name' => '-',
                            ];
                        } else {
                            $editSaleTrait->m1datePayrollData($checked->pid, $checked->m1_date);
                        }
                    }

                    if ($success) {
                        // M2 IS PAID & M2 DATE GETS REMOVED
                        if (! empty($salesMaster->m2_date) && empty($checked->m2_date)) {
                            if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the M1 date cannot be removed because the commission amount has already been paid',
                                    'realMessage' => 'Apologies, the M1 date cannot be removed because the commission amount has already been paid',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the M2 date cannot be removed because the commission amount has finalized or executed from reconciliation',
                                    'realMessage' => 'Apologies, the M2 date cannot be removed because the commission amount has finalized or executed from reconciliation',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } elseif (ReconOverrideHistory::where(['pid' => $checked->pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the M2 date cannot be removed because the Override amount has finalized or executed from reconciliation',
                                    'realMessage' => 'Apologies, the M2 date cannot be removed because the Override amount has finalized or executed from reconciliation',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } else {
                                $editSaleTrait->m2dateSalesData($checked->pid, $salesMaster->m2_date);
                            }
                        }

                        // M2 DATE GETS CHANGED
                        if (! empty($salesMaster->m2_date) && ! empty($checked->m2_date) && $salesMaster->m2_date != $checked->m2_date) {
                            if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, The M2 date cannot be changed because the commission amount has already been paid',
                                    'realMessage' => 'Apologies, The M2 date cannot be changed because the commission amount has already been paid',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the M2 date cannot be changed because the commission amount has finalized or executed from reconciliation',
                                    'realMessage' => 'Apologies, the M2 date cannot be changed because the commission amount has finalized or executed from reconciliation',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } elseif (ReconOverrideHistory::where(['pid' => $checked->pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the M2 date cannot be changed because the Override amount has finalized or executed from reconciliation',
                                    'realMessage' => 'Apologies, the M2 date cannot be changed because the Override amount has finalized or executed from reconciliation',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } else {
                                $editSaleTrait->m2datePayrollData($checked->pid, $checked->m2_date);
                            }
                        }
                    }

                    if ($success) {
                        // CLOSER 1 GOT CHANGE
                        if (isset($salesMasterProcess->closer1_id) && isset($checked->closer1_id) && $checked->closer1_id != $salesMasterProcess->closer1_id) {
                            if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, The sales rep cannot be changed because the commission amount has already been paid',
                                    'realMessage' => 'Apologies, The sales rep cannot be changed because the commission amount has already been paid',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the sales rep be change because the commission amount has been finalized or executed from reconciliation',
                                    'realMessage' => 'Apologies, the sales rep be change because the commission amount has been finalized or executed from reconciliation',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } elseif (ReconOverrideHistory::where(['pid' => $checked->pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                $isImportStatus = 2;
                                $success = false;
                                $salesErrorReport[] = [
                                    'is_error' => true,
                                    'pid' => $checked->pid,
                                    'message' => 'Apologies, the sales sales rep be change because the Override amount has been finalized or executed from reconciliation',
                                    'realMessage' => 'Apologies, the sales rep cannot be change because the Override amount has been finalized or executed from reconciliation',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } else {
                                $editSaleTrait->clawbackSalesData($salesMasterProcess->closer1_id, $salesMaster);
                                $salesMasterProcess->closer1_m1 = 0;
                                $salesMasterProcess->job_status = $checked->job_status ?? null;
                                $salesMasterProcess->save();
                            }
                        }
                    }
                }

                if ($success) {
                    $data = [
                        'weekly_sheet_id' => $salesMaster->weekly_sheet_id,
                        'pid' => $checked->pid,
                        'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : null,
                        'job_status' => $checked->job_status,
                    ];
                    SaleMasterProcess::updateOrCreate(['pid' => $checked->pid], $data);
                    SalesMaster::where('pid', $checked->pid)->update($saleMasterData);

                    $closer = User::where('id', $checked->closer1_id)->first();
                    $null_table_val = $saleMasterData;
                    $null_table_val['closer_id'] = $checked->closer1_id;
                    $null_table_val['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : null;
                    $null_table_val['sales_rep_email'] = isset($closer->email) ? $closer->email : null;
                    $null_table_val['job_status'] = $checked->job_status;
                    LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $null_table_val);

                    if ($check > 0) {
                        if (! empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                            // When Clawback Is Paid, Sale Should Act As It's New Therefore
                            salesDataChangesBasedOnClawback($salesMaster->pid);
                            ClawbackSettlement::where(['pid' => $checked->pid, 'is_displayed' => '1', 'status' => '1'])->where('user_id', '!=', $checked->closer1_id)->delete();
                        }
                        (new ApiMissingDataController)->pestSubroutineForExcel($checked->pid);
                        $salesSuccessReport[] = [
                            'is_error' => false,
                            'pid' => $checked->pid,
                            'message' => 'Success',
                            'realMessage' => 'Success',
                            'file' => '',
                            'line' => '',
                            'name' => '-',
                        ];
                    } else {
                        $salesSuccessReport[] = [
                            'is_error' => false,
                            'pid' => $checked->pid,
                            'message' => 'Success!!',
                            'realMessage' => 'Success!!',
                            'file' => '',
                            'line' => '',
                            'name' => '-',
                        ];
                    }
                    $excel = ExcelImportHistory::where('id', $excelId)->first();
                    if ($excel) {
                        $excel->updated_records = $excel->updated_records + 1;
                        $excel->save();
                    }
                } else {
                    $excel = ExcelImportHistory::where('id', $excelId)->first();
                    if ($excel) {
                        $excel->error_records = $excel->error_records + 1;
                        $excel->save();
                    }
                }
                // } catch (\Throwable $e) {
                //     $isImportStatus = 2;
                //     $salesErrorReport[] = [
                //         'is_error' => true,
                //         'pid' => $checked->pid,
                //         'message' => 'Error During Subroutine Process',
                //         'realMessage' => $e->getMessage(),
                //         'file' => $e->getFile(),
                //         'line' => $e->getLine(),
                //         'name' => '-'
                //     ];
                //     DB::rollBack();
                //     $excel = ExcelImportHistory::where('id', $excelId)->first();
                //     if ($excel) {
                //         $excel->error_records = $excel->error_records + 1;
                //         $excel->save();
                //     }
                // }
            }

            // UPDATE STATUS IN HISTORY TABLE FOR EXECUTED SALES.
            LegacyApiRawDataHistory::where(['pid' => $checked->pid, 'data_source_type' => 'excel', 'import_to_sales' => '0'])->update(['import_to_sales' => $isImportStatus]);
            // DB::commit();
            $successPID[] = $checked->pid;
        }

        $excel = ExcelImportHistory::where('id', $excelId)->first();
        if ($excel) {
            $excel->updated_records = $excel->total_records - $excel->new_records - $excel->error_records;
            $excel->save();
        }
        dispatch(new GenerateAlertJob(implode(',', $successPID)));
    }
}

if (! function_exists('excel_insert_update_sale_master')) {
    function excel_insert_update_sale_master($user)
    {
        $successPID = [];
        $excelId = '';
        try {
            User::query()->update(['email' => \DB::raw('LOWER(email)')]);
            LegacyApiRawDataHistory::query()->update(['sales_rep_email' => \DB::raw('LOWER(sales_rep_email)')]);
            $newData = LegacyApiRawDataHistory::with('userDetail')->where(['data_source_type' => 'excel', 'import_to_sales' => '0'])
                ->whereIn('id', function ($q) {
                    $q->selectRaw('MAX(id)')->from('legacy_api_raw_data_histories as his')->whereColumn('his.pid', 'legacy_api_raw_data_histories.pid')->where(['data_source_type' => 'excel', 'import_to_sales' => '0'])->groupBy('pid');
                })->get();

            if (! class_exists('NewClass')) {
                class NewClass
                {
                    use EditSaleTrait, SetterSubroutineListTrait;
                }
            }
            $editSaleTrait = new NewClass;

            $excelId = @$newData[0]->excel_import_id;
            $salesSuccessReport = [];
            $salesErrorReport = [];
            $domainName = config('app.domain_name'); // Retrieve the value of DOMAIN_NAME
            foreach ($newData as $checked) {
                DB::beginTransaction();
                $check = 0;
                $salesMaster = SalesMaster::where('pid', $checked->pid)->first();
                if (! empty($checked->m2_date)) {
                    $m2_updated_date = $checked->m2_date; // Use the value directly
                } elseif (! empty($checked->m1_date)) {
                    try {
                        $date = new DateTime($checked->m1_date); // Ensure m1_date is valid
                        $date->modify('+90 days'); // Add 90 days
                        $m2_updated_date = $date->format('Y-m-d'); // Format as YYYY-MM-DD
                    } catch (Exception $e) {
                        $m2_updated_date = null; // Handle invalid m1_date gracefully
                    }
                } else {
                    $m2_updated_date = null; // Fallback to null if both dates are empty
                }

                $domainName = config('app.domain_name');
                if ($domainName == 'phoenixlending') {
                    $net_epc = ($checked->net_epc ?? 0) > 0 ? $checked->net_epc : 1;
                } else {
                    $net_epc = $checked->net_epc;
                }

                $saleMasterData = [
                    'pid' => $checked->pid,
                    'weekly_sheet_id' => null,
                    'install_partner' => $checked->install_partner,
                    'install_partner_id' => $checked->install_partner_id,
                    'customer_name' => $checked->customer_name,
                    'customer_address' => $checked->customer_address,
                    'customer_address_2' => $checked->customer_address_2,
                    'customer_city' => $checked->customer_city,
                    'customer_state' => $checked->customer_state,
                    'location_code' => $checked->location_code,
                    'customer_zip' => $checked->customer_zip,
                    'customer_email' => $checked->customer_email,
                    'customer_phone' => $checked->customer_phone,
                    'homeowner_id' => $checked->homeowner_id,
                    'proposal_id' => $checked->proposal_id,
                    'sales_rep_name' => $checked->sales_rep_name,
                    'employee_id' => $checked->employee_id,
                    'sales_rep_email' => $checked->sales_rep_email,
                    'kw' => $checked->kw,
                    'date_cancelled' => $checked->date_cancelled,
                    'customer_signoff' => $checked->customer_signoff,
                    'm1_date' => $checked->m1_date,
                    'm2_date' => ($domainName === 'onyx' && ! is_null($m2_updated_date)) ? $m2_updated_date : $checked->m2_date,
                    'product' => $checked->product,
                    'epc' => $checked->epc,
                    'net_epc' => $net_epc,
                    'gross_account_value' => $checked->gross_account_value,
                    'dealer_fee_percentage' => $checked->dealer_fee_percentage,
                    'adders' => $checked->adders,
                    'adders_description' => $checked->adders_description,
                    'funding_source' => $checked->funding_source,
                    'financing_rate' => $checked->financing_rate,
                    'financing_term' => $checked->financing_term,
                    'scheduled_install' => $checked->scheduled_install,
                    'install_complete_date' => $checked->install_complete_date,
                    'return_sales_date' => $checked->return_sales_date,
                    'cash_amount' => $checked->cash_amount,
                    'loan_amount' => $checked->loan_amount,
                    'redline' => $checked->redline,
                    'cancel_fee' => $checked->cancel_fee,
                    'data_source_type' => 'excel',
                    'job_status' => $checked->job_status,
                    'location_code' => strtolower(trim($checked->location_code)),
                    'customer_state' => strtoupper(trim($checked->customer_state)),
                ];

                $closer = User::where('id', $checked->closer1_id)->first();
                $setter = User::where('id', $checked->setter1_id)->first();
                $isImportStatus = 1;
                if (! $salesMaster) {
                    $null_table_val = $saleMasterData;
                    $null_table_val['setter_id'] = $checked->setter1_id;
                    $null_table_val['closer_id'] = $checked->closer1_id;
                    $null_table_val['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : null;
                    $null_table_val['sales_rep_email'] = isset($closer->email) ? $closer->email : null;
                    $null_table_val['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name.' '.$setter->last_name : null;
                    $null_table_val['sales_setter_email'] = isset($setter->email) ? $setter->email : null;
                    $null_table_val['job_status'] = $checked->job_status;
                    LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $null_table_val);

                    $saleMaster = SalesMaster::create($saleMasterData);
                    $saleMasterProcessData = [
                        'sale_master_id' => $saleMaster->id,
                        'weekly_sheet_id' => $saleMaster->weekly_sheet_id,
                        'pid' => $checked->pid,
                        'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : null,
                        'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : null,
                        'job_status' => $checked->job_status,
                    ];
                    SaleMasterProcess::create($saleMasterProcessData);

                    try {
                        (new ApiMissingDataController)->newSubroutineForExcel($saleMaster->pid);
                        $salesSuccessReport[] = [
                            'is_error' => false,
                            'pid' => $checked->pid,
                            'message' => 'Success',
                            'realMessage' => 'Success',
                            'file' => '',
                            'line' => '',
                            'name' => '-',
                        ];
                        $excel = ExcelImportHistory::where('id', $excelId)->first();
                        if ($excel) {
                            $excel->new_records = $excel->new_records + 1;
                            $excel->save();
                        }
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $salesErrorReport[] = [
                            'is_error' => true,
                            'pid' => $checked->pid,
                            'message' => 'Error During Subroutine Process',
                            'realMessage' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'name' => '-',
                        ];
                        DB::rollBack();
                        $excel = ExcelImportHistory::where('id', $excelId)->first();
                        if ($excel) {
                            $excel->error_records = $excel->error_records + 1;
                            $excel->save();
                        }
                    }
                } else {
                    try {
                        $check_kw = ($checked->kw == $salesMaster->kw) ? 0 : 1;
                        $check_net_epc = ($checked->net_epc == $salesMaster->net_epc) ? 0 : 1;
                        $check_date_cancelled = ($checked->date_cancelled == $salesMaster->date_cancelled) ? 0 : 1;
                        $check_customer_state = ($checked->customer_state == $salesMaster->customer_state) ? 0 : 1;
                        $check_m1_date = ($checked->m1_date == $salesMaster->m1_date) ? 0 : 1;
                        $check_m2_date = ($checked->m2_date == $salesMaster->m2_date) ? 0 : 1;

                        $salesMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                        salesDataChangesClawback($salesMasterProcess->pid);
                        $check_setter = 0;
                        $check_closer = 0;
                        if ($salesMasterProcess) {
                            $check_setter = ($checked->setter1_id == $salesMasterProcess->setter1_id) ? 0 : 1;
                            $check_closer = ($checked->closer1_id == $salesMasterProcess->closer1_id) ? 0 : 1;
                        }
                        $check = ($check_kw + $check_net_epc + $check_date_cancelled + $check_customer_state + $check_m1_date + $check_m2_date + $check_setter + $check_closer);

                        $success = true;
                        if ($check > 0) {
                            if (! empty($salesMaster->m1_date) && empty($checked->m1_date)) {
                                if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M1 date cannot be removed because the upfront amount has already been paid',
                                        'realMessage' => 'Apologies, the M1 date cannot be removed because the upfront amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M1 date cannot be removed because the upfront amount has finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the M1 date cannot be removed because the upfront amount has finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } else {
                                    $editSaleTrait->m1dateSalesData($checked->pid);
                                }
                            }

                            if (! empty($salesMaster->m1_date) && ! empty($checked->m1_date) && $salesMaster->m1_date != $checked->m1_date) {
                                if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M1 date cannot be changed because the upfront amount has already been paid',
                                        'realMessage' => 'Apologies, the M1 date cannot be changed because the upfront amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M1 date cannot be removed because the upfront amount has finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the M1 date cannot be removed because the upfront amount has finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } else {
                                    $editSaleTrait->m1datePayrollData($checked->pid, $checked->m1_date);
                                }
                            }

                            if (! empty($salesMaster->m2_date) && empty($checked->m2_date)) {
                                if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M2 date cannot be removed because the M2 amount has already been paid',
                                        'realMessage' => 'Apologies, the M2 date cannot be removed because the M2 amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M2 date cannot be removed because the M2 amount has finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the M2 date cannot be removed because the M2 amount has finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } elseif (ReconOverrideHistory::where(['pid' => $checked->pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M2 date cannot be removed because the Override amount has finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the M2 date cannot be removed because the Override amount has finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } else {
                                    $editSaleTrait->m2dateSalesData($checked->pid, $salesMaster->m2_date);
                                }
                            }

                            if (! empty($salesMaster->m2_date) && ! empty($checked->m2_date) && $salesMaster->m2_date != $checked->m2_date) {
                                if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M2 date cannot be changed because the M2 amount has already been paid',
                                        'realMessage' => 'Apologies, the M2 date cannot be changed because the M2 amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M2 date cannot be changed because the M2 amount has finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the M2 date cannot be changed because the M2 amount has finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } elseif (ReconOverrideHistory::where(['pid' => $checked->pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, the M2 date cannot be changed because the Override amount has finalized or executed from reconciliation',
                                        'realMessage' => 'Apologies, the M2 date cannot be changed because the Override amount has finalized or executed from reconciliation',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } else {
                                    $editSaleTrait->m2datePayrollData($checked->pid, $checked->m2_date);
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->closer1_id) && isset($checked->closer1_id) && $checked->closer1_id != $salesMasterProcess->closer1_id) {
                                    if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the closer be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } elseif (ReconOverrideHistory::where(['pid' => $checked->pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the sales closer be change because the Override amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the closer cannot be change because the Override amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } else {
                                        $editSaleTrait->clawbackSalesData($salesMasterProcess->closer1_id, $salesMaster);
                                        $salesMasterProcess->setter1_m1_paid_status = null;
                                        $salesMasterProcess->closer1_m1 = 0;
                                        $salesMasterProcess->job_status = $checked->job_status ?? null;
                                        $salesMasterProcess->save();
                                    }
                                }
                            }

                            if ($success) {
                                if (isset($salesMasterProcess->setter1_id) && isset($checked->setter1_id) && $checked->setter1_id != $salesMasterProcess->setter1_id) {
                                    if (UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The setter cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } elseif (ReconCommissionHistory::where(['pid' => $checked->pid, 'type' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the setter cannot be change because the M2 amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the setter cannot be change because the M2 amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } elseif (ReconOverrideHistory::where(['pid' => $checked->pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->first()) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, the setter cannot be change because the Override amount has been finalized or executed from reconciliation',
                                            'realMessage' => 'Apologies, the setter cannot be change because the Override amount has been finalized or executed from reconciliation',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } else {
                                        $editSaleTrait->clawbackSalesData($salesMasterProcess->setter1_id, $salesMaster);
                                        $salesMasterProcess->setter1_m1_paid_status = null;
                                        $salesMasterProcess->setter1_m1 = 0;
                                        $salesMasterProcess->job_status = $checked->job_status ?? null;
                                        $salesMasterProcess->save();
                                    }
                                }
                            }
                        }

                        if ($success) {
                            $data = [
                                'weekly_sheet_id' => $salesMaster->weekly_sheet_id,
                                'pid' => $checked->pid,
                                'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : null,
                                'setter1_id' => isset($checked->setter1_id) ? $checked->setter1_id : null,
                                'job_status' => $checked->job_status,
                            ];
                            SaleMasterProcess::updateOrCreate(['pid' => $checked->pid], $data);
                            SalesMaster::where('pid', $checked->pid)->update($saleMasterData);

                            $closer = User::where('id', $checked->closer1_id)->first();
                            $setter = User::where('id', $checked->setter1_id)->first();
                            $null_table_val = $saleMasterData;
                            $null_table_val['setter_id'] = $checked->setter1_id;
                            $null_table_val['closer_id'] = $checked->closer1_id;
                            $null_table_val['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : null;
                            $null_table_val['sales_rep_email'] = isset($closer->email) ? $closer->email : null;
                            $null_table_val['sales_setter_name'] = isset($setter->first_name) ? $setter->first_name.' '.$setter->last_name : null;
                            $null_table_val['sales_setter_email'] = isset($setter->email) ? $setter->email : null;
                            $null_table_val['job_status'] = $checked->job_status;
                            LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $null_table_val);

                            if ($check > 0) {
                                if (! empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                                    // When Clawback Is Paid, Sale Should Act As It's New Therefore
                                    salesDataChangesBasedOnClawback($salesMaster->pid);
                                }
                                (new ApiMissingDataController)->newSubroutineForExcel($checked->pid);
                                $salesSuccessReport[] = [
                                    'is_error' => false,
                                    'pid' => $checked->pid,
                                    'message' => 'Success',
                                    'realMessage' => 'Success',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } else {
                                $salesSuccessReport[] = [
                                    'is_error' => false,
                                    'pid' => $checked->pid,
                                    'message' => 'Success!!',
                                    'realMessage' => 'Success!!',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            }
                            $excel = ExcelImportHistory::where('id', $excelId)->first();
                            if ($excel) {
                                $excel->updated_records = $excel->updated_records + 1;
                                $excel->save();
                            }
                        } else {
                            $excel = ExcelImportHistory::where('id', $excelId)->first();
                            if ($excel) {
                                $excel->error_records = $excel->error_records + 1;
                                $excel->save();
                            }
                        }
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $salesErrorReport[] = [
                            'is_error' => true,
                            'pid' => $checked->pid,
                            'message' => 'Error During Subroutine Process',
                            'realMessage' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'name' => '-',
                        ];
                        DB::rollBack();
                        $excel = ExcelImportHistory::where('id', $excelId)->first();
                        if ($excel) {
                            $excel->error_records = $excel->error_records + 1;
                            $excel->save();
                        }
                    }
                }

                // UPDATE STATUS IN HISTORY TABLE FOR EXECUTED SALES.
                LegacyApiRawDataHistory::where(['pid' => $checked->pid, 'data_source_type' => 'excel', 'import_to_sales' => '0'])->update(['import_to_sales' => $isImportStatus]);
                DB::commit();
                $successPID[] = $checked->pid;
            }

            $excel = ExcelImportHistory::where('id', $excelId)->first();
            if ($excel) {
                $excel->updated_records = $excel->total_records - $excel->new_records - $excel->error_records;
                $excel->save();
            }
            dispatch(new GenerateAlertJob(implode(',', $successPID)));
        } catch (\Throwable $e) {
            dispatch(new GenerateAlertJob(implode(',', $successPID)));
            LegacyApiRawDataHistory::where(['data_source_type' => 'excel', 'import_to_sales' => '0'])->whereNotIn('pid', $successPID)->update(['import_to_sales' => '2']);
            if ($excelId) {
                $excel = ExcelImportHistory::where('id', $excelId)->first();
                if ($excel) {
                    $excel->error_records = $excel->total_records - $excel->new_records - $excel->updated_records;
                    $excel->save();
                }
            }
            Log::info([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $errors[] = [
                'pid' => '',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
    }
}

if (! function_exists('user_activity_log')) {
    function user_activity_log($page, $action, $description)
    {
        $userId = auth::user()->id;
        $firstName = auth::user()->first_name;
        $lastName = auth::user()->Last_name;
        $userName = $firstName.' '.$lastName;
        try {
            $data = UserActivityLog::Create([
                'user_id' => $userId,
                'user_name' => $userName,
                'page' => $page,
                'action' => $action,
                'description' => $description,
            ]);

            return $data;
        } catch (\Exception $e) {
        }
    }
}

if (! function_exists('resolve_sale_and_alert')) {
    function resolve_sale_and_alert($email)
    {
        $legacydata = LegacyApiNullData::where('sales_rep_email', $email)->whereNotNull('missingrep_alert')->select('pid')->get();
        foreach ($legacydata as $ld) {
            insert_update_sale_master($ld['pid']); // helper function
        }
    }
}

if (! function_exists('generateRandomPassword')) {
    function generateRandomPassword(): array
    {
        $characters = '$@!&_*0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';

        for ($i = 0; $i < 5; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $password .= $characters[$index];
        }

        return [
            'plain_password' => $password,
            'hash_password' => Hash::make($password),
        ];

    }
}

if (! function_exists('generate_ticket_id_number')) {

    function generate_ticket_id_number($ticketId, $prefix): string
    {
        [$_, $number] = explode($prefix, $ticketId);

        return $prefix.sprintf('%04d', (++$number));
    }
}

if (! function_exists('check_symbols_in_data')) {
    function check_symbols_in_data($string, $symbols_arr = [])
    {
        // $symbols_arr=['+','-','/','*','=','(',')','!','%']
        // $data = '=1000-10+234/324234=342%';
        foreach ($symbols_arr as $key => $symbol) {
            if (strpos($string, $symbol) !== false) {
                return $symbols_arr[$key];
            }
        }

        return '';
    }
}

if (!function_exists('create_paystub_employee')) {
    function create_paystub_employee($detail = [], $isOneTime = 0)
    {
        $companyProfile = CompanyProfile::first();
        if (!empty($detail) && count($detail) > 0) {
            $usersData = User::where('id', $detail['user_id'])->get();
        } else {
            $usersData = User::get();
        }

        foreach ($usersData as $userData) {
            $dataToBeCreate = [
                'user_id' => $userData['id'],
                'user_employee_id' => $userData['employee_id'],
                'user_first_name' => $userData['first_name'],
                'user_middle_name' => $userData['middle_name'],
                'user_last_name' => $userData['last_name'],
                'user_zip_code' => $userData['zip_code'],
                'user_email' => $userData['email'],
                'user_work_email' => $userData['work_email'],
                'user_home_address' => $userData['home_address'],
                'user_position_id' => $userData['position_id'],
                'user_social_sequrity_no' => $userData['social_sequrity_no'],

                'user_entity_type' => $userData['entity_type'],
                'user_business_name' => $userData['business_name'],
                'user_business_type' => $userData['business_type'],
                'user_business_ein' => $userData['business_ein'],

                'user_name_of_bank' => $userData['name_of_bank'],
                'user_routing_no' => $userData['routing_no'],
                'user_account_no' => $userData['account_no'],
                'user_type_of_account' => $userData['type_of_account'],

                'company_name' => $companyProfile['name'],
                'company_address' => $companyProfile['address'],
                'company_website' => $companyProfile['website'],
                'company_phone_number' => $companyProfile['phone_number'],
                'company_type' => $companyProfile['type'],
                'company_email' => $companyProfile['email'],
                'company_business_name' => $companyProfile['business_name'],
                'company_mailing_address' => $companyProfile['mailing_address'],
                'company_business_ein' => $companyProfile['business_ein'],
                'company_business_phone' => $companyProfile['business_phone'],
                'company_business_address' => $companyProfile['business_address'],
                'company_business_city' => $companyProfile['business_city'],
                'company_business_state' => $companyProfile['business_state'],
                'company_business_zip' => $companyProfile['business_zip'],
                'company_mailing_state' => $companyProfile['mailing_state'],
                'company_mailing_city' => $companyProfile['mailing_city'],
                'company_mailing_zip' => $companyProfile['mailing_zip'],
                'company_time_zone' => $companyProfile['time_zone'],
                'company_business_address_1' => $companyProfile['business_address_1'],
                'company_business_address_2' => $companyProfile['business_address_2'],
                'company_business_lat' => $companyProfile['business_lat'],
                'company_business_long' => $companyProfile['business_long'],
                'company_mailing_address_1' => $companyProfile['mailing_address_1'],
                'company_mailing_address_2' => $companyProfile['mailing_address_2'],
                'company_mailing_lat' => $companyProfile['mailing_lat'],
                'company_mailing_long' => $companyProfile['mailing_long'],
                'company_business_address_time_zone' => $companyProfile['business_address_time_zone'],
                'company_mailing_address_time_zone' => $companyProfile['mailing_address_time_zone'],
                'company_margin' => $companyProfile['margin'],
                'company_country' => $companyProfile['country'],
                'company_logo' => $companyProfile['logo'],
                'company_lat' => $companyProfile['lat'],
                'company_lng' => $companyProfile['lng']
            ];

            $datacount = paystubEmployee::where('user_id', $userData['id'])->count();
            if (!empty($detail) && count($detail) > 0) {
                $dataToBeCreate['pay_period_from'] = $detail['pay_period_from'];
                $dataToBeCreate['pay_period_to'] = $detail['pay_period_to'];
                if ($isOneTime) {
                    $dataToBeCreate['is_onetime_payment'] = 1;
                    $dataToBeCreate['one_time_payment_id'] = $detail['one_time_payment_id'];
                }
                paystubEmployee::create($dataToBeCreate);
            } elseif ($datacount == 0) {
                $dataToBeCreate['pay_period_from'] = null;
                $dataToBeCreate['pay_period_to'] = null;
                paystubEmployee::create($dataToBeCreate);
            }
        }
    }
}

if (! function_exists('isUndefinedSignature')) {

    function isUndefinedSignature($signature)
    {

        if ($signature === 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAAAyCAYAAACqNX6+AAAAAXNSR0IArs4c6QAABSJJREFUeF7tmnWopVUUxX+jmJjYomJi/GEgFnZgd3d3MXZ3i44NioGFiKJiJ+YwKoqJHSg6iIGKioWi+wf7g6u89+Z9zr3vHblnw/Dm3Xu+c/dZa6+197kzY6hRFAJjisqmJkMlpLAiqIRUQgpDoLB0qkIqIYUhUFg6VSGVkMIQKCydqpBKSGEIFJZOVUglpDAECkunKqQSUhgChaVTFVIJKQyBwtKpCqmEFIZAYelUhVRCCkOgsHSqQiohhSFQWDpVIZWQwhAoLJ2qkEpIYQgUlk5VSCXkHwjMAPw0CUwsmoOBq3qE3ZTANMDPPdq/1bajqZAlgReBpYFPhsh6O+AO6Nl/e70GkJR9WyHXo8WjRcgUwNPAXMASwF+DnM91bwKfARvmmumBNYBHuoDJusATwPbAnV3Yb7K3GC1Ctgpl3A3sBdw4xCmadTsFcbfnusuAw4EZh2F3QwHUkD0toFp/n2w0u7BBNwhZKirs7Ra5+JmvAX8AKwJ/5rO+PmuA/W3+LmCvDrDuZOC7YfaUqYDpgB8GyG9z4F7An/e3yL+nS9sSYhO+EPgifPesqKoTgXMAwdN25g8v/jzWaCtn5EGfSY/eLZ47AJgYNrQNsDowPk+3A3A5MCewMfAwsBlwX1jbWoB7CO6RwPeAvm+4XrB/A/bPNVfmz1OCbP8YsyXRi8XzY4FlgtA5QqGfAuv3FOGWm7cl5BZg15hIjk1VPJCgW2XnBjAnAKsClwIrJGEC7XPaTxNPAvq3cRRwESCQh8Z+ywPvpjo+SoJcd130kn2i4i8Aju8gzGa8Sr73eAwKGwXg90T1LxdgT8jfZ09iH/0XPp1F0RK63ixvQ4g+flsA/WCC+FL6+KLRBxbM5nhtEqKN/QgsC9ycr72QxDi+rgM8laTYVK3u/YCXgUOSUPuEpPramjkEuOciqcgPgLeAj4GdE54tkojTwxa3zM87KFVl8RinxUCwchSBanc4KCraEGK1vgMcFwexwrULD3dmVP/reSr9f4EgYWFAAh0nb811N+VzWoW9wt7xUFbw+2lDl6RKVJXV3ExWkqrlNfalolTW9akUc7kaOCLy+yVzsa+5xmnM3I0NgG3j3nEecFiqUgwGm/JGnKzhEuLF6VdgXFqWABhOJ9qX05IhCI6zq+U6R8l5s9JVwnwdQJ8NnAS8Eb1kvVDG10G4vcS+5N3E0L60I+3MfddOAgVf4lWMPez8HJ8lSMt0cjswBwLJs29ohxaDijF/9/0GOCZ724iDP9AHDpcQn/0yDyKA74VdeGEzrEJV4zSjxTRW9lX6/cUJnHbmiGm/8W4hGAI8c/YebWnqVNupHcnaf+wB9oZXws5mySlt6xwOdo9+o1UZVv0Vue4GYO+8X1gw8+QaP1+7vCuGh5XyWfctItoQsmlOJNqUDXyXuGHvmfcJm6224NSkbentNn8vdNrbYzmVWckqypu37zs1+ZohkEcDer1jsQAK9ExJoiOqe/lZc6d67EfeZywEe5D21NigBWGuqkny/Lt2ZSGYo+pyf8kpJtoQ0pm0zznqNneIzveG+n5KcBfq6Dk+p/UIkt8laW+OxQJsv7Cam8brKD3Q902qyui82C0ehH84SH6udQJzKJjU92gjTtR/JaRXiW6SCrFneTNXKQ4NfROlEeLFUYvZMS+KVroXwb6J0ghxOnsu0bfJP9s3TORBSyPEfPaI+8LzOcn1Gx89+zeGvgOyWwcuTSHdOtf/dp9KSGHUVUIqIYUhUFg6fwMtz+kzTNy/rwAAAABJRU5ErkJggg==') {
            return true;
        }

        return false;

    }

}

if (! function_exists('isS3ImageAccessible')) {

    function isS3ImageAccessible($imgUrl)
    {

        $response = Http::head($imgUrl);

        return $response->successful();

    }

}

if (! function_exists('salesDataChangesBasedOnClawback')) {
    // When Clawback Is Paid, Sale Should Act As It's New Therefore
    function salesDataChangesBasedOnClawback($pid)
    {
        $sale = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        if ($sale && @$sale->salesMasterProcess) {
            $saleProcess = $sale->salesMasterProcess;
            $setter1 = $saleProcess->setter1_id;
            $setter2 = $saleProcess->setter2_id;
            $closer1 = $saleProcess->closer1_id;
            $closer2 = $saleProcess->closer2_id;

            $saleUsers = [];
            $existWorker = ExternalSaleWorker::where('pid', $pid)->pluck('user_id')->toArray();
            if ($existWorker) {
                $saleUsers = array_merge($saleUsers, $existWorker);
            }

            if ($closer1) {
                $saleUsers[] = $closer1;
            }
            if ($closer2) {
                $saleUsers[] = $closer2;
            }
            if ($setter1) {
                $saleUsers[] = $setter1;
            }
            if ($setter2) {
                $saleUsers[] = $setter2;
            }

            $commissionClawBack = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'next payroll', 'status' => '1', 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->get();
            $overrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'next payroll', 'status' => '1', 'type' => 'overrides', 'is_displayed' => '1'])
            ->where(function($query) use ($saleUsers) {
                $query->whereIn('sale_user_id', $saleUsers)
                        ->orWhere('adders_type', 'One Time');
            })->get();
            $clawbBacks = $commissionClawBack->merge($overrideClawBack);
            foreach ($clawbBacks as $clawbBack) {
                $clawbBack->delete();
            }

            $commissions = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'next payroll', 'status' => '3', 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->get();
            foreach ($commissions as $commission) {
                $commission->is_displayed = '0';
                $commission->save();

                UserCommission::where(['user_id' => $commission->user_id, 'amount_type' => $commission->adders_type, 'status' => '3', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                ReconCommissionHistory::where(['user_id' => $commission->user_id, 'pid' => $pid, 'type' => $commission->adders_type, 'during' => $commission->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
            }

            $commissions = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'reconciliation', 'status' => '3', 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->get();
            foreach ($commissions as $commission) {
                $reconCb = ReconClawbackHistory::where([
                    'pid' => $pid,
                    'user_id' => $commission->user_id,
                    'type' => 'commission',
                    'adders_type' => $commission->amount_type,
                    'during' => $commission->during,
                    'is_displayed' => '1',
                ])->sum('paid_amount');
                if ($reconCb) {
                    UserCommission::where(['user_id' => $commission->user_id, 'amount_type' => $commission->adders_type, 'status' => '3', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                    ReconCommissionHistory::where(['user_id' => $commission->user_id, 'pid' => $pid, 'type' => $commission->adders_type, 'during' => $commission->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
                    $commission->is_displayed = '0';
                    $commission->save();
                } else {
                    $commission->delete();
                }
            }

            $overrides = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'next payroll', 'status' => '3', 'type' => 'overrides', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->get();
            foreach ($overrides as $override) {
                $override->is_displayed = '0';
                $override->save();

                UserOverrides::where(['pid' => $pid, 'user_id' => $override->user_id, 'sale_user_id' => $override->sale_user_id, 'type' => $override->adders_type, 'during' => $override->during, 'is_displayed' => '1'])->whereIn('settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $override->user_id, 'overrider' => $override->sale_user_id, 'type' => $override->adders_type, 'during' => $override->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
            }

            $overrides = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'reconciliation', 'status' => '3', 'type' => 'overrides', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->get();
            foreach ($overrides as $override) {
                $reconCb = ReconClawbackHistory::where([
                    'pid' => $pid,
                    'user_id' => $override->user_id,
                    'sale_user_id' => $override->sale_user_id,
                    'type' => 'overrides',
                    'adders_type' => $override->amount_type,
                    'during' => $override->during,
                    'is_displayed' => '1',
                ])->sum('paid_amount');
                if ($reconCb) {
                    UserOverrides::where(['pid' => $pid, 'user_id' => $override->user_id, 'sale_user_id' => $override->sale_user_id, 'type' => $override->adders_type, 'during' => $override->during, 'is_displayed' => '1'])->whereIn('settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                    ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $override->user_id, 'overrider' => $override->sale_user_id, 'type' => $override->adders_type, 'during' => $override->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
                    $override->is_displayed = '0';
                    $override->save();
                } else {
                    $override->delete();
                }
            }
        }
    }
}

if (! function_exists('salesDataChangesClawback')) {
    function salesDataChangesClawback($pid)
    {
        $sale = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        if ($sale && @$sale->salesMasterProcess) {
            $clawBackCommissions = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'next payroll', 'status' => '3', 'type' => 'commission', 'is_displayed' => '1'])->get();
            foreach ($clawBackCommissions as $clawBackCommission) {
                $clawBackCommission->is_displayed = '0';
                $clawBackCommission->save();

                UserCommission::where(['user_id' => $clawBackCommission->user_id, 'amount_type' => $clawBackCommission->adders_type, 'schema_type' => $clawBackCommission->schema_type, 'status' => '3', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                ReconCommissionHistory::where(['user_id' => $clawBackCommission->user_id, 'pid' => $pid, 'type' => $clawBackCommission->adders_type, 'during' => $clawBackCommission->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
            }

            $clawBackReconCommissions = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'reconciliation', 'status' => '3', 'recon_status' => '3', 'type' => 'commission', 'is_displayed' => '1'])->get();
            foreach ($clawBackReconCommissions as $clawBackReconCommission) {
                $clawBackReconCommission->is_displayed = '0';
                $clawBackReconCommission->save();

                UserCommission::where(['user_id' => $clawBackReconCommission->user_id, 'amount_type' => $clawBackReconCommission->adders_type, 'schema_type' => $clawBackReconCommission->schema_type, 'status' => '3', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                ReconCommissionHistory::where(['user_id' => $clawBackReconCommission->user_id, 'pid' => $pid, 'type' => $clawBackReconCommission->adders_type, 'during' => $clawBackReconCommission->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
            }

            $clawBackOverrides = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'next payroll', 'status' => '3', 'type' => 'overrides', 'is_displayed' => '1'])->get();
            foreach ($clawBackOverrides as $clawBackOverride) {
                $clawBackOverride->is_displayed = '0';
                $clawBackOverride->save();

                UserOverrides::where(['pid' => $pid, 'user_id' => $clawBackOverride->user_id, 'sale_user_id' => $clawBackOverride->sale_user_id, 'type' => $clawBackOverride->adders_type, 'during' => $clawBackOverride->during, 'is_displayed' => '1'])->whereIn('overrides_settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $clawBackOverride->user_id, 'overrider' => $clawBackOverride->sale_user_id, 'type' => $clawBackOverride->adders_type, 'during' => $clawBackOverride->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
            }

            $clawBackReconOverrides = ClawbackSettlement::where(['pid' => $pid, 'clawback_type' => 'reconciliation', 'status' => '3', 'recon_status' => '3', 'type' => 'overrides', 'is_displayed' => '1'])->get();
            foreach ($clawBackReconOverrides as $clawBackReconOverride) {
                $clawBackReconOverride->is_displayed = '0';
                $clawBackReconOverride->save();

                UserOverrides::where(['pid' => $pid, 'user_id' => $clawBackReconOverride->user_id, 'sale_user_id' => $clawBackReconOverride->sale_user_id, 'type' => $clawBackReconOverride->adders_type, 'during' => $clawBackReconOverride->during, 'is_displayed' => '1'])->whereIn('overrides_settlement_type', ['during_m2', 'reconciliation'])->update(['is_displayed' => '0']);
                ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $clawBackReconOverride->user_id, 'overrider' => $clawBackReconOverride->sale_user_id, 'type' => $clawBackReconOverride->adders_type, 'during' => $clawBackReconOverride->during, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
            }
        }
    }
}

if (! function_exists('getExportBaseUrl')) {
    function getExportBaseUrl()
    {
        if (function_exists('tenant')) {
            $url = 'https://'.tenant()->id.'.api.sequifi.com';

            if (str_contains(config('app.url'), '.cloud')) {
                $url = 'https://'.tenant()->id.'.api.sequifi.cloud';
            }

            if (config('app.env') === 'local') {
                $url = 'http://'.tenant()->id.'.sequifi.test';
            }

            if (config('app.domain_name') === 'demo') {
                $url .= '/public/';
            } elseif (config('app.domain_name') === 'dev1') {
                $url .= '/';
            } else {
                $url .= '/';
            }

            return $url;
        } else {
            $url = config('app.base_url') ? \Config::get('app.base_url') : \Config::get('app.url');
            if (config('app.domain_name') === 'demo') {
                $url .= '/public/';
            } elseif (config('app.domain_name') === 'dev1') {
                $url .= '/';
            } else {
                $url .= '/';
            }

            return $url;
        }
    }
}

if (! function_exists('encryptData')) {
    function encryptData($data)
    {
        $key = config('app.mail_send_encryption_key');
        // Remove the base64 encoding from our key
        $encryption_key = base64_decode($key);
        // Generate an initialization vector
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        // Encrypt the data using AES 256 encryption in CBC mode using our encryption key and initialization vector.
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
        // The $iv is just as important as the key for decrypting, so save it with our encrypted data using a unique separator (::)
        $encrypted_data = base64_encode($encrypted.'::'.$iv);

        // if (str_contains($encrypted_data, '/')) {
        //     encryptData($data);
        // }
        $encrypted_data = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encrypted_data));

        return $encrypted_data;
    }
}

if (! function_exists('decryptData')) {
    function decryptData($encryptedData)
    {
        $key = config('app.mail_send_encryption_key');
        // Remove the base64 encoding from our key
        $encryption_key = base64_decode($key);

        $encryptedData = base64_decode(str_replace(['-', '_'], ['+', '/'], $encryptedData));

        // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
        [$encrypted_data, $iv] = explode('::', base64_decode($encryptedData), 2);

        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
    }
}

if (! function_exists('createLogFile')) {
    function createLogFile($fileName, $content)
    {
        $path = storage_path('logs/').$fileName.'.log';
        if (! \File::exists($path)) {
            // If the file doesn't exist, create it and write initial content
            \File::put($path, print_r($content, true)."\n");
        } else {
            // If the file exists, append content to it
            \File::append($path, print_r($content, true)."\n");
        }
    }
}

if (! function_exists('removeHttp')) {
    // Reusable function to remove http:// or https:// from a URL
    function removeHttp($url)
    {
        return preg_replace('~^(?:f|ht)tps?://~i', '', $url);
    }

}

if (! function_exists('exportNumberFormat')) {
    function exportNumberFormat($value)
    {
        return number_format($value, 2, '.', ',');
    }
}

if (! function_exists('isEncrypted')) {
    function isEncrypted($value)
    {
        return dataDecrypt($value);
    }
}

if (! function_exists('dataDecrypt')) {
    function dataDecrypt($encryptedData)
    {
        $method = config('app.encryption_cipher_algo', 'aes-256-cbc');
        $key = config('app.encryption_key');
        $iv = config('app.encryption_iv');
        
        // If encryption variables are not set, return the data as-is
        if (empty($method) || empty($key) || empty($iv)) {
            \Log::warning('Encryption variables not set, returning data as-is', [
                'method' => $method ? 'set' : 'missing',
                'key' => $key ? 'set' : 'missing', 
                'iv' => $iv ? 'set' : 'missing'
            ]);
            return $encryptedData;
        }
        
        $decryptData = base64_decode($encryptedData);
        $decrypted = openssl_decrypt($decryptData, $method, $key, 0, $iv);
        
        // If decryption fails, return original data
        if ($decrypted === false) {
            \Log::warning('Decryption failed, returning original data');
            return $encryptedData;
        }
        
        return $decrypted;
    }
}

if (! function_exists('dataEncrypt')) {
    function dataEncrypt($data)
    {
        $method = config('app.encryption_cipher_algo', 'aes-256-cbc');
        $key = config('app.encryption_key');
        $iv = config('app.encryption_iv');
        
        // If encryption variables are not set, return the data as-is
        if (empty($method) || empty($key) || empty($iv)) {
            \Log::warning('Encryption variables not set, returning data as-is');
            return $data;
        }
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        // If encryption fails, return original data
        if ($encrypted === false) {
            \Log::warning('Encryption failed, returning original data');
            return $data;
        }

        return base64_encode($encrypted);
    }
}

/**
 * Method getFilterDate : This function return date as per filter name
 *
 * @param  $filterName  $filterName [explicit description]
 * @return array
 */
if (! function_exists('getFilterDate')) {
    function getFilterDate($filterName)
    {
        $startDate = '';
        $endDate = '';
        if ($filterName == 'this_week') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterName == 'last_week') {
            $startOfLastWeek = \Carbon\Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = \Carbon\Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));
        } elseif ($filterName == 'this_month') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->endOfMonth()));
        } elseif ($filterName == 'last_month') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));
        } elseif ($filterName == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterName == 'last_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
        } elseif ($filterName == 'this_year') {
            $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
            $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
        } elseif ($filterName == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
        } elseif ($filterName == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }
}

if (! function_exists('sortData')) {
    function sortData($data, $field, $direction)
    {
        $data = $data->toArray();

        if ($direction == 'desc') {
            array_multisort(array_column($data, $field), SORT_DESC, $data);
        } else {
            array_multisort(array_column($data, $field), SORT_ASC, $data);
        }

        return collect($data);
    }
}

if (! function_exists('pagination')) {
    function pagination($items, $perPage = 10, $page = null)
    {
        // Determine the current page
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        // Handle cases where $items is a Collection
        if ($items instanceof Collection) {
            $items = $items->all();
        }

        // Handle cases where $items is an object with a paginate method
        elseif (is_object($items) && method_exists($items, 'paginate')) {
            return $items->paginate($perPage);
        }

        // Ensure $items is an array
        elseif (! is_array($items)) {
            return 'Neither Collection, Array, nor paginatable object';
        }

        // Calculate the total number of items
        $total = count($items);

        // Slice the array to get the items for the current page
        $start = ($page - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        // Create the paginator
        return new LengthAwarePaginator($sliced, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }
}
if (! function_exists('commissionBreakdownSum')) {
    function commissionBreakdownSum($userId, $pid)
    {
        $totalCommission = SaleMasterProcess::select(
            DB::raw("CASE
                    WHEN closer1_id = $userId THEN closer1_commission
                    WHEN setter1_id = $userId THEN setter1_commission
                    WHEN closer2_id = $userId THEN closer2_commission
                    WHEN setter2_id = $userId THEN setter2_commission
                    ELSE NULL
                END AS user_commission"),
        )
            ->where('pid', $pid)->where(function ($query) use ($userId) {
                $query->where('closer1_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter2_id', $userId);
            })->first();
        $m1Amount = UserCommission::where('pid', $pid)->where('user_id', $userId)->where('amount_type', 'm1');
        $m2Amount = UserCommission::where('pid', $pid)->where('user_id', $userId)->where('amount_type', 'm2');

        $saleData = SalesMaster::where('pid', $pid)->first();
        if (! $saleData->date_cancelled) {
            $reconAmount = floatval($totalCommission->user_commission) - (floatval($m1Amount->sum('amount')) + floatval($m2Amount->sum('amount')));
        } else {
            $reconAmount = -1 * ReconciliationFinalizeHistory::where('pid', $pid)->where('user_id', $userId)->where('status', 'clawback')->sum('net_amount');
        }

        return [
            'pid' => $pid,
            'totalCommission' => floatval($totalCommission->user_commission),
            'm1Amount' => $m1Amount->sum('amount'),
            'm2Amount' => $m2Amount->sum('amount'),
            'reconAmount' => $reconAmount,
            'm2AmountMoveToRecon' => $m2Amount->first()?->is_move_to_recon,
            'm1AmountMoveToRecon' => $m1Amount->first()?->is_move_to_recon,
        ];
    }

    return [
        'startDate' => @$startDate,
        'endDate' => @$endDate,
    ];
}

if (! function_exists('getdates')) {
    function getdates()
    {
        $trigger_date = ['m1_date', 'm2_date', 'install_complete_date'];
        $custom_field_names = Crmcustomfields::where('type', 'date')->pluck('name')->toArray();

        return array_merge($trigger_date, $custom_field_names);
    }
}

/**
 * Validate if the given startDate and endDate fall within any valid season.
 */
function seasonValidator(string $startDate, string $endDate): bool
{
    $startDateObj = Carbon::parse($startDate);
    $endDateObj = Carbon::parse($endDate);

    // Check if startDate is less than or equal to endDate
    if ($startDateObj->gt($endDateObj)) {
        return false; // Return false if startDate is greater than endDate
    }

    // Define the range of years you want to check, including past and future seasons
    $startYear = Carbon::now()->year - 100; // Start year (e.g., 100 years ago)
    $futureYear = Carbon::now()->year + 100; // Future year (e.g., 100 years into the future)

    $inSeason = false; // Flag to check if dates fall in any season

    for ($year = $startYear; $year <= $futureYear; $year++) {
        // Season starts on October 1st of the current year and ends on September 30th of the following year
        $seasonStartDate = Carbon::create($year, 10, 1);  // October 1st of the current year
        $seasonEndDate = Carbon::create($year + 1, 9, 30); // September 30th of the next year

        // Check if both start date and end date fall within this season
        if ($startDateObj->between($seasonStartDate, $seasonEndDate) && $endDateObj->between($seasonStartDate, $seasonEndDate)) {
            $inSeason = true;
            break; // Exit the loop once a valid season is found
        }
    }

    return $inSeason;

}

// need to work here
if (! function_exists('field_routes_create_raw_data_history_api')) {
    function field_routes_create_raw_data_history_api($data)
    {
        $dataCreate = [

            'pid' => isset($data['pid']) ? $data['pid'] : null,
            'customer_name' => isset($data['customer_name']) ? $data['customer_name'] : null,
            'customer_address' => isset($data['customer_address']) ? $data['customer_address'] : null,
            'customer_city' => isset($data['customer_city']) ? $data['customer_city'] : null,
            'customer_state' => isset($data['customer_state']) ? $data['customer_state'] : null,
            'customer_zip' => isset($data['customer_zip']) ? $data['customer_zip'] : null,
            'customer_email' => isset($data['customer_email']) ? $data['customer_email'] : null,
            'customer_phone' => isset($data['customer_phone']) ? $data['customer_phone'] : null,
            'card_on_file' => isset($data['card_on_file']) ? $data['card_on_file'] : null,
            'bill_status' => isset($data['bill_status']) ? $data['bill_status'] : null,
            'auto_pay' => isset($data['auto_pay']) ? $data['auto_pay'] : null,
            'date_cancelled' => isset($data['date_cancelled']) ? $data['date_cancelled'] : null,
            'gross_account_value' => isset($data['gross_account_value']) ? $data['gross_account_value'] : null,
            'product' => isset($data['product']) ? $data['product'] : null,
            'length_of_agreement' => isset($data['length_of_agreement']) ? $data['length_of_agreement'] : null,
            'service_schedule' => isset($data['service_schedule']) ? $data['service_schedule'] : null,
            'initial_service_cost' => isset($data['initial_service_cost']) ? $data['initial_service_cost'] : null,
            'subscription_payment' => isset($data['subscription_payment']) ? $data['subscription_payment'] : null,
            'service_completed' => isset($data['service_completed']) ? $data['service_completed'] : 0,
            'm1_date' => null,
            'm2_date' => isset($data['m2_date']) ? $data['m2_date'] : null,
            'last_service_date' => isset($data['last_service_date']) ? $data['last_service_date'] : null,
            'initial_service_date' => isset($data['initial_service_date']) ? $data['initial_service_date'] : null,
            'customer_signoff' => isset($data['customer_signoff']) ? $data['customer_signoff'] : null,
            'sales_rep_name' => isset($data['sales_rep_name']) ? $data['sales_rep_name'] : null,
            'sales_rep_email' => isset($data['sales_rep_email']) ? $data['sales_rep_email'] : null,
            'data_source_type' => 'evoPest_field_routes',
        ];
        Log::info(['dataCreate==>' => $dataCreate]);
        // $dataCreate = $data;
        // $dataCreate['data_source_type'] = 'evoPest_field_routes';
        $history = LegacyApiRawDataHistory::where('pid', $dataCreate['pid'])->orderBy('id', 'DESC')->first();

        if (empty($history)) {
            Log::info(['not_found==>' => $dataCreate['pid']]);
            $create_raw_data_history = LegacyApiRawDataHistory::create($dataCreate);
            Log::info(['LegacyApiRawDataHistory==>' => 'inserted']);
            $new_pid_null = $dataCreate['pid'];
        } else {
            Log::info(['found==>' => $history->pid]);
            $history_data = LegacyApiRawDataHistory::where('pid', $history->pid)->where($dataCreate)->first();
            if (empty($history_data)) {
                $create_raw_data_history = LegacyApiRawDataHistory::create($dataCreate);
                Log::info(['LegacyApiRawDataHistory==>' => 'inserted/updated']);
                $updated_pid_null = $dataCreate['pid'];
            }
            Log::info(['empty history_data==>' => false]);
        }
        // return ['new_pid_null'=>$new_pid_null,'updated_pid_null'=>$updated_pid_null];
    }
}

// if(!function_exists('curlRequestDataForFieldRoutes')){
function curlRequestDataForFieldRoutes($url, $payloadData, $headers, $method = 'POST')
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $payloadData,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

// }

if (! function_exists('insert_update_sale_master_for_evo_past')) {
    function insert_update_sale_master_for_evo_past($user)
    {
        Log::info(['insert_update_sale_master_for_evo_past==>' => 'called']);
        $successPID = [];
        $excelId = '';
        try {
            User::query()->update(['email' => \DB::raw('LOWER(email)')]);
            LegacyApiRawDataHistory::query()->update(['sales_rep_email' => \DB::raw('LOWER(sales_rep_email)')]);
            $newData = LegacyApiRawDataHistory::with('userDetail')->where(['data_source_type' => 'evoPest_field_routes', 'import_to_sales' => '0'])->groupBy('pid')->get();

            if (! class_exists('NewClass')) {
                class NewClass
                {
                    use EditSaleTrait, SetterSubroutineListTrait;
                }
            }
            $editSaleTrait = new NewClass;

            $excelId = @$newData[0]->excel_import_id;
            $salesSuccessReport = [];
            $salesErrorReport = [];
            foreach ($newData as $checked) {
                DB::beginTransaction();
                $check = 0;
                $salesMaster = SalesMaster::where('pid', $checked->pid)->first();

                $state = State::where('state_code', $checked->customer_state)->first();
                $saleMasterData = [
                    'pid' => $checked->pid,
                    'customer_name' => $checked->customer_name,
                    'customer_address' => $checked->customer_address,
                    'customer_address_2' => $checked->customer_address_2,
                    'customer_city' => $checked->customer_city,
                    'state_id' => isset($state->id) ? $state->id : null,
                    'customer_state' => strtoupper(trim($checked->customer_state)),
                    'location_code' => strtolower(trim($checked->location_code)),
                    'customer_zip' => $checked->customer_zip,
                    'customer_email' => $checked->customer_email,
                    'customer_phone' => $checked->customer_phone,
                    'sales_rep_email' => $checked->sales_rep_email,
                    'install_partner' => $checked->install_partner,
                    'customer_signoff' => $checked->customer_signoff,
                    'm1_date' => $checked->m1_date,
                    'm2_date' => $checked->m2_date,
                    'install_complete_date' => $checked->install_complete_date,
                    'date_cancelled' => $checked->date_cancelled,
                    'gross_account_value' => $checked->gross_account_value,
                    'product' => $checked->product,
                    'length_of_agreement' => $checked->length_of_agreement,
                    'service_schedule' => $checked->service_schedule,
                    'initial_service_cost' => $checked->initial_service_cost,
                    'subscription_payment' => $checked->subscription_payment,
                    'card_on_file' => $checked->card_on_file,
                    'auto_pay' => $checked->auto_pay,
                    'service_completed' => $checked->service_completed,
                    'last_service_date' => $checked->last_service_date,
                    'bill_status' => $checked->bill_status,
                    'data_source_type' => 'evoPest_field_routes',
                    'job_status' => $checked->job_status,
                    'initial_service_date' => $checked->initial_service_date,
                ];

                $closer = User::where('id', $checked->closer1_id)->first();
                $isImportStatus = 1;
                if (! $salesMaster) {
                    $null_table_val = $saleMasterData;
                    $null_table_val['closer_id'] = $checked->closer1_id;
                    $null_table_val['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : null;
                    $null_table_val['sales_rep_email'] = isset($closer->email) ? $closer->email : null;
                    $null_table_val['job_status'] = $checked->job_status;
                    LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $null_table_val);

                    $saleMaster = SalesMaster::create($saleMasterData);
                    $saleMasterProcessData = [
                        'sale_master_id' => $saleMaster->id,
                        'pid' => $checked->pid,
                        'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : null,
                        'job_status' => $checked->job_status,
                    ];
                    SaleMasterProcess::create($saleMasterProcessData);

                    $crmcustomfields = Crmcustomfields::where('status', 1)->get();
                    // Filter only for the `initial_service_date` field
                    $customFieldsData = $crmcustomfields
                        ->filter(function ($field) {
                            return $field->name == 'initial_service_date' || $field->name == 'initial service date'; // Match by the name
                        })
                        ->map(function ($field) use ($checked) {
                            return [
                                'custom_fild_id' => $field->id,
                                'value' => isset($checked->initial_service_date) ? $checked->initial_service_date : '',
                            ];
                        })
                        ->values();
                    $saleInfoData['pid'] = $checked->pid;
                    $saleInfoData['created_id'] = 1;
                    $saleInfoData['status'] = null;
                    $saleInfoData['custom_fields'] = json_encode($customFieldsData);

                    $checkSaleInfo = CrmSaleInfo::where('pid', $checked->pid)->first();
                    if (empty($checkSaleInfo)) {
                        CrmSaleInfo::create($saleInfoData);
                    } else {
                        $checkSaleInfo->update($saleInfoData);
                    }

                    try {
                        (new ApiMissingDataController)->pestSubroutineForEvoPest($saleMaster->pid);
                        $salesSuccessReport[] = [
                            'is_error' => false,
                            'pid' => $checked->pid,
                            'message' => 'Success',
                            'realMessage' => 'Success',
                            'file' => '',
                            'line' => '',
                            'name' => '-',
                        ];
                        // $excel = ExcelImportHistory::where('id', $excelId)->first();
                        // if ($excel) {
                        //     $excel->new_records = $excel->new_records + 1;
                        //     $excel->save();
                        // }
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $salesErrorReport[] = [
                            'is_error' => true,
                            'pid' => $checked->pid,
                            'message' => 'Error During Subroutine Process',
                            'realMessage' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'name' => '-',
                        ];
                        DB::rollBack();
                        // $excel = ExcelImportHistory::where('id', $excelId)->first();
                        // if ($excel) {
                        //     $excel->error_records = $excel->error_records + 1;
                        //     $excel->save();
                        // }
                    }
                } else {
                    try {
                        $grossAmount = ($checked->gross_account_value == $salesMaster->gross_account_value) ? 0 : 1;
                        $check_date_cancelled = ($checked->date_cancelled == $salesMaster->date_cancelled) ? 0 : 1;
                        $check_customer_state = ($checked->customer_state == $salesMaster->customer_state) ? 0 : 1;
                        $check_m1_date = ($checked->m1_date == $salesMaster->m1_date) ? 0 : 1;
                        $check_m2_date = ($checked->m2_date == $salesMaster->m2_date) ? 0 : 1;

                        $salesMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                        salesDataChangesClawback($salesMasterProcess->pid);
                        $check_closer = 0;
                        if ($salesMasterProcess) {
                            $check_closer = ($checked->closer1_id == $salesMasterProcess->closer1_id) ? 0 : 1;
                        }
                        $check = ($grossAmount + $check_date_cancelled + $check_customer_state + $check_m1_date + $check_m2_date + $check_closer);

                        $success = true;
                        if ($check > 0) {
                            // M1 IS PAID & M1 DATE GETS REMOVED
                            if (! empty($salesMaster->m1_date) && empty($checked->m1_date)) {
                                $m1Comm = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm1', 'status' => 3, 'is_displayed' => '1'])->first();
                                if ($m1Comm) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, The M1 date cannot be remove because the upfront amount has already been paid',
                                        'realMessage' => 'Apologies, The M1 date cannot be remove because the upfront amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } else {
                                    $editSaleTrait->m1dateSalesData($checked->pid);
                                }
                            }

                            // M1 DATE GETS CHANGED
                            if (! empty($salesMaster->m1_date) && ! empty($checked->m1_date) && $salesMaster->m1_date != $checked->m1_date) {
                                $m1Comm = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm1', 'status' => 3, 'is_displayed' => '1'])->first();
                                if ($m1Comm) {
                                    $isImportStatus = 2;
                                    $success = false;
                                    $salesErrorReport[] = [
                                        'is_error' => true,
                                        'pid' => $checked->pid,
                                        'message' => 'Apologies, The M1 date cannot be changed because the upfront amount has already been paid',
                                        'realMessage' => 'Apologies, The M1 date cannot be changed because the upfront amount has already been paid',
                                        'file' => '',
                                        'line' => '',
                                        'name' => '-',
                                    ];
                                } else {
                                    $editSaleTrait->m1datePayrollData($checked->pid, $checked->m1_date);
                                }
                            }

                            if ($success) {
                                // M2 IS PAID & M2 DATE GETS REMOVED
                                if (! empty($salesMaster->m2_date) && empty($checked->m2_date)) {
                                    $m2Comm = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
                                    if ($m2Comm) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, The M2 date cannot be remove because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The M2 date cannot be remove because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } else {
                                        $editSaleTrait->m2dateSalesData($checked->pid, $salesMaster->m2_date);
                                    }
                                }

                                // M2 DATE GETS CHANGED
                                if (! empty($salesMaster->m2_date) && ! empty($checked->m2_date) && $salesMaster->m2_date != $checked->m2_date) {
                                    $m2Comm = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
                                    if ($m2Comm) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, The M2 date cannot be remove because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The M2 date cannot be remove because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } else {
                                        $editSaleTrait->m2datePayrollData($checked->pid, $checked->m2_date);
                                    }
                                }
                            }

                            if ($success) {
                                // CLOSER 1 GOT CHANGE
                                if (isset($salesMasterProcess->closer1_id) && isset($checked->closer1_id) && $checked->closer1_id != $salesMasterProcess->closer1_id) {
                                    $m2Comm = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
                                    if ($m2Comm) {
                                        $isImportStatus = 2;
                                        $success = false;
                                        $salesErrorReport[] = [
                                            'is_error' => true,
                                            'pid' => $checked->pid,
                                            'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'realMessage' => 'Apologies, The closer cannot be changed because the commission amount has already been paid',
                                            'file' => '',
                                            'line' => '',
                                            'name' => '-',
                                        ];
                                    } else {
                                        $editSaleTrait->updateSalesData($salesMasterProcess->closer1_id, 2, $checked->pid);
                                        $clawbackSett = ClawbackSettlement::where(['pid' => $checked->pid, 'user_id' => $salesMasterProcess->closer1_id, 'type' => 'commission', 'is_displayed' => '1'])->first();
                                        if (empty($clawbackSett)) {
                                            $editSaleTrait->clawbackSalesData($salesMasterProcess->closer1_id, $salesMaster);
                                        }

                                        $clawbackSett = ClawbackSettlement::where(['pid' => $checked->pid, 'user_id' => $checked->closer1_id, 'is_displayed' => '1', 'status' => '1', 'is_displayed' => '1'])->first();
                                        // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER CLOSER SELECTED AND CLAWBACK HASN'T PAID YET
                                        if ($clawbackSett) {
                                            ClawbackSettlement::where(['user_id' => $checked->closer1_id, 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                                            ClawbackSettlement::where(['sale_user_id' => $checked->closer1_id, 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                                        }

                                        $salesMasterProcess->closer1_m1 = 0;
                                        $salesMasterProcess->job_status = $checked->job_status ?? null;
                                        $salesMasterProcess->save();
                                    }
                                }
                            }
                        }

                        if ($success) {
                            $data = [
                                'weekly_sheet_id' => $salesMaster->weekly_sheet_id,
                                'pid' => $checked->pid,
                                'closer1_id' => isset($checked->closer1_id) ? $checked->closer1_id : null,
                                'job_status' => $checked->job_status,
                            ];
                            SaleMasterProcess::updateOrCreate(['pid' => $checked->pid], $data);
                            SalesMaster::where('pid', $checked->pid)->update($saleMasterData);

                            $closer = User::where('id', $checked->closer1_id)->first();
                            $null_table_val = $saleMasterData;
                            $null_table_val['closer_id'] = $checked->closer1_id;
                            $null_table_val['sales_rep_name'] = isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : null;
                            $null_table_val['sales_rep_email'] = isset($closer->email) ? $closer->email : null;
                            $null_table_val['job_status'] = $checked->job_status;
                            LegacyApiNullData::updateOrCreate(['pid' => $checked->pid], $null_table_val);

                            if ($check > 0) {
                                if (! empty($salesMaster->date_cancelled) && empty($checked->date_cancelled)) {
                                    // When Clawback Is Paid, Sale Should Act As It's New Therefore
                                    salesDataChangesBasedOnClawback($salesMaster->pid);
                                    ClawbackSettlement::where(['pid' => $checked->pid, 'is_displayed' => '1', 'status' => '1'])->where('user_id', '!=', $checked->closer1_id)->delete();
                                }
                                (new ApiMissingDataController)->pestSubroutineForEvoPest($checked->pid);
                                $salesSuccessReport[] = [
                                    'is_error' => false,
                                    'pid' => $checked->pid,
                                    'message' => 'Success',
                                    'realMessage' => 'Success',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            } else {
                                $salesSuccessReport[] = [
                                    'is_error' => false,
                                    'pid' => $checked->pid,
                                    'message' => 'Success!!',
                                    'realMessage' => 'Success!!',
                                    'file' => '',
                                    'line' => '',
                                    'name' => '-',
                                ];
                            }
                            // $excel = ExcelImportHistory::where('id', $excelId)->first();
                            // if ($excel) {
                            //     $excel->updated_records = $excel->updated_records + 1;
                            //     $excel->save();
                            // }
                        } else {
                            // $excel = ExcelImportHistory::where('id', $excelId)->first();
                            // if ($excel) {
                            //     $excel->error_records = $excel->error_records + 1;
                            //     $excel->save();
                            // }
                        }
                    } catch (\Throwable $e) {
                        $isImportStatus = 2;
                        $salesErrorReport[] = [
                            'is_error' => true,
                            'pid' => $checked->pid,
                            'message' => 'Error During Subroutine Process',
                            'realMessage' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'name' => '-',
                        ];
                        DB::rollBack();
                        // $excel = ExcelImportHistory::where('id', $excelId)->first();
                        // if ($excel) {
                        //     $excel->error_records = $excel->error_records + 1;
                        //     $excel->save();
                        // }
                    }
                }

                // UPDATE STATUS IN HISTORY TABLE FOR EXECUTED SALES.
                LegacyApiRawDataHistory::where(['pid' => $checked->pid, 'data_source_type' => 'evoPest_field_routes', 'import_to_sales' => '0'])->update(['import_to_sales' => $isImportStatus]);
                DB::commit();
                $successPID[] = $checked->pid;
            }

            // $excel = ExcelImportHistory::where('id', $excelId)->first();
            // if ($excel) {
            //     $excel->updated_records = $excel->total_records - $excel->new_records - $excel->error_records;
            //     $excel->save();
            // }
            dispatch(new GenerateAlertJob(implode(',', $successPID)));
            // Artisan::call('generate:alert', ['pid' => $pid]);
            // If Sales From Excel Sheet Has One Or More Error
            if (count($salesErrorReport) != 0) {
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Master Process Failed For EvoPest',
                    'template' => view('mail.saleImportFailed', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user]),
                ];
                if (! class_exists('NewEmailClass')) {
                    class NewEmailClass
                    {
                        use EmailNotificationTrait;
                    }
                }
                (new NewEmailClass)->sendEmailNotification($data);
            } else {
                // If Sales From Excel Sheet Has No Error
                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale Master Process Success For EvoPest',
                    'template' => view('mail.saleImportSuccess', ['errorReports' => $salesErrorReport, 'successReports' => $salesSuccessReport, 'user' => $user]),
                ];
                if (! class_exists('NewEmailClass')) {
                    class NewEmailClass
                    {
                        use EmailNotificationTrait;
                    }
                }
                (new NewEmailClass)->sendEmailNotification($data);
            }
        } catch (\Throwable $e) {
            dispatch(new GenerateAlertJob(implode(',', $successPID)));
            LegacyApiRawDataHistory::where(['data_source_type' => 'evoPest_field_routes', 'import_to_sales' => '0'])->whereNotIn('pid', $successPID)->update(['import_to_sales' => '2']);
            // if ($excelId) {
            //     $excel = ExcelImportHistory::where('id', $excelId)->first();
            //     if ($excel) {
            //         $excel->error_records = $excel->total_records - $excel->new_records - $excel->updated_records;
            //         $excel->save();
            //     }
            // }
            Log::info([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}

function applyPayrollUpdate(Builder $query, $pay_frequency, $start_date, $end_date, $user_id, $payroll_id, $additionalConditions = [])
{
    if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
        $query->whereBetween('pay_period_from', [$start_date, $end_date])
            ->whereBetween('pay_period_to', [$start_date, $end_date])
            ->whereColumn('pay_period_from', 'pay_period_to')
            ->where('user_id', $user_id)
            ->where('status', '!=', 3);
    } else {
        $query->where('user_id', $user_id)
            ->where('pay_period_from', $pay_period_from)
            ->where('pay_period_to', $pay_period_to)
            ->where('status', '!=', 3);
    }

    // Apply additional conditions if provided
    if (! empty($additionalConditions)) {
        foreach ($additionalConditions as $column => $value) {
            $query->where($column, $value);
        }
    }

    $query->update(['payroll_id' => $payroll_id]);
}

function getCommonPayrolls($model, $start_date, $end_date, $isDailyPay, $fullName)
{
    $query = $model::with('saledata')
        ->where('status', '!=', '3');

    if ($isDailyPay) {
        $query->whereBetween('pay_period_from', [$start_date, $end_date])
            ->whereBetween('pay_period_to', [$start_date, $end_date])
            ->whereColumn('pay_period_from', 'pay_period_to');
    } else {
        $query->where([
            'pay_period_from' => $start_date,
            'pay_period_to' => $end_date,
        ]);
    }

    if ($fullName && ! empty($fullName)) {
        $query->whereHas('saledata', function ($q) {
            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')
                ->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
        });
    }

    return $query->get();
}

function getPayrollDataCount(Builder $query, $pay_frequency, $start_date, $end_date, $user_string, $user_id, $status)
{
    if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
        $query->whereBetween('pay_period_from', [$start_date, $end_date])
            ->whereBetween('pay_period_to', [$start_date, $end_date])
            ->whereColumn('pay_period_from', 'pay_period_to');
        if ($user_string == 'user_id') {
            $query->where('user_id', $user_id);
        } elseif ($user_string == 'closer_id') {
            $query->where('closer_id', $user_id);
        } elseif ($user_string == 'setter_id') {
            $query->where('setter_id', $user_id);
        }
        if ($status == 'number') {
            $query->where('status', '!=', 3);
        } else {
            $query->where('status', '!=', 'Accept');
        }

    } else {
        // $query->where('user_id', $user_id)
        $query->where('pay_period_from', $start_date)
            ->where('pay_period_to', $end_date);
        if ($user_string == 'user_id') {
            $query->where('user_id', $user_id);
        } elseif ($user_string == 'closer_id') {
            $query->where('closer_id', $user_id);
        } elseif ($user_string == 'setter_id') {
            $query->where('setter_id', $user_id);
        }
        if ($status == 'number') {
            $query->where('status', '!=', 3);
        } else {
            $query->where('status', '!=', 'Accept');
        }
    }

    $query->count();
}
function calculateLeadRating($ratings)
{
    /**
     * Formula:
     * Weighted Average Rating= ∑(rating×weight)/∑weights
     * if i have 3 ratings
     * Rating 1: 1 out of 5
     * Rating 2: 4 out of 5
     * Rating 3: 3 out of 5
     * (1×5)+(4×5)+(3×5)/5+5+5
     */
    $totalWeightedScore = 0;
    $totalWeights = 0;

    foreach ($ratings as $rating) {
        $score = $rating['score'];  // Rating received
        $max = $rating['max'];     // Maximum possible rating
        $totalWeightedScore += (int) $score * $max;
        $totalWeights += $max;
    }

    // Calculate weighted average
    $average = $totalWeights > 0 ? $totalWeightedScore / $totalWeights : 0;

    return round($average, 2); // Round to 2 decimal places
}
function calculateCustomRating($customFieldsDetail)
{
    $fields = json_decode($customFieldsDetail, true);

    // Check if the value is null, an empty array, or invalid JSON
    if (empty($fields) || ! is_array($fields)) {
        return 0; // No fields to calculate
    }

    $ratings = [];

    foreach ($fields as $field) {
        // Only include fields with 'scored' set to 1
        if (isset($field['scored']) && $field['scored'] == 1) {

            if (isset($field['value']) && isset($field['attribute_option'])) {
                $index = array_search($field['value'], $field['attribute_option']);

                // $attributeOptionRating = json_decode($field['attribute_option_rating'] ?? '[]', true);
                $attributeOptionRating = $field['attribute_option_rating'] ?? [];
                // dump($attributeOptionRating);
                $attributeOptionRating = json_decode($attributeOptionRating, true);
                // dd('stop');
                $rating = $index !== false && isset($attributeOptionRating[$index]) ? $attributeOptionRating[$index] : 0;
                $ratings[] = [
                    'score' => $rating,
                    'max' => 5,
                ];
            }
            // foreach ($attributeOptionRating as $index => $rating) {
            //     if (!is_null($rating)) {
            //         $ratings[] = [
            //             'score' => $rating,
            //             'max'   => 5,
            //         ];
            //     }
            // }
        }
    }

    return calculateLeadRating($ratings);
}
function setSMTPConfig()
{
    // Determine if we're using development or production environment based on EMAIL_TESTING flag
    $isEmailTesting = config('mail.testing', false) == 1;

    // Log which environment is being used
    \Log::info('Mail Configuration', [
        'environment' => $isEmailTesting ? 'Development (EMAIL_TESTING=1)' : 'Production (EMAIL_TESTING=0)',
        'timestamp' => now()->toDateTimeString(),
    ]);

    // Get configuration based on EMAIL_TESTING flag
    if ($isEmailTesting) {
        // Development environment using DEV prefixed variables
        $mailConfig = [
            'transport' => config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host', 'smtp.sendgrid.net'),
            'port' => config('mail.mailers.smtp.port', '587'),
            'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'username' => config('mail.mailers.smtp.username', 'apikey'),
            'password' => config('mail.mailers.smtp.password'),
            'timeout' => null,
        ];

        $fromAddress = config('mail.from.address', 'no-return@sequifi.com');
        $fromName = config('mail.from.name', 'Sequifi');

        // Log development mail server details
        \Log::info('Using Development SMTP Server', [
            'host' => $mailConfig['host'],
            'port' => $mailConfig['port'],
            'encryption' => $mailConfig['encryption'],
            'username' => $mailConfig['username'],
            'from_address' => $fromAddress,
            'from_name' => $fromName,
        ]);
    } else {
        // Production environment using standard variables
        $mailConfig = [
            'transport' => config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host', 'email-smtp.us-west-1.amazonaws.com'),
            'port' => config('mail.mailers.smtp.port', '587'),
            'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'username' => config('mail.mailers.ses.username', 'AKIA5SVTEQW4T4XTDUE6'),
            'password' => config('mail.mailers.smtp.password'),
            'timeout' => null,
        ];

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', 'Sequifi');

        // Log production mail server details
        \Log::info('Using Production SMTP Server', [
            'host' => $mailConfig['host'],
            'port' => $mailConfig['port'],
            'encryption' => $mailConfig['encryption'],
            'username' => $mailConfig['username'],
            'from_address' => $fromAddress,
            'from_name' => $fromName,
        ]);
    }

    // Apply configuration
    config(['mail.mailers.smtp' => $mailConfig]);
    config(['mail.from.address' => $fromAddress]);
    config(['mail.from.name' => $fromName]);

    \Log::info('Mail configuration applied', [
        'configured_at' => now()->toDateTimeString(),
    ]);
}

// added code for  genrate random password
// if (!function_exists('randPassForUsers')) {
function randPassForUsers($length = 12)
{
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%*()-_+=';
    $all = $upper.$lower.$numbers.$special;
    $password = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $numbers[random_int(0, strlen($numbers) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];
    for ($i = 4; $i < $length; $i++) {
        $password[] = $all[random_int(0, strlen($all) - 1)];
    }
    shuffle($password);
    $final_password = implode('', $password);

    return [
        'plain_password' => $final_password,
        'password' => Hash::make($final_password),
    ];
}
// }

function getRemoteFileSize($url)
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);

    $response = curl_exec($ch);

    if ($response === false) {
        return false;
    }

    $contentLength = -1;
    if (preg_match('/Content-Length: (\d+)/i', $response, $matches)) {
        $contentLength = (int) $matches[1];
    }

    curl_close($ch);

    if ($contentLength !== -1) {
        return $contentLength;
    } else {
        return fetchFileSizeByDownloading($url);
    }
}

function fetchFileSizeByDownloading($url)
{
    try {

        $fileContent = file_get_contents($url);

        if ($fileContent !== false) {
            return strlen($fileContent); // Return file size in bytes
        }

    } catch (\Throwable $th) {
        //
    }

    return false; // If all fails
}

function formatFileSize($bytes)
{
    if ($bytes < 1024) {
        return $bytes.' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2).' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2).' MB';
    } else {
        return round($bytes / 1073741824, 2).' GB';
    }
}

// if (!function_exists("hs_create_raw_data_history_api_new")) {
function hs_create_raw_data_history_api_new($data)
{
    $closerId = null;
    $closerDetails = User::where('email', $data['sales_rep_email'])->first();
    if (isset($closerDetails)) {
        $closerId = $closerDetails->id;
    }

    $dataCreate = $data;
    $dataCreate['location_code'] = isset($data['customer_state']) ? $data['customer_state'] : null;
    $dataCreate['customer_name'] = isset($dataCreate['customer_name']) ? $dataCreate['customer_name'] : null;
    $dataCreate['customer_state'] = isset($dataCreate['customer_state']) ? $dataCreate['customer_state'] : null;
    $dataCreate['location_code'] = isset($dataCreate['location_code']) ? $dataCreate['location_code'] : null;
    $dataCreate['customer_signoff'] = isset($dataCreate['customer_signoff']) ? $dataCreate['customer_signoff'] : null;
    $dataCreate['epc'] = isset($dataCreate['epc']) ? $dataCreate['epc'] : null;
    $dataCreate['net_epc'] = isset($dataCreate['net_epc']) ? $dataCreate['net_epc'] : null;
    $dataCreate['gross_account_value'] = isset($dataCreate['gross_account_value']) ? $dataCreate['gross_account_value'] : null;
    $dataCreate['setter1_id'] = $closerId;
    $dataCreate['closer1_id'] = $closerId;
    LegacyApiRawDataHistory::create($dataCreate);

    dispatch(new SaleMasterJob($dataCreate['data_source_type'], 100, 'sales-process'));
}
// }

function getStoragePath($path)
{
    if (function_exists('tenant')) {
        $target = storage_path().'/app/public';
        $link = public_path().'/'.tenant()->id.'-storage';

        if (! file_exists($link)) {
            symlink($target, $link);
        }

        return getExportBaseUrl().tenant()->id.'-storage/'.$path;
    } else {
        return getExportBaseUrl().'storage/'.$path;
    }
}

function getStreamContext()
{
    $context = stream_context_create([
        'http' => [
            'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ],
    ]);

    return $context;
}

if (! function_exists('mapTimeZone')) {
    // Maps timezone label to PHP-compatible timezone string, with fallback to app timezone.
    function mapTimeZone($label)
    {
        return [
            '(UTC-12:00) International Date Line West' => 'Etc/GMT+12',
            '(UTC-11:00) Coordinated Universal Time-11' => 'Etc/GMT+11',
            '(UTC-10:00) Hawaii' => 'Pacific/Honolulu',
            '(UTC-09:00) Alaska' => 'America/Anchorage',
            '(UTC-08:00) Baja California' => 'America/Tijuana',
            '(UTC-07:00) Pacific Daylight Time (US & Canada)' => 'America/Los_Angeles',
            '(UTC-08:00) Pacific Standard Time (US & Canada)' => 'America/Los_Angeles',
            '(UTC-07:00) Arizona' => 'America/Phoenix',
            '(UTC-07:00) Chihuahua, La Paz, Mazatlan' => 'America/Chihuahua',
            '(UTC-07:00) Mountain Time (US & Canada)' => 'America/Denver',
            '(UTC-06:00) Central America' => 'America/Guatemala',
            '(UTC-06:00) Central Time (US & Canada)' => 'America/Chicago',
            '(UTC-06:00) Guadalajara, Mexico City, Monterrey' => 'America/Mexico_City',
            '(UTC-06:00) Saskatchewan' => 'America/Regina',
            '(UTC-05:00) Bogota, Lima, Quito' => 'America/Bogota',
            '(UTC-05:00) Eastern Time (US & Canada)' => 'America/New_York',
            '(UTC-04:00) Eastern Daylight Time (US & Canada)' => 'America/New_York',
            '(UTC-05:00) Indiana (East)' => 'America/Indiana/Indianapolis',
            '(UTC-04:30) Caracas' => 'America/Caracas',
            '(UTC-04:00) Asuncion' => 'America/Asuncion',
            '(UTC-04:00) Atlantic Time (Canada)' => 'America/Halifax',
            '(UTC-04:00) Cuiaba' => 'America/Cuiaba',
            '(UTC-04:00) Georgetown, La Paz, Manaus, San Juan' => 'America/La_Paz',
            '(UTC-04:00) Santiago' => 'America/Santiago',
            '(UTC-03:30) Newfoundland' => 'America/St_Johns',
            '(UTC-03:00) Brasilia' => 'America/Sao_Paulo',
            '(UTC-03:00) Buenos Aires' => 'America/Argentina/Buenos_Aires',
            '(UTC-03:00) Cayenne, Fortaleza' => 'America/Fortaleza',
            '(UTC-03:00) Greenland' => 'America/Godthab',
            '(UTC-03:00) Montevideo' => 'America/Montevideo',
            '(UTC-03:00) Salvador' => 'America/Bahia',
            '(UTC-02:00) Coordinated Universal Time-02' => 'Etc/GMT+2',
            '(UTC-02:00) Mid-Atlantic - Old' => 'America/Noronha',
            '(UTC-01:00) Azores' => 'Atlantic/Azores',
            '(UTC-01:00) Cape Verde Is.' => 'Atlantic/Cape_Verde',
            '(UTC) Casablanca' => 'Africa/Casablanca',
            '(UTC) Coordinated Universal Time' => 'Etc/UTC',
            '(UTC) Edinburgh, London' => 'Europe/London',
            '(UTC+01:00) Edinburgh, London' => 'Europe/London',
            '(UTC) Dublin, Lisbon' => 'Europe/Lisbon',
            '(UTC) Monrovia, Reykjavik' => 'Atlantic/Reykjavik',
            '(UTC+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna' => 'Europe/Berlin',
            '(UTC+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague' => 'Europe/Belgrade',
            '(UTC+01:00) Brussels, Copenhagen, Madrid, Paris' => 'Europe/Paris',
            '(UTC+01:00) Sarajevo, Skopje, Warsaw, Zagreb' => 'Europe/Warsaw',
            '(UTC+01:00) West Central Africa' => 'Africa/Lagos',
            '(UTC+01:00) Windhoek' => 'Africa/Windhoek',
            '(UTC+02:00) Athens, Bucharest' => 'Europe/Athens',
            '(UTC+02:00) Beirut' => 'Asia/Beirut',
            '(UTC+02:00) Cairo' => 'Africa/Cairo',
            '(UTC+02:00) Damascus' => 'Asia/Damascus',
            '(UTC+02:00) E. Europe' => 'Europe/Bucharest',
            '(UTC+02:00) Harare, Pretoria' => 'Africa/Harare',
            '(UTC+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius' => 'Europe/Helsinki',
            '(UTC+03:00) Istanbul' => 'Europe/Istanbul',
            '(UTC+02:00) Jerusalem' => 'Asia/Jerusalem',
            '(UTC+02:00) Tripoli' => 'Africa/Tripoli',
            '(UTC+03:00) Amman' => 'Asia/Amman',
            '(UTC+03:00) Baghdad' => 'Asia/Baghdad',
            '(UTC+02:00) Kaliningrad' => 'Europe/Kaliningrad',
            '(UTC+03:00) Kuwait, Riyadh' => 'Asia/Riyadh',
            '(UTC+03:00) Nairobi' => 'Africa/Nairobi',
            '(UTC+03:00) Moscow, St. Petersburg, Volgograd, Minsk' => 'Europe/Moscow',
            '(UTC+04:00) Samara, Ulyanovsk, Saratov' => 'Europe/Samara',
            '(UTC+03:30) Tehran' => 'Asia/Tehran',
            '(UTC+04:00) Abu Dhabi, Muscat' => 'Asia/Dubai',
            '(UTC+04:00) Baku' => 'Asia/Baku',
            '(UTC+04:00) Port Louis' => 'Indian/Mauritius',
            '(UTC+04:00) Tbilisi' => 'Asia/Tbilisi',
            '(UTC+04:00) Yerevan' => 'Asia/Yerevan',
            '(UTC+04:30) Kabul' => 'Asia/Kabul',
            '(UTC+05:00) Ashgabat, Tashkent' => 'Asia/Tashkent',
            '(UTC+05:00) Yekaterinburg' => 'Asia/Yekaterinburg',
            '(UTC+05:00) Islamabad, Karachi' => 'Asia/Karachi',
            '(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi' => 'Asia/Kolkata',
            '(UTC+05:30) Sri Jayawardenepura' => 'Asia/Colombo',
            '(UTC+05:45) Kathmandu' => 'Asia/Kathmandu',
            '(UTC+06:00) Nur-Sultan (Astana)' => 'Asia/Almaty',
            '(UTC+06:00) Dhaka' => 'Asia/Dhaka',
            '(UTC+06:30) Yangon (Rangoon)' => 'Asia/Yangon',
            '(UTC+07:00) Bangkok, Hanoi, Jakarta' => 'Asia/Bangkok',
            '(UTC+07:00) Novosibirsk' => 'Asia/Novosibirsk',
            '(UTC+08:00) Beijing, Chongqing, Hong Kong, Urumqi' => 'Asia/Shanghai',
            '(UTC+08:00) Krasnoyarsk' => 'Asia/Krasnoyarsk',
            '(UTC+08:00) Kuala Lumpur, Singapore' => 'Asia/Singapore',
            '(UTC+08:00) Perth' => 'Australia/Perth',
            '(UTC+08:00) Taipei' => 'Asia/Taipei',
            '(UTC+08:00) Ulaanbaatar' => 'Asia/Ulaanbaatar',
            '(UTC+08:00) Irkutsk' => 'Asia/Irkutsk',
            '(UTC+09:00) Osaka, Sapporo, Tokyo' => 'Asia/Tokyo',
            '(UTC+09:00) Seoul' => 'Asia/Seoul',
            '(UTC+09:30) Adelaide' => 'Australia/Adelaide',
            '(UTC+09:30) Darwin' => 'Australia/Darwin',
            '(UTC+10:00) Brisbane' => 'Australia/Brisbane',
            '(UTC+10:00) Canberra, Melbourne, Sydney' => 'Australia/Sydney',
            '(UTC+10:00) Guam, Port Moresby' => 'Pacific/Port_Moresby',
            '(UTC+10:00) Hobart' => 'Australia/Hobart',
            '(UTC+09:00) Yakutsk' => 'Asia/Yakutsk',
            '(UTC+11:00) Solomon Is., New Caledonia' => 'Pacific/Guadalcanal',
            '(UTC+11:00) Vladivostok' => 'Asia/Vladivostok',
            '(UTC+12:00) Auckland, Wellington' => 'Pacific/Auckland',
            '(UTC+12:00) Coordinated Universal Time+12' => 'Etc/GMT-12',
            '(UTC+12:00) Fiji' => 'Pacific/Fiji',
            '(UTC+12:00) Magadan' => 'Asia/Magadan',
            '(UTC+12:00) Petropavlovsk-Kamchatsky - Old' => 'Asia/Kamchatka',
            '(UTC+13:00) Nuku\'alofa' => 'Pacific/Tongatapu',
            '(UTC+13:00) Samoa' => 'Pacific/Apia',
        ][$label] ?? config('app.timezone');
    }
}

/**
 * Find user by flexible ID with priority order.
 * Priority: Flexi ID 1 → Flexi ID 2 → Flexi ID 3 → Primary Email → Work Email → Additional Emails
 *
 * @param  string  $identifier  The identifier to search for
 * @return App\Models\User|null
 */
if (! function_exists('findUserByIdentifier')) {
    function findUserByIdentifier($identifier)
    {
        if (empty($identifier)) {
            return null;
        }

        return \App\Models\User::findByFlexibleIdOrEmail($identifier);
    }
}

/**
 * Find user specifically by flexible ID value.
 *
 * @param  string  $flexibleIdValue  The flexible ID value to search for
 * @return App\Models\User|null
 */
if (! function_exists('findUserByFlexibleId')) {
    function findUserByFlexibleId($flexibleIdValue)
    {
        if (empty($flexibleIdValue)) {
            return null;
        }

        return \App\Models\UserFlexibleId::findUserByFlexibleId($flexibleIdValue);
    }
}

/**
 * Check if a flexible ID value is unique.
 *
 * @param  string  $value  The flexible ID value to check
 * @param  int|null  $excludeId  The ID to exclude from uniqueness check
 * @return bool
 */
if (! function_exists('isFlexibleIdUnique')) {
    function isFlexibleIdUnique($value, $excludeId = null)
    {
        if (empty($value)) {
            return true;
        }

        return ! \App\Models\UserFlexibleId::valueExists($value, $excludeId);
    }
}

/**
 * Get all flexible IDs for a user.
 *
 * @param  int  $userId  The user ID
 * @return array
 */
if (! function_exists('getUserFlexibleIds')) {
    function getUserFlexibleIds($userId)
    {
        $user = \App\Models\User::find($userId);
        if (! $user) {
            return [];
        }

        return [
            'flexi_id_1' => $user->flexi_id_1,
            'flexi_id_2' => $user->flexi_id_2,
            'flexi_id_3' => $user->flexi_id_3,
        ];
    }
}

/**
 * Set flexible ID for a user.
 *
 * @param  int  $userId  The user ID
 * @param  string  $type  The flexible ID type (flexi_id_1, flexi_id_2, flexi_id_3)
 * @param  string  $value  The flexible ID value
 * @return App\Models\UserFlexibleId|null
 */
if (! function_exists('setUserFlexibleId')) {
    function setUserFlexibleId($userId, $type, $value)
    {
        $user = \App\Models\User::find($userId);
        if (! $user) {
            return null;
        }

        return $user->setFlexibleId($type, $value);
    }
}
