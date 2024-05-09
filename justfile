COMPOSER := 'composer --no-interaction'

install-latest:
	{{COMPOSER}} update --prefer-stable

install-lowest:
	{{COMPOSER}} update --prefer-lowest

test:
	{{COMPOSER}} validate --strict --no-check-lock
	vendor/bin/phpcs
	vendor/bin/phpstan analyse
	vendor/bin/phpunit

test-coverage:
	php -d zend_extension=xdebug -d xdebug.mode=coverage -d memory_limit=-1 vendor/bin/phpunit --coverage-html coverage
