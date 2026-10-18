.PHONY: build install shell validate lint test test-coverage ci audit fixtures fetch-up fetch-down live-check source-matrix bench canary snapshots

-include .env

DOCKER_COMPOSE ?= docker compose
# How PHP is run: CRAWLERX_PHP from .env (for example the Workspaces shared runtime), else the project's own Docker.
PHP ?= $(if $(CRAWLERX_PHP),$(CRAWLERX_PHP),$(DOCKER_COMPOSE) run --rm php)
NODE = $(DOCKER_COMPOSE) --profile fetch run --rm node

build:
	$(DOCKER_COMPOSE) build

install: build
	$(PHP) composer install

shell:
	$(DOCKER_COMPOSE) run --rm php bash

validate:
	$(PHP) composer validate --strict

lint:
	$(PHP) composer lint

test:
	$(PHP) composer test

test-coverage:
	$(PHP) composer test:coverage

audit:
	$(PHP) composer audit

fixtures:
	npm install
	npx playwright install chromium
	node tools/fixtures/capture.mjs --manifest

fetch-up:
	$(DOCKER_COMPOSE) --profile fetch up -d --wait node flaresolverr

fetch-down:
	$(DOCKER_COMPOSE) --profile fetch down

# Live tools use the service addresses from .env (PLAYWRIGHT_URL, FLARESOLVERR_URL). They start nothing.
# Own Docker services: run `make fetch-up` first and use the Docker values from .env.example.
live-check:
	$(PHP) php tools/live-check.php $(SITE)

source-matrix:
	$(PHP) php tools/source-matrix.php $(if $(SITE),--site=$(SITE),)

bench:
	node tools/benchmark/run.mjs $(if $(METHODS),--methods=$(METHODS),) $(if $(COUNT),--count=$(COUNT),)

canary:
	node tools/canary/run.mjs $(if $(SITE),--site=$(SITE),)

snapshots:
	node tools/fixtures/capture.mjs --manifest $(if $(SITE),--site=$(SITE),)

ci:
	$(PHP) composer ci
