requirements:
	@echo "\n--------------> requirements <--------------\n"
	composer install

build: requirements
build-dist: requirements