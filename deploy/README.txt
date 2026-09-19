=== DEPLOY TOOL ===

This tool tracks file changes and uploads them via FTP.

USAGE:

1) First run - create a baseline snapshot:
   double-click changes.bat

2) After editing files, run again to see what changed:
   double-click changes.bat

3) Upload changed files to hosting:
   changes.bat -Upload -FtpHost yourdomain.com -FtpUser youruser -FtpPass yourpass -RemotePath /public_html

   Or save credentials once:
   changes.bat -SaveCreds -FtpHost yourdomain.com -FtpUser youruser -FtpPass yourpass -RemotePath /public_html
   Then next time just: changes.bat -Upload

WHAT IT TRACKS:
- All PHP, CSS, JS, SQL, JSON files (excludes vendor/, node_modules/, storage/)
- Creates deploy/file_hashes.json as a baseline
- Compares MD5 hashes to detect changes

FILES TO UPLOAD (common):
- app/Controllers/*.php
- app/Services/*.php
- resources/views/**/*.php
- public/assets/**/*
- routes/web.php
- database/**/*.sql
