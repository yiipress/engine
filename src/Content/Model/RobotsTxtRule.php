<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class RobotsTxtRule
{
    /**
     * @param list<string> $allow
     * @param list<string> $disallow
     */
    public function __construct(
        public string $userAgent = '*',
        public array $allow = [],
        public array $disallow = [],
        public ?int $crawlDelay = null,
    ) {}
}
