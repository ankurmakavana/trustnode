#!/bin/sh
set -e

# Ensure Laravel storage directories exist for fresh named volumes
mkdir -p storage/framework/views \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/logs

# Execute the main command
exec "$@"
