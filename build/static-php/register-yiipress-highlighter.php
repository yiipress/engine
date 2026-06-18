<?php

declare(strict_types=1);

use SPC\store\FileSystem;

if (patch_point() === 'before-php-make') {
    $internalFunctionsFile = SOURCE_PATH . '/php-src/main/internal_functions_cli.c';
    if (!file_exists($internalFunctionsFile)) {
        throw new RuntimeException('Generated internal_functions_cli.c was not found.');
    }

    $contents = file_get_contents($internalFunctionsFile);
    if ($contents === false) {
        throw new RuntimeException('Unable to read generated internal_functions_cli.c.');
    }

    $includeProcessExtensions = getenv('YIIPRESS_STATIC_INCLUDE_PROCESS_EXTENSIONS') !== '0';
    if (!str_contains($contents, 'YIIPRESS_STATIC_PATCHED_INTERNAL_FUNCTIONS')) {
        $processExtensionIncludes = '';
        if ($includeProcessExtensions) {
            $processExtensionIncludes = <<<'C'
#include "ext/pcntl/php_pcntl.h"
#include "ext/posix/php_posix.h"
C;
        }

        $includes = <<<C
/* YIIPRESS_STATIC_PATCHED_INTERNAL_FUNCTIONS */
#include "ext/opcache/zend_accelerator_module.h"
#include "ext/standard/php_standard.h"
#include "ext/spl/php_spl.h"
#include "ext/phar/php_phar.h"
#include "ext/random/php_random.h"
#include "ext/reflection/php_reflection.h"
#include "ext/uri/php_uri.h"
#include "ext/xml/php_xml.h"
#include "ext/xmlwriter/php_xmlwriter.h"
{$processExtensionIncludes}

extern zend_module_entry markdown_module_entry;
#ifndef phpext_markdown_ptr
# define phpext_markdown_ptr &markdown_module_entry
#endif

extern zend_module_entry yaml_module_entry;
#ifndef phpext_yaml_ptr
# define phpext_yaml_ptr &yaml_module_entry
#endif

extern zend_module_entry highlighter_module_entry;
#ifndef phpext_highlighter_ptr
# define phpext_highlighter_ptr &highlighter_module_entry
#endif
C;

        $contents = preg_replace('/#include "php\.h"\R/', "$0$includes\n", $contents, 1);
        if ($contents === null) {
            throw new RuntimeException('Unable to patch generated internal_functions_cli.c.');
        }

        file_put_contents($internalFunctionsFile, $contents);
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        return;
    }

    $target = getenv('CARGO_BUILD_TARGET') ?: 'x86_64-unknown-linux-musl';
    $highlighterSource = getenv('HIGHLIGHTER_SOURCE') ?: SOURCE_PATH . '/php-src/ext/highlighter';
    $rustLibrary = "{$highlighterSource}/target/{$target}/release/libhighlighter.a";
    if (!file_exists($rustLibrary)) {
        throw new RuntimeException("Rust highlighter library was not built at {$rustLibrary}.");
    }

    $makefile = SOURCE_PATH . '/php-src/Makefile';
    $makefileContents = file_get_contents($makefile);
    if ($makefileContents === false) {
        throw new RuntimeException('Unable to read generated PHP Makefile.');
    }

    if (!str_contains($makefileContents, $rustLibrary)) {
        $makefileContents = str_replace(
            '$(EXTRA_LIBS)',
            "{$rustLibrary} $(EXTRA_LIBS)",
            $makefileContents,
            $replacementCount
        );

        if ($replacementCount === 0) {
            throw new RuntimeException('Unable to patch generated PHP Makefile link rules.');
        }

        file_put_contents($makefile, $makefileContents);
    }

    return;
}

if (patch_point() !== 'after-exts-extract') {
    return;
}

$source = getenv('HIGHLIGHTER_SOURCE');
if ($source === false || $source === '') {
    throw new RuntimeException('HIGHLIGHTER_SOURCE is required.');
}

FileSystem::copyDir($source, SOURCE_PATH . '/php-src/ext/highlighter');

$highlighterWindowsConfig = SOURCE_PATH . '/php-src/ext/highlighter/config.w32';
$highlighterWindowsConfigContents = file_get_contents($highlighterWindowsConfig);
if ($highlighterWindowsConfigContents === false) {
    throw new RuntimeException('Unable to read highlighter config.w32.');
}

if (!str_contains($highlighterWindowsConfigContents, 'ARG_ENABLE("highlighter"')) {
    $highlighterWindowsConfigContents = <<<JS
ARG_ENABLE("highlighter", "highlighter", "no");

if (PHP_HIGHLIGHTER == "yes") {
{$highlighterWindowsConfigContents}
}
JS;
}

$highlighterWindowsConfigContents = str_replace(
    'EXTENSION("highlighter", "highlighter.c", true);',
    'EXTENSION("highlighter", "highlighter.c", false);',
    $highlighterWindowsConfigContents,
    $highlighterWindowsConfigReplacementCount,
);

if ($highlighterWindowsConfigReplacementCount !== 1) {
    if (!str_contains($highlighterWindowsConfigContents, 'EXTENSION("highlighter", "highlighter.c", false);')) {
        throw new RuntimeException('Unable to patch highlighter config.w32 extension mode.');
    }
}

file_put_contents($highlighterWindowsConfig, $highlighterWindowsConfigContents);

$highlighterConfig = SOURCE_PATH . '/php-src/ext/highlighter/config.m4';
$highlighterConfigContents = file_get_contents($highlighterConfig);
if ($highlighterConfigContents === false) {
    throw new RuntimeException('Unable to read highlighter config.m4.');
}

$highlighterConfigContents = str_replace(
    [
        "\next_shared=yes\n",
        'PHP_NEW_EXTENSION([highlighter], [highlighter.c], [yes])',
    ],
    [
        "\n",
        'PHP_NEW_EXTENSION([highlighter], [highlighter.c], [$ext_shared])',
    ],
    $highlighterConfigContents,
);

file_put_contents($highlighterConfig, $highlighterConfigContents);

$markdownSource = getenv('MARKDOWN_SOURCE');
if ($markdownSource === false || $markdownSource === '') {
    throw new RuntimeException('MARKDOWN_SOURCE is required.');
}

FileSystem::copyDir($markdownSource, SOURCE_PATH . '/php-src/ext/markdown');

$markdownWindowsConfig = SOURCE_PATH . '/php-src/ext/markdown/config.w32';
if (!is_file($markdownWindowsConfig)) {
    throw new RuntimeException('markdown config.w32 was not found.');
}

$markdownWindowsConfigContents = file_get_contents($markdownWindowsConfig);
if ($markdownWindowsConfigContents === false) {
    throw new RuntimeException('Unable to read markdown config.w32.');
}

$markdownWindowsConfigContents = str_replace(
    'EXTENSION("markdown", "markdown.c");',
    'EXTENSION("markdown", "markdown.c", false);',
    $markdownWindowsConfigContents,
    $markdownWindowsConfigReplacementCount,
);

if ($markdownWindowsConfigReplacementCount !== 1) {
    if (!str_contains($markdownWindowsConfigContents, 'EXTENSION("markdown", "markdown.c", false);')) {
        throw new RuntimeException('Unable to patch markdown config.w32 extension mode.');
    }
}

file_put_contents($markdownWindowsConfig, $markdownWindowsConfigContents);
