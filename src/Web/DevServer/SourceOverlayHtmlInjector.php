<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

final class SourceOverlayHtmlInjector
{
    public static function inject(string $body): string
    {
        return LiveReloadHtmlInjector::injectBeforeBodyEnd($body, ScriptAsset::tag('source-overlay.js'));
    }
}
