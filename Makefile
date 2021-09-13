.PHONY: install-latest install-lowest test test-coverage

COMPOSER=composer --no-interaction

install-latest:
	${COMPOSER} update --prefer-stable --ignore-platform-reqs

install-lowest:
	${COMPOSER} update --prefer-lowest --ignore-platform-reqs

test:
	${COMPOSER} validate --strict --no-check-lock
	vendor/bin/phpcs
	vendor/bin/phpstan analyse
	vendor/bin/phpunit

test-coverage:
	phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --coverage-html coverage
