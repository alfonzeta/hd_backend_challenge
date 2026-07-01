.PHONY: install test stan cs cs-fix check serve

install:
	composer install

test:
	composer test

stan:
	composer stan

cs:
	composer cs

cs-fix:
	composer cs-fix

check: cs stan test

serve:
	php -S localhost:8080 -t public
