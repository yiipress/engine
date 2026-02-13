<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\CrossReferenceResolver;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

final class CrossReferenceResolverTest extends TestCase
{
    public function testResolvesRelativeLinkInSameDirectory(): void
    {
        $resolver = new CrossReferenceResolver([
            'blog/2024-03-15-hello-world.md' => '/blog/hello-world/',
        ]);

        $result = $resolver->withCurrentDir('blog')
            ->resolve('Check [this post](./2024-03-15-hello-world.md).');

        assertSame('Check [this post](/blog/hello-world/).', $result);
    }

    public function testResolvesParentDirectoryLink(): void
    {
        $resolver = new CrossReferenceResolver([
            'contact.md' => '/contact/',
        ]);

        $result = $resolver->withCurrentDir('blog')
            ->resolve('See [contact](../contact.md).');

        assertSame('See [contact](/contact/).', $result);
    }

    public function testLeavesUnknownFileUnchanged(): void
    {
        $resolver = new CrossReferenceResolver([]);

        $result = $resolver->withCurrentDir('blog')
            ->resolve('See [missing](./missing.md).');

        assertSame('See [missing](./missing.md).', $result);
    }

    public function testResolvesMultipleLinks(): void
    {
        $resolver = new CrossReferenceResolver([
            'blog/first.md' => '/blog/first/',
            'blog/second.md' => '/blog/second/',
        ]);

        $result = $resolver->withCurrentDir('blog')
            ->resolve('Read [first](./first.md) and [second](./second.md).');

        assertSame('Read [first](/blog/first/) and [second](/blog/second/).', $result);
    }

    public function testNoMdLinksReturnsUnchanged(): void
    {
        $resolver = new CrossReferenceResolver([
            'blog/test.md' => '/blog/test/',
        ]);

        $markdown = 'No markdown file links [here](https://example.com).';

        assertSame($markdown, $resolver->resolve($markdown));
    }

    public function testResolvesAbsoluteContentPath(): void
    {
        $resolver = new CrossReferenceResolver([
            'blog/hello.md' => '/blog/hello/',
        ]);

        $result = $resolver->resolve('See [hello](blog/hello.md).');

        assertSame('See [hello](/blog/hello/).', $result);
    }

    public function testResolvesFromRootLevelFile(): void
    {
        $resolver = new CrossReferenceResolver([
            'blog/2024-03-15-test-post.md' => '/blog/test-post/',
        ]);

        $result = $resolver->withCurrentDir('')
            ->resolve('Read [the post](./blog/2024-03-15-test-post.md).');

        assertSame('Read [the post](/blog/test-post/).', $result);
    }

    public function testResolvesLinkWithAnchorFragment(): void
    {
        $resolver = new CrossReferenceResolver([
            'content.md' => '/content/',
        ]);

        $result = $resolver->resolve('See [Content](content.md#authors).');

        assertSame('See [Content](/content/#authors).', $result);
    }

    public function testResolvesRelativeLinkWithAnchorFragment(): void
    {
        $resolver = new CrossReferenceResolver([
            'config.md' => '/config/',
        ]);

        $result = $resolver->withCurrentDir('')
            ->resolve('See [Configuration](./config.md#markdown-extensions).');

        assertSame('See [Configuration](/config/#markdown-extensions).', $result);
    }

    public function testLeavesUnknownLinkWithAnchorUnchanged(): void
    {
        $resolver = new CrossReferenceResolver([]);

        $result = $resolver->resolve('See [missing](missing.md#section).');

        assertSame('See [missing](missing.md#section).', $result);
    }
}
