@echo off
REM ⚡ THREVOLT — Fast local dev server (Windows)
REM Same as serve-fast.sh but for CMD / double-click.
REM Usage: serve-fast.bat [port] [host]   (default port 8000, host 0.0.0.0)
REM Host 0.0.0.0 makes the API reachable from phones on the same Wi-Fi
REM (needed by the Android app) and from localhost.
REM
REM NOTE: no PHP_CLI_SERVER_WORKERS - unreliable on Windows (workers fail to
REM load the router script / hang). OPcache alone is ~4x faster per request.
REM For true parallel processing use XAMPP Apache + PHP instead.
setlocal
cd /d "%~dp0"

set "PORT=8000"
if not "%1"=="" set "PORT=%1"
set "HOST=0.0.0.0"
if not "%2"=="" set "HOST=%2"

set "OPCACHE_DLL=C:\xampp\php\ext\php_opcache.dll"
if not exist "%OPCACHE_DLL%" set "OPCACHE_DLL="

REM Absolute router path - relative paths break with the built-in server.
set "ROUTER=%~dp0server.php"

echo [THREVOLT] Starting API on http://%HOST%:%PORT%  (OPcache)
if defined OPCACHE_DLL (
  echo [THREVOLT] OPcache ON
  cd public
  php -d "zend_extension=%OPCACHE_DLL%" -d opcache.enable_cli=1 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=2 -S %HOST%:%PORT% "%ROUTER%"
) else (
  echo [THREVOLT] WARNING: OPcache DLL not found - serving slower
  cd public
  php -S %HOST%:%PORT% "%ROUTER%"
)
