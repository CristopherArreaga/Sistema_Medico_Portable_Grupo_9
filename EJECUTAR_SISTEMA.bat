@echo off
title Sistema Medico Portable mejorado del Grupo 9
set "PHP_BIN=%~dp0php\php.exe"
set "PHP_INI=%~dp0php\php.ini"

echo ---------------------------------------------------
echo INICIANDO SISTEMA CON DRIVER SQLITE
echo ---------------------------------------------------

start "" cmd /c "timeout /t 2 /nobreak >nul && start http://127.0.0.1:8080"
"%PHP_BIN%" -c "%PHP_INI%" -S 127.0.0.1:8080
pause