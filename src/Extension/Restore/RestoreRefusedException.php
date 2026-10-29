<?php

declare(strict_types=1);

namespace YiiPress\Extension\Restore;

use RuntimeException;
use YiiPress\Extension\Install\InstallException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;
use Yiisoft\Yii\Console\ExitCode;

use function array_map;
use function count;
use function implode;
use function sprintf;

/**
 * Thrown when restoring would need to overwrite, delete or substitute something. Nothing was changed.
 */
final class RestoreRefusedException extends RuntimeException implements FriendlyExceptionInterface
{
    /**
     * @param non-empty-list<InstallException> $problems Every problem found, each with its code and solution.
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(
            sprintf(
                'Nothing was restored because of %s: %s',
                count($problems) === 1 ? 'a problem' : count($problems) . ' problems',
                implode(' ', array_map(
                    static fn(InstallException $problem): string => $problem->getMessage(),
                    $problems,
                )),
            ),
            ExitCode::DATAERR,
        );
    }

    public function getName(): string
    {
        return 'Extensions were not restored';
    }

    public function getSolution(): string
    {
        return 'Fix each problem, then run restore:extensions again; it never overwrites or deletes your files.';
    }
}
