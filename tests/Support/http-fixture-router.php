<?php

declare(strict_types=1);

// Router for `php -S` used by the extension HTTP client tests.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/ok':
        header('X-Test: yes');
        echo 'hello';
        break;
    case '/redirect':
        header('Location: /ok', true, 302);
        break;
    case '/redirect-away':
        header('Location: https://example.com/x', true, 302);
        break;
    case '/loop':
        header('Location: /loop', true, 302);
        break;
    case '/big':
        echo str_repeat('x', 2000);
        break;
    case '/agent':
        echo $_SERVER['HTTP_USER_AGENT'] ?? '';
        break;
    default:
        http_response_code(404);
        echo 'nope';
}
