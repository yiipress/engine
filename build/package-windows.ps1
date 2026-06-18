param(
    [string] $DistDir = "dist/windows-amd64",
    [string] $PhpVersion = "8.5",
    [string] $StaticPhpCliRef = "5b5861c366a0d94bc84002db7b3f46144b388fbb",
    [string] $StaticPhpExtensions = "ctype,dom,filter,highlighter,markdown,mbstring,opcache,phar,xmlwriter,yaml"
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$distPath = Join-Path $root $DistDir
$workPath = Join-Path $root "runtime/package-windows"
$appPath = Join-Path $workPath "app"
$staticPhpPath = Join-Path $workPath "static-php-cli"
$markdownPath = Join-Path $workPath "yiipress-markdown"
$highlighterPath = Join-Path $workPath "yiipress-highlighter"
$pharPath = Join-Path $workPath "yiipress.phar"
$exePath = Join-Path $distPath "yiipress.exe"

function Invoke-NativeCommand {
    param(
        [string] $FilePath,
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]] $Arguments
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed with exit code ${LASTEXITCODE}: $FilePath $($Arguments -join ' ')"
    }
}

function Test-NativeCommand {
    param(
        [string] $Command
    )

    if ($null -eq (Get-Command $Command -ErrorAction SilentlyContinue)) {
        throw "Required command was not found in PATH: $Command"
    }
}

function Write-LogTail {
    param(
        [string] $Path
    )

    if (Test-Path $Path) {
        Write-Output "===== $Path ====="
        Get-Content $Path -Tail 200
    }
}

function Expand-TarGzArchive {
    param(
        [string] $Url,
        [string] $Destination
    )

    $archive = Join-Path $workPath ([IO.Path]::GetRandomFileName() + ".tar.gz")
    try {
        Invoke-WebRequest -Uri $Url -OutFile $archive -TimeoutSec 300
        New-Item -ItemType Directory -Force -Path $Destination | Out-Null
        Invoke-NativeCommand "tar" @("-xzf", $archive, "--strip-components=1", "-C", $Destination)
    } finally {
        if (Test-Path $archive) {
            Remove-Item $archive -Force
        }
    }
}

function Copy-CleanPath {
    param(
        [string] $Source,
        [string] $Destination
    )

    if (Test-Path $Destination) {
        Remove-Item $Destination -Recurse -Force
    }

    Copy-Item $Source $Destination -Recurse -Force
}

function Get-PackagistLatestStableVersion {
    param(
        [string] $Package
    )

    $metadata = Invoke-RestMethod -Uri "https://repo.packagist.org/p2/${Package}.json"
    $releases = $metadata.packages.PSObject.Properties[$Package].Value
    foreach ($release in $releases) {
        $version = [string] $release.version
        if ($version -notmatch "(?i)(^dev-|dev$|alpha|beta|RC)") {
            return $version
        }
    }

    throw "Unable to determine latest stable Packagist version for $Package."
}

foreach ($command in @("php", "composer", "tar", "rustup", "cargo")) {
    Test-NativeCommand $command
}

foreach ($command in @("cl", "nmake")) {
    Test-NativeCommand $command
}

New-Item -ItemType Directory -Force -Path $distPath, $workPath | Out-Null
$legacyDistPharPath = Join-Path $distPath "yiipress.phar"
if (Test-Path $legacyDistPharPath) {
    Remove-Item $legacyDistPharPath -Force
}

New-Item -ItemType Directory -Force -Path $appPath | Out-Null
foreach ($directory in @("config", "public", "src", "themes")) {
    Copy-CleanPath (Join-Path $root $directory) (Join-Path $appPath $directory)
}
New-Item -ItemType Directory -Force -Path (Join-Path $appPath "build") | Out-Null
Copy-Item (Join-Path $root "build/package-phar.php") (Join-Path $appPath "build/package-phar.php") -Force
Copy-Item (Join-Path $root "build/PharArchiveFilter.php") (Join-Path $appPath "build/PharArchiveFilter.php") -Force
Copy-Item (Join-Path $root "build/PhpDocStripper.php") (Join-Path $appPath "build/PhpDocStripper.php") -Force
Copy-Item (Join-Path $root "yii") (Join-Path $appPath "yii") -Force
Copy-Item (Join-Path $root "composer.json") (Join-Path $appPath "composer.json") -Force
Copy-Item (Join-Path $root "composer.lock") (Join-Path $appPath "composer.lock") -Force

Push-Location $appPath
try {
    Invoke-NativeCommand "composer" @(
        "install",
        "--no-dev",
        "--no-progress",
        "--no-interaction",
        "--classmap-authoritative",
        "--ignore-platform-req=ext-inotify",
        "--ignore-platform-req=ext-pcntl",
        "--ignore-platform-req=ext-posix",
        "--ignore-platform-req=ext-markdown",
        "--ignore-platform-req=ext-yaml",
        "--ignore-platform-req=ext-highlighter"
    )
    Invoke-NativeCommand "php" @("-d", "phar.readonly=0", "build/package-phar.php", $pharPath)
} finally {
    Pop-Location
}

if (!(Test-Path $staticPhpPath)) {
    Expand-TarGzArchive `
        "https://github.com/crazywhalecc/static-php-cli/archive/${StaticPhpCliRef}.tar.gz" `
        $staticPhpPath
}

foreach ($path in @($markdownPath, $highlighterPath)) {
    if (Test-Path $path) {
        Remove-Item $path -Recurse -Force
    }
}

Invoke-NativeCommand "composer" @(
    "create-project",
    "--no-dev",
    "--no-progress",
    "--no-interaction",
    "yiipress/markdown",
    $markdownPath
)
Invoke-NativeCommand "composer" @(
    "create-project",
    "--no-dev",
    "--no-progress",
    "--no-interaction",
    "yiipress/highlighter",
    $highlighterPath
)

Push-Location $staticPhpPath
try {
    Invoke-NativeCommand "composer" @(
        "install",
        "--no-dev",
        "--no-progress",
        "--no-interaction",
        "--classmap-authoritative"
    )

    $env:CARGO_BUILD_TARGET = "x86_64-pc-windows-msvc"
    $env:HIGHLIGHTER_SOURCE = $highlighterPath
    $env:MARKDOWN_SOURCE = $markdownPath
    $env:HIGHLIGHTER_VERSION = Get-PackagistLatestStableVersion "yiipress/highlighter"
    $env:MARKDOWN_VERSION = Get-PackagistLatestStableVersion "yiipress/markdown"
    $env:YIIPRESS_STATIC_INCLUDE_PROCESS_EXTENSIONS = "0"
    $env:RUSTFLAGS = "-C target-feature=+crt-static"

    Push-Location $highlighterPath
    try {
        Invoke-NativeCommand "rustup" @("target", "add", $env:CARGO_BUILD_TARGET)
        Invoke-NativeCommand "cargo" @("build", "--release", "--target", $env:CARGO_BUILD_TARGET)
    } finally {
        Pop-Location
    }

    Invoke-NativeCommand "php" @((Join-Path $root "build/static-php/patch-extension-config.php"), "config/ext.json")
    Invoke-NativeCommand "php" @(
        (Join-Path $root "build/static-php/patch-source-config.php"),
        "config/source.json",
        "config/lib.json"
    )
    Invoke-NativeCommand "php" @(
        "bin/spc",
        "download",
        "--for-extensions=${StaticPhpExtensions}",
        "--with-php=${PhpVersion}",
        "--without-suggestions"
    )
    Invoke-NativeCommand "php" @("bin/spc", "doctor", "--auto-fix")
    $buildrootLibraryPath = Join-Path $staticPhpPath "buildroot/lib"
    New-Item -ItemType Directory -Force -Path $buildrootLibraryPath | Out-Null
    Copy-Item `
        (Join-Path $highlighterPath "target/$($env:CARGO_BUILD_TARGET)/release/highlighter.lib") `
        (Join-Path $buildrootLibraryPath "highlighter.lib") `
        -Force
    try {
        Invoke-NativeCommand "php" @(
            "bin/spc",
            "build",
            $StaticPhpExtensions,
            "--build-micro",
            "-P",
            (Join-Path $root "build/static-php/register-yiipress-highlighter.php")
        )
    } catch {
        Write-LogTail (Join-Path $staticPhpPath "log/spc.output.log")
        Write-LogTail (Join-Path $staticPhpPath "log/spc.shell.log")
        throw
    }
    Invoke-NativeCommand "php" @("bin/spc", "micro:combine", $pharPath, "-O", $exePath)
} finally {
    Pop-Location
}

Write-Output "Built $exePath"
