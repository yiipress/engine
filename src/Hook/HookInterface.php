<?php

declare(strict_types=1);

namespace YiiPress\Hook;

interface HookInterface
{
    public function handle(HookEventInterface $event): void;
}
