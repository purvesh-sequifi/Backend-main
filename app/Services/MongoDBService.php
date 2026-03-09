<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use MongoDB\Database;
use MongoDB\Collection;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Exception;

class MongoDBService
{
    private static ?Database $database = null;
    private static ?Client $client = null;
    private static array $collections = [];

    /**
     * Get cached MongoDB database instance (much faster than DB::connection)
     */
    public static function getDatabase(): Database
    {
        if (self::$database === null) {
            // Get MongoDB connection details from Laravel config
            $dsn = config('database.connections.mongodb.dsn');
            $database = config('database.connections.mongodb.database');

            // Fallback to environment variables if config not available
            if (!$dsn) {
                $dsn = config('database.connections.mongodb.dsn');
            }
            if (!$database) {
                $database = config('database.connections.mongodb.database', 'SequifiArena');
            }

            if (!$dsn) {
                throw new Exception('MongoDB connection string (MONGODB_URI) is not configured.');
            }

            // Direct MongoDB client connection (bypasses Laravel's connection overhead)
            self::$client = new Client($dsn);
            self::$database = self::$client->selectDatabase($database);
        }

        return self::$database;
    }

    /**
     * Get cached collection instance (avoids repeated collection selection)
     */
    public static function getCollection(string $collectionName): Collection
    {
        if (!isset(self::$collections[$collectionName])) {
            self::$collections[$collectionName] = self::getDatabase()->selectCollection($collectionName);
        }

        return self::$collections[$collectionName];
    }

    /**
     * Fast connection test using cached client
     */
    public static function ping(): bool
    {
        try {
            $database = self::getDatabase();
            $result = $database->command(['ping' => 1]);
            $resultArray = iterator_to_array($result);
            return isset($resultArray[0]['ok']) && $resultArray[0]['ok'] === 1;
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("MongoDB ping failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get collections list with caching (5 minutes cache)
     */
    public static function listCollections(): array
    {
        return Cache::remember('mongodb_collections', 300, function () {
            $collections = [];
            foreach (self::getDatabase()->listCollections() as $collectionInfo) {
                $collections[] = [
                    'name' => $collectionInfo->getName(),
                    'type' => $collectionInfo->getType() ?? 'collection'
                ];
            }
            return $collections;
        });
    }

    /**
     * Fast document retrieval with optimized projections
     */
    public static function getDocuments(
        string $collection,
        array $filter = [],
        array $options = []
    ): array {
        $coll = self::getCollection($collection);

        // Default options for performance
        $defaultOptions = [
            'limit' => $options['limit'] ?? 10,
            'skip' => $options['skip'] ?? 0,
            'sort' => $options['sort'] ?? ['_id' => -1], // Most recent first
            'projection' => $options['projection'] ?? null, // Limit fields returned
            'maxTimeMS' => 5000, // 5 second timeout
        ];

        // Remove null values
        $finalOptions = array_filter($defaultOptions, fn($value) => $value !== null);

        $cursor = $coll->find($filter, $finalOptions);
        return iterator_to_array($cursor);
    }

    /**
     * Fast document count with caching for common queries
     */
    public static function countDocuments(string $collection, array $filter = []): int
    {
        $cacheKey = 'mongodb_count_' . $collection . '_' . md5(json_encode($filter));

        // Cache counts for 1 minute (adjust based on your data update frequency)
        return Cache::remember($cacheKey, 60, function () use ($collection, $filter) {
            return self::getCollection($collection)->countDocuments($filter);
        });
    }

    /**
     * Optimized aggregation with result caching
     */
    public static function aggregate(
        string $collection,
        array $pipeline,
        bool $useCache = false,
        int $cacheTtl = 300
    ): array {
        $coll = self::getCollection($collection);

        if ($useCache) {
            $cacheKey = 'mongodb_agg_' . $collection . '_' . md5(json_encode($pipeline));
            return Cache::remember($cacheKey, $cacheTtl, function () use ($coll, $pipeline) {
                return iterator_to_array($coll->aggregate($pipeline, ['maxTimeMS' => 10000]));
            });
        }

        return iterator_to_array($coll->aggregate($pipeline, ['maxTimeMS' => 10000]));
    }

    /**
     * Fast bulk insert (much faster than individual inserts)
     */
    public static function insertMany(string $collection, array $documents): array
    {
        $coll = self::getCollection($collection);

        // Add timestamps to all documents
        $timestamp = new UTCDateTime();
        foreach ($documents as &$doc) {
            $doc['created_at'] = $timestamp;
        }

        $result = $coll->insertMany($documents);
        return [
            'inserted_count' => $result->getInsertedCount(),
            'inserted_ids' => array_map('strval', $result->getInsertedIds())
        ];
    }

    /**
     * Fast single insert
     */
    public static function insertOne(string $collection, array $document): string
    {
        $coll = self::getCollection($collection);

        $document['created_at'] = new UTCDateTime();
        $result = $coll->insertOne($document);

        return (string) $result->getInsertedId();
    }

    /**
     * Optimized update operations
     */
    public static function updateMany(
        string $collection,
        array $filter,
        array $update,
        array $options = []
    ): array {
        $coll = self::getCollection($collection);

        // Add updated timestamp
        if (!isset($update['$set'])) {
            $update['$set'] = [];
        }
        $update['$set']['updated_at'] = new UTCDateTime();

        $result = $coll->updateMany($filter, $update, $options);

        return [
            'matched_count' => $result->getMatchedCount(),
            'modified_count' => $result->getModifiedCount(),
            'upserted_count' => $result->getUpsertedCount()
        ];
    }

    /**
     * Clear specific cache keys when data changes
     */
    public static function clearCache(?string $collection = null): void
    {
        if ($collection) {
            // Clear specific collection caches
            Cache::forget('mongodb_collections');

            // Clear count caches for this collection
            $pattern = "mongodb_count_{$collection}_*";
            // Note: In production, you might want to use Redis with pattern deletion

            // Clear aggregation caches for this collection
            $pattern = "mongodb_agg_{$collection}_*";
        } else {
            // Clear all MongoDB caches
            Cache::flush();
        }
    }

    /**
     * Get database statistics (cached)
     */
    public static function getStats(): array
    {
        return Cache::remember('mongodb_stats', 300, function () {
            $result = self::getDatabase()->command(['dbStats' => 1]);
            $stats = iterator_to_array($result)[0];
            return [
                'database' => $stats['db'] ?? 'unknown',
                'collections' => $stats['collections'] ?? 0,
                'objects' => $stats['objects'] ?? 0,
                'data_size' => $stats['dataSize'] ?? 0,
                'storage_size' => $stats['storageSize'] ?? 0,
                'indexes' => $stats['indexes'] ?? 0
            ];
        });
    }
}
