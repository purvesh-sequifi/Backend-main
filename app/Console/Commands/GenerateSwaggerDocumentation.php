<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;

class GenerateSwaggerDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:auto-generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generate Swagger documentation for all API routes';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    /**
     * Extract validation rules from a FormRequest class
     */
    protected function extractValidationRules(string $requestClass): array
    {
        if (! class_exists($requestClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($requestClass);
            $instance = $reflection->newInstanceWithoutConstructor();

            // Try to get rules from rules() method
            if ($reflection->hasMethod('rules')) {
                $method = $reflection->getMethod('rules');
                $method->setAccessible(true);

                try {
                    return $method->invoke($instance);
                } catch (\Exception $e) {
                    // If rules() method fails, look for $rules property
                    if ($reflection->hasProperty('rules')) {
                        $property = $reflection->getProperty('rules');
                        $property->setAccessible(true);

                        try {
                            return $property->getValue($instance);
                        } catch (\Exception $e) {
                            // Unable to get rules
                            return [];
                        }
                    }
                }
            }

            // Look for $rules property if rules() method doesn't exist
            if ($reflection->hasProperty('rules')) {
                $property = $reflection->getProperty('rules');
                $property->setAccessible(true);

                try {
                    return $property->getValue($instance);
                } catch (\Exception $e) {
                    // Unable to get rules
                    return [];
                }
            }
        } catch (\Exception $e) {
            // Unable to reflect class
            return [];
        }

        return [];
    }

    /**
     * Convert Laravel validation rules to OpenAPI schema
     *
     * @param  string|array  $rules
     */
    protected function convertRulesToSchema($rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $schema = [
            'type' => 'string',
        ];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                // Parse rule with parameters
                if (Str::contains($rule, ':')) {
                    [$ruleName, $ruleParams] = explode(':', $rule, 2);
                    $ruleParams = explode(',', $ruleParams);
                } else {
                    $ruleName = $rule;
                    $ruleParams = [];
                }

                switch ($ruleName) {
                    case 'integer':
                    case 'numeric':
                        $schema['type'] = 'integer';
                        break;

                    case 'boolean':
                        $schema['type'] = 'boolean';
                        break;

                    case 'array':
                        $schema['type'] = 'array';
                        $schema['items'] = ['type' => 'string'];
                        break;

                    case 'date':
                    case 'date_format':
                        $schema['type'] = 'string';
                        $schema['format'] = 'date';
                        break;

                    case 'email':
                        $schema['type'] = 'string';
                        $schema['format'] = 'email';
                        break;

                    case 'required':
                        // This will be handled separately
                        break;

                    case 'max':
                        if ($schema['type'] === 'string') {
                            $schema['maxLength'] = (int) $ruleParams[0];
                        } elseif ($schema['type'] === 'integer') {
                            $schema['maximum'] = (int) $ruleParams[0];
                        } elseif ($schema['type'] === 'array') {
                            $schema['maxItems'] = (int) $ruleParams[0];
                        }
                        break;

                    case 'min':
                        if ($schema['type'] === 'string') {
                            $schema['minLength'] = (int) $ruleParams[0];
                        } elseif ($schema['type'] === 'integer') {
                            $schema['minimum'] = (int) $ruleParams[0];
                        } elseif ($schema['type'] === 'array') {
                            $schema['minItems'] = (int) $ruleParams[0];
                        }
                        break;

                    case 'in':
                        $schema['enum'] = $ruleParams;
                        break;
                }
            }
        }

        return $schema;
    }

    /**
     * Check if field is required based on rules
     *
     * @param  array|string  $rules
     */
    protected function isFieldRequired($rules): bool
    {
        if (is_string($rules)) {
            return Str::contains($rules, 'required');
        }

        if (is_array($rules)) {
            foreach ($rules as $rule) {
                if (is_string($rule) && $rule === 'required') {
                    return true;
                }
            }
        }

        return false;
    }

    public function handle(): int
    {
        $this->info('Scanning API routes...');

        $routes = Route::getRoutes();
        $apiRoutes = [];
        $processedCount = 0;

        // Create a master OpenAPI file
        $openApiMaster = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Sequifi API Documentation',
                'description' => 'Complete API documentation for Sequifi',
                'version' => '1.0.0',
                'contact' => [
                    'email' => 'gary@sequifi.in',
                ],
            ],
            'servers' => [
                [
                    'url' => '/api',
                    'description' => 'API Server',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'api_key' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'api-key',
                    ],
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'paths' => [],
        ];

        $this->info('Scanning API routes for documentation...');
        $bar = $this->output->createProgressBar(count($routes));
        $bar->start();

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Only include routes that start with 'api'
            if (Str::startsWith($uri, 'api/')) {
                $methods = $route->methods();
                $action = $route->getAction();

                // Skip routes with no controller
                if (! isset($action['controller'])) {
                    $bar->advance();

                    continue;
                }

                try {
                    $controller = $action['controller'];

                    // Handle invokable controllers
                    if (Str::contains($controller, '@')) {
                        [$controllerClass, $method] = explode('@', $controller);
                    } else {
                        $controllerClass = $controller;
                        $method = '__invoke';
                    }

                    // Clean up the URI for OpenAPI format
                    $openApiPath = '/'.$uri;

                    // Replace route parameters with OpenAPI parameters
                    $openApiPath = preg_replace('/{([^}]+)}/', '{$1}', $openApiPath);

                    // Extract route parameters
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
                            ];
                        }
                    }

                    // Create basic documentation for each HTTP method
                    foreach ($methods as $method) {
                        // Skip HEAD and OPTIONS methods
                        if (in_array($method, ['HEAD', 'OPTIONS'])) {
                            continue;
                        }

                        $method = strtolower($method);

                        // Determine appropriate tag name from controller namespace
                        $tags = [];
                        if (Str::contains($controllerClass, 'App\\Http\\Controllers\\API\\')) {
                            $namespaceParts = explode('\\', $controllerClass);
                            $controllerName = end($namespaceParts);

                            // Try to determine a category from the namespace
                            for ($i = 0; $i < count($namespaceParts); $i++) {
                                if ($namespaceParts[$i] === 'API' && isset($namespaceParts[$i + 1])) {
                                    $tags[] = $namespaceParts[$i + 1];
                                    break;
                                }
                            }

                            // If no category found, use controller name
                            if (empty($tags)) {
                                $tags[] = str_replace('Controller', '', $controllerName);
                            }
                        } else {
                            // Fallback to a generic tag
                            $tags[] = 'API';
                        }

                        // Generate an operationId
                        $operationId = Str::camel(
                            implode('_', array_filter(explode('/', str_replace(['{', '}'], '', $uri))))
                        );

                        if (! isset($openApiMaster['paths'][$openApiPath])) {
                            $openApiMaster['paths'][$openApiPath] = [];
                        }

                        // Determine if this route requires authentication
                        $security = [];
                        if (isset($action['middleware']) && is_array($action['middleware'])) {
                            if (in_array('auth:sanctum', $action['middleware'])) {
                                $security[] = ['sanctum' => []];
                            }
                            if (in_array('swaggerAuth', $action['middleware'])) {
                                $security[] = ['api_key' => []];
                            }
                        }

                        // Basic endpoint documentation
                        $openApiMaster['paths'][$openApiPath][$method] = [
                            'tags' => $tags,
                            'summary' => 'Endpoint for '.$openApiPath,
                            'operationId' => $method.ucfirst($operationId),
                            'parameters' => $parameters,
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
                            $openApiMaster['paths'][$openApiPath][$method]['security'] = $security;
                        }

                        // Try to add request body for POST, PUT, PATCH methods
                        if (in_array($method, ['post', 'put', 'patch'])) {
                            // Look for type-hinted FormRequest in the controller method
                            try {
                                $reflectionController = new ReflectionClass($controllerClass);
                                if ($reflectionController->hasMethod($method)) {
                                    $reflectionMethod = $reflectionController->getMethod($method);
                                    $parameters = $reflectionMethod->getParameters();

                                    $requestBodyProperties = [];
                                    $requiredProperties = [];

                                    // Look for FormRequest parameters
                                    foreach ($parameters as $parameter) {
                                        if ($parameter->getType() && ! $parameter->getType()->isBuiltin()) {
                                            $parameterType = $parameter->getType()->getName();

                                            // If this is a FormRequest class
                                            if (is_subclass_of($parameterType, 'Illuminate\Foundation\Http\FormRequest')) {
                                                // Extract validation rules
                                                $rules = $this->extractValidationRules($parameterType);

                                                // Convert rules to OpenAPI schema
                                                foreach ($rules as $field => $fieldRules) {
                                                    $schema = $this->convertRulesToSchema($fieldRules);
                                                    $requestBodyProperties[$field] = $schema;

                                                    // Check if field is required
                                                    if ($this->isFieldRequired($fieldRules)) {
                                                        $requiredProperties[] = $field;
                                                    }
                                                }

                                                break; // Only use the first FormRequest parameter
                                            }
                                        }
                                    }

                                    // Create a request body schema with the extracted properties
                                    $requestBodySchema = [
                                        'type' => 'object',
                                        'properties' => $requestBodyProperties,
                                    ];

                                    if (! empty($requiredProperties)) {
                                        $requestBodySchema['required'] = $requiredProperties;
                                    }

                                    // If we found properties, use them for the request body
                                    if (! empty($requestBodyProperties)) {
                                        $openApiMaster['paths'][$openApiPath][$method]['requestBody'] = [
                                            'description' => 'Request body',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => $requestBodySchema,
                                                ],
                                            ],
                                        ];
                                    } else {
                                        // Default request body if no properties found
                                        $openApiMaster['paths'][$openApiPath][$method]['requestBody'] = [
                                            'description' => 'Request body',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => [
                                                        'type' => 'object',
                                                    ],
                                                ],
                                            ],
                                        ];
                                    }
                                }
                            } catch (\Exception $e) {
                                // Default request body if reflection fails
                                $openApiMaster['paths'][$openApiPath][$method]['requestBody'] = [
                                    'description' => 'Request body',
                                    'content' => [
                                        'application/json' => [
                                            'schema' => [
                                                'type' => 'object',
                                            ],
                                        ],
                                    ],
                                ];
                            }
                        }

                        $processedCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("Error processing route {$uri}: ".$e->getMessage());
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Create output directory if not exists
        $outputDir = storage_path('api-docs');
        if (! File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Write the generated OpenAPI documentation to a JSON file
        $outputPath = $outputDir.'/api-docs-auto.json';
        File::put($outputPath, json_encode($openApiMaster, JSON_PRETTY_PRINT));

        // Also write to public directory for easy access
        $publicDir = public_path('swagger-json');
        if (! File::exists($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }
        File::put($publicDir.'/api-docs-auto.json', json_encode($openApiMaster, JSON_PRETTY_PRINT));

        // Sync api-docs.json from storage (from l5-swagger:generate) for swagger-ui.html
        $this->syncApiDocsJson($publicDir);

        $this->info("Processed {$processedCount} API endpoints.");
        $this->info("OpenAPI documentation generated at: {$outputPath}");
        $this->info("Public copy available at: {$publicDir}/api-docs-auto.json");

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
}
