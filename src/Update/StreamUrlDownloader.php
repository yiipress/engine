<?php

declare(strict_types=1);

namespace YiiPress\Update;

use RuntimeException;

use function fclose;
use function fopen;
use function stream_copy_to_stream;
use function stream_context_create;
use function unlink;

final readonly class StreamUrlDownloader implements UrlDownloaderInterface
{
    public function download(string $url, string $destination): void
    {
        $context = stream_context_create(['http' => ['header' => "User-Agent: YiiPress self-update\r\n", 'timeout' => 30]]);
        $source = @fopen($url, 'rb', false, $context);
        if ($source === false) {
            throw new RuntimeException("Could not download $url.");
        }

        $target = @fopen($destination, 'wb');
        if ($target === false) {
            fclose($source);
            throw new RuntimeException("Could not write temporary update file $destination.");
        }

        $copied = false;
        try {
            $copied = stream_copy_to_stream($source, $target) !== false;
        } finally {
            fclose($source);
            fclose($target);
        }

        if (!$copied) {
            @unlink($destination);
            throw new RuntimeException("Could not download $url.");
        }
    }
}
