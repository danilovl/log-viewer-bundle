<?php declare(strict_types=1);

use Symfony\Component\ErrorHandler\ErrorHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

if (class_exists(ErrorHandler::class)) {
    ErrorHandler::register(null, false);
}
