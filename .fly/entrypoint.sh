#!/usr/bin/env sh

# Run user scripts, if they exist
for f in /var/www/html/.fly/scripts/*.sh; do
    # Bail out this loop if any script exits with non-zero status code
    bash "$f" || break
done
chown -R www-data:www-data /var/www/html

# Make sure to run litefs! It will run exec to run our server later
exec litefs mount

