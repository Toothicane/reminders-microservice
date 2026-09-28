.PHONY: start stop test lint

start:
	docker compose up --build -d

stop:
	docker compose down

test:
	docker compose exec app php bin/phpunit

lint:
	docker compose exec app composer lint:cs && docker compose exec app composer lint:types

