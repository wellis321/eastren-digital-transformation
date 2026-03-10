#!/bin/bash
# Start PHP dev server on port 8001
# Run from project root.
# Open: http://localhost:8001/reports.php or http://localhost:8001/public/
cd "$(dirname "$0")"
php -S localhost:8001 router.php
