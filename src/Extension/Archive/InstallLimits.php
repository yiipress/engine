<?php

declare(strict_types=1);

namespace YiiPress\Extension\Archive;

/**
 * Hard limits for downloading and unpacking an extension. No command option disables them.
 *
 * The values are proposed defaults, not measured; see docs/decisions/0002-theme-installation-spec.md.
 * The constructor exists so that tests can use small limits.
 */
final readonly class InstallLimits
{
    public const int DOWNLOAD_BYTES = 50 * 1024 * 1024;
    public const int EXTRACTED_BYTES = 200 * 1024 * 1024;
    public const int FILE_BYTES = 20 * 1024 * 1024;
    public const int ENTRIES = 10_000;
    public const int API_PAGES = 10;
    public const int CANDIDATE_MANIFESTS = 20;
    public const int CONNECT_TIMEOUT_SECONDS = 15;
    public const int REQUEST_TIMEOUT_SECONDS = 300;
    public const int RETRIES = 3;
    public const int MAX_RETRY_AFTER_SECONDS = 60;
    public const int REDIRECTS = 5;

    public function __construct(
        public int $downloadBytes = self::DOWNLOAD_BYTES,
        public int $extractedBytes = self::EXTRACTED_BYTES,
        public int $fileBytes = self::FILE_BYTES,
        public int $entries = self::ENTRIES,
    ) {}
}
