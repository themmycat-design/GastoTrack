@echo off
setlocal

set "GASTOTRACK_PHP=%~1"
if not defined GASTOTRACK_PHP if exist "%USERPROFILE%\.config\herd\bin\php84\php.exe" set "GASTOTRACK_PHP=%USERPROFILE%\.config\herd\bin\php84\php.exe"
if not defined GASTOTRACK_PHP for %%I in (php.exe) do set "GASTOTRACK_PHP=%%~$PATH:I"

if not defined GASTOTRACK_PHP (
    echo PHP was not found. Pass the full path to your PHP 8.4 executable as the first argument.
    exit /b 1
)
if not exist "%GASTOTRACK_PHP%" (
    echo PHP was not found at the selected path.
    exit /b 1
)

"%GASTOTRACK_PHP%" -r "exit(version_compare(PHP_VERSION, '8.4.1', '>=') ? 0 : 1);"
if errorlevel 1 (
    echo GastoTrack requires PHP 8.4.1 or newer. Pass the full path to PHP 8.4 as the first argument.
    exit /b 1
)

set "GASTOTRACK_WEB=%~dp0..\web"
set "GASTOTRACK_ROUTER=%GASTOTRACK_WEB%\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
if not exist "%GASTOTRACK_ROUTER%" (
    echo Laravel dependencies are missing. Install them in the web directory first.
    exit /b 1
)

pushd "%GASTOTRACK_WEB%\public" || exit /b 1
echo Starting GastoTrack on 127.0.0.1:8000 with support for 5 MB photos. Press Ctrl+C to stop.
"%GASTOTRACK_PHP%" -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8000 "%GASTOTRACK_ROUTER%"
set "GASTOTRACK_EXIT=%ERRORLEVEL%"
popd
exit /b %GASTOTRACK_EXIT%
