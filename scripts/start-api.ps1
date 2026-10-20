param(
    [string]$PhpPath,
    [ValidateRange(1, 65535)][int]$Port = 8000
)
$ErrorActionPreference = 'Stop'
if (!$PhpPath) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) {
        $PhpPath = $phpCommand.Source
    } else {
        $PhpPath = Join-Path $env:USERPROFILE '.config\herd\bin\php84\php.exe'
    }
}
if (!(Test-Path -LiteralPath $PhpPath)) {
    throw 'PHP was not found. Run this script with -PhpPath pointing to your PHP 8.4 executable.'
}
$webPath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\web'))
$router = Join-Path $webPath 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
if (!(Test-Path -LiteralPath $router)) { throw 'Install the Laravel dependencies first.' }
Push-Location (Join-Path $webPath 'public')
try {
    Write-Host "Starting the API on localhost:$Port with support for 5 MB photos. Press Ctrl+C to stop."
    & $PhpPath -d upload_max_filesize=6M -d post_max_size=8M -S "127.0.0.1:$Port" $router
} finally {
    Pop-Location
}
