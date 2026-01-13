PORT ?= 8000
start:
	php -S 0.0.0.0:$(PORT) -t public public/index.php

lint:
	composer validate --strict
	composer exec --verbose phpcs -- --standard=PSR12 src tests public
	composer exec --verbose phpstan analyze

lint-fix:
	composer exec --verbose phpcbf -- --standard=PSR12 src tests public

test:
	composer exec --verbose phpunit tests

test-coverage:
	XDEBUG_MODE=coverage composer exec --verbose phpunit tests -- --coverage-clover=build/logs/clover.xml

test-coverage-local:
	XDEBUG_MODE=coverage composer exec --verbose phpunit tests -- --coverage-text --coverage-html var/coverage-html

check-dockerfile:
	docker run --rm -i ghcr.io/hadolint/hadolint < Dockerfile