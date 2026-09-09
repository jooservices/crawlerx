.PHONY: build install shell validate lint test test-coverage ci audit fixtures fetch-up fetch-down live-check

DOCKER_COMPOSE ?= docker compose
# Workspace runtime policy: use the host PHP toolchain when its version
# matches the project requirement (^8.5); fall back to Docker otherwise.
HOST_PHP_MATCHES := $(shell php -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") && version_compare(PHP_VERSION, "8.6.0", "<") ? 0 : 1);' >/dev/null 2>&1 && echo yes || echo no)

ifeq ($(HOST_PHP_MATCHES),yes)
COMPOSER := composer
else
COMPOSER := $(DOCKER_COMPOSE) run --rm php composer
endif

NODE = $(DOCKER_COMPOSE) --profile fetch run --rm node

build:
ifeq ($(HOST_PHP_MATCHES),yes)
	@echo "Host PHP matches ^8.5 ($(shell php -r 'echo PHP_VERSION;')); skipping Docker image build."
else
	$(DOCKER_COMPOSE) build
endif

install: build
	$(COMPOSER) install

shell:
	$(DOCKER_COMPOSE) run --rm php bash

validate:
	$(COMPOSER) validate --strict

lint:
	$(COMPOSER) lint

test:
	$(COMPOSER) test

test-coverage:
	$(COMPOSER) test:coverage

audit:
	$(COMPOSER) audit

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

ci:
	$(COMPOSER) ci
