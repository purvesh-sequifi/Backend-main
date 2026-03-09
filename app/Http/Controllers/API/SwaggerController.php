<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Sequifi API Documentation",
 *     version="1.0.0",
 *     description="Complete API documentation for Sequifi Backend",
 *
 *     @OA\Contact(
 *         email="gary@sequifi.in",
 *         name="Sequifi Support"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="api_key",
 *     type="apiKey",
 *     in="header",
 *     name="api-key"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer"
 * )
 */
class SwaggerController extends Controller
{
    /**
     * Generate OpenAPI schema for all routes
     */
    public function generateApiDocs(): JsonResponse
    {
        $routes = Route::getRoutes();
        $apiRoutes = [];

        // Filter for API routes
        foreach ($routes as $route) {
            $uri = $route->uri();

            // Only include routes that start with 'api/'
            if (Str::startsWith($uri, 'api/')) {
                $methods = $route->methods();
                $action = $route->getAction();

                if (isset($action['controller'])) {
                    $controller = $action['controller'];
                    [$controllerClass, $method] = explode('@', $controller);

                    $apiRoutes[] = [
                        'uri' => $uri,
                        'methods' => $methods,
                        'controller' => $controllerClass,
                        'method' => $method,
                    ];
                }
            }
        }

        return response()->json($apiRoutes);
    }

    /**
     * @OA\Get(
     *     path="/api/documentation",
     *     summary="Get API documentation",
     *     description="Returns all API routes in JSON format",
     *     operationId="getApiDocs",
     *     tags={"Documentation"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="uri", type="string"),
     *                 @OA\Property(property="methods", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="controller", type="string"),
     *                 @OA\Property(property="method", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return $this->generateApiDocs();
    }
}
