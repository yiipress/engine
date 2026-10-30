<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\EngineCompatibility;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Manifest\Author;
use YiiPress\Extension\Manifest\ExtensionManifest;
use YiiPress\Extension\Manifest\ManifestError;
use YiiPress\Extension\Manifest\Platforms;
use YiiPress\Extension\Platform;

use function PHPUnit\Framework\assertSame;

final class EngineCompatibilityTest extends TestCase
{
    private const string COMMIT = '80964110a7e8b1c2d3e4f5a6b7c8d9e0f1a2b3c4';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function compatibleDataProvider(): iterable
    {
        yield 'caret' => ['^0.1.13', '0.1.13'];
        yield 'caret patch' => ['^0.1.13', '0.1.20'];
        yield 'range' => ['>=0.1.13 <0.3', '0.2.5'];
        yield 'or' => ['^0.1 || ^1.0', '1.2.0'];
        yield 'tilde' => ['~0.1.13', '0.1.14'];
    }

    #[DataProvider('compatibleDataProvider')]
    public function testAcceptsMatchingRelease(string $constraint, string $version): void
    {
        $errors = new EngineCompatibility()->check(self::manifest($constraint), EngineVersion::fromBuild($version), self::linux());

        assertSame([], $errors);
    }

    public function testRejectsReleaseOutsideConstraint(): void
    {
        $errors = new EngineCompatibility()->check(self::manifest('^0.2'), EngineVersion::fromBuild('0.1.13'), self::linux());

        assertSame(['engine_incompatible'], self::codes($errors));
        assertSame('yiipress', $errors[0]->field);
        assertSame('Wonder 1.0.0 supports YiiPress ^0.2; this is YiiPress 0.1.13.', $errors[0]->message);
    }

    public function testChecksNightlyAsItsBaseRelease(): void
    {
        $errors = new EngineCompatibility()->check(
            self::manifest('^0.2'),
            EngineVersion::fromBuild(null, self::COMMIT, '0.1.13'),
            self::linux(),
        );

        assertSame(
            'Wonder 1.0.0 supports YiiPress ^0.2; this is YiiPress nightly built on 0.1.13 (commit 80964110a7e8).',
            $errors[0]->message,
        );
    }

    public function testAcceptsNightlyBuiltOnMatchingRelease(): void
    {
        $errors = new EngineCompatibility()->check(
            self::manifest('^0.1.13'),
            EngineVersion::fromBuild(null, self::COMMIT, '0.1.13'),
            self::linux(),
        );

        assertSame([], $errors);
    }

    public function testRejectsUnknownEngineVersion(): void
    {
        $errors = new EngineCompatibility()->check(self::manifest('^0.1.13'), EngineVersion::fromBuild('dev-master'), self::linux());

        assertSame(['engine_version_unknown'], self::codes($errors));
        assertSame(
            'Wonder 1.0.0 supports YiiPress ^0.1.13; this YiiPress is an unknown version, so compatibility cannot be checked.',
            $errors[0]->message,
        );
    }

    public function testAcceptsListedPlatform(): void
    {
        $manifest = self::manifest('^0.1.13', new Platforms(['linux', 'macos'], ['amd64']));

        assertSame([], new EngineCompatibility()->check($manifest, EngineVersion::fromBuild('0.1.13'), self::linux()));
    }

    public function testRejectsUnlistedOperatingSystem(): void
    {
        $manifest = self::manifest('^0.1.13', new Platforms(['macos', 'windows']));

        $errors = new EngineCompatibility()->check($manifest, EngineVersion::fromBuild('0.1.13'), self::linux());

        assertSame(['platform_unsupported'], self::codes($errors));
        assertSame('platforms.os', $errors[0]->field);
        assertSame('Wonder 1.0.0 supports macos, windows; this system is linux.', $errors[0]->message);
    }

    public function testRejectsUnknownArchitectureWhenListIsDeclared(): void
    {
        $manifest = self::manifest('^0.1.13', new Platforms([], ['arm64']));

        $errors = new EngineCompatibility()->check($manifest, EngineVersion::fromBuild('0.1.13'), new Platform('linux', null));

        assertSame('Wonder 1.0.0 supports arm64; this system is not one of them.', $errors[0]->message);
    }

    public function testIgnoresUnknownPlatformWhenNothingIsDeclared(): void
    {
        $errors = new EngineCompatibility()->check(self::manifest('^0.1.13'), EngineVersion::fromBuild('0.1.13'), new Platform(null, null));

        assertSame([], $errors);
    }

    public function testReportsVersionAndPlatformTogether(): void
    {
        $manifest = self::manifest('^0.2', new Platforms(['windows']));

        $errors = new EngineCompatibility()->check($manifest, EngineVersion::fromBuild('0.1.13'), self::linux());

        assertSame(['engine_incompatible', 'platform_unsupported'], self::codes($errors));
    }

    private static function manifest(string $constraint, Platforms $platforms = new Platforms()): ExtensionManifest
    {
        $id = ExtensionId::tryFromString('yiipress/wonder');
        self::assertNotNull($id);

        return new ExtensionManifest(
            manifest: 1,
            id: $id,
            type: ExtensionType::Theme,
            name: 'Wonder',
            description: 'A theme.',
            version: '1.0.0',
            yiipress: $constraint,
            license: 'MIT',
            authors: [new Author('Jane Doe')],
            platforms: $platforms,
        );
    }

    private static function linux(): Platform
    {
        return new Platform('linux', 'amd64');
    }

    /**
     * @param list<ManifestError> $errors
     * @return list<string>
     */
    private static function codes(array $errors): array
    {
        return array_map(static fn(ManifestError $e): string => $e->code, $errors);
    }
}
