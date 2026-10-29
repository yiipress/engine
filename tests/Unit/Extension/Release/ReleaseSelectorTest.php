<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\EngineVersion;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Platform;
use YiiPress\Extension\Release\GitHubReleaseSource;
use YiiPress\Extension\Release\GitHubRepository;
use YiiPress\Extension\Release\ReleaseSelector;
use YiiPress\Extension\Release\SelectedRelease;
use YiiPress\Tests\Support\FakeHttpClient;

use function sprintf;

final class ReleaseSelectorTest extends TestCase
{
    private const string REPO = 'yiipress/theme-wonder';

    public function testPicksHighestCompatibleRelease(): void
    {
        $http = new FakeHttpClient()->gitHub(self::REPO, [
            'v1.0.0' => ['manifest' => self::manifest('1.0.0', '^0.1')],
            'v1.2.0' => ['manifest' => self::manifest('1.2.0', '^0.2')],
            'v1.1.0' => ['manifest' => self::manifest('1.1.0', '^0.1')],
        ]);

        $selected = $this->select($http);

        self::assertSame('v1.1.0', $selected->release->tag);
        self::assertSame('1.1.0', $selected->manifest->version);
        self::assertSame('highest stable release compatible with YiiPress 0.1.13', $selected->reason);
    }

    public function testSkipsDraftsPrereleasesAndNonVersionTags(): void
    {
        $http = new FakeHttpClient()->gitHub(self::REPO, [
            'v2.0.0' => ['manifest' => self::manifest('2.0.0'), 'draft' => true],
            'v1.9.0' => ['manifest' => self::manifest('1.9.0'), 'prerelease' => true],
            '1.8.0-beta' => ['manifest' => self::manifest('1.8.0-beta')],
            'latest' => ['manifest' => self::manifest('1.0.0')],
            'vv1.7.0' => ['manifest' => self::manifest('1.7.0')],
            '1.0.0' => ['manifest' => self::manifest('1.0.0')],
        ]);

        self::assertSame('1.0.0', $this->select($http)->release->tag);
    }

    public function testFollowsPaginationOnApiHost(): void
    {
        $page2 = 'https://api.github.com/repositories/1/releases?per_page=100&page=2';
        $http = new FakeHttpClient()
            ->route(
                'https://api.github.com/repos/yiipress/theme-wonder/releases?per_page=100',
                '[{"tag_name":"v1.0.0","draft":false,"prerelease":false}]',
                headers: ['link' => [sprintf('<%s>; rel="next", <%s>; rel="last"', $page2, $page2)]],
            )
            ->route($page2, '[{"tag_name":"v1.1.0","draft":false,"prerelease":false}]')
            ->route('https://raw.githubusercontent.com/yiipress/theme-wonder/v1.1.0/extension.yaml', self::manifest('1.1.0'));

        self::assertSame('v1.1.0', $this->select($http)->release->tag);
    }

    public function testCandidateWithBrokenManifestIsSkipped(): void
    {
        $http = new FakeHttpClient()->gitHub(self::REPO, [
            'v1.0.0' => ['manifest' => self::manifest('1.0.0')],
            'v1.1.0' => ['manifest' => 'id: [broken'],
            'v1.2.0' => ['manifest' => self::manifest('1.1.0')],
            'v1.3.0' => [],
        ]);

        self::assertSame('v1.0.0', $this->select($http)->release->tag);
    }

    public function testInstallsRequestedVersion(): void
    {
        $http = new FakeHttpClient()->gitHub(self::REPO, [
            'v1.0.0' => ['manifest' => self::manifest('1.0.0')],
            'v1.1.0' => ['manifest' => self::manifest('1.1.0')],
        ]);

        $selected = $this->select($http, '1.0.0');

        self::assertSame('v1.0.0', $selected->release->tag);
        self::assertSame('the requested version', $selected->reason);
    }

    #[DataProvider('failures')]
    public function testFails(FakeHttpClient $http, ?string $version, string $code, ?EngineVersion $engine = null): void
    {
        try {
            $this->select($http, $version, $engine);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{FakeHttpClient, string|null, string, 3?: EngineVersion}>
     */
    public static function failures(): iterable
    {
        $two = static fn(): FakeHttpClient => new FakeHttpClient()->gitHub(self::REPO, [
            'v1.0.0' => ['manifest' => self::manifest('1.0.0')],
            'v1.1.0' => ['manifest' => self::manifest('1.1.0', '^0.3')],
        ]);

        yield 'repository missing' => [new FakeHttpClient(), null, 'repository_not_found'];
        yield 'no releases' => [new FakeHttpClient()->gitHub(self::REPO, []), null, 'release_none'];
        yield 'engine unknown' => [$two(), null, 'engine_version_unknown', EngineVersion::fromBuild(null)];
        yield 'requested missing' => [$two(), '2.0.0', 'release_not_found'];
        yield 'requested not a version' => [$two(), 'v1.0.0', 'release_not_found'];
        yield 'requested pre-release' => [$two(), '1.0.0-beta', 'release_prerelease'];
        yield 'requested incompatible' => [$two(), '1.1.0', 'release_incompatible'];
        yield 'none compatible' => [
            new FakeHttpClient()->gitHub(self::REPO, ['v1.1.0' => ['manifest' => self::manifest('1.1.0', '^0.3')]]),
            null,
            'no_compatible_release',
        ];
        yield 'ambiguous' => [
            new FakeHttpClient()->gitHub(self::REPO, [
                'v1.0.0' => ['manifest' => self::manifest('1.0.0')],
                '1.0.0' => ['manifest' => self::manifest('1.0.0')],
            ]),
            null,
            'release_ambiguous',
        ];
        yield 'requested is not a theme' => [
            new FakeHttpClient()->gitHub(self::REPO, ['v1.0.0' => ['manifest' => self::processorManifest()]]),
            '1.0.0',
            'manifest_invalid',
        ];
        yield 'requested version differs from tag' => [
            new FakeHttpClient()->gitHub(self::REPO, ['v1.0.0' => ['manifest' => self::manifest('1.0.1')]]),
            '1.0.0',
            'manifest_mismatch',
        ];
        yield 'rate limited' => [
            new FakeHttpClient()->route(
                'https://api.github.com/repos/yiipress/theme-wonder/releases?per_page=100',
                '{}',
                403,
                ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-reset' => ['1893456000']],
            ),
            null,
            'rate_limited',
        ];
        yield 'server error' => [
            new FakeHttpClient()->route('https://api.github.com/repos/yiipress/theme-wonder/releases?per_page=100', '', 502),
            null,
            'network_failed',
        ];
        yield 'not a list' => [
            new FakeHttpClient()->route('https://api.github.com/repos/yiipress/theme-wonder/releases?per_page=100', '{"a":1}'),
            null,
            'network_failed',
        ];
    }

    public function testNoCompatibleReleaseListsCandidates(): void
    {
        $http = new FakeHttpClient()->gitHub(self::REPO, [
            'v1.1.0' => ['manifest' => self::manifest('1.1.0', '^0.3')],
            'v1.0.0' => [],
        ]);

        $this->expectExceptionMessageMatches('/v1\.1\.0: .*\^0\.3.*\n- v1\.0\.0: .*no extension\.yaml/s');

        $this->select($http);
    }

    private function select(FakeHttpClient $http, ?string $version = null, ?EngineVersion $engine = null): SelectedRelease
    {
        return new ReleaseSelector(new GitHubReleaseSource($http))->select(
            GitHubRepository::fromUrl('https://github.com/' . self::REPO),
            ExtensionType::Theme,
            $version,
            $engine ?? EngineVersion::fromBuild('0.1.13'),
            new Platform('linux', 'amd64'),
        );
    }

    public static function manifest(string $version, string $yiipress = '^0.1'): string
    {
        return <<<YAML
            manifest: 1
            id: yiipress/wonder
            type: theme
            name: Wonder
            description: A clean blog theme.
            version: $version
            yiipress: "$yiipress"
            license: BSD-3-Clause
            authors: [{name: Jane Doe}]
            YAML;
    }

    private static function processorManifest(): string
    {
        return <<<'YAML'
            manifest: 1
            id: yiipress/emoji
            type: processor
            name: Emoji
            description: Emoji.
            version: 1.0.0
            yiipress: ^0.1
            license: MIT
            authors: [{name: Jane Doe}]
            processor:
              entry_points:
                - path: src/emoji.php
                  stages: [before_markdown]
            YAML;
    }
}
