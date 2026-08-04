$ErrorActionPreference = "Stop"

$Repository = if ($env:YIIPRESS_REPOSITORY) { $env:YIIPRESS_REPOSITORY } else { "yiipress/engine" }
$InstallDirectory = if ($env:YIIPRESS_INSTALL_DIR) {
    $env:YIIPRESS_INSTALL_DIR
} else {
    Join-Path ([Environment]::GetFolderPath("LocalApplicationData")) "Programs\YiiPress"
}
$Version = if ($env:YIIPRESS_VERSION) { $env:YIIPRESS_VERSION } else { "latest" }

if (-not [Runtime.InteropServices.RuntimeInformation]::IsOSPlatform([Runtime.InteropServices.OSPlatform]::Windows)) {
    throw "This installer supports Windows only. Use install.sh on Linux or macOS."
}
if ([Runtime.InteropServices.RuntimeInformation]::OSArchitecture -ne [Runtime.InteropServices.Architecture]::X64) {
    throw "The YiiPress Windows installer currently supports x64 Windows only."
}

$Asset = "yiipress-windows-amd64.zip"
$ReleaseUrl = if ($Version -eq "latest") {
    "https://github.com/$Repository/releases/latest/download"
} else {
    "https://github.com/$Repository/releases/download/$Version"
}
$WorkDirectory = Join-Path ([IO.Path]::GetTempPath()) ("yiipress-installer-" + [Guid]::NewGuid())

try {
    New-Item -ItemType Directory -Path $WorkDirectory | Out-Null
    $Archive = Join-Path $WorkDirectory $Asset
    $Checksums = Join-Path $WorkDirectory "SHA256SUMS"

    Write-Host "Downloading YiiPress $Version for windows/amd64..."
    Invoke-WebRequest -Uri "$ReleaseUrl/$Asset" -OutFile $Archive
    Invoke-WebRequest -Uri "$ReleaseUrl/SHA256SUMS" -OutFile $Checksums

    $ChecksumLine = Get-Content $Checksums | Where-Object {
        $_ -match "^[0-9a-fA-F]{64}\s+(?:\*|assets/)?$([Regex]::Escape($Asset))$"
    } | Select-Object -First 1
    if (-not $ChecksumLine) {
        throw "SHA256SUMS does not contain $Asset."
    }
    $ExpectedChecksum = ($ChecksumLine -split "\s+")[0]
    $ActualChecksum = (Get-FileHash -Algorithm SHA256 -Path $Archive).Hash
    if ($ActualChecksum -ne $ExpectedChecksum) {
        throw "Checksum verification failed for $Asset."
    }

    Expand-Archive -Path $Archive -DestinationPath $WorkDirectory -Force
    $Binary = Join-Path $WorkDirectory "yiipress.exe"
    if (-not (Test-Path -PathType Leaf $Binary)) {
        throw "The release archive does not contain yiipress.exe."
    }

    New-Item -ItemType Directory -Path $InstallDirectory -Force | Out-Null
    $Target = Join-Path $InstallDirectory "yiipress.exe"
    $TemporaryTarget = Join-Path $InstallDirectory (".yiipress-" + [Guid]::NewGuid() + ".exe")
    Copy-Item -Path $Binary -Destination $TemporaryTarget
    [IO.File]::Move($TemporaryTarget, $Target, $true)

    $UserPath = [Environment]::GetEnvironmentVariable("Path", "User")
    $PathEntries = if ($UserPath) { $UserPath -split ";" } else { @() }
    if ($InstallDirectory -notin $PathEntries) {
        $UpdatedPath = (@($PathEntries) + $InstallDirectory | Where-Object { $_ }) -join ";"
        [Environment]::SetEnvironmentVariable("Path", $UpdatedPath, "User")
        Write-Host "Added $InstallDirectory to your user PATH. Open a new terminal to use yiipress."
    }

    Write-Host "YiiPress was installed to $Target."
} finally {
    if (Test-Path $WorkDirectory) {
        Remove-Item -Path $WorkDirectory -Recurse -Force
    }
}
