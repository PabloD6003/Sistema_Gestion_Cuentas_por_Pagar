<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<int, array{method: string, path: string, regex: string, handler: callable|array{0: class-string, 1: string}}> */
    private array $routes = [];

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => $this->compile($path),
            'handler' => $handler,
        ];
    }

    private function compile(string $path): string
    {
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $matches): string => '(?P<' . $matches[1] . '>[^/]+)',
            $path
        );

        return '#^' . ($regex ?? $path) . '$#';
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return $this->invoke($route['handler'], $request, $params);
        }

        if ($allowed !== []) {
            throw new HttpException('Método no permitido.', 405);
        }

        throw new HttpException('Página no encontrada.', 404);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<string, string> $params
     */
    private function invoke(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $response = $controller->{$method}($request, $params);
        } else {
            $response = $handler($request, $params);
        }

        if (!$response instanceof Response) {
            throw new \RuntimeException('El manejador de la ruta no devolvió una respuesta.');
        }

        return $response;
    }
}
