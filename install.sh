#!/bin/sh

set -eu

repository="${YIIPRESS_REPOSITORY:-yiipress/engine}"
install_dir="${YIIPRESS_INSTALL_DIR:-/usr/local/bin}"
version="${YIIPRESS_VERSION:-latest}"

for command in curl tar awk mktemp install mv rm uname; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "YiiPress installer requires $command." >&2
        exit 1
    fi
done

system="$(uname -s)"
machine="$(uname -m)"
case "$system" in
    Linux)
        platform="linux"
        checksum_command="sha256sum"
        case "$machine" in
            x86_64|amd64) architecture="amd64" ;;
            *)
                echo "Unsupported Linux architecture: ${machine}." >&2
                exit 1
                ;;
        esac
        ;;
    Darwin)
        platform="macos"
        checksum_command="shasum"
        case "$machine" in
            arm64|aarch64) architecture="arm64" ;;
            *)
                echo "Unsupported macOS architecture: ${machine}." >&2
                exit 1
                ;;
        esac
        ;;
    *)
        echo "Unsupported operating system: ${system}. Use install.ps1 on Windows." >&2
        exit 1
        ;;
esac

if ! command -v "$checksum_command" >/dev/null 2>&1; then
    echo "YiiPress installer requires $checksum_command." >&2
    exit 1
fi

asset="yiipress-${platform}-${architecture}.tar.gz"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT HUP INT TERM

if [ "$version" = "nightly" ]; then
    version=""
    page=1
    max_pages=10
    while [ -z "$version" ] && [ "$page" -le "$max_pages" ]; do
        curl -fsSL --retry 3 --retry-delay 2 \
            "https://api.github.com/repos/${repository}/releases?per_page=100&page=${page}" \
            -o "${work_dir}/releases.json"
        version="$(awk '
            {
                for (position = 1; position <= length($0); position++) {
                    character = substr($0, position, 1)
                    if (depth > 0) {
                        release = release character
                    }
                    if (escaped) {
                        escaped = 0
                    } else if (in_string && character == "\\") {
                        escaped = 1
                    } else if (character == "\"") {
                        in_string = !in_string
                    } else if (!in_string && character == "{") {
                        if (depth == 0) {
                            release = "{"
                        }
                        depth++
                    } else if (!in_string && character == "}") {
                        depth--
                        if (depth == 0) {
                            if (release ~ /"prerelease"[[:space:]]*:[[:space:]]*true/) {
                                fields = split(release, values, "\"")
                                for (field = 2; field + 2 <= fields; field += 2) {
                                    if (values[field] == "tag_name" && values[field + 2] ~ /^nightly-[0-9]+-[0-9]+-[0-9a-f]+$/) {
                                        print values[field + 2]
                                        exit
                                    }
                                }
                            }
                            release = ""
                        }
                    }
                }
            }
        ' "${work_dir}/releases.json")"
        if [ -n "$version" ]; then
            break
        fi
        if awk '{ content = content $0 } END { gsub(/[[:space:]]/, "", content); exit content == "[]" ? 0 : 1 }' "${work_dir}/releases.json"; then
            break
        fi
        page=$((page + 1))
    done
    if [ -z "$version" ]; then
        echo "Could not find a YiiPress nightly release for ${repository}." >&2
        exit 1
    fi
fi

if [ "$version" = "latest" ]; then
    release_url="https://github.com/${repository}/releases/latest/download"
else
    release_url="https://github.com/${repository}/releases/download/${version}"
fi

if [ "$version" = "latest" ]; then
    redirect_url="$(curl -fsS --retry 3 --retry-delay 2 -w '%{redirect_url}' "${release_url}/SHA256SUMS" -o /dev/null)"
    redirect_url="${redirect_url%%\?*}"
    release_url="${redirect_url%/SHA256SUMS}"
    version="${release_url##*/}"
fi

echo "Downloading YiiPress ${version} for ${platform}/${architecture}..."
curl -fsSL --retry 3 --retry-delay 2 "${release_url}/SHA256SUMS" -o "${work_dir}/SHA256SUMS"
curl -fsSL --retry 3 --retry-delay 2 "${release_url}/${asset}" -o "${work_dir}/${asset}"

expected_checksum="$(awk -v asset="$asset" '$2 == asset || $2 == "*" asset || $2 == "assets/" asset { print $1; exit }' "${work_dir}/SHA256SUMS")"
if [ -z "$expected_checksum" ]; then
    echo "SHA256SUMS does not contain ${asset}." >&2
    exit 1
fi

if [ "$checksum_command" = "shasum" ]; then
    actual_checksum="$(shasum -a 256 "${work_dir}/${asset}" | awk '{ print $1 }')"
else
    actual_checksum="$(sha256sum "${work_dir}/${asset}" | awk '{ print $1 }')"
fi
if [ "$actual_checksum" != "$expected_checksum" ]; then
    echo "Checksum verification failed for ${asset}." >&2
    exit 1
fi

tar -xzf "${work_dir}/${asset}" -C "$work_dir" yiipress
if [ ! -f "${work_dir}/yiipress" ] || [ -L "${work_dir}/yiipress" ]; then
    echo "The release archive does not contain the yiipress binary." >&2
    exit 1
fi

elevate=""
if { [ ! -d "$install_dir" ] && ! mkdir -p "$install_dir" 2>/dev/null; } || [ ! -w "$install_dir" ]; then
    if command -v sudo >/dev/null 2>&1; then
        elevate="sudo"
    else
        echo "Cannot write to ${install_dir}. Run as root or set YIIPRESS_INSTALL_DIR." >&2
        exit 1
    fi
fi

$elevate install -d "$install_dir"
temporary_target="${install_dir}/.yiipress.$$"
$elevate install -m 0755 "${work_dir}/yiipress" "$temporary_target"
$elevate mv -f "$temporary_target" "${install_dir}/yiipress"

echo "YiiPress was installed to ${install_dir}/yiipress."
