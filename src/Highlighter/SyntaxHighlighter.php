<?php

declare(strict_types=1);

namespace App\Highlighter;

use FFI;
use RuntimeException;

final class SyntaxHighlighter
{
    private const string HEADER = <<<'C'
        char *yiipress_highlight(const char *html);
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
        /** @var FFI\CData|null $resultPtr */
        $resultPtr = $this->ffi->yiipress_highlight($html);

        if ($resultPtr === null) {
            throw new RuntimeException('Syntax highlighting failed');
        }

        try {
            return FFI::string($resultPtr);
        } finally {
            $this->ffi->yiipress_highlight_free($resultPtr);
        }
    }
}
