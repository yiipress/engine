<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Packaging;

use PharData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiPress\Build\PharArchiveValidator;

use function sys_get_temp_dir;
use function unlink;
use function uniqid;

final class PharArchiveValidatorTest extends TestCase
{
    private string $pharPath;

    protected function setUp(): void
    {
        $this->pharPath = sys_get_temp_dir() . '/yiipress-validator-' . uniqid('', true) . '.tar';
    }

    protected function tearDown(): void
    {
        @unlink($this->pharPath);
    }

    #[Test]
    public function rejectsExcludedEntry(): void
    {
        $phar = new PharData($this->pharPath);
        $phar->addFromString('vendor/acme/package/tests/FeatureTest.php', '<?php');
        unset($phar);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Excluded file was added to the PHAR: vendor/acme/package/tests/FeatureTest.php',
        );

        PharArchiveValidator::validate(new PharData($this->pharPath), []);
    }

    #[Test]
    public function rejectsMissingRequiredEntry(): void
    {
        $phar = new PharData($this->pharPath);
        unset($phar);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required runtime file is missing from the PHAR: config/runtime.php');

        PharArchiveValidator::validate(new PharData($this->pharPath), ['config/runtime.php']);
    }

    #[Test]
    public function acceptsArchiveWithRuntimeEntries(): void
    {
        $phar = new PharData($this->pharPath);
        $phar->addFromString('config/runtime.php', '<?php return [];');
        $phar->addFromString('vendor/acme/package/src/Runtime.php', '<?php');
        unset($phar);

        $phar = new PharData($this->pharPath);
        PharArchiveValidator::validate($phar, ['config/runtime.php']);

        self::assertTrue(isset($phar['config/runtime.php']));
    }
}
