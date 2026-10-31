<?php
namespace App\Core;

class Router {
    protected array $routes = [];
    protected array $groupMiddleware = [];
    protected string $groupPrefix = '';

    public function group(array $attributes, callable $callback): void {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->groupPrefix = rtrim($previousPrefix, '/') . '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        }

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function add(string $method, string $uri, $action, array $middleware = []): void {
        $fullUri = rtrim($this->groupPrefix, '/') . '/' . trim($uri, '/');
        $fullUri = '/' . trim($fullUri, '/');
        if ($fullUri === '//') $fullUri = '/';

        $allMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $fullUri,
            'action' => $action,
            'middleware' => $allMiddleware,
        ];
    }

    public function get(string $uri, $action, array $middleware = []): void {
        $this->add('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, $action, array $middleware = []): void {
        $this->add('POST', $uri, $action, $middleware);
    }

    public function dispatch(Request $request): void {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (!is_numeric($k)) {
                        $params[$k] = $v;
                    }
                }
                $request->setParams($params);

                // Run middlewares
                foreach ($route['middleware'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $response = $middleware->handle($request);
                    if ($response !== null) {
                        return;
                    }
                }

                // Execute action
                $action = $route['action'];
                if (is_callable($action)) {
                    call_user_func($action, $request);
                    return;
                }

                if (is_string($action) && str_contains($action, '@')) {
                    [$controllerClass, $methodName] = explode('@', $action);
                    $fullClass = 'App\\Controllers\\' . $controllerClass;
                    if (!class_exists($fullClass)) {
                        Response::error("Controller {$fullClass} not found", 500);
                        return;
                    }
                    $controller = new $fullClass();
                    if (!method_exists($controller, $methodName)) {
                        Response::error("Method {$methodName} on controller {$fullClass} not found", 500);
                        return;
                    }
                    $controller->$methodName($request);
                    return;
                }
            }
        }

        Response::error("Page Not Found", 404);
    }
}
