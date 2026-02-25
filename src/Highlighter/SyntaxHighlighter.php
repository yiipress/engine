<?php

declare(strict_types=1);

namespace App\Highlighter;

use FFI;
use RuntimeException;

final class SyntaxHighlighter
{
    private const string HEADER = <<<'C'
        char *yiipress_highlight(const char *html, char **error);
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
        // Create FFI CData for error output parameter
        $error = $this->ffi->new('char*');

        /** @var FFI\CData|null $resultPtr */
        $resultPtr = $this->ffi->yiipress_highlight($html, FFI::addr($error));

        if ($resultPtr === null) {
            $errorMessage = 'Syntax highlighting failed.';

            // Get detailed error message if available
            if ($error !== null) {
                $errorStr = FFI::string($error);
                if ($errorStr !== '') {
                    $errorMessage = $errorStr;
                }
                $this->ffi->yiipress_highlight_free($error);
            }

            throw new RuntimeException($errorMessage);
        }

        try {
            return FFI::string($resultPtr);
        } finally {
            $this->ffi->yiipress_highlight_free($resultPtr);

            // Clean up error if it was set
            if ($error !== null) {
                $this->ffi->yiipress_highlight_free($error);
            }
        }
    }
}
