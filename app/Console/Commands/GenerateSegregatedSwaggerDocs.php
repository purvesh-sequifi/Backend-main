<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateSegregatedSwaggerDocs extends GenerateCompleteSwaggerDocs
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate-segregated {--route= : Specific route to document}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Swagger documentation with ALL parameters, segregated between in-house and external APIs';

    /**
     * List of external API prefixes and namespaces
     *
     * @var array
     */
    protected $externalApiIdentifiers = [
        // Prefixes
        'prefixes' => [
            'plaid',
            'everee',
            's-clearance',
            's_clearance',

            'digisigner',
            'hubspot',
            'turn-ai',
            'turn_ai',
            'turnai',
            's_clearance_turn',
            'stripe',
            'v1/s-clearance',
            'v1/s_clearance_turn',
            'v1/turn',
            'v2/turn',
            'v3/turn',
            'background-verification',
        ],
        // Namespace fragments that indicate external APIs
        'namespaces' => [
            'Plaid',
            'Everee',
            'SClearance',
            'TurnAi',
            'Turn\\',
            'TurnAPI',
            'Digisigner',
            'HubSpot',
            'Stripe',
            'ExternalHiring',
            'BackgroundCheck',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating segregated Swagger documentation with ALL parameters...');

        // Load database schema
        $this->loadDatabaseSchema();

        // Initialize OpenAPI specification
        $openApiSpec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Sequifi API Documentation',
                'description' => 'Comprehensive API documentation for Sequifi Backend',
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => '/api',
                    'description' => 'API Server',
                ],
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Laravel Sanctum token authentication',
                    ],
                    'api_key' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'api-key',
                        'description' => 'API key authentication',
                    ],
                ],
            ],
            'tags' => [
                [
                    'name' => 'In-House APIs',
                    'description' => 'APIs developed and maintained internally by Sequifi',
                ],
                [
                    'name' => 'External APIs',
                    'description' => 'APIs that interface with external services and third-party systems',
                ],
            ],
        ];

        // Get all routes
        $routes = Route::getRoutes();

        // Filter route based on option
        $specificRoute = $this->option('route');

        // Count the number of API endpoints
        $endpointCount = 0;
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (Str::startsWith($uri, 'api/') && isset($route->getAction()['controller'])) {
                $endpointCount++;
            }
        }

        $this->info('Scanning routes for complete parameter documentation...');
        $bar = $this->output->createProgressBar($endpointCount);
        $bar->start();

        // Process each route
        foreach ($routes as $route) {
            $uri = $route->uri();

            // Skip if a specific route is specified and this isn't it
            if ($specificRoute && $uri !== $specificRoute) {
                continue;
            }

            // Only include API routes
            if (! Str::startsWith($uri, 'api/')) {
                if ($specificRoute) {
                    $bar->advance();
                }

                continue;
            }

            $methods = $route->methods();
            $action = $route->getAction();

            // Skip routes without a controller
            if (! isset($action['controller'])) {
                $bar->advance();

                continue;
            }

            try {
                $controller = $action['controller'];

                // Handle invokable controllers
                if (Str::contains($controller, '@')) {
                    [$controllerClass, $methodName] = explode('@', $controller);
                } else {
                    $controllerClass = $controller;
                    $methodName = '__invoke';
                }

                // Format the path
                $openApiPath = '/'.$uri;
                $openApiPath = preg_replace('/{([^}]+)}/', '{$1}', $openApiPath);

                // Get route parameters
                $parameters = [];
                preg_match_all('/{([^}]+)}/', $openApiPath, $matches);
                if (isset($matches[1])) {
                    foreach ($matches[1] as $param) {
                        $parameters[] = [
                            'name' => $param,
                            'in' => 'path',
                            'required' => true,
                            'schema' => [
                                'type' => 'string',
                            ],
                            'description' => "Route parameter: {$param}",
                        ];
                    }
                }

                // Determine if this is an external API
                $isExternalApi = $this->isExternalApi($uri, $controllerClass);

                // Get tag name from controller namespace
                $tags = [];

                // Add top-level category tag
                $tags[] = $isExternalApi ? 'External APIs' : 'In-House APIs';

                // Add more specific tag based on controller namespace
                if (Str::contains($controllerClass, 'App\\Http\\Controllers\\API\\')) {
                    $namespaceParts = explode('\\', $controllerClass);

                    // Try to extract category from namespace
                    for ($i = 0; $i < count($namespaceParts); $i++) {
                        if ($namespaceParts[$i] === 'API' && isset($namespaceParts[$i + 1])) {
                            $subTag = $namespaceParts[$i + 1];
                            if ($isExternalApi) {
                                // For external APIs, prefix with "External:"
                                $tags[] = "External: {$subTag}";
                            } else {
                                $tags[] = $subTag;
                            }
                            break;
                        }
                    }

                    // Fallback to controller name
                    if (count($tags) === 1) {
                        $controllerName = end($namespaceParts);
                        $subTag = str_replace('Controller', '', $controllerName);
                        if ($isExternalApi) {
                            // For external APIs, prefix with "External:"
                            $tags[] = "External: {$subTag}";
                        } else {
                            $tags[] = $subTag;
                        }
                    }
                } else {
                    $tags[] = 'API';
                }

                // Initialize path in OpenAPI spec
                if (! isset($openApiSpec['paths'][$openApiPath])) {
                    $openApiSpec['paths'][$openApiPath] = [];
                }

                // Process each HTTP method
                foreach ($methods as $method) {
                    // Skip HEAD and OPTIONS methods
                    if (in_array($method, ['HEAD', 'OPTIONS'])) {
                        continue;
                    }

                    $method = strtolower($method);

                    // Generate an operation ID
                    $operationId = Str::camel(implode('_', array_filter(explode('/', str_replace(['{', '}'], '', $uri))))).ucfirst($method);

                    // Determine if authentication is required
                    $security = [];
                    if (isset($action['middleware']) && is_array($action['middleware'])) {
                        if (in_array('auth:sanctum', $action['middleware'])) {
                            $security[] = ['sanctum' => []];
                        }
                        if (in_array('swaggerAuth', $action['middleware'])) {
                            $security[] = ['api_key' => []];
                        }
                    }

                    // Generate complete parameters for this endpoint
                    $completeParams = $this->generateCompleteParameters($controllerClass, $methodName, $uri);

                    // Prepare parameters for OpenAPI spec
                    $pathParameters = [];
                    $bodyParameters = [];

                    foreach ($parameters as $param) {
                        $pathParameters[] = $param;
                    }

                    // Create parameters and request body
                    foreach ($completeParams as $name => $param) {
                        // Skip parameters already included in path parameters
                        if (in_array($name, array_column($pathParameters, 'name'))) {
                            continue;
                        }

                        // For GET, include as query parameters
                        if ($method === 'get') {
                            $pathParameters[] = [
                                'name' => $name,
                                'in' => 'query',
                                'required' => isset($param['required']) ? $param['required'] : false,
                                'schema' => [
                                    'type' => isset($param['type']) ? $param['type'] : 'string',
                                ],
                                'description' => isset($param['description']) ? $param['description'] : "Parameter: {$name}",
                            ];
                        } else {
                            // For POST, PUT, PATCH, include in request body
                            $bodyParameters[$name] = [
                                'type' => isset($param['type']) ? $param['type'] : 'string',
                                'description' => isset($param['description']) ? $param['description'] : "Parameter: {$name}",
                            ];

                            // Include additional schema properties
                            foreach ($param as $key => $value) {
                                if (! in_array($key, ['type', 'description', 'required'])) {
                                    $bodyParameters[$name][$key] = $value;
                                }
                            }
                        }
                    }

                    // Build OpenAPI operation
                    $operation = [
                        'tags' => $tags,
                        'summary' => "Endpoint for {$openApiPath}",
                        'description' => "Controller: {$controllerClass}@{$methodName}",
                        'operationId' => $operationId,
                        'parameters' => $pathParameters,
                        'responses' => [
                            '200' => [
                                'description' => 'Successful operation',
                            ],
                            '400' => [
                                'description' => 'Bad request',
                            ],
                            '401' => [
                                'description' => 'Unauthorized',
                            ],
                            '500' => [
                                'description' => 'Server error',
                            ],
                        ],
                    ];

                    // Add security if required
                    if (! empty($security)) {
                        $operation['security'] = $security;
                    }

                    // Add request body for non-GET methods
                    if ($method !== 'get' && ! empty($bodyParameters)) {
                        $operation['requestBody'] = [
                            'description' => 'Request body',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => $bodyParameters,
                                    ],
                                ],
                            ],
                        ];

                        // Add required properties
                        $requiredProperties = [];
                        foreach ($completeParams as $name => $param) {
                            if (isset($param['required']) && $param['required'] && isset($bodyParameters[$name])) {
                                $requiredProperties[] = $name;
                            }
                        }

                        if (! empty($requiredProperties)) {
                            $operation['requestBody']['content']['application/json']['schema']['required'] = $requiredProperties;
                        }
                    }

                    // Add operation to OpenAPI spec
                    $openApiSpec['paths'][$openApiPath][$method] = $operation;
                }
            } catch (\Exception $e) {
                $this->warn("Error processing route {$uri}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Calculate processed endpoints
        $processedEndpoints = count($openApiSpec['paths']);
        $this->info("Processed {$processedEndpoints} API endpoints with complete parameter documentation.");

        // Create the output directory if it doesn't exist
        $outputDir = storage_path('api-docs');
        if (! File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Save the OpenAPI specification
        $outputFile = $outputDir.'/api-docs-segregated.json';
        File::put($outputFile, json_encode($openApiSpec, JSON_PRETTY_PRINT));
        $this->info("Segregated OpenAPI documentation generated at: {$outputFile}");

        // Copy to public directory
        $publicDir = public_path('swagger-json');
        if (! File::exists($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }

        $publicFile = $publicDir.'/api-docs-segregated.json';
        File::copy($outputFile, $publicFile);
        $this->info("Public copy available at: {$publicFile}");

        // Sync api-docs.json from storage (from l5-swagger:generate) for swagger-ui.html
        $this->syncApiDocsJson($publicDir);

        return 0;
    }

    /**
     * Sync api-docs.json from storage to public for swagger-ui.html.
     * Run `php artisan l5-swagger:generate` first to create the source file.
     */
    protected function syncApiDocsJson(string $publicDir): void
    {
        $source = storage_path('api-docs/api-docs.json');
        $dest = $publicDir.'/api-docs.json';
        if (File::exists($source)) {
            File::copy($source, $dest);
            $this->info("Synced api-docs.json to public for swagger-ui.html.");
        } else {
            $this->warn("api-docs.json not found. Run: php artisan l5-swagger:generate");
        }
    }

    /**
     * Determine if an API is external based on URI or controller class
     */
    protected function isExternalApi(string $uri, string $controllerClass): bool
    {
        // Check by URI prefix
        foreach ($this->externalApiIdentifiers['prefixes'] as $prefix) {
            if (Str::contains($uri, "api/{$prefix}/") || Str::contains($uri, "api/{$prefix}") || Str::endsWith($uri, $prefix)) {
                return true;
            }
        }

        // Check by controller namespace
        foreach ($this->externalApiIdentifiers['namespaces'] as $namespace) {
            if (Str::contains($controllerClass, "\\{$namespace}\\") || Str::endsWith($controllerClass, "\\{$namespace}Controller")) {
                return true;
            }
        }

        return false;
    }
}
