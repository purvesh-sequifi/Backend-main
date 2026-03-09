<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SFTP;

class ClarkSftpService
{
    /** @var SFTP The SFTP connection instance */
    protected $connection;

    /** @var array SFTP configuration */
    protected $config;

    /** @var bool Whether connection is established */
    protected $isConnected = false;

    /**
     * Create a new Clark SFTP service instance
     *
     * @return void
     */
    public function __construct()
    {
        $this->config = Config::get('sftp.connections.clark');
        if (! $this->config) {
            Log::channel('clark_sftp')->info('Clark SFTP configuration: '.json_encode($this->config));
            throw new \InvalidArgumentException('Clark SFTP connection not configured');
        }
    }

    /**
     * Connect to the SFTP server
     *
     * @param  int|null  $connectionTimeout  Override connection timeout in seconds
     * @return bool Whether connection was successful
     */
    public function connect(?int $connectionTimeout = null): bool
    {
        if ($this->isConnected) {
            Log::channel('clark_sftp')->info('Already connected to SFTP');

            return true;
        }

        try {
            Log::channel('clark_sftp')->info('Attempting to connect to Clark SFTP', [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'username' => $this->config['username'],
            ]);

            $this->connection = new SFTP($this->config['host'], $this->config['port'], $connectionTimeout);

            if (! $this->connection->login($this->config['username'], $this->config['password'])) {
                $error = $this->connection->getLastError();
                Log::channel('clark_sftp')->error('Clark SFTP login failed', [
                    'host' => $this->config['host'],
                    'username' => $this->config['username'],
                    'error' => $error,
                ]);

                return false;
            }

            $this->isConnected = true;
            Log::channel('clark_sftp')->info('Successfully connected to Clark SFTP');

            // Test connection by trying to list root directory
            $test = $this->connection->nlist('.');
            Log::channel('clark_sftp')->info('Root directory listing test', ['success' => is_array($test), 'files' => $test]);

            return true;

        } catch (\Exception $e) {
            Log::channel('clark_sftp')->error('Clark SFTP connection failed', [
                'error' => $e->getMessage(),
                'host' => $this->config['host'],
            ]);

            return false;
        }
    }

    /**
     * List files in a directory
     *
     * @param  string  $directory  Directory path
     * @return array List of files
     */
    public function listFiles(string $directory): array
    {
        Log::channel('clark_sftp')->info("Attempting to list files in directory: {$directory}");

        if (! $this->connect()) {
            Log::channel('clark_sftp')->error('Failed to connect to SFTP server');

            return [];
        }

        try {
            Log::channel('clark_sftp')->info('Connected to SFTP, listing directory');
            $files = $this->connection->nlist($directory);

            if (! is_array($files)) {
                Log::channel('clark_sftp')->error('Failed to get file list', ['error' => $this->connection->getLastError()]);

                return [];
            }

            $filteredFiles = array_filter($files, function ($file) {
                return ! in_array($file, ['.', '..']);
            });

            Log::channel('clark_sftp')->info('Found files in directory', ['count' => count($filteredFiles), 'files' => $filteredFiles]);

            return $filteredFiles;
        } catch (\Exception $e) {
            Log::channel('clark_sftp')->error('Clark SFTP list files failed', [
                'error' => $e->getMessage(),
                'directory' => $directory,
            ]);

            return [];
        }
    }

    /**
     * Download a file from SFTP
     *
     * @param  string  $remotePath  Remote file path
     * @param  string  $localPath  Local file path
     * @return bool Whether download was successful
     */
    public function downloadFile(string $remotePath, string $localPath): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            if (! $this->connection->get($remotePath, $localPath)) {
                Log::channel('clark_sftp')->error('Clark SFTP file download failed', [
                    'remote_path' => $remotePath,
                    'local_path' => $localPath,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Clark SFTP file download failed', [
                'error' => $e->getMessage(),
                'remote_path' => $remotePath,
                'local_path' => $localPath,
            ]);

            return false;
        }
    }

    /**
     * Move a file on the SFTP server
     *
     * @param  string  $fromPath  Source path
     * @param  string  $toPath  Destination path
     * @return bool Whether move was successful
     */
    public function moveFile(string $fromPath, string $toPath): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            if (! $this->connection->rename($fromPath, $toPath)) {
                Log::channel('clark_sftp')->error('Clark SFTP file move failed', [
                    'from_path' => $fromPath,
                    'to_path' => $toPath,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Clark SFTP file move failed', [
                'error' => $e->getMessage(),
                'from_path' => $fromPath,
                'to_path' => $toPath,
            ]);

            return false;
        }
    }

    /**
     * Get file content from SFTP server
     *
     * @param  string  $remoteFile  The remote file path
     * @param  int|null  $timeout  Override timeout in seconds
     * @return string The file content
     */
    public function getFile(string $remoteFile, ?int $timeout = null): string
    {
        if (! $this->isConnected && ! $this->connect()) {
            throw new \Exception('Not connected to SFTP server');
        }

        $fileGetTimeout = $timeout ?? Config::get('sftp.timeout.fileget', 120);

        try {
            // Set timeout for file retrieval
            set_time_limit($fileGetTimeout);
            $this->connection->setTimeout($fileGetTimeout);

            $fileStart = microtime(true);
            Log::channel('clark_sftp')->info("Retrieving file {$remoteFile} with {$fileGetTimeout}s timeout");

            // Get file content
            $content = $this->connection->get($remoteFile);

            if ($content === false) {
                throw new \Exception("Failed to retrieve file: {$remoteFile}");
            }

            $fileTime = round(microtime(true) - $fileStart, 2);
            Log::channel('clark_sftp')->info("File retrieved successfully (took {$fileTime}s)");

            return $content;

        } catch (\Exception $e) {
            $errorMessage = "SFTP file retrieval error: {$e->getMessage()}";
            Log::error($errorMessage, ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Upload a file to the SFTP server
     *
     * @param  string  $localFile  Path to the local file
     * @param  string  $remoteFile  The remote file path
     * @param  int|null  $timeout  Override timeout in seconds
     * @return bool Whether upload was successful
     */
    public function putFile(string $localFile, string $remoteFile, ?int $timeout = null): bool
    {
        if (! $this->isConnected && ! $this->connect()) {
            throw new \Exception('Not connected to SFTP server');
        }

        $filePutTimeout = $timeout ?? Config::get('sftp.timeout.fileput', 120);

        try {
            // Set timeout for file upload
            set_time_limit($filePutTimeout);
            $this->connection->setTimeout($filePutTimeout);

            $fileStart = microtime(true);
            Log::channel('clark_sftp')->info("Uploading file to {$remoteFile} with {$filePutTimeout}s timeout");

            // Upload file
            $success = $this->connection->put($remoteFile, $localFile, SFTP::SOURCE_LOCAL_FILE);

            if (! $success) {
                throw new \Exception("Failed to upload file to: {$remoteFile}");
            }

            $fileTime = round(microtime(true) - $fileStart, 2);
            Log::channel('clark_sftp')->info("File uploaded successfully (took {$fileTime}s)");

            return true;

        } catch (\Exception $e) {
            $errorMessage = "SFTP file upload error: {$e->getMessage()}";
            Log::error($errorMessage, ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Delete a file on the SFTP server
     *
     * @param  string  $remoteFile  The remote file path
     * @return bool Whether deletion was successful
     */
    public function deleteFile(string $remoteFile): bool
    {
        if (! $this->isConnected && ! $this->connect()) {
            throw new \Exception('Not connected to SFTP server');
        }

        try {
            Log::channel('clark_sftp')->info("Deleting file {$remoteFile}");

            // Delete file
            $success = $this->connection->delete($remoteFile);

            if (! $success) {
                throw new \Exception("Failed to delete file: {$remoteFile}");
            }

            Log::channel('clark_sftp')->info('File deleted successfully');

            return true;

        } catch (\Exception $e) {
            $errorMessage = "SFTP file deletion error: {$e->getMessage()}";
            Log::error($errorMessage, ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Check if a file exists on the SFTP server
     *
     * @param  string  $path  File path
     * @return bool Whether file exists
     */
    public function fileExists(string $path): bool
    {
        if (! $this->connect()) {
            return false;
        }

        try {
            return $this->connection->file_exists($path);
        } catch (\Exception $e) {
            Log::channel('clark_sftp')->error('Clark SFTP file check failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            return false;
        }
    }

    /**
     * Close the SFTP connection
     */
    public function disconnect(): void
    {
        if ($this->isConnected && $this->connection) {
            $this->connection->disconnect();
            $this->isConnected = false;
        }
    }

    /**
     * Destructor to ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
