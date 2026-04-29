#!/bin/sh
set -e

# Rendre les fichiers montés lisibles par www-data
# (2>/dev/null : les volumes :ro ne peuvent pas être chmod-és, c'est normal)
chmod -R a+rX /var/www/html 2>/dev/null || true

exec apache2-foreground "$@"
