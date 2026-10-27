<?php

declare(strict_types=1);

namespace YiiPress\Extension\Manifest;

use Composer\Semver\VersionParser;
use LogicException;
use UnexpectedValueException;
use YiiPress\Extension\ExtensionId;
use YiiPress\Extension\ExtensionType;
use YiiPress\Extension\SemanticVersion;

use function filter_var;
use function in_array;
use function preg_match;
use function sprintf;
use function stripos;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * Turns `extension.yaml` text into an {@see ExtensionManifest}. Works without a filesystem, so the catalog
 * can use it on a manifest fetched from a release.
 */
final class ManifestParser
{
    public const int MANIFEST_VERSION = 1;

    private const array COMMON_KEYS = [
        'manifest', 'id', 'type', 'name', 'description', 'version', 'yiipress', 'license', 'authors', 'homepage',
        'platforms',
    ];
    private const array SECTION_KEYS = ['theme', 'processor'];
    private const array RESERVED_THEME_NAMES = ['minimal', 'local'];
    private const string LICENSE_PATTERN = '/^[A-Za-z0-9.+\-() ]{1,100}$/';
    private const int MAX_AUTHORS = 20;

    /**
     * @throws InvalidManifestException When the manifest has errors; it lists all of them.
     */
    public function parse(string $yaml): ExtensionManifest
    {
        $fields = new FieldReader();
        $data = new YamlDocumentLoader()->load($yaml, $fields);
        if ($data === null || !$this->isSupportedManifest($data, $fields)) {
            throw new InvalidManifestException($fields->errors());
        }

        $type = $this->type($data, $fields);
        $this->rejectUnknownKeys($data, $type, $fields);
        $id = $this->id($data, $type, $fields);
        $name = $this->text($data, 'name', 100, false, $fields);
        $description = $this->text($data, 'description', 500, true, $fields);
        $version = $this->version($data, $fields);
        $constraint = $this->constraint($data, $fields);
        $license = $this->license($data, $fields);
        $authors = $this->authors($data, $fields);
        $homepage = $fields->string($data, 'homepage', 'homepage', false);
        $homepage = $homepage === null ? null : $fields->httpsUrl($homepage, 'homepage');
        $platforms = new PlatformsParser()->parse($data, $fields);
        $theme = $type === ExtensionType::Theme ? new ThemeSectionParser()->parse($data, $fields) : null;
        $processor = $type === ExtensionType::Processor ? new ProcessorSectionParser()->parse($data, $fields) : null;

        if ($fields->hasErrors()) {
            throw new InvalidManifestException($fields->errors());
        }

        return new ExtensionManifest(
            manifest: self::MANIFEST_VERSION,
            id: self::valid($id),
            type: self::valid($type),
            name: self::valid($name),
            description: self::valid($description),
            version: self::valid($version),
            yiipress: self::valid($constraint),
            license: self::valid($license),
            authors: $authors,
            homepage: $homepage,
            platforms: self::valid($platforms),
            theme: $theme,
            processor: $processor,
        );
    }

    /**
     * @param array<mixed> $data
     */
    private function isSupportedManifest(array $data, FieldReader $fields): bool
    {
        $manifest = $fields->integer($data, 'manifest', 'manifest');
        if ($manifest === null || $manifest === self::MANIFEST_VERSION) {
            return true;
        }

        $fields->error('manifest_unsupported', 'manifest', $manifest > self::MANIFEST_VERSION
            ? sprintf('version %d requires a newer YiiPress; this one reads version %d', $manifest, self::MANIFEST_VERSION)
            : sprintf('version %d is not supported; use %d', $manifest, self::MANIFEST_VERSION));
        return false;
    }

    /**
     * Returns the type when it is valid and supported; type-specific checks are skipped otherwise.
     *
     * @param array<mixed> $data
     */
    private function type(array $data, FieldReader $fields): ?ExtensionType
    {
        $value = $fields->string($data, 'type', 'type');
        if ($value === null) {
            return null;
        }

        $type = ExtensionType::tryFrom($value);
        if ($type === null) {
            $fields->error('type_invalid', 'type', sprintf('"%s" is not an extension type; use theme or processor', $value));
            return null;
        }
        if (!$type->isSupported()) {
            $fields->error('type_unsupported', 'type', sprintf('"%s" packages are not supported yet; use theme or processor', $value));
            return null;
        }

        return $type;
    }

    /**
     * @param array<mixed> $data
     */
    private function rejectUnknownKeys(array $data, ?ExtensionType $type, FieldReader $fields): void
    {
        $allowed = self::COMMON_KEYS;
        $explained = [];
        foreach (self::SECTION_KEYS as $section) {
            if ($type === null || $type->value === $section) {
                $allowed[] = $section;
                continue;
            }
            $explained[$section] = sprintf('section is allowed only when type is "%s"', $section);
        }
        foreach ([ExtensionType::Importer, ExtensionType::Plugin] as $reserved) {
            $explained[$reserved->value] = sprintf(
                'section is reserved for type "%s", which manifest version %d does not support',
                $reserved->value,
                self::MANIFEST_VERSION,
            );
        }

        $fields->rejectUnknownKeys($data, $allowed, '', $explained);
    }

    /**
     * @param array<mixed> $data
     */
    private function id(array $data, ?ExtensionType $type, FieldReader $fields): ?ExtensionId
    {
        $value = $fields->string($data, 'id', 'id');
        if ($value === null) {
            return null;
        }

        $id = ExtensionId::tryFromString($value);
        if ($id === null) {
            $fields->error('id_invalid', 'id', sprintf(
                '"%s" must be "vendor/name", each part 1 to 64 lowercase letters, digits and single hyphens '
                . 'and not a reserved Windows name',
                $value,
            ));
            return null;
        }
        if ($type === ExtensionType::Theme && in_array($id->name, self::RESERVED_THEME_NAMES, true)) {
            $fields->error('id_reserved', 'id', sprintf(
                'theme name "%s" is reserved for a theme directory of YiiPress; choose another name',
                $id->name,
            ));
            return null;
        }

        return $id;
    }

    /**
     * @param array<mixed> $data
     */
    private function text(array $data, string $key, int $maxLength, bool $multiline, FieldReader $fields): ?string
    {
        $value = $fields->string($data, $key, $key);
        return $value === null ? null : $fields->text($value, $key, $maxLength, $multiline);
    }

    /**
     * @param array<mixed> $data
     */
    private function version(array $data, FieldReader $fields): ?string
    {
        $value = $fields->string($data, 'version', 'version');
        if ($value === null || SemanticVersion::isValid($value)) {
            return $value;
        }

        $fields->error('version_invalid', 'version', sprintf(
            '"%s" is not a SemVer version such as 1.0.0 (no "v" prefix, no build metadata, no leading zeros)',
            $value,
        ));
        return null;
    }

    /**
     * @param array<mixed> $data
     */
    private function constraint(array $data, FieldReader $fields): ?string
    {
        $value = $fields->string($data, 'yiipress', 'yiipress');
        if ($value === null) {
            return null;
        }
        if (trim($value) === '*' || stripos($value, 'dev') !== false || preg_match('/@/', $value) === 1) {
            $fields->error('constraint_invalid', 'yiipress', sprintf(
                '"%s" must state a real version range such as ^0.1.13; "*", branches and stability flags are not allowed',
                $value,
            ));
            return null;
        }

        try {
            new VersionParser()->parseConstraints($value);
        } catch (UnexpectedValueException) {
            $fields->error('constraint_invalid', 'yiipress', sprintf(
                '"%s" is not a valid version constraint; use Composer syntax such as ^0.1.13',
                $value,
            ));
            return null;
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function license(array $data, FieldReader $fields): ?string
    {
        $value = $fields->string($data, 'license', 'license');
        if ($value === null || preg_match(self::LICENSE_PATTERN, $value) === 1) {
            return $value;
        }

        $fields->error('field_invalid', 'license', sprintf(
            '"%s" is not a valid license; use an SPDX identifier or expression such as MIT, or "proprietary"',
            $value,
        ));
        return null;
    }

    /**
     * @param array<mixed> $data
     * @return list<Author>
     */
    private function authors(array $data, FieldReader $fields): array
    {
        $authors = [];
        foreach ($fields->list($data, 'authors', 'authors', 1, self::MAX_AUTHORS) ?? [] as $index => $item) {
            $author = $this->author($item, FieldReader::path('authors', $index), $fields);
            if ($author !== null) {
                $authors[] = $author;
            }
        }

        return $authors;
    }

    private function author(mixed $item, string $field, FieldReader $fields): ?Author
    {
        $map = $fields->asMapping($item, $field);
        if ($map === null) {
            return null;
        }
        $fields->rejectUnknownKeys($map, ['name', 'email', 'url'], $field);

        $name = $fields->string($map, 'name', $field . '.name');
        $name = $name === null ? null : $fields->text($name, $field . '.name', 100);
        $email = $fields->string($map, 'email', $field . '.email', false);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fields->error('email_invalid', $field . '.email', sprintf('"%s" is not a valid email address', $email));
            $email = null;
        }
        $url = $fields->string($map, 'url', $field . '.url', false);
        $url = $url === null ? null : $fields->httpsUrl($url, $field . '.url');

        return $name === null ? null : new Author($name, $email, $url);
    }

    /**
     * Narrows a value that is known to be set because no errors were recorded.
     *
     * @template T
     * @param T|null $value
     * @return T
     */
    private static function valid(mixed $value): mixed
    {
        return $value ?? throw new LogicException('A manifest without errors must have every required field.');
    }
}
