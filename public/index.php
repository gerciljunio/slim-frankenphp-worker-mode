<?php
declare(strict_types=1);

use App\Exceptions\Handler;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// registra o middleware de erros e define o handler global
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(new Handler($app->getResponseFactory()));

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
