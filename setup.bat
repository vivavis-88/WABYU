@echo off
cd /d "%~dp0"
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" setup.php
) else (
  echo Could not find C:\xampp\php\php.exe. Edit this file if XAMPP is installed elsewhere.
)
pause
