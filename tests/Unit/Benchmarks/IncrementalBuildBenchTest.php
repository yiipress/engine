<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Benchmarks;

use PhpBench\Benchmark\Metadata\Driver\AttributeDriver;
use PhpBench\Reflection\ReflectionClass as BenchReflectionClass;
use PhpBench\Reflection\ReflectionHierarchy;
use PhpBench\Reflection\ReflectionMethod as BenchReflectionMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;
use YiiPress\Benchmarks\LargeContentBuildBench;
use YiiPress\Benchmarks\SmallSiteBuildBench;

require_once dirname(__DIR__, 3) . '/benchmarks/SmallSiteBuildBench.php';
require_once dirname(__DIR__, 3) . '/benchmarks/LargeContentBuildBench.php';

final class IncrementalBuildBenchTest extends TestCase
{
    /** @return iterable<string, array{class-string}> */
    public static function benchmarks(): iterable
    {
        yield 'small entries' => [SmallSiteBuildBench::class];
        yield 'realistic entries' => [LargeContentBuildBench::class];
    }

    /** @param class-string $class */
    #[DataProvider('benchmarks')]
    public function testPendingEditIsConsumedByExactlyOneTimedBuild(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $instantiate = static fn(ReflectionAttribute $attribute): object => $attribute->newInstance();
        $benchmark = new BenchReflectionClass($reflection->getFileName(), $class);
        $benchmark->attributes = array_map($instantiate, $reflection->getAttributes());
        $method = new BenchReflectionMethod();
        $method->name = 'benchIncrementalSingleChangedEntrySequential';
        $method->attributes = array_map($instantiate, $reflection->getMethod($method->name)->getAttributes());
        $benchmark->methods[$method->name] = $method;

        // Use PHPBench's effective metadata, including class attributes and default warmup.
        $metadata = new AttributeDriver()->getMetadataForHierarchy(new ReflectionHierarchy([$benchmark]));
        $subject = $metadata->getSubjects()[$method->name];

        self::assertSame([0], $subject->getWarmup(), 'A warmup would consume the edit before timing.');
        self::assertSame([1], $subject->getRevs(), 'Later revisions would measure unchanged builds.');
        self::assertSame(['setUp', 'prepareIncrementalSingleChangedEntry'], $subject->getBeforeMethods());
    }
}
