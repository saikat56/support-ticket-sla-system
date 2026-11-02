.PHONY: setup up down restart build logs logs-queue logs-scheduler exec migrate seed fresh-seed test check-sla composer-install

setup:
	./bin/setup.sh

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose up -d --build

composer-install:
	docker compose exec app composer install

logs:
	docker compose logs -f

logs-queue:
	docker compose logs -f queue

logs-scheduler:
	docker compose logs -f scheduler

exec:
	docker compose exec app bash

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh-seed:
	docker compose exec app php artisan migrate:fresh --seed

test:
	docker compose exec app php artisan test

check-sla:
	docker compose exec app php artisan tickets:check-sla
