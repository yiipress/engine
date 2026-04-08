<?php

declare(strict_types=1);

namespace App\Highlighter;

use FFI;
use RuntimeException;

use function str_contains;
use function strlen;

final class SyntaxHighlighter
{
    private const string HEADER = <<<'C'
        char *yiipress_highlight(const char *html, size_t html_len, size_t *result_len, char **error);
        void yiipress_highlight_free(char *ptr);
        C;

    private const string LIBRARY_NAME = 'libyiipress_highlighter.so';

    private FFI $ffi;

    public function __construct()
    {
        $this->ffi = FFI::cdef(self::HEADER, self::LIBRARY_NAME);
    }

    public function highlight(string $html): string
    {
        if (!str_contains($html, '<pre><code class="language-')) {
            return $html;
        }

        // Create FFI CData for error output parameter
        $error = $this->ffi->new('char*');
        $resultLength = $this->ffi->new('size_t');

        /** @var FFI\CData|null $resultPtr */
        $resultPtr = $this->ffi->yiipress_highlight($html, strlen($html), FFI::addr($resultLength), FFI::addr($error));

        if ($resultPtr === null) {
            // null result + null error = no code blocks, input unchanged
            if ($error === null || FFI::isNull($error)) {
                return $html;
            }

            // null result + error = actual failure
            $errorStr = FFI::string($error);
            $this->ffi->yiipress_highlight_free($error);

            throw new RuntimeException($errorStr !== '' ? $errorStr : 'Syntax highlighting failed.');
        }

        try {
            return FFI::string($resultPtr, (int) $resultLength->cdata);
        } finally {
            $this->ffi->yiipress_highlight_free($resultPtr);
        }
    }
}
