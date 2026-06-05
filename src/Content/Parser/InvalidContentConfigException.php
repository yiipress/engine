<?php

declare(strict_types=1);

namespace YiiPress\Content\Parser;

use RuntimeException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;

final class InvalidContentConfigException extends RuntimeException implements FriendlyExceptionInterface
{
    public function __construct(
        string $message,
        private readonly string $filePath,
        private readonly ?string $solution = null,
    ) {
        parent::__construct($message);
    }

    public function getName(): string
    {
        return 'Invalid content configuration';
    }

    public function getSolution(): ?string
    {
        return $this->solution;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }
}
