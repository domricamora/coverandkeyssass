@echo off
set PATH=C:\wamp64\bin\php\php8.3.14;%PATH%
cd /d C:\wamp64\www
if exist ck_build rmdir /s /q ck_build
php C:\ProgramData\ComposerSetup\bin\composer.phar create-project laravel/laravel ck_build --no-interaction --prefer-dist
echo SCAFFOLD_EXIT=%ERRORLEVEL%
