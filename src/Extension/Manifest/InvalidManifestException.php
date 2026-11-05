<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use RuntimeException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;

use function count;
use function implode;
use function sprintf;

/**
 * Thrown when `extension.yaml` has one or more errors. The message lists all of them.
 */
final class InvalidManifestException extends RuntimeException implements FriendlyExceptionInterface
{
    /**
     * @param list<ManifestError> $errors
     */
    public function __construct(private readonly array $errors)
    {
        $lines = [];
        foreach ($errors as $error) {
            $lines[] = '- ' . $error->message;
        }

        $count = count($errors);
        parent::__construct(sprintf(
            "extension.yaml has %d error%s:\n%s",
            $count,
            $count === 1 ? '' : 's',
            implode("\n", $lines),
        ));
    }

    public function getName(): string
    {
        return 'Invalid extension manifest';
    }

    public function getSolution(): string
    {
        return 'Fix the listed fields in extension.yaml of the extension package. '
            . 'The manifest reference is in the YiiPress documentation, section "Extension packages".';
    }

    /**
     * @return list<ManifestError>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
