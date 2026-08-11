<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router;

class GenerateRouteSwagger extends Command
{
    protected $signature = 'swagger:generate-routes';

    protected $description = 'Generate L5 Swagger documentation and include every registered API route';

    public function handle(Router $router): int
    {
        $this->call('l5-swagger:generate');
        $path = storage_path('api-docs/api-docs.json');
        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($router->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || str_starts_with($route->uri(), 'api/documentation')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $swaggerPath = '/'.preg_replace('/\{([^}]+)\??\}/', '{$1}', $route->uri());
                $operation = strtolower($method);
                if (isset($document['paths'][$swaggerPath][$operation])) {
                    continue;
                }

                $document['paths'][$swaggerPath][$operation] = $this->operation($route, $method);
            }
        }

        ksort($document['paths']);
        file_put_contents($path, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
        $this->info(count($document['paths']).' API path(s) written to Swagger.');

        return self::SUCCESS;
    }

    private function operation(LaravelRoute $route, string $method): array
    {
        preg_match_all('/\{([^}]+)\??\}/', $route->uri(), $matches);
        $action = class_basename((string) $route->getActionName());
        $tag = ucfirst(explode('/', str_replace('api/', '', $route->uri()))[0] ?: 'API');
        $operation = [
            'tags' => [$tag],
            'summary' => str_replace('@', ' ', $action),
            'operationId' => strtolower($method).'_'.preg_replace('/[^a-zA-Z0-9]+/', '_', $route->uri()),
            'responses' => [
                '200' => ['description' => 'Successful response'],
                '422' => ['description' => 'Validation failed'],
            ],
        ];

        foreach ($matches[1] as $parameter) {
            $operation['parameters'][] = [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'integer'],
            ];
        }

        if (collect($route->gatherMiddleware())->contains(fn ($middleware) => str_contains((string) $middleware, 'sanctum'))) {
            $operation['security'] = [['sanctum' => []]];
            $operation['responses']['401'] = ['description' => 'Unauthenticated'];
            $operation['responses']['403'] = ['description' => 'Forbidden'];
        }

        return $operation;
    }
}
