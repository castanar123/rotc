@echo off
cd /d "c:\xampp\htdocs\generate qr"
mysql -u root rotc_management < "db\rifle_management_schema.sql"
echo Schema execution completed.
pause