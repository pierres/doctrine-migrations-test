.EXPORT_ALL_VARIABLES:
.PHONY: install-latest install-lowest test test-coverage

UID!=id -u
GID!=id -g
COMPOSE=UID=${UID} GID=${GID} docker-compose -f docker/docker-compose.yml
COMPOSE-RUN=${COMPOSE} run --rm -u ${UID}:${GID}
PHP-RUN=${COMPOSE-RUN} php
COMPOSER=${PHP-RUN} composer --no-interaction

install-latest:
	${COMPOSER} update --prefer-stable

install-lowest:
	${COMPOSER} update --prefer-lowest

test:
	${COMPOSER} validate --strict --no-check-lock
	${PHP-RUN} vendor/bin/phpcs
	${PHP-RUN} vendor/bin/phpstan analyse
	${PHP-RUN} vendor/bin/phpunit

test-coverage:
	${PHP-RUN} phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --coverage-html coverage
