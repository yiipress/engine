<?php

declare(strict_types=1);

namespace App\Content\Model;

final readonly class RobotsTxtConfig
{
    /**
     * @param list<RobotsTxtRule> $rules
     */
    public function __construct(
        public bool $generate = true,
        public array $rules = [],
    ) {}
}
