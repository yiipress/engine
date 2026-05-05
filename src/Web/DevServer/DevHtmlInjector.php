<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

final class DevHtmlInjector
{
    public static function inject(string $body): string
    {
        return SourceOverlayHtmlInjector::inject(LiveReloadHtmlInjector::inject($body));
    }
}
