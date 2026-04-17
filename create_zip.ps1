$src = 'c:\xampp\htdocs\tudu_haute_couture_tech'
$zipPath = 'c:\xampp\htdocs\tudu_haute_couture_tech\tudu_deploy.zip'

# Remove old zip if exists
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

# The API files will be included dynamically below using Get-ChildItem

# Root files to include (no debug/migration files)
$rootFiles = @(
    'config.php',
    'csrf.php',
    'db_credentials.php',
    'index.php',
    '.htaccess',
    'manifest.json',
    'sw.js',
    'public_task.php',
    'agregar_nota.php',
    'public_upload.php',
    'tudu-logo-transparent.png',
    'cbor.php',
    'update_production_db_v10.php',
    'debug_proyectos_prod.php',
    'tudu_v10_master.sql',
    '.env.example'
)

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

function AddFileToZip($zip, $filePath, $entryName) {
    if (Test-Path $filePath) {
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $filePath, $entryName, 'Optimal') | Out-Null
        Write-Host "  + $entryName"
    }
    else {
        Write-Host "  MISSING: $entryName"
    }
}

Write-Host "=== Adding root files ==="
foreach ($f in $rootFiles) {
    AddFileToZip $zip "$src\$f" $f
}

Write-Host "=== Adding API files dynamically ==="
$apiPath = "$src\api"
if (Test-Path $apiPath) {
    Get-ChildItem -Path $apiPath -File | Where-Object {
        # Include all files EXCEPT debug, test, logs, composer, and migrations
        $name = $_.Name
        -not ($name -match '^debug_') -and
        -not ($name -match '^test_') -and
        -not ($name -like '*.log') -and
        -not ($name -like 'composer.*') -and
        -not ($name -like '*.sql') -and
        -not ($name -match '^fix_')
    } | ForEach-Object {
        $relative = $_.FullName.Substring($apiPath.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "api/$relative", 'Optimal') | Out-Null
        Write-Host "  + api/$relative"
    }
}

Write-Host "=== Adding frontend/dist ==="
$distPath = "$src\frontend\dist"
if (Test-Path $distPath) {
    Get-ChildItem -Path $distPath -Recurse -File | ForEach-Object {
        $relative = $_.FullName.Substring($distPath.Length + 1).Replace('\', '/')
        $entryName = "frontend/dist/$relative"
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName, 'Optimal') | Out-Null
        Write-Host "  + $entryName"
    }
}
else {
    Write-Host "  ERROR: frontend/dist not found! Run npm run build first."
}

Write-Host "=== Adding icons ==="
$iconsPath = "$src\icons"
if (Test-Path $iconsPath) {
    Get-ChildItem -Path $iconsPath -Recurse -File | ForEach-Object {
        $relative = $_.FullName.Substring($iconsPath.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "icons/$relative", 'Optimal') | Out-Null
    }
    Write-Host "  + icons/ folder"
}

Write-Host "=== Adding API Vendor ==="
$vendorPath = "$src\api\vendor"
if (Test-Path $vendorPath) {
    Get-ChildItem -Path $vendorPath -Recurse -File | ForEach-Object {
        $relative = $_.FullName.Substring($vendorPath.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "api/vendor/$relative", 'Optimal') | Out-Null
    }
    Write-Host "  + api/vendor/ folder"
}

$zip.Dispose()

$sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
Write-Host ""
Write-Host "==================================="
Write-Host "ZIP creado exitosamente!"
Write-Host "Ruta: $zipPath"
Write-Host "Tamano: $sizeMB MB"
Write-Host "==================================="
