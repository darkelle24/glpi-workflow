param([string]$OutputDirectory = (Join-Path $PSScriptRoot '../build'))
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).ProviderPath
[IO.Directory]::CreateDirectory($OutputDirectory) | Out-Null
$outputRoot = (Resolve-Path $OutputDirectory).ProviderPath
$packageRoot = Join-Path ([IO.Path]::GetTempPath()) ('workflowbuilder-package-' + [guid]::NewGuid().ToString('N'))
$pluginRoot = Join-Path $packageRoot 'workflowbuilder'
[IO.Directory]::CreateDirectory($pluginRoot) | Out-Null
$include = @('setup.php','hook.php','bootstrap.php','src','front','migrations','inc','public','examples','locales','docs','deploy','tests','tools','package.json','package-lock.json','playwright.config.cjs','README.md','CHANGELOG.md','LICENSE')
foreach ($entry in $include) {
    $source = Join-Path $projectRoot $entry
    if (-not (Test-Path -LiteralPath $source)) { throw "Required package entry is missing: $entry" }
    Copy-Item -LiteralPath $source -Destination $pluginRoot -Recurse
}
$archive = Join-Path $outputRoot 'workflowbuilder-0.4.0.zip'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream = [IO.File]::Open($archive, [IO.FileMode]::Create)
$zip = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create, $false)
try {
    foreach ($file in Get-ChildItem -LiteralPath $pluginRoot -Recurse -File) {
        # ZIP entries must use forward slashes for extraction in the Linux GLPI container.
        $entryName = 'workflowbuilder/' + $file.FullName.Substring($pluginRoot.Length + 1).Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $zip.Dispose()
    $stream.Dispose()
}
Write-Output $archive
Write-Output ('SHA256 ' + (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant())
