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

    if (!str_contains($contents, 'YIIPRESS_STATIC_PATCHED_INTERNAL_FUNCTIONS')) {
        $includes = <<<'C'
/* YIIPRESS_STATIC_PATCHED_INTERNAL_FUNCTIONS */
#include "ext/opcache/zend_accelerator_module.h"
#include "ext/pcntl/php_pcntl.h"
#include "ext/standard/php_standard.h"
#include "ext/spl/php_spl.h"
#include "ext/phar/php_phar.h"
#include "ext/random/php_random.h"
#include "ext/reflection/php_reflection.h"
#include "ext/simplexml/php_simplexml.h"
#include "ext/uri/php_uri.h"
#include "ext/xml/php_xml.h"
#include "ext/xmlwriter/php_xmlwriter.h"

extern zend_module_entry md4c_module_entry;
#ifndef phpext_md4c_ptr
# define phpext_md4c_ptr &md4c_module_entry
#endif

extern zend_module_entry yaml_module_entry;
#ifndef phpext_yaml_ptr
# define phpext_yaml_ptr &yaml_module_entry
#endif

extern zend_module_entry yiipress_highlighter_module_entry;
#ifndef phpext_yiipress_highlighter_ptr
# define phpext_yiipress_highlighter_ptr &yiipress_highlighter_module_entry
#endif
C;

        $contents = preg_replace('/#include "php\.h"\R/', "$0$includes\n", $contents, 1);
        if ($contents === null) {
            throw new RuntimeException('Unable to patch generated internal_functions_cli.c.');
        }

        file_put_contents($internalFunctionsFile, $contents);
    }

    $target = getenv('CARGO_BUILD_TARGET') ?: 'x86_64-unknown-linux-musl';
    $highlighterSource = getenv('YIIPRESS_HIGHLIGHTER_SOURCE') ?: SOURCE_PATH . '/php-src/ext/yiipress_highlighter';
    $rustLibrary = "{$highlighterSource}/target/{$target}/release/libyiipress_highlighter.a";
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

if (patch_point() !== 'before-php-buildconf') {
    return;
}

$source = getenv('YIIPRESS_HIGHLIGHTER_SOURCE');
if ($source === false || $source === '') {
    throw new RuntimeException('YIIPRESS_HIGHLIGHTER_SOURCE is required.');
}

FileSystem::copyDir($source, SOURCE_PATH . '/php-src/ext/yiipress_highlighter');

$md4cSource = getenv('YIIPRESS_MD4C_SOURCE');
if ($md4cSource === false || $md4cSource === '') {
    throw new RuntimeException('YIIPRESS_MD4C_SOURCE is required.');
}

FileSystem::copyDir($md4cSource, SOURCE_PATH . '/php-src/ext/md4c');
