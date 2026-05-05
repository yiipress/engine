<?php

declare(strict_types=1);

namespace YiiPress\Web\DevServer;

interface EditorLauncherInterface
{
    /**
     * @param string|array<array-key, mixed>|null $configuredEditor
     */
    public function open(string $filePath, string|array|null $configuredEditor): bool;

    /**
     * @return string|list<string>|null
     */
    public function configuredEditorFromFile(string $configPath): string|array|null;
}
