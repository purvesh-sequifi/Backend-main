<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;

class GenerateCompleteSwaggerDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate-complete {--route= : Specific route to document}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate comprehensive Swagger documentation with ALL parameters';

    /**
     * Database tables and their columns
     */
    protected $databaseSchema = [];

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
     * Extract method parameters, including those from FormRequest objects
     */
    protected function extractMethodParameters($controller, $method)
    {
        try {
            $reflectionController = new ReflectionClass($controller);
            $reflectionMethod = $reflectionController->getMethod($method);
            $parameters = $reflectionMethod->getParameters();

            $methodParams = [];

            foreach ($parameters as $parameter) {
                // Skip the request object itself
                if ($parameter->getType() && ($parameter->getType()->getName() === 'Illuminate\Http\Request')) {
                    continue;
                }

                $paramName = $parameter->getName();
                $paramType = $parameter->getType() ? $parameter->getType()->getName() : 'mixed';

                // Handle FormRequest objects
                if ($paramType && class_exists($paramType) && is_subclass_of($paramType, 'Illuminate\Foundation\Http\FormRequest')) {
                    $formRequestParams = $this->extractFormRequestParameters($paramType);
                    $methodParams = array_merge($methodParams, $formRequestParams);
                } else {
                    // Regular parameter
                    $methodParams[$paramName] = [
                        'type' => $this->mapPhpTypeToOpenApiType($paramType),
                        'required' => ! $parameter->isOptional(),
                        'description' => "Parameter: {$paramName}",
                    ];
                }
            }

            return $methodParams;
        } catch (\Exception $e) {
            $this->warn("Error extracting method parameters for {$controller}@{$method}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Extract parameters from FormRequest class
     */
    protected function extractFormRequestParameters($requestClass)
    {
        try {
            $reflection = new ReflectionClass($requestClass);
            $instance = $reflection->newInstanceWithoutConstructor();

            $rules = [];

            // Try to get rules from rules() method
            if ($reflection->hasMethod('rules')) {
                $method = $reflection->getMethod('rules');
                $method->setAccessible(true);

                try {
                    $rules = $method->invoke($instance);
                } catch (\Exception $e) {
                    // If rules() method fails, try to get from $rules property
                    if ($reflection->hasProperty('rules')) {
                        $property = $reflection->getProperty('rules');
                        $property->setAccessible(true);

                        try {
                            $rules = $property->getValue($instance);
                        } catch (\Exception $e) {
                            // Cannot get rules
                        }
                    }
                }
            } elseif ($reflection->hasProperty('rules')) {
                // Look for $rules property if rules() method doesn't exist
                $property = $reflection->getProperty('rules');
                $property->setAccessible(true);

                try {
                    $rules = $property->getValue($instance);
                } catch (\Exception $e) {
                    // Cannot get rules
                }
            }

            $params = [];

            foreach ($rules as $field => $ruleSet) {
                $params[$field] = $this->parseValidationRules($field, $ruleSet);
            }

            return $params;
        } catch (\Exception $e) {
            $this->warn("Error extracting FormRequest parameters for {$requestClass}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Parse Laravel validation rules and convert to OpenAPI schema
     */
    protected function parseValidationRules($field, $rules)
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $param = [
            'type' => 'string',
            'required' => false,
            'description' => "Field: {$field}",
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

                // Set parameter type based on validation rules
                switch ($ruleName) {
                    case 'required':
                        $param['required'] = true;
                        break;

                    case 'integer':
                    case 'numeric':
                        $param['type'] = 'integer';
                        break;

                    case 'boolean':
                        $param['type'] = 'boolean';
                        break;

                    case 'array':
                        $param['type'] = 'array';
                        $param['items'] = ['type' => 'string'];
                        break;

                    case 'date':
                    case 'date_format':
                        $param['type'] = 'string';
                        $param['format'] = 'date';
                        break;

                    case 'email':
                        $param['type'] = 'string';
                        $param['format'] = 'email';
                        break;

                    case 'max':
                        if ($param['type'] === 'string') {
                            $param['maxLength'] = (int) $ruleParams[0];
                        } elseif ($param['type'] === 'integer') {
                            $param['maximum'] = (int) $ruleParams[0];
                        } elseif ($param['type'] === 'array') {
                            $param['maxItems'] = (int) $ruleParams[0];
                        }
                        break;

                    case 'min':
                        if ($param['type'] === 'string') {
                            $param['minLength'] = (int) $ruleParams[0];
                        } elseif ($param['type'] === 'integer') {
                            $param['minimum'] = (int) $ruleParams[0];
                        } elseif ($param['type'] === 'array') {
                            $param['minItems'] = (int) $ruleParams[0];
                        }
                        break;

                    case 'in':
                        $param['enum'] = $ruleParams;
                        break;

                    case 'regex':
                        $param['pattern'] = $ruleParams[0];
                        break;
                }

                // Add more detailed description based on the rule
                if (! isset($param['description_details'])) {
                    $param['description_details'] = [];
                }

                switch ($ruleName) {
                    case 'required':
                        $param['description_details'][] = 'Required field';
                        break;

                    case 'email':
                        $param['description_details'][] = 'Must be a valid email address';
                        break;

                    case 'min':
                        if ($param['type'] === 'string') {
                            $param['description_details'][] = "Minimum length: {$ruleParams[0]} characters";
                        } elseif ($param['type'] === 'integer') {
                            $param['description_details'][] = "Minimum value: {$ruleParams[0]}";
                        } elseif ($param['type'] === 'array') {
                            $param['description_details'][] = "Minimum items: {$ruleParams[0]}";
                        }
                        break;

                    case 'max':
                        if ($param['type'] === 'string') {
                            $param['description_details'][] = "Maximum length: {$ruleParams[0]} characters";
                        } elseif ($param['type'] === 'integer') {
                            $param['description_details'][] = "Maximum value: {$ruleParams[0]}";
                        } elseif ($param['type'] === 'array') {
                            $param['description_details'][] = "Maximum items: {$ruleParams[0]}";
                        }
                        break;

                    case 'in':
                        $param['description_details'][] = 'Allowed values: '.implode(', ', $ruleParams);
                        break;
                }
            }
        }

        // Combine description details
        if (isset($param['description_details']) && ! empty($param['description_details'])) {
            $param['description'] .= ' - '.implode('. ', $param['description_details']);
            unset($param['description_details']);
        }

        return $param;
    }

    /**
     * Map PHP types to OpenAPI types
     */
    protected function mapPhpTypeToOpenApiType($phpType)
    {
        switch (strtolower($phpType)) {
            case 'int':
            case 'integer':
                return 'integer';
            case 'bool':
            case 'boolean':
                return 'boolean';
            case 'float':
            case 'double':
                return 'number';
            case 'array':
                return 'array';
            case 'object':
                return 'object';
            case 'mixed':
            case 'string':
            default:
                return 'string';
        }
    }

    /**
     * Load and cache database schema for all tables
     */
    protected function loadDatabaseSchema()
    {
        if (! empty($this->databaseSchema)) {
            return;
        }

        try {
            $tables = DB::select('SHOW TABLES');

            foreach ($tables as $table) {
                $tableName = reset($table);
                $columns = Schema::getColumnListing($tableName);

                $tableColumns = [];
                foreach ($columns as $column) {
                    $type = Schema::getColumnType($tableName, $column);
                    $tableColumns[$column] = $type;
                }

                $this->databaseSchema[$tableName] = $tableColumns;
            }
        } catch (\Exception $e) {
            $this->warn('Error loading database schema: '.$e->getMessage());
        }
    }

    /**
     * Try to extract model fields from database schema
     */
    protected function getModelFieldsFromDatabase($modelName)
    {
        try {
            // Load database schema if not loaded
            $this->loadDatabaseSchema();

            // Try to guess table name from model name
            $tableName = Str::snake(Str::pluralStudly(class_basename($modelName)));

            if (isset($this->databaseSchema[$tableName])) {
                $fields = [];

                foreach ($this->databaseSchema[$tableName] as $column => $type) {
                    $fields[$column] = [
                        'type' => $this->mapDatabaseTypeToOpenApiType($type),
                        'required' => $column !== 'id' && ! Str::endsWith($column, '_at') && $column !== 'created_at' && $column !== 'updated_at',
                        'description' => "DB field: {$column} ({$type})",
                    ];
                }

                return $fields;
            }
        } catch (\Exception $e) {
            $this->warn("Error getting model fields for {$modelName}: ".$e->getMessage());
        }

        return [];
    }

    /**
     * Map database types to OpenAPI types
     */
    protected function mapDatabaseTypeToOpenApiType($dbType)
    {
        switch (strtolower($dbType)) {
            case 'integer':
            case 'bigint':
            case 'smallint':
            case 'tinyint':
                return 'integer';
            case 'decimal':
            case 'float':
            case 'double':
                return 'number';
            case 'boolean':
                return 'boolean';
            case 'json':
                return 'object';
            case 'text':
            case 'string':
            case 'varchar':
            case 'char':
            default:
                return 'string';
        }
    }

    /**
     * Extract parameters from method doc block
     */
    protected function extractDocBlockParameters($controller, $method)
    {
        try {
            $reflectionController = new ReflectionClass($controller);
            $reflectionMethod = $reflectionController->getMethod($method);
            $docComment = $reflectionMethod->getDocComment();

            if (! $docComment) {
                return [];
            }

            $params = [];

            // Extract @param comments
            preg_match_all('/@param\s+([^\s]+)\s+\$([^\s]+)(?:\s+(.*))?/', $docComment, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $type = isset($match[1]) ? $match[1] : 'mixed';
                $name = isset($match[2]) ? $match[2] : '';
                $description = isset($match[3]) ? $match[3] : "Parameter: {$name}";

                if (! empty($name)) {
                    $params[$name] = [
                        'type' => $this->mapPhpTypeToOpenApiType($type),
                        'required' => true,
                        'description' => $description,
                    ];
                }
            }

            return $params;
        } catch (\Exception $e) {
            $this->warn("Error extracting docblock parameters for {$controller}@{$method}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Analyze code to extract potential parameter usage
     */
    protected function analyzeControllerCodeForParameters($controller, $method)
    {
        try {
            $reflectionController = new ReflectionClass($controller);
            $reflectionMethod = $reflectionController->getMethod($method);

            $fileName = $reflectionMethod->getFileName();
            $startLine = $reflectionMethod->getStartLine();
            $endLine = $reflectionMethod->getEndLine();

            if (! $fileName || ! file_exists($fileName)) {
                return [];
            }

            $fileContent = file_get_contents($fileName);
            $fileLines = explode("\n", $fileContent);
            $methodCode = implode("\n", array_slice($fileLines, $startLine - 1, $endLine - $startLine + 1));

            $params = [];

            // Look for $request->input(...), $request->get(...), etc.
            preg_match_all('/\$request->(input|get|post|query|json|all|only|except)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $methodCode, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $method = $match[1];
                $paramName = $match[2];

                if (! isset($params[$paramName])) {
                    $params[$paramName] = [
                        'type' => 'string',
                        'required' => false,
                        'description' => "Parameter extracted from code: {$paramName}",
                    ];
                }
            }

            // Look for $request->... direct access
            preg_match_all('/\$request->([a-zA-Z0-9_]+)/', $methodCode, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $paramName = $match[1];

                // Skip methods, only include properties
                if (in_array($paramName, ['input', 'get', 'post', 'query', 'json', 'all', 'only', 'except', 'validate', 'has', 'exists'])) {
                    continue;
                }

                if (! isset($params[$paramName])) {
                    $params[$paramName] = [
                        'type' => 'string',
                        'required' => false,
                        'description' => "Parameter extracted from code: {$paramName}",
                    ];
                }
            }

            return $params;
        } catch (\Exception $e) {
            $this->warn("Error analyzing controller code for {$controller}@{$method}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Generate a combined set of parameters from all sources
     */
    protected function generateCompleteParameters($controller, $method, $route)
    {
        $allParams = [];

        // 1. Extract method parameters and FormRequest rules
        $methodParams = $this->extractMethodParameters($controller, $method);
        $allParams = array_merge($allParams, $methodParams);

        // 2. Extract parameters from doc blocks
        $docBlockParams = $this->extractDocBlockParameters($controller, $method);
        $allParams = array_merge($allParams, $docBlockParams);

        // 3. Extract used parameters by analyzing the code
        $codeParams = $this->analyzeControllerCodeForParameters($controller, $method);

        foreach ($codeParams as $name => $param) {
            if (! isset($allParams[$name])) {
                $allParams[$name] = $param;
            }
        }

        // Extract route parameters
        preg_match_all('/{([^}]+)}/', $route, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $routeParam) {
                if (! isset($allParams[$routeParam])) {
                    $allParams[$routeParam] = [
                        'type' => 'string',
                        'required' => true,
                        'description' => "Route parameter: {$routeParam}",
                    ];
                } else {
                    $allParams[$routeParam]['required'] = true;
                    $allParams[$routeParam]['description'] = "Route parameter: {$routeParam}";
                }
            }
        }

        return $allParams;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating comprehensive Swagger documentation with ALL parameters...');

        $routeFilter = $this->option('route');
        $routes = Route::getRoutes();

        $openApiSpec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Sequifi API Documentation',
                'description' => 'Complete API documentation with ALL parameters for Sequifi',
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

        $processedCount = 0;
        $this->info('Scanning routes for complete parameter documentation...');

        $bar = $this->output->createProgressBar(count($routes));
        $bar->start();

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Filter routes
            if ($routeFilter && ! Str::contains($uri, $routeFilter)) {
                $bar->advance();

                continue;
            }

            // Only include API routes
            if (! Str::startsWith($uri, 'api/')) {
                $bar->advance();

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

                // Get tag name from controller namespace
                $tags = [];
                if (Str::contains($controllerClass, 'App\\Http\\Controllers\\API\\')) {
                    $namespaceParts = explode('\\', $controllerClass);

                    // Try to extract category from namespace
                    for ($i = 0; $i < count($namespaceParts); $i++) {
                        if ($namespaceParts[$i] === 'API' && isset($namespaceParts[$i + 1])) {
                            $tags[] = $namespaceParts[$i + 1];
                            break;
                        }
                    }

                    // Fallback to controller name
                    if (empty($tags)) {
                        $controllerName = end($namespaceParts);
                        $tags[] = str_replace('Controller', '', $controllerName);
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
                    $processedCount++;
                }
            } catch (\Exception $e) {
                $this->warn("Error processing route {$uri}: ".$e->getMessage());
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

        // Write the generated OpenAPI spec to a JSON file
        $outputPath = $outputDir.'/api-docs-complete.json';
        File::put($outputPath, json_encode($openApiSpec, JSON_PRETTY_PRINT));

        // Copy to public directory for easy access
        $publicDir = public_path('swagger-json');
        if (! File::exists($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }
        File::put($publicDir.'/api-docs-complete.json', json_encode($openApiSpec, JSON_PRETTY_PRINT));

        // Sync api-docs.json from storage (from l5-swagger:generate) for swagger-ui.html
        $this->syncApiDocsJson($publicDir);

        $this->info("Processed {$processedCount} API endpoints with complete parameter documentation.");
        $this->info("Comprehensive OpenAPI documentation generated at: {$outputPath}");
        $this->info("Public copy available at: {$publicDir}/api-docs-complete.json");

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
