<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Entry;
use RuntimeException;

use function dirname;
use function htmlspecialchars;
use function json_encode;

final class RedirectPageWriter
{
    public function write(Entry $entry, string $filePath): void
    {
        $target = $entry->redirectTo;
        $targetEscaped = htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $targetJson = json_encode($target, JSON_THROW_ON_ERROR);

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="UTF-8">
            <title>Redirecting...</title>
            <link rel="canonical" href="$targetEscaped">
            <meta http-equiv="refresh" content="0; url=$targetEscaped">
            <script>window.location.replace($targetJson);</script>
            </head>
            <body>
            <p>This page has moved. <a href="$targetEscaped">Click here</a> if you are not redirected automatically.</p>
            </body>
            </html>
            HTML;

        $dirPath = dirname($filePath);
        if (!is_dir($dirPath) && !mkdir($dirPath, 0o755, true) && !is_dir($dirPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dirPath));
        }

        file_put_contents($filePath, $html);
    }
}
