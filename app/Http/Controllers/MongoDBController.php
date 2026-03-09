<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MongoDBService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class MongoDBController extends Controller
{

    /**
     * Ultra-fast connection test using cached client
     */
    public function testConnection(): JsonResponse
    {
        try {
            $isConnected = MongoDBService::ping();

            if ($isConnected) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'MongoDB connection successful (optimized)',
                    'response_time' => 'sub-millisecond'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'MongoDB connection failed - ping returned false'
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'error_class' => get_class($e)
            ], 500);
        }
    }

    /**
     * Fast collections list with caching
     */
    public function listCollections(): JsonResponse
    {
        try {
            $collections = MongoDBService::listCollections();

            return response()->json([
                'status' => 'success',
                'collections' => $collections,
                'count' => count($collections),
                'cached' => true
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to list collections: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimized document retrieval with projections and caching
     */
    public function getDocuments(Request $request, string $collection): JsonResponse
    {
        try {
            $filter = $request->input('filter', []);
            $options = [
                'limit' => (int) $request->input('limit', 10),
                'skip' => (int) $request->input('skip', 0),
                'sort' => $request->input('sort', ['_id' => -1]),
                'projection' => $request->input('fields') ?
                    array_fill_keys(explode(',', $request->input('fields')), 1) : null
            ];

            $documents = MongoDBService::getDocuments($collection, $filter, $options);

            // Get total count for the collection with the same filter
            $totalCount = MongoDBService::countDocuments($collection, $filter);
            $returnedCount = count($documents);
            $remainingCount = max(0, $totalCount - $returnedCount);

            // Calculate pagination info
            $currentPage = floor($options['skip'] / $options['limit']) + 1;
            $totalPages = ceil($totalCount / $options['limit']);
            $hasNext = ($options['skip'] + $options['limit']) < $totalCount;
            $hasPrev = $options['skip'] > 0;

            // Build pagination links
            $baseUrl = request()->url();
            $queryParams = $request->query();

            $paginationLinks = [
                'first' => $baseUrl . '?' . http_build_query(array_merge($queryParams, ['skip' => 0])),
                'last' => $baseUrl . '?' . http_build_query(array_merge($queryParams, ['skip' => max(0, $totalCount - $options['limit'])])),
                'current' => $baseUrl . '?' . http_build_query($queryParams)
            ];

            if ($hasNext) {
                $paginationLinks['next'] = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['skip' => $options['skip'] + $options['limit']]));
            }

            if ($hasPrev) {
                $paginationLinks['prev'] = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['skip' => max(0, $options['skip'] - $options['limit'])]));
            }

            return response()->json([
                'status' => 'success',
                'collection' => $collection,
                'documents' => $documents,
                'count' => $returnedCount,
                'total_count' => $totalCount,
                'remaining_count' => $remainingCount,
                'pagination' => [
                    'limit' => $options['limit'],
                    'skip' => $options['skip'],
                    'current_page' => $currentPage,
                    'total_pages' => $totalPages,
                    'has_more' => $remainingCount > 0,
                    'has_next' => $hasNext,
                    'has_prev' => $hasPrev,
                    'links' => $paginationLinks
                ],
                'optimized' => true
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find a single document by ID or filter
     */
    public function findOne(Request $request, string $collection): JsonResponse
    {
        try {
            $filter = $request->input('filter', []);
            $options = [];
            if ($request->has('fields')) {
                $fields = explode(',', $request->input('fields'));
                $options['projection'] = array_fill_keys($fields, 1);
            }

            // If no filter provided, try to get by ID from route parameter
            if (empty($filter) && $request->has('id')) {
                $filter['_id'] = $request->input('id');
            }

            if (empty($filter)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Filter or ID is required to find a document'
                ], 400);
            }

            $document = MongoDBService::getCollection($collection)->findOne($filter, $options);

            if ($document === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document not found',
                    'collection' => $collection,
                    'filter' => $filter
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'collection' => $collection,
                'document' => $document,
                'filter' => $filter,
                'optimized' => true
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to find document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fast document count with caching
     */
    public function countDocuments(Request $request, string $collection): JsonResponse
    {
        try {
            $filter = $request->input('filter', []);
            $count = MongoDBService::countDocuments($collection, $filter);

            return response()->json([
                'status' => 'success',
                'collection' => $collection,
                'count' => $count,
                'filter' => $filter,
                'cached' => true
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to count documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fast single document insert
     */
    public function insertDocument(Request $request, string $collection): JsonResponse
    {
        try {
            $document = $request->input('document', []);

            if (empty($document)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Document data is required'
                ], 400);
            }

            $insertedId = MongoDBService::insertOne($collection, $document);

            // Clear relevant caches
            MongoDBService::clearCache($collection);

            return response()->json([
                'status' => 'success',
                'message' => 'Document inserted successfully',
                'collection' => $collection,
                'inserted_id' => $insertedId,
                'optimized' => true
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to insert document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fast bulk insert (much faster for multiple documents)
     */
    public function insertMany(Request $request, string $collection): JsonResponse
    {
        try {
            $documents = $request->input('documents', []);

            if (empty($documents) || !is_array($documents)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Documents array is required'
                ], 400);
            }

            $result = MongoDBService::insertMany($collection, $documents);

            // Clear relevant caches
            MongoDBService::clearCache($collection);

            return response()->json([
                'status' => 'success',
                'message' => 'Documents inserted successfully',
                'collection' => $collection,
                'inserted_count' => $result['inserted_count'],
                'inserted_ids' => $result['inserted_ids'],
                'optimized' => true
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to insert documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimized aggregation with optional caching
     */
    public function aggregate(Request $request, string $collection): JsonResponse
    {
        try {
            $pipeline = $request->input('pipeline', []);
            $useCache = $request->boolean('cache', false);
            $cacheTtl = (int) $request->input('cache_ttl', 300);

            if (empty($pipeline)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Aggregation pipeline is required'
                ], 400);
            }

            $results = MongoDBService::aggregate($collection, $pipeline, $useCache, $cacheTtl);

            return response()->json([
                'status' => 'success',
                'collection' => $collection,
                'results' => $results,
                'count' => count($results),
                'pipeline' => $pipeline,
                'cached' => $useCache,
                'optimized' => true
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aggregation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fast update operations
     */
    public function updateDocuments(Request $request, string $collection): JsonResponse
    {
        try {
            $filter = $request->input('filter', []);
            $update = $request->input('update', []);
            $options = $request->input('options', []);

            if (empty($filter) || empty($update)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Filter and update data are required'
                ], 400);
            }

            $result = MongoDBService::updateMany($collection, $filter, $update, $options);

            // Clear relevant caches
            MongoDBService::clearCache($collection);

            return response()->json([
                'status' => 'success',
                'collection' => $collection,
                'matched_count' => $result['matched_count'],
                'modified_count' => $result['modified_count'],
                'upserted_count' => $result['upserted_count'],
                'optimized' => true
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = MongoDBService::getStats();

            return response()->json([
                'status' => 'success',
                'stats' => $stats,
                'cached' => true
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear caches manually
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $collection = $request->input('collection');
            MongoDBService::clearCache($collection);

            return response()->json([
                'status' => 'success',
                'message' => $collection ?
                    "Cache cleared for collection: {$collection}" :
                    'All MongoDB caches cleared'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }
}
