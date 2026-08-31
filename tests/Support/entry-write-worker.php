<?php

declare(strict_types=1);

use YiiPress\Build\EntryWriteWorkerJob;
use YiiPress\Build\ParallelEntryWriter;
use YiiPress\Build\Theme;
use YiiPress\Build\ThemeRegistry;
use YiiPress\Build\TemplateResolver;
use YiiPress\Content\Model\Entry;
use YiiPress\Processor\ContentProcessorInterface;
use YiiPress\Processor\ContentProcessorPipeline;

use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function getmypid;
use function posix_kill;
use function unserialize;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$contents = file_get_contents($argv[2]);
$job = $contents === false ? false : unserialize($contents, ['allowed_classes' => true]);
if (!$job instanceof EntryWriteWorkerJob) {
    exit(1);
}

$killOnTitle = getenv('YIIPRESS_TEST_KILL_ON_TITLE');

$pipeline = new ContentProcessorPipeline(
    new readonly class ($killOnTitle !== false ? $killOnTitle : '') implements ContentProcessorInterface {
        public function __construct(private string $killOnTitle) {}

        public function process(string $content, Entry $entry): string
        {
            if ($this->killOnTitle !== '' && $entry->title === $this->killOnTitle) {
                posix_kill(getmypid(), \SIGKILL);
            }

            return '<p>' . $content . '</p>';
        }
    },
);

$registry = new ThemeRegistry();
$registry->register(new Theme('test', dirname($job->contentDir()) . '/theme'));
$templateResolver = new TemplateResolver($registry);

$writer = new ParallelEntryWriter(
    $pipeline,
    $templateResolver,
    $job->cache(),
    $job->assetManifest(),
    $job->relatedIndex(),
    $job->translationIndex(),
);
$writer->writeChunk($job->siteConfig(), $job->tasks(), $job->contentDir(), $job->navigation(), $job->crossRefResolver(), $job->authors(), $job->noWrite());

file_put_contents($argv[3], (string) count($job->tasks()), LOCK_EX);
