phpunit: ./vendor
	./vendor/bin/phpunit --configuration phpunit.xml.dist

phpstan: ./vendor
	./vendor/phpstan/phpstan/phpstan analyse -vvv --error-format=raw -c phpstan.neon.dist
