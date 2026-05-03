<?php

declare(strict_types=1);

namespace YiiPress\Content\Model;

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
