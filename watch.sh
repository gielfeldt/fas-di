#!/bin/bash

while true; do

    composer update

    find /app/src/ -type f -name '*.php' -exec php -l {} \; | (! grep -v "No syntax errors detected" )
    vendor/bin/phpcs --standard=psr12 src
    vendor/bin/phpmd /app/src text cleancode,codesize,controversial,design,naming,unusedcode
    XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html /app/coverage --whitelist src/ tests/

    inotifywait -r --event modify,create,delete,move src/ tests/ composer.lock composer.json || exit 1
done