<?php

declare(strict_types=1);

namespace YiiPress\Extension;

use Composer\InstalledVersions;
use YiiPress\ApplicationInfo;

use function constant;
use function defined;
use function is_string;
use function sprintf;
use function substr;

/**
 * Version of the running YiiPress, as used to check the `yiipress` constraint of an extension.
 *
 * A release reports its tag. A nightly build reports the release it is built on, when the build stamped
 * `ApplicationInfo::BASE_VERSION`. A source checkout has no known version.
 */
final readonly class EngineVersion
{
    private const int SHORT_COMMIT_LENGTH = 12;

    /**
     * @param string|null $version SemVer version used for compatibility checks, `null` when unknown.
     * @param string|null $commit Commit of a nightly build, `null` for a release or a source checkout.
     */
    private function __construct(
        public ?string $version,
        public ?string $commit,
    ) {}

    public static function current(): self
    {
        $package = InstalledVersions::getRootPackage();
        $packageVersion = $package['name'] === 'yiipress/engine' ? $package['pretty_version'] : null;
        $baseVersionConstant = ApplicationInfo::class . '::BASE_VERSION';
        $baseVersion = defined($baseVersionConstant) ? constant($baseVersionConstant) : '';

        return self::fromBuild($packageVersion, ApplicationInfo::COMMIT, is_string($baseVersion) ? $baseVersion : '');
    }

    /**
     * @param string|null $packageVersion Root Composer package version, the tag for a release.
     * @param string $commit Commit stamped into a nightly build, empty otherwise.
     * @param string $baseVersion Release a nightly build is built on, empty when not stamped.
     */
    public static function fromBuild(?string $packageVersion, string $commit = '', string $baseVersion = ''): self
    {
        if ($commit !== '') {
            return new self(SemanticVersion::isValid($baseVersion) ? $baseVersion : null, $commit);
        }

        if ($packageVersion !== null && SemanticVersion::isValid($packageVersion)) {
            return new self($packageVersion, null);
        }

        return new self(null, null);
    }

    public function isNightly(): bool
    {
        return $this->commit !== null;
    }

    /**
     * Human-readable version for messages, for example «nightly built on 0.1.13 (commit 80964110a7e8)».
     */
    public function describe(): string
    {
        if ($this->commit === null) {
            return $this->version ?? 'an unknown version';
        }

        $commit = substr($this->commit, 0, self::SHORT_COMMIT_LENGTH);

        return $this->version === null
            ? sprintf('a nightly build of unknown version (commit %s)', $commit)
            : sprintf('nightly built on %s (commit %s)', $this->version, $commit);
    }
}
