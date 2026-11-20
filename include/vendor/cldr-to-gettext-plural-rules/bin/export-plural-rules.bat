@echo off

if "%PHPBIN%" == "" set PHPBIN=@php_bin@
if not exist "%PHPBIN%" set PHPBIN=%PHP_PEAR_PHP_BIN%
if not exist "%PHPBIN%" set PHPBIN=php
set MY_BIN_FOLDER=@bin_dir@
if not exist "%MY_BIN_FOLDER%" set MY_BIN_FOLDER=%~dp0
"%PHPBIN%" "%MY_BIN_FOLDER%\export-plural-rules" %*
