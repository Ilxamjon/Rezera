# Build a production (release) APK pointed at an HTTPS API.
# Usage:
#   .\scripts\build-release-apk.ps1 -ApiBaseUrl https://api.example.uz
# Optional:
#   .\scripts\build-release-apk.ps1 -ApiBaseUrl https://api.example.uz -OutName rezera-1.0.0.apk

param(
    [Parameter(Mandatory = $true)]
    [string] $ApiBaseUrl,

    [string] $OutName = ""
)

$ErrorActionPreference = "Stop"

if ($ApiBaseUrl -notmatch '^https://') {
    Write-Error "Production APK requires HTTPS API_BASE_URL (got: $ApiBaseUrl)"
}

$root = Split-Path -Parent $PSScriptRoot
$mobile = Join-Path $root "mobile"
Set-Location $mobile

Write-Host "Building release APK → $ApiBaseUrl"
flutter pub get
flutter build apk --release --dart-define="API_BASE_URL=$ApiBaseUrl"

$apk = Join-Path $mobile "build\app\outputs\flutter-apk\app-release.apk"
if (-not (Test-Path $apk)) {
    Write-Error "APK not found at $apk"
}

if ($OutName -ne "") {
    $destDir = Join-Path $mobile "build\app\outputs\flutter-apk"
    $dest = Join-Path $destDir $OutName
    Copy-Item -Force $apk $dest
    Write-Host "Copied → $dest"
}

Write-Host "OK: $apk"
Write-Host "Install: adb install -r `"$apk`""
