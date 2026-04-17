# Build Production Package Script
# This script automates the process of building the frontend and packaging it with the backend for production deployment.

$ErrorActionPreference = "Stop"

Write-Host "Starting Production Build Process..." -ForegroundColor Cyan

# 1. Build Frontend
Write-Host "Building Frontend..." -ForegroundColor Yellow
Set-Location "frontend"
try {
    npm install
    npm run build
} catch {
    Write-Error "Frontend build failed: $_"
}
Set-Location ..

# 2. Create Production Package Directory
$packageDir = "production_package"
if (Test-Path $packageDir) {
    Write-Host "Cleaning existing package directory..." -ForegroundColor Yellow
    Remove-Item $packageDir -Recurse -Force
}
New-Item -ItemType Directory -Path $packageDir | Out-Null

# 3. Copy Backend Files
Write-Host "Copying Backend Files..." -ForegroundColor Yellow
$backendFiles = @(
    "api",
    "assets", # PHP assets if any, though usually empty or specific
    "uploads", # Should be created empty if not exists
    "*.php",
    ".htaccess"
)

foreach ($item in $backendFiles) {
    if (Test-Path $item) {
        Copy-Item -Path $item -Destination $packageDir -Recurse -Force
    }
}

# 4. Copy Frontend Build Artifacts
Write-Host "Copying Frontend Build..." -ForegroundColor Yellow
$frontendDist = "frontend/dist"
if (Test-Path $frontendDist) {
    Copy-Item -Path "$frontendDist/*" -Destination $packageDir -Recurse -Force
} else {
    Write-Error "Frontend build directory not found!"
}

# 5. Cleanup / Exclude Dev Files from Package if copied by wildcard
# (Already handled by specific includes, but double check)
$exclude = @(
    "tests",
    "node_modules",
    ".git",
    "frontend" # We only want the build output, not the source
)

# 6. Create Zip (Optional but requested "compression" context)
# Check if 7z or Compress-Archive is available
$zipFile = "tudu_production.zip"
if (Test-Path $zipFile) {
    Remove-Item $zipFile -Force
}

Write-Host "Creating Zip Archive..." -ForegroundColor Yellow
Compress-Archive -Path "$packageDir/*" -DestinationPath $zipFile -Force

Write-Host "Build Complete! Package available in '$packageDir' and '$zipFile'" -ForegroundColor Green
