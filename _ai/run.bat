@echo off
REM Helper: run project commands with WAMP PHP 8.3 + MySQL on PATH.
REM Usage: _ai\run.bat php artisan migrate   |   _ai\run.bat composer require pkg
setlocal
set PATH=C:\wamp64\bin\php\php8.3.14;C:\wamp64\bin\mysql\mysql9.1.0\bin;%PATH%
cd /d C:\wamp64\www\ck
if /I "%1"=="composer" (
  shift
  php C:\ProgramData\ComposerSetup\bin\composer.phar %1 %2 %3 %4 %5 %6 %7 %8 %9
) else (
  %*
)
endlocal
