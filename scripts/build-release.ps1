# Builds a cPanel-ready release: Laravel backend (prod vendor/) with the Vue
# SPA baked into public/, zipped as saunaspeak-release.zip.
# Run from anywhere:  powershell -File scripts\build-release.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$release = Join-Path $root "release"
$zip = Join-Path $root "saunaspeak-release.zip"

Write-Host "1/5 Building frontend..." -ForegroundColor Cyan
Push-Location (Join-Path $root "frontend")
npm run build
if ($LASTEXITCODE -ne 0) { throw "frontend build failed" }
Pop-Location

Write-Host "2/5 Staging backend..." -ForegroundColor Cyan
if (Test-Path $release) { Remove-Item $release -Recurse -Force }
New-Item -ItemType Directory $release | Out-Null

# Everything Laravel needs at runtime; no tests, no .env, no dev tooling.
$dirs = @("app", "bootstrap", "config", "database", "public", "resources", "routes")
foreach ($d in $dirs) {
  robocopy (Join-Path $root "backend\$d") (Join-Path $release $d) /E /NFL /NDL /NJH /NJS | Out-Null
}
foreach ($f in @("artisan", "composer.json", "composer.lock")) {
  Copy-Item (Join-Path $root "backend\$f") $release
}
# Fresh storage skeleton (never ship local logs/caches).
foreach ($d in @("storage\app\public", "storage\framework\cache\data", "storage\framework\sessions", "storage\framework\views", "storage\logs")) {
  New-Item -ItemType Directory (Join-Path $release $d) -Force | Out-Null
}
# Clear bootstrap caches from dev.
Get-ChildItem (Join-Path $release "bootstrap\cache") -File -ErrorAction SilentlyContinue | Remove-Item -Force

Write-Host "3/5 Installing production vendor/ ..." -ForegroundColor Cyan
Push-Location $release
composer install --no-dev --optimize-autoloader --no-interaction
if ($LASTEXITCODE -ne 0) { throw "composer install failed" }
Pop-Location

Write-Host "4/5 Baking SPA into public/ ..." -ForegroundColor Cyan
robocopy (Join-Path $root "frontend\dist") (Join-Path $release "public") /E /NFL /NDL /NJH /NJS | Out-Null

# Env template the server admin fills in as .env.
Copy-Item (Join-Path $root "scripts\env.production.example") (Join-Path $release ".env.example.production")

Write-Host "5/5 Zipping..." -ForegroundColor Cyan
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path "$release\*" -DestinationPath $zip

$size = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host "Done: saunaspeak-release.zip ($size MB)" -ForegroundColor Green
Write-Host "Next: upload + extract on cPanel, see DEPLOY.md"
