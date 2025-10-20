<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// 1) Middlewares seguros no worker
$app->addBodyParsingMiddleware();

// 2) Error handler global SEMPRE JSON
$responseFactory = $app->getResponseFactory();
$customErrorHandler = function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($responseFactory): Response {
    $status = 500;
    $message = 'Internal Server Error';

    if ($exception instanceof \Slim\Exception\HttpException) {
        $status  = $exception->getCode() ?: 500;
        $message = $exception->getMessage() ?: (string) $status;
    }

    $payload = [
        'error'  => true,
        'status' => $status,
        'message'=> $message,
        'path'   => $request->getUri()->getPath(),
        'method' => $request->getMethod(),
    ];

    $response = $responseFactory->createResponse($status);

    if ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException) {
        $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
    }

    return toResponse($response, $payload, $status);
};

// registre o middleware de erros e só depois defina o handler
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

// Carrega as rotas a partir do arquivo dedicado
$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

// 4) Runner (clássico/worker)
$runner = static function (): void {
    // O AppFactory::create() e o $app vêm do escopo superior; não reter estado aqui
    // Para manter $app visível, use a closure abaixo com "use ($app)"
};

$runner = static function () use ($app): void {
    $app->run();
};

if (function_exists('frankenphp_handle_request')) {
    frankenphp_handle_request($runner);
} else {
    $runner();
}
