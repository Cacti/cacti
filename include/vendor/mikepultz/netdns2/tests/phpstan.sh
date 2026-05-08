#!/bin/bash

if [ ! -f vendor/autoload.php ]; then
    composer install --working-dir=.. --no-interaction --quiet
fi

phpstan analyse --memory-limit 2G --level=8 -c phpstan.neon
