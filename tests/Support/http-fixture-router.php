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
    case '/drip':
        // Never idle for long, but slow overall: 20 bytes, one every 0.2 s.
        header('Content-Length: 20');
        for ($i = 0; $i < 20; $i++) {
            echo 'x';
            flush();
            usleep(200_000);
        }
        break;
    case '/agent':
        echo $_SERVER['HTTP_USER_AGENT'] ?? '';
        break;
    default:
        http_response_code(404);
        echo 'nope';
}
