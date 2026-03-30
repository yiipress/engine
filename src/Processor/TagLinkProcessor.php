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
        // Protect pre/code/a blocks (their content shouldn't have hashtags converted)
        // Then split by remaining HTML tags to exclude hashtags in attributes
        $parts = preg_split('/(<pre[^>]*>.*?<\/pre>|<code[^>]*>.*?<\/code>|<a[^>]*>.*?<\/a>|<[^>]+>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $content;
        }

        $result = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                // Outside any HTML tag - convert hashtags
                $result .= $this->convertHashtags($part);
            } elseif (preg_match('/^<(pre|code|a)[^>]*>/i', $part)) {
                // Protected block (pre/code/a with content) - preserve unchanged
                $result .= $part;
            } elseif (preg_match('/^<\/(pre|code|a)>/i', $part)) {
                // Closing tags of protected blocks - already captured in the block
                $result .= $part;
            } else {
                // Other HTML tags (including attributes) - preserve unchanged
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
