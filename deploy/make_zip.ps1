Add-Type -Assembly 'System.IO.Compression.FileSystem'

$source = "C:\laragon\www\DSR\backend"
$dest   = "C:\laragon\www\DSR\deploy\backend.zip"

$excludeDirs = @("node_modules", ".git", "vendor")
$excludeFiles = @(".env")

if (Test-Path $dest) { Remove-Item $dest -Force }

$zip = [System.IO.Compression.ZipFile]::Open($dest, 'Create')

$files = Get-ChildItem -Path $source -Recurse -File | Where-Object {
    $rel = $_.FullName.Substring($source.Length + 1)
    $skip = $false
    foreach ($ex in $excludeDirs) {
        if ($rel.StartsWith($ex + "\")) { $skip = $true; break }
    }
    if ($excludeFiles -contains $_.Name) { $skip = $true }
    if ($rel -like "storage\logs\*.log") { $skip = $true }
    -not $skip
}

Write-Host "Zipping $($files.Count) files..."
$i = 0
foreach ($file in $files) {
    $entryName = "backend\" + $file.FullName.Substring($source.Length + 1)
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, 'Optimal') | Out-Null
    $i++
    if ($i % 500 -eq 0) { Write-Host "  $i / $($files.Count)..." }
}

$zip.Dispose()
Write-Host ("Done. Size: " + [math]::Round((Get-Item $dest).Length / 1MB, 1) + " MB")
