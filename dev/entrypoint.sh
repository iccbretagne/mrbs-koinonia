#!/bin/sh
set -e

# Rendre les fichiers montés lisibles par www-data
chmod -R a+rX /var/www/html

exec apache2-foreground "$@"
