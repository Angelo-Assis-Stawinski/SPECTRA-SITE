@echo off
cd /d "%~dp0"
php -d upload_max_filesize=25M -d post_max_size=30M -d display_errors=0 -S 127.0.0.1:8080 router.php
pause
