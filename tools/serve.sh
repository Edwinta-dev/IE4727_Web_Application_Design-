#!/usr/bin/env bash

# Testing only. Production is XAMPP/Apache.
set -e
echo "Serving clinic portal at http://localhost:8000"
php -S localhost:8000 -t clinic-base
