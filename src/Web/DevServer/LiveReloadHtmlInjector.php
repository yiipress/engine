<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

final class LiveReloadHtmlInjector
{
    public static function inject(string $body): string
    {
        return self::injectBeforeBodyEnd($body, ScriptAsset::tag('live-reload.js'));
    }

    public static function injectBeforeBodyEnd(string $body, string $script): string
    {
        $position = strripos($body, '</body>');

        if ($position === false) {
            return $body;
        }

        return substr($body, 0, $position) . $script . "\n" . substr($body, $position);
    }
}
