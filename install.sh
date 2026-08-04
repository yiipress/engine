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
if [ "$version" = "latest" ]; then
    release_url="https://github.com/${repository}/releases/latest/download"
else
    release_url="https://github.com/${repository}/releases/download/${version}"
fi

work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT HUP INT TERM

echo "Downloading YiiPress ${version} for ${platform}/${architecture}..."
curl -fsSL --retry 3 --retry-delay 2 "${release_url}/${asset}" -o "${work_dir}/${asset}"
curl -fsSL --retry 3 --retry-delay 2 "${release_url}/SHA256SUMS" -o "${work_dir}/SHA256SUMS"

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
if [ ! -f "${work_dir}/yiipress" ]; then
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
