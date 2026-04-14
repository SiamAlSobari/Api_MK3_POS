@echo off
set PHP_PATH=C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe
"%PHP_PATH%" -v
"%PHP_PATH%" composer.phar install
pause
