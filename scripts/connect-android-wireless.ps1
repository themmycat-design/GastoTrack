param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $Serial
)

$ErrorActionPreference = 'Stop'
$adbCommand = Get-Command adb.exe -ErrorAction SilentlyContinue
if (-not $adbCommand) {
    throw 'adb.exe was not found. Add Android SDK platform-tools to PATH, open a new PowerShell window, then retry.'
}

$deviceOutput = & $adbCommand.Source devices
if ($LASTEXITCODE -ne 0) {
    throw 'Could not list ADB devices. Check Android platform-tools and Wireless debugging connection.'
}

$deviceRecord = $deviceOutput | Where-Object {
    $fields = "$_".Trim() -split '\s+'
    $fields.Count -ge 2 -and $fields[0] -eq $Serial
} | Select-Object -First 1

if (-not $deviceRecord) {
    throw "Device '$Serial' is not listed by adb. Run 'adb devices -l', connect the phone, and pass its exact serial."
}

$recordFields = "$deviceRecord".Trim() -split '\s+'
if ($recordFields[1] -ne 'device') {
    throw "Device '$Serial' is in state '$($recordFields[1])', not 'device'. Unlock the phone and accept the debugging prompt, then retry."
}

foreach ($port in @(8000, 8081)) {
    & $adbCommand.Source -s $Serial reverse "tcp:$port" "tcp:$port"
    if ($LASTEXITCODE -ne 0) {
        throw "Could not reverse port $port for '$Serial'. Confirm this is the wireless phone serial and retry."
    }
}

Write-Host "ADB reverse is ready for $Serial (Laravel 8000, Metro 8081)."
& $adbCommand.Source -s $Serial reverse --list
if ($LASTEXITCODE -ne 0) {
    throw "Port forwarding was set, but ADB could not list mappings for '$Serial'."
}
