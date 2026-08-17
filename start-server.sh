#!/bin/bash
echo "Starting TAPP on http://localhost:8000"
echo "Open: http://localhost:8000/login.php"
echo
cd "$(dirname "$0")"
php -S localhost:8000 router.php
