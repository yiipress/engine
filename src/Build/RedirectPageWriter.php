<?php

declare(strict_types=1);

namespace App\Build;

use App\Content\Model\Entry;
use App\I18n\UiText;
use RuntimeException;

use function dirname;
use function htmlspecialchars;
use function json_encode;

final class RedirectPageWriter
{
    public function write(Entry $entry, string $filePath, string $language = 'en', ?UiText $ui = null, bool $noWrite = false): void
    {
        $htmlLanguage = $language !== '' ? $language : 'en';
        $target = $entry->redirectTo;
        $targetEscaped = htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $targetJson = json_encode($target, JSON_THROW_ON_ERROR);
        $ui ??= UiText::for($htmlLanguage);
        $redirectLink = '<a href="' . $targetEscaped . '">' . $ui->get('redirect_click_here') . '</a>';
        $redirectMessage = $ui->get('redirect_moved', ['link' => $redirectLink]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="$htmlLanguage">
            <head>
            <meta charset="UTF-8">
            <title>{$ui->get('redirecting')}</title>
            <link rel="canonical" href="$targetEscaped">
            <meta http-equiv="refresh" content="0; url=$targetEscaped">
            <script>window.location.replace($targetJson);</script>
            </head>
            <body>
            <p>$redirectMessage</p>
            </body>
            </html>
            HTML;

        if ($noWrite) {
            return;
        }

        $dirPath = dirname($filePath);
        if (!is_dir($dirPath) && !mkdir($dirPath, 0o755, true) && !is_dir($dirPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dirPath));
        }

        file_put_contents($filePath, $html);
    }
}
