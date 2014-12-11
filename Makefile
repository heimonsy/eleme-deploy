ctags:
	ctags -R --fields=+aimS --languages=php --php-kinds=cidf --exclude=tests --exclude=composer.phar
requirements:
	@echo "\n--------------> requirements <--------------\n"
	composer install

build: requirements
build-dist: requirements
deploy: requirements
