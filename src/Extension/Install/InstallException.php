<?php

declare(strict_types=1);

namespace YiiPress\Extension\Install;

use RuntimeException;
use Throwable;
use YiiPress\Extension\Manifest\InvalidManifestException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;
use Yiisoft\Yii\Console\ExitCode;

/**
 * Thrown when an extension cannot be installed. The code of the problem is in {@see $errorCode},
 * the console exit code in {@see getCode()}.
 */
final class InstallException extends RuntimeException implements FriendlyExceptionInterface
{
    /**
     * @param string $errorCode Stable machine-readable code, for example `archive_link`.
     * @param string $message Full sentence naming the value.
     * @param int $exitCode Console exit code.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $exitCode = ExitCode::DATAERR,
        private readonly ?string $solution = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $exitCode, $previous);
    }

    public static function fromInvalidManifest(InvalidManifestException $exception): self
    {
        return new self('manifest_invalid', $exception->getMessage(), solution: $exception->getSolution(), previous: $exception);
    }

    public function getName(): string
    {
        return 'Extension installation failed';
    }

    public function getSolution(): ?string
    {
        return $this->solution;
    }
}
