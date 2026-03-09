<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SFTP;

class SftpService
{
    /** @var SFTP The SFTP connection instance */
    protected $connection;

    /** @var array SFTP configuration */
    protected $config;

    /** @var string Connection name */
    protected $connectionName;

    /** @var bool Whether connection is established */
    protected $isConnected = false;

    /**
     * Create a new SFTP service instance
     *
     * @param  string  $connectionName  The connection name from sftp.php config
     * @return void
     */
    public function __construct(string $connectionName = 'default')
    {
        $this->connectionName = $connectionName;
        $this->config = Config::get("sftp.connections.{$connectionName}");

        if (! $this->config) {
            throw new \InvalidArgumentException("SFTP connection '{$connectionName}' not configured");
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
            return true;
        }

        $host = $this->config['host'];
        $port = $this->config['port'];
        $username = $this->config['username'];
        $encryptedPassword = $this->config['password'];
        // Decrypt the password for use
        $password = '398ytaghobf'; // EncryptionService::decrypt($encryptedPassword);

        if ($password === false) {
            Log::error('Failed to decrypt SFTP password');
            throw new \Exception('Failed to decrypt SFTP password');
        }

        // Set timeout constants
        $timeout = $connectionTimeout ?? Config::get('sftp.timeout.connection', 30);

        try {
            // Create SFTP connection
            $this->connection = new SFTP($host, $port);

            // Set connection timeout
            $this->connection->setTimeout($timeout);

            Log::info("Connecting to SFTP server at {$host}:{$port} with {$timeout}s timeout");

            // Attempt login
            $loginStart = microtime(true);

            if (! $this->connection->login($username, $password)) {
                Log::error('SFTP login failed');
                throw new \Exception('SFTP login failed');
            }

            $loginTime = round(microtime(true) - $loginStart, 2);
            Log::info("SFTP login successful (took {$loginTime}s)");

            $this->isConnected = true;

            return true;

        } catch (\Exception $e) {
            $errorMessage = "SFTP connection error: {$e->getMessage()}";
            Log::error($errorMessage, ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * List files in a directory
     *
     * @param  string|null  $remotePath  The remote directory path
     * @param  int|null  $timeout  Override timeout in seconds
     * @return array List of files
     */
    public function listFiles(?string $remotePath = null, ?int $timeout = null): array
    {
        if (! $this->isConnected && ! $this->connect()) {
            throw new \Exception('Not connected to SFTP server');
        }

        $path = $remotePath ?? $this->config['remote_path'] ?? '';
        $listTimeout = $timeout ?? Config::get('sftp.timeout.dirlist', 60);

        try {
            // Set timeout for directory listing
            set_time_limit($listTimeout);
            $this->connection->setTimeout($listTimeout);

            $listStart = microtime(true);
            Log::info("Listing directory contents with {$listTimeout}s timeout");

            // Get directory listing
            $files = $this->connection->nlist($path);

            $listTime = round(microtime(true) - $listStart, 2);
            Log::info("Directory listing successful (took {$listTime}s)");

            return $files;

        } catch (\Exception $e) {
            $errorMessage = "SFTP directory listing error: {$e->getMessage()}";
            Log::error($errorMessage, ['exception' => $e]);
            throw $e;
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
            Log::info("Retrieving file {$remoteFile} with {$fileGetTimeout}s timeout");

            // Get file content
            $content = $this->connection->get($remoteFile);

            if ($content === false) {
                throw new \Exception("Failed to retrieve file: {$remoteFile}");
            }

            $fileTime = round(microtime(true) - $fileStart, 2);
            Log::info("File retrieved successfully (took {$fileTime}s)");

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
            Log::info("Uploading file to {$remoteFile} with {$filePutTimeout}s timeout");

            // Upload file
            $success = $this->connection->put($remoteFile, $localFile, SFTP::SOURCE_LOCAL_FILE);

            if (! $success) {
                throw new \Exception("Failed to upload file to: {$remoteFile}");
            }

            $fileTime = round(microtime(true) - $fileStart, 2);
            Log::info("File uploaded successfully (took {$fileTime}s)");

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
            Log::info("Deleting file {$remoteFile}");

            // Delete file
            $success = $this->connection->delete($remoteFile);

            if (! $success) {
                throw new \Exception("Failed to delete file: {$remoteFile}");
            }

            Log::info('File deleted successfully');

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
     * @param  string  $remoteFile  The remote file path
     * @return bool Whether file exists
     */
    public function fileExists(string $remoteFile): bool
    {
        if (! $this->isConnected && ! $this->connect()) {
            throw new \Exception('Not connected to SFTP server');
        }

        try {
            return $this->connection->file_exists($remoteFile);
        } catch (\Exception $e) {
            $errorMessage = "SFTP file check error: {$e->getMessage()}";
            Log::error($errorMessage, ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Create a directory on the SFTP server
     *
     * @param  string  $remoteDir  The remote directory path
     * @param  bool  $recursive  Whether to create parent directories
     * @return bool Whether directory creation was successful
     */
    public function mkdir(string $remoteDir, bool $recursive = true): bool
    {
        if (! $this->isConnected && ! $this->connect()) {
            throw new \Exception('Not connected to SFTP server');
        }

        try {
            return $this->connection->mkdir($remoteDir, -1, $recursive);
        } catch (\Exception $e) {
            $errorMessage = "SFTP directory creation error: {$e->getMessage()}";
            Log::error($errorMessage, ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Disconnect from the SFTP server
     */
    public function disconnect(): void
    {
        if ($this->isConnected && $this->connection) {
            $this->connection->disconnect();
            $this->isConnected = false;
            Log::info('Disconnected from SFTP server');
        }
    }

    /**
     * Get the raw SFTP connection for advanced operations
     *
     * @return SFTP|null The SFTP connection instance
     */
    public function getConnection(): ?SFTP
    {
        if (! $this->isConnected) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Magic method to handle object destruction
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
