help:
	@echo "Please use 'make <target>' where <target> is one of"
	@echo "  test         to perform unit tests."
	@echo "  phpstan      to run phpstan on the codebase."
	@echo "  psalm        to run psalm on the codebase."
	@echo "  fixer-fix    to run php-cs-fixer on the codebase, writing the changes."
	@echo "  fixer-check  to run php-cs-fixer on the codebase, just check."

test: 
	php vendor/bin/phpunit

psalm:
	php vendor/bin/psalm

phpstan:
	php vendor/bin/phpstan

fixer-fix:
	php vendor/bin/php-cs-fixer fix --diff

fixer-check:
	php vendor/bin/php-cs-fixer fix --diff --dry-run