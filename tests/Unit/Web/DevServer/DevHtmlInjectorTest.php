<?php

declare(strict_types=1);

namespace YiiPress\Tests\Unit\Web\DevServer;

use YiiPress\Web\DevServer\DevHtmlInjector;
use YiiPress\Web\DevServer\LiveReloadHtmlInjector;
use PHPUnit\Framework\TestCase;

final class DevHtmlInjectorTest extends TestCase
{
    public function testInjectsLiveReloadAndSourceOverlayBeforeClosingBodyTag(): void
    {
        $html = '<html><body><p>Hello</p></body></html>';

        $body = DevHtmlInjector::inject($html);

        self::assertStringContainsString('EventSource("/_live-reload")', $body);
        self::assertStringContainsString('id = "yiipress-open-source"', $body);
        self::assertStringContainsString('button.textContent = "✏️";', $body);
        self::assertStringContainsString('border-radius:50%;background:rgba(17,17,17,.68)', $body);
        self::assertStringContainsString('transition:transform .14s ease,background .14s ease', $body);
        self::assertStringContainsString('button.style.transform = "scale(1.12)";', $body);
        self::assertStringContainsString('fetch("/_open-source"', $body);
        self::assertStringContainsString('</body>', $body);
    }

    public function testLiveReloadKeepsPingEventsPassive(): void
    {
        $body = LiveReloadHtmlInjector::inject('<html><body><p>Hello</p></body></html>');

        self::assertStringContainsString('es.addEventListener("ping", function() {});', $body);
        self::assertStringNotContainsString('es.addEventListener("ping", function() { es.close(); connect(); });', $body);
    }

    public function testLiveReloadShowsBuildErrorsOnPage(): void
    {
        $body = LiveReloadHtmlInjector::inject('<html><body><p>Hello</p></body></html>');

        self::assertStringContainsString('errorPanel.id = "yiipress-build-error";', $body);
        self::assertStringContainsString('title.textContent = "Build failed";', $body);
        self::assertStringContainsString('showBuildError(output);', $body);
        self::assertStringContainsString('hideBuildError(); es.close(); location.reload();', $body);
        self::assertStringNotContainsString('console.error', $body);
    }

    public function testLiveReloadClosesEventSourceWhenPageIsLeaving(): void
    {
        $body = LiveReloadHtmlInjector::inject('<html><body><p>Hello</p></body></html>');

        self::assertStringContainsString('window.addEventListener("pagehide", close);', $body);
        self::assertStringContainsString('window.addEventListener("beforeunload", close);', $body);
        self::assertStringContainsString('if (!leaving)', $body);
    }

    public function testSkipsHtmlWithoutBodyTag(): void
    {
        $html = '<html><p>No body tag</p></html>';

        self::assertSame($html, DevHtmlInjector::inject($html));
    }
}
