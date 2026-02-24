migrate:
	docker-compose exec php-api php artisan migrate

seed:
	docker-compose exec php-api php artisan db:seed

test:
	docker-compose exec php-api phpunit
	docker-compose exec python-worker pytest

up:
	docker-compose up -d

down:
	docker-compose down
