<?php

declare(strict_types=1);

namespace YiiPress\Benchmarks;

use YiiPress\Build\TemplateResolver;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\I18n\UiText;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
final class UiTextBench
{
    private TemplateResolver $templateResolver;

    public function setUp(): void
    {
        $registry = new ThemeRegistry();
        $registry->register(new Theme('minimal', dirname(__DIR__) . '/themes/minimal'));
        $this->templateResolver = new TemplateResolver($registry);
    }

    #[Revs(1000)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchResolveSingleUiLanguage(): void
    {
        UiText::forTheme('ru', $this->templateResolver, 'minimal', 'en');
    }

    #[Revs(500)]
    #[Iterations(3)]
    #[Warmup(1)]
    public function benchResolveUiCatalogs(): void
    {
        UiText::catalogsForTheme(['en', 'ru'], $this->templateResolver, 'minimal', 'en');
    }
}
