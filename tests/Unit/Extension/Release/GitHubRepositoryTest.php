<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Extension\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiPress\Extension\Install\InstallException;
use YiiPress\Extension\Release\GitHubRepository;

final class GitHubRepositoryTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function testParsesRepositoryUrl(string $url): void
    {
        $repository = GitHubRepository::fromUrl($url);

        self::assertSame('yiipress', $repository->owner);
        self::assertSame('theme-wonder', $repository->name);
        self::assertSame('https://github.com/yiipress/theme-wonder', $repository->url());
        self::assertSame('yiipress/theme-wonder', (string) $repository);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validUrls(): iterable
    {
        yield 'plain' => ['https://github.com/yiipress/theme-wonder'];
        yield 'trailing slash' => ['https://github.com/yiipress/theme-wonder/'];
        yield 'git suffix' => ['https://github.com/yiipress/theme-wonder.git'];
        yield 'host case' => ['https://GitHub.com/yiipress/theme-wonder'];
    }

    #[DataProvider('invalidUrls')]
    public function testRejectsUrl(string $url, string $code): void
    {
        try {
            GitHubRepository::fromUrl($url);
            self::fail('No exception thrown.');
        } catch (InstallException $exception) {
            self::assertSame($code, $exception->errorCode);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'not a URL' => ['yiipress/theme-wonder', 'source_invalid'];
        yield 'http' => ['http://github.com/yiipress/theme-wonder', 'source_invalid'];
        yield 'deeper path' => ['https://github.com/yiipress/theme-wonder/tree/main', 'source_invalid'];
        yield 'owner only' => ['https://github.com/yiipress', 'source_invalid'];
        yield 'credentials' => ['https://user@github.com/yiipress/theme-wonder', 'source_invalid'];
        yield 'query' => ['https://github.com/yiipress/theme-wonder?tab=readme', 'source_invalid'];
        yield 'dot dot' => ['https://github.com/yiipress/..', 'source_invalid'];
        yield 'GitLab' => ['https://gitlab.com/yiipress/theme-wonder', 'source_unsupported'];
    }
}
