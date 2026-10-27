# Developer shortcuts. Run `make help` for the list.
COMPOSE := docker compose
API     := $(COMPOSE) exec api
API_RUN := $(COMPOSE) run --rm --no-deps api

.DEFAULT_GOAL := help
.PHONY: help setup up down restart ps logs sh artisan migrate seed fresh web \
        test test-api test-web lint lint-api lint-web types

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

setup: ## First-time setup: env files, images, dependencies, keys, database
	@[ -f .env ] || cp .env.example .env
	@[ -f apps/api/.env ] || cp apps/api/.env.example apps/api/.env
	@[ -f apps/web/.env ] || cp apps/web/.env.example apps/web/.env
	$(COMPOSE) build
	$(COMPOSE) up -d mysql redis mailpit
	$(API_RUN) composer install --no-interaction
	@grep -q '^APP_KEY=$$' apps/api/.env && $(API_RUN) php artisan key:generate --no-interaction || true
	@perl -pi -e 'BEGIN { chomp($$k = `openssl rand -hex 16`); chomp($$s = `openssl rand -hex 32`); $$i = int(rand(900000)) + 100000 } s/^REVERB_APP_ID=$$/REVERB_APP_ID=$$i/; s/^REVERB_APP_KEY=$$/REVERB_APP_KEY=$$k/; s/^REVERB_APP_SECRET=$$/REVERB_APP_SECRET=$$s/' apps/api/.env
	$(COMPOSE) run --rm api php artisan migrate --seed --no-interaction
	$(COMPOSE) up -d
	cd apps/web && npm ci
	@echo "\nDone. Run 'make web' and open http://localhost:5175"

up: ## Start all backend services
	$(COMPOSE) up -d

down: ## Stop all services (data volumes are kept)
	$(COMPOSE) down

restart: ## Restart backend services (e.g. after .env changes)
	$(COMPOSE) restart api reverb horizon scheduler

ps: ## Show service status
	$(COMPOSE) ps

logs: ## Follow backend logs
	$(COMPOSE) logs -f api nginx reverb horizon scheduler

sh: ## Open a shell in the API container
	$(API) sh

artisan: ## Run an artisan command: make artisan cmd="route:list"
	$(API) php artisan $(cmd)

migrate: ## Run database migrations
	$(API) php artisan migrate

seed: ## Seed reference data (idempotent)
	$(API) php artisan db:seed

fresh: ## DESTRUCTIVE: drop all tables in the dev database, re-migrate and seed
	$(API) php artisan migrate:fresh --seed

web: ## Start the frontend dev server (http://localhost:5175)
	cd apps/web && npm run dev

types: ## Regenerate frontend API types from the running backend's OpenAPI spec
	cd apps/web && npm run api:types

test: test-api test-web ## Run all tests

test-api: ## Run backend tests (MySQL "testing" database)
	$(API) php artisan test

test-web: ## Run frontend tests
	cd apps/web && npm test

lint: lint-api lint-web ## Run all linters and static analysis

lint-api:
	$(API) composer lint
	$(API) composer analyse

lint-web:
	cd apps/web && npm run typecheck && npm run lint && npm run format:check
