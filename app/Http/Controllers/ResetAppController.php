<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ResetAppController extends Controller
{
    public function reset(): JsonResponse
    {

        if (config('app.url') != 'https://testing.sequifi.com' || config('app.url') != 'https://preprod.sequifi.com') {

            return response()->json([

                'status' => false,
                'message' => 'not allowed in this app',

            ], 500);

        }
    }

    public function resetStopForNow() // Do not run it ==== Gorakh
    {
        if (config('app.url') != 'https://testing.sequifi.com' || config('app.url') != 'https://preprod.sequifi.com') {

            return response()->json([

                'status' => false,
                'message' => 'not allowed in this app',

            ], 500);

        }
        //  This code is commennted by Gorakh

        // $phpExecutablePath = PHP_BINARY;
        // $phpExecutablePath = '"' . PHP_BINARY . '"';
        // $phpExecutablePath = str_replace('-fpm', '', PHP_BINARY);

        // $artisanToolPath = base_path('artisan');

        // $arguments = [$phpExecutablePath, $artisanToolPath];

        // $scriptPath = base_path() . DIRECTORY_SEPARATOR . 'shell-scripts' . DIRECTORY_SEPARATOR . 'resetApp.sh';
        // $command = ['sh', $scriptPath];
        // $command = array_merge($command, $arguments);

        // $process = new Process($command);
        // $process->run();

        // if (!$process->isSuccessful()) {

        //     throw new ProcessFailedException($process);

        // }

        // $data = $process->getOutput();

        return response()->json([

            'status' => true,
            'output' => $data,
            'message' => 'App reset done',

        ], 200);

    }
}
