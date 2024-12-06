#!/bin/sh

docker compose exec web ./vendor/friendsofphp/php-cs-fixer/php-cs-fixer fix ./
