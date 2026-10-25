<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class Router
{
    /**
     * @var array<int, array{method: string, path: string, regex: string, params: array<int, string>, handler: callable|array{0: class-string, 1: string}}>
     */
    private array $routes = [];

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function add(string $method, string $path, callable|array $handler): self
    {
        $path = self::normalizePath($path);
        [$regex, $params] = self::compile($path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function get(string $path, callable|array $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function post(string $path, callable|array $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function put(string $path, callable|array $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function patch(string $path, callable|array $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function delete(string $path, callable|array $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                if (isset($matches[$name])) {
                    $params[$name] = urldecode((string) $matches[$name]);
                }
            }

            $result = $this->invoke($route['handler'], $request, $params);

            if ($result instanceof Response) {
                return $result;
            }

            if (is_array($result)) {
                return Response::json($result);
            }

            return Response::json(['data' => $result]);
        }

        if ($allowed !== []) {
            $allowed = array_values(array_unique($allowed));
            throw new HttpException(
                'Método no permitido.',
                405,
                ['allowed' => $allowed],
                ['Allow' => implode(', ', $allowed)]
            );
        }

        throw new HttpException('Ruta no encontrada.', 404);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<string, string> $params
     */
    private function invoke(callable|array $handler, Request $request, array $params): mixed
    {
        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0]) && class_exists($handler[0])) {
            $controller = new $handler[0]();
            $method = $handler[1];

            return $controller->{$method}($request, $params);
        }

        if ($handler instanceof Closure || is_callable($handler)) {
            return $handler($request, $params);
        }

        throw new HttpException('Controlador no válido.', 500);
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private static function compile(string $path): array
    {
        if ($path === '/') {
            return ['#^/$#', []];
        }

        $segments = explode('/', trim($path, '/'));
        $pattern = [];
        $params = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $match)) {
                $name = $match[1];
                $params[] = $name;
                $pattern[] = '(?P<' . $name . '>[^/]+)';
                continue;
            }

            $pattern[] = preg_quote($segment, '#');
        }

        return ['#^/' . implode('/', $pattern) . '$#', $params];
    }
}
