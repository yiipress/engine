<?php

declare(strict_types=1);

namespace App\Processor;

use App\Content\Model\Entry;

final readonly class TagLinkProcessor implements ContentProcessorInterface
{
    public function __construct(
        private string $rootPath = '/',
    ) {}

    public function process(string $content, Entry $entry): string
    {
        $parts = preg_split('/(<pre[^>]*>.*?<\/pre>|<code[^>]*>.*?<\/code>|<a[^>]*>.*?<\/a>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $content;
        }

        $result = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $result .= $this->convertHashtags($part);
            } else {
                $result .= $part;
            }
        }

        return $result;
    }

    private function convertHashtags(string $text): string
    {
        return preg_replace_callback(
            '/(?<!\w)#(\w+)(?!\w)/',
            function (array $matches): string {
                $tagDisplay = $matches[1];
                $tagUrl = mb_strtolower($tagDisplay);
                $url = $this->rootPath . 'tags/' . $tagUrl . '/';
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="tag-link">#' . htmlspecialchars($tagDisplay, ENT_QUOTES, 'UTF-8') . '</a>';
            },
            $text
        );
    }
}
