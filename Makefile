.PHONY: build install shell validate lint test test-coverage ci audit fixtures fetch-up fetch-down live-check bench canary snapshots

DOCKER_COMPOSE ?= docker compose
PHP = $(DOCKER_COMPOSE) run --rm php
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

live-check: fetch-up
	$(DOCKER_COMPOSE) run --rm php php tools/live-check.php $(SITE)

bench: fetch-up
	node tools/benchmark/run.mjs $(if $(METHODS),--methods=$(METHODS),) $(if $(COUNT),--count=$(COUNT),)

canary: fetch-up
	node tools/canary/run.mjs $(if $(SITE),--site=$(SITE),)

snapshots:
	node tools/fixtures/capture.mjs --manifest $(if $(SITE),--site=$(SITE),)

ci:
	$(PHP) composer ci
