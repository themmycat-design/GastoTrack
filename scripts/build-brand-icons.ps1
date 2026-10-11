param(
    [string]$Source = (Join-Path (Split-Path -Parent $PSScriptRoot) 'assets\brand\gastotrack-logo.png')
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$projectRoot = Split-Path -Parent $PSScriptRoot
$sourceImage = [System.Drawing.Image]::FromFile($Source)

function Save-BrandIcon {
    param(
        [int]$Size,
        [string]$Destination,
        [ValidateSet('rounded', 'round', 'square')][string]$Shape
    )

    $pixelFormat = if ($Shape -eq 'square') {
        [System.Drawing.Imaging.PixelFormat]::Format24bppRgb
    } else {
        [System.Drawing.Imaging.PixelFormat]::Format32bppArgb
    }
    $bitmap = [System.Drawing.Bitmap]::new($Size, $Size, $pixelFormat)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $brush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::White)
    try {
        $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
        $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $graphics.Clear([System.Drawing.Color]::Transparent)

        if ($Shape -eq 'round') {
            $graphics.FillEllipse($brush, 0, 0, $Size, $Size)
        } elseif ($Shape -eq 'rounded') {
            $radius = [Math]::Max(2, [int]($Size * 0.22))
            $path = [System.Drawing.Drawing2D.GraphicsPath]::new()
            try {
                $path.AddArc(0, 0, $radius * 2, $radius * 2, 180, 90)
                $path.AddArc($Size - $radius * 2, 0, $radius * 2, $radius * 2, 270, 90)
                $path.AddArc($Size - $radius * 2, $Size - $radius * 2, $radius * 2, $radius * 2, 0, 90)
                $path.AddArc(0, $Size - $radius * 2, $radius * 2, $radius * 2, 90, 90)
                $path.CloseFigure()
                $graphics.FillPath($brush, $path)
            } finally {
                $path.Dispose()
            }
        } else {
            $graphics.Clear([System.Drawing.Color]::White)
        }

        $paddingRatio = if ($Shape -eq 'round') { 0.16 } else { 0.12 }
        $padding = [int][Math]::Round($Size * $paddingRatio)
        $graphics.DrawImage($sourceImage, [System.Drawing.Rectangle]::new($padding, $padding, $Size - 2 * $padding, $Size - 2 * $padding))
        $bitmap.Save($Destination, [System.Drawing.Imaging.ImageFormat]::Png)
    } finally {
        $brush.Dispose()
        $graphics.Dispose()
        $bitmap.Dispose()
    }
}

try {
    $androidSizes = [ordered]@{ 'mdpi' = 48; 'hdpi' = 72; 'xhdpi' = 96; 'xxhdpi' = 144; 'xxxhdpi' = 192 }
    foreach ($density in $androidSizes.Keys) {
        $directory = Join-Path $projectRoot "android\app\src\main\res\mipmap-$density"
        Save-BrandIcon -Size $androidSizes[$density] -Destination (Join-Path $directory 'ic_launcher.png') -Shape rounded
        Save-BrandIcon -Size $androidSizes[$density] -Destination (Join-Path $directory 'ic_launcher_round.png') -Shape round
    }

    $iosDirectory = Join-Path $projectRoot 'ios\GastoTrack\Images.xcassets\AppIcon.appiconset'
    $iosIcons = [ordered]@{
        'AppIcon-20@2x.png' = 40
        'AppIcon-20@3x.png' = 60
        'AppIcon-29@2x.png' = 58
        'AppIcon-29@3x.png' = 87
        'AppIcon-40@2x.png' = 80
        'AppIcon-40@3x.png' = 120
        'AppIcon-60@2x.png' = 120
        'AppIcon-60@3x.png' = 180
        'AppIcon-1024.png' = 1024
    }
    foreach ($fileName in $iosIcons.Keys) {
        Save-BrandIcon -Size $iosIcons[$fileName] -Destination (Join-Path $iosDirectory $fileName) -Shape square
    }
} finally {
    $sourceImage.Dispose()
}
