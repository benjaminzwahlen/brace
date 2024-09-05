<?php

enum Method: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case CONNECT = 'CONNECT';
    case OPTIONS = 'OPTIONS';
    case TRACE = 'TRACE';
    case PATCH = 'PATCH';

    public static function fromName(string $name): Method
    {
        $method = self::tryFromName($name);

        if (is_null($method)) {
            $enumName = static::class;
            throw new ValueError("$name is not a valid name for enum \"$enumName\"");
        }

        return $method;
    }

    private static function tryFromName(?string $name): ?Method
    {
        // We aren't allowed to pass a null value to strtoupper(), so we have to handle this early.
        if (is_null($name)) {
            return null;
        }

        $name = strtoupper($name);

        if (defined("self::$name")) {
            /**
             * @var Method
             */
            $enumCase = constant("self::$name");
            return $enumCase;
        }

        return null;
    }
}

class Route
{
    public ?string $controllerName;
    public ?string $functionName;
    public ?Method $method;
    public ?string $path;
    public ?array $tokenArray = null;
    public ?string $requiredPermission = null;

    private function __construct() {}

    public static function parse(array $r, array $tokenArray_ = null): Route
    {
        $route = new Route();
        $route->method = Method::tryFrom($r['methods']);
        $route->controllerName = $r['controller'];
        $route->functionName = $r['function'];
        $route->path = $r['path'];
        //$route->requiredPermission = $r['permission'];
        $route->tokenArray = $tokenArray_;

        return $route;
    }
}

class Router
{

    private ?array $routes;

    public function __construct(string $filename = null)
    {
        if ($filename == null)
            $this->routes = array();
        else
            $this->routes = yaml_parse_file($filename);
    }


    public static function pathMatches(string $path, $route): array
    {
        $arr = [
            "matched" => true,
            "tokenArray" => null
        ];

        if ($path == $route)
        return $arr;

        $explodePath = array_values(array_filter(explode("/", $path)));
        $explodeRoute = array_values(array_filter(explode("/", $route)));

        if (count($explodePath) != count($explodeRoute)) {
            $arr["matched"] = false;
            return $arr;
        }

        $tokens = [];

        for ($i = 0; $i < count($explodePath); $i++) {

            if (str_starts_with($explodeRoute[$i], "{") && str_ends_with($explodeRoute[$i], "}")) {
                //This is a token, add it to the array and move on
                $tokens[substr($explodeRoute[$i], 1, strlen($explodeRoute[$i]) - 2)] = $explodePath[$i];
            } else {
                if ($explodeRoute[$i] != $explodePath[$i]) {
                    $arr["matched"] = false;
                    break;
                }
            }
        }

        if ($arr["matched"] && count($tokens) > 0)
            $arr["tokenArray"] = $tokens;


        return $arr;
    }

    public function match(string $path, string $methodString): ?Route
    {

        $mismatchedMethod = 0;
        foreach ($this->routes as $route) {
            $mismatchedMethod = 0;

            $checkMatch = Router::pathMatches($path, $route['path']);

            if ($checkMatch["matched"]) {
                //There's  match, check the allowed methods
                if ($route['methods'] == $methodString)
                    return Route::parse($route, $checkMatch["tokenArray"]);
                else {
                    //It matches on path but the method doesn't match
                    $mismatchedMethod += 1;
                }
            }
        }

        if ($mismatchedMethod > 0)
            throw new MethodNotAllowedException($path . " tried: " . $methodString);
        else
            throw new RouteNotFoundException($path);

    }

}
