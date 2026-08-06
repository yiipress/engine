<?php

declare(strict_types=1);

namespace YiiPress\Update;

interface UrlDownloaderInterface
{
    public function download(string $url, string $destination): void;
}
