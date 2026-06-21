#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_VERSION="${PHP_VERSION:-8.5}"
STATIC_PHP_CLI_REF="${STATIC_PHP_CLI_REF:-5b5861c366a0d94bc84002db7b3f46144b388fbb}"
STATIC_PHP_EXTENSIONS="${STATIC_PHP_EXTENSIONS:-ctype,dom,filter,highlighter,mdparser,mbstring,opcache,pcntl,phar,posix,xmlwriter,yaml}"

detect_arch() {
    case "$(uname -m)" in
        arm64|aarch64)
            printf 'arm64'
            ;;
        x86_64|amd64)
            printf 'amd64'
            ;;
        *)
            printf 'Unsupported macOS architecture: %s\n' "$(uname -m)" >&2
            exit 1
            ;;
    esac
}

HOST_ARCH="$(detect_arch)"
ARCH="$HOST_ARCH"
DIST_DIR="dist/macos-${ARCH}"

while [ "$#" -gt 0 ]; do
    case "$1" in
        --arch)
            ARCH="$2"
            shift 2
            ;;
        --dist-dir)
            DIST_DIR="$2"
            shift 2
            ;;
        *)
            printf 'Unknown argument: %s\n' "$1" >&2
            exit 1
            ;;
    esac
done

if [ "$(uname -s)" != "Darwin" ]; then
    printf 'package-macos must run on macOS.\n' >&2
    exit 1
fi

if [ "$ARCH" != "$HOST_ARCH" ]; then
    printf 'package-macos does not support cross-compilation: requested %s on %s host.\n' "$ARCH" "$HOST_ARCH" >&2
    exit 1
fi

case "$ARCH" in
    arm64)
        CARGO_BUILD_TARGET="aarch64-apple-darwin"
        ;;
    amd64)
        CARGO_BUILD_TARGET="x86_64-apple-darwin"
        ;;
    *)
        printf 'Unsupported macOS package architecture: %s\n' "$ARCH" >&2
        exit 1
        ;;
esac

DIST_PATH="${ROOT}/${DIST_DIR}"
WORK_PATH="${ROOT}/runtime/package-macos"
APP_PATH="${WORK_PATH}/app"
STATIC_PHP_PATH="${WORK_PATH}/static-php-cli"
MDPARSER_PATH="${WORK_PATH}/mdparser"
HIGHLIGHTER_PATH="${WORK_PATH}/yiipress-highlighter"
PHAR_PATH="${WORK_PATH}/yiipress.phar"
BIN_PATH="${DIST_PATH}/yiipress"

invoke() {
    "$@"
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        printf 'Required command was not found in PATH: %s\n' "$1" >&2
        exit 127
    fi
}

write_log_tail() {
    local path="$1"
    if [ -f "$path" ]; then
        printf '===== %s =====\n' "$path"
        tail -n 200 "$path"
    fi
}

expand_tar_gz_archive() {
    local url="$1"
    local destination="$2"
    local archive

    archive="$(mktemp "${WORK_PATH}/archive.XXXXXX")"
    curl -fsSL --max-time 300 --retry 3 --retry-delay 5 "$url" -o "$archive"
    mkdir -p "$destination"
    tar -xzf "$archive" --strip-components=1 -C "$destination"
    rm -f "$archive"
}

copy_clean_path() {
    local source="$1"
    local destination="$2"

    rm -rf "$destination"
    cp -R "$source" "$destination"
}

packagist_latest_stable_version() {
    local package="$1"
    local version

    version="$(php -r '
$package = $argv[1];
$data = json_decode(file_get_contents("https://repo.packagist.org/p2/" . $package . ".json"), true, 512, JSON_THROW_ON_ERROR);
foreach ($data["packages"][$package] as $release) {
    $version = $release["version"];
    if (!preg_match("/(?:^dev-|dev$|alpha|beta|RC)/i", $version)) {
        echo $version;
        exit(0);
    }
}
exit(1);
' "$package")"
    if [ -z "$version" ]; then
        printf 'Unable to determine latest stable Packagist version for %s.\n' "$package" >&2
        exit 1
    fi

    printf '%s' "$version"
}

for command in php composer tar curl rustup cargo make; do
    require_command "$command"
done

mkdir -p "$DIST_PATH" "$WORK_PATH"
rm -f "${DIST_PATH}/yiipress.phar"

mkdir -p "$APP_PATH"
for directory in config public src themes; do
    copy_clean_path "${ROOT}/${directory}" "${APP_PATH}/${directory}"
done
mkdir -p "${APP_PATH}/build"
cp "${ROOT}/build/package-phar.php" "${APP_PATH}/build/package-phar.php"
cp "${ROOT}/build/PharArchiveFilter.php" "${APP_PATH}/build/PharArchiveFilter.php"
cp "${ROOT}/build/PhpDocStripper.php" "${APP_PATH}/build/PhpDocStripper.php"
cp "${ROOT}/yii" "${APP_PATH}/yii"
cp "${ROOT}/composer.json" "${APP_PATH}/composer.json"
cp "${ROOT}/composer.lock" "${APP_PATH}/composer.lock"

pushd "$APP_PATH" >/dev/null
invoke composer install \
    --no-dev \
    --no-progress \
    --no-interaction \
    --classmap-authoritative \
    --ignore-platform-req=ext-inotify \
    --ignore-platform-req=ext-mdparser \
    --ignore-platform-req=ext-yaml \
    --ignore-platform-req=ext-highlighter
invoke php -d phar.readonly=0 build/package-phar.php "$PHAR_PATH"
popd >/dev/null

if [ ! -d "$STATIC_PHP_PATH" ]; then
    expand_tar_gz_archive \
        "https://github.com/crazywhalecc/static-php-cli/archive/${STATIC_PHP_CLI_REF}.tar.gz" \
        "$STATIC_PHP_PATH"
fi

rm -rf "$MDPARSER_PATH" "$HIGHLIGHTER_PATH"
invoke composer create-project \
    --no-dev \
    --no-progress \
    --no-interaction \
    iliaal/mdparser \
    "$MDPARSER_PATH"
invoke composer create-project \
    --no-dev \
    --no-progress \
    --no-interaction \
    yiipress/highlighter \
    "$HIGHLIGHTER_PATH"

pushd "$STATIC_PHP_PATH" >/dev/null
invoke composer install \
    --no-dev \
    --no-progress \
    --no-interaction \
    --classmap-authoritative

export CARGO_BUILD_TARGET
export HIGHLIGHTER_SOURCE="$HIGHLIGHTER_PATH"
export MDPARSER_SOURCE="$MDPARSER_PATH"
export HIGHLIGHTER_VERSION="$(packagist_latest_stable_version yiipress/highlighter)"
export YIIPRESS_STATIC_INCLUDE_PROCESS_EXTENSIONS=1

pushd "$HIGHLIGHTER_PATH" >/dev/null
invoke rustup target add "$CARGO_BUILD_TARGET"
invoke cargo build --release --target "$CARGO_BUILD_TARGET"
popd >/dev/null

invoke php "${ROOT}/build/static-php/patch-extension-config.php" config/ext.json
invoke php "${ROOT}/build/static-php/patch-source-config.php" config/source.json config/lib.json
invoke php bin/spc download \
    --for-extensions="${STATIC_PHP_EXTENSIONS}" \
    --with-php="${PHP_VERSION}" \
    --without-suggestions
invoke php bin/spc doctor --auto-fix
if ! invoke php bin/spc build \
    "$STATIC_PHP_EXTENSIONS" \
    --build-micro \
    -P "${ROOT}/build/static-php/register-yiipress-highlighter.php"; then
    write_log_tail "${STATIC_PHP_PATH}/log/spc.output.log"
    write_log_tail "${STATIC_PHP_PATH}/log/spc.shell.log"
    exit 1
fi
invoke php bin/spc micro:combine "$PHAR_PATH" -O "$BIN_PATH"
popd >/dev/null

chmod +x "$BIN_PATH"
printf 'Built %s\n' "$BIN_PATH"
