param(
    [string] $DistDir = "dist/windows-amd64",
    [string] $PhpVersion = "8.5",
    [string] $Md4cVersion = "1.1",
    [string] $StaticPhpCliRef = "5b5861c366a0d94bc84002db7b3f46144b388fbb",
    [string] $StaticPhpExtensions = "ctype,dom,filter,highlighter,mbstring,md4c,opcache,phar,xml,xmlwriter,yaml"
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$distPath = Join-Path $root $DistDir
$workPath = Join-Path $root "runtime/package-windows"
$appPath = Join-Path $workPath "app"
$staticPhpPath = Join-Path $workPath "static-php-cli"
$md4cPath = Join-Path $workPath "md4c-extension"
$highlighterPath = Join-Path $workPath "yiipress-highlighter"
$pharPath = Join-Path $distPath "yiipress.phar"
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
    Invoke-WebRequest -Uri $Url -OutFile $archive
    New-Item -ItemType Directory -Force -Path $Destination | Out-Null
    Invoke-NativeCommand "tar" @("-xzf", $archive, "--strip-components=1", "-C", $Destination)
    Remove-Item $archive -Force
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

foreach ($command in @("php", "composer", "tar", "rustup", "cargo")) {
    Test-NativeCommand $command
}

if ($IsWindows) {
    foreach ($command in @("cl", "nmake")) {
        Test-NativeCommand $command
    }
}

New-Item -ItemType Directory -Force -Path $distPath, $workPath | Out-Null

New-Item -ItemType Directory -Force -Path $appPath | Out-Null
foreach ($directory in @("config", "public", "src", "themes")) {
    Copy-CleanPath (Join-Path $root $directory) (Join-Path $appPath $directory)
}
New-Item -ItemType Directory -Force -Path (Join-Path $appPath "build") | Out-Null
Copy-Item (Join-Path $root "build/package-phar.php") (Join-Path $appPath "build/package-phar.php") -Force
Copy-Item (Join-Path $root "build/PharArchiveFilter.php") (Join-Path $appPath "build/PharArchiveFilter.php") -Force
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
        "--ignore-platform-req=ext-md4c",
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

if (!(Test-Path $md4cPath)) {
    Expand-TarGzArchive "https://pecl.php.net/get/md4c-${Md4cVersion}.tgz" $md4cPath
}

if (!(Test-Path $highlighterPath)) {
    Invoke-NativeCommand "composer" @(
        "create-project",
        "--no-dev",
        "--no-progress",
        "--no-interaction",
        "yiipress/highlighter",
        $highlighterPath,
        "dev-master"
    )
}

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
    $env:YIIPRESS_MD4C_SOURCE = $md4cPath
    $env:YIIPRESS_STATIC_INCLUDE_PROCESS_EXTENSIONS = "0"

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
