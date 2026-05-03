<?php

declare(strict_types=1);

use YiiPress\Environment;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Environment::prepare(str_starts_with(__FILE__, 'phar://') ? Environment::PROD : null);
