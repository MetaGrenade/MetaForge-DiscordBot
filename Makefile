.PHONY: setup setup-check bot-install bot-dev bot-build bot-start docker-up docker-down test

setup: bot-install setup-check
	@echo "Ready: configure bot/.env and MetaForge .env, then npm run dev (bot) and php artisan discord:ping (app)."

setup-check:
	@bash scripts/setup-check.sh all

setup-check-bot:
	@bash scripts/setup-check.sh bot

bot-install:
	cd bot && npm ci

bot-dev:
	cd bot && npm run dev

bot-build:
	cd bot && npm run build

bot-start:
	cd bot && npm start

docker-up:
	docker compose up --build -d

docker-down:
	docker compose down

test:
	cd laravel && composer test
	cd bot && npm run typecheck
