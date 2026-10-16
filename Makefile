.PHONY: build install shell validate lint test test-coverage ci audit fixtures fetch-up fetch-down live-check source-matrix source-matrix-image bench canary snapshots

DOCKER_COMPOSE ?= docker compose
PHP = $(DOCKER_COMPOSE) run --rm php
NODE = $(DOCKER_COMPOSE) --profile fetch run --rm node
SOURCE_MATRIX_IMAGE ?= jooservices/crawlerx:php85

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

source-matrix-image:
	@docker image inspect $(SOURCE_MATRIX_IMAGE) >/dev/null 2>&1 || docker build --tag $(SOURCE_MATRIX_IMAGE) --file Dockerfile .

source-matrix: source-matrix-image
	@set -eu; \
	COMPOSE_PROJECT_NAME="crawlerx-source-matrix-$$$$"; \
	CRAWLERX_NETWORK_NAME="$$COMPOSE_PROJECT_NAME"; \
	CRAWLERX_NETWORK_SUBNET="10.252.$$(($$$$ % 256)).0/24"; \
	BUILDX_CONFIG="$(CURDIR)/.tmp/buildx-$$$$"; \
	export COMPOSE_PROJECT_NAME CRAWLERX_NETWORK_NAME CRAWLERX_NETWORK_SUBNET BUILDX_CONFIG PLAYWRIGHT_HOST_PORT=0 FLARESOLVERR_HOST_PORT=0; \
	mkdir -p "$$BUILDX_CONFIG"; \
	trap '$(DOCKER_COMPOSE) --env-file /dev/null --profile fetch down --remove-orphans; rm -rf "$$BUILDX_CONFIG"; rmdir "$(CURDIR)/.tmp" 2>/dev/null || true' EXIT; \
	$(DOCKER_COMPOSE) --env-file /dev/null --profile fetch up -d --build --no-deps --wait node flaresolverr; \
	MATRIX_HOST_CPU_COUNT="$$(getconf _NPROCESSORS_ONLN)"; \
	while :; do \
	  MATRIX_HOST_LOAD_START="$$(uptime | sed -E 's/^.*load averages?: //; s/,/ /g; s/^[[:space:]]+//; s/[[:space:]]+$$//')"; \
	  MATRIX_ONE_MINUTE_LOAD="$$(printf '%s\n' "$$MATRIX_HOST_LOAD_START" | awk '{print $$1}')"; \
	  if awk -v load="$$MATRIX_ONE_MINUTE_LOAD" -v cpus="$$MATRIX_HOST_CPU_COUNT" 'BEGIN { if (load !~ /^[0-9]+([.][0-9]+)?$$/ || cpus !~ /^[0-9]+$$/) exit 1; exit !(load < cpus) }'; then break; fi; \
	  printf 'Waiting: 1-minute load %s is not below CPU count %s.\n' "$$MATRIX_ONE_MINUTE_LOAD" "$$MATRIX_HOST_CPU_COUNT" >&2; \
	  sleep 30; \
	done; \
	MATRIX_STARTED_AT_UTC="$$(date -u +%Y-%m-%dT%H:%M:%SZ)"; \
	MATRIX_TOOL_GIT_SHA="$$(git rev-parse HEAD)"; \
	docker run --rm --network "$$CRAWLERX_NETWORK_NAME" \
	  --volume "$(CURDIR):/app" --workdir /app \
	  --env "CRAWLERX_MATRIX_STARTED_AT_UTC=$$MATRIX_STARTED_AT_UTC" \
	  --env "CRAWLERX_MATRIX_TOOL_GIT_SHA=$$MATRIX_TOOL_GIT_SHA" \
	  --env "CRAWLERX_MATRIX_HOST_CPU_COUNT=$$MATRIX_HOST_CPU_COUNT" \
	  --env "CRAWLERX_MATRIX_HOST_LOAD_START=$$MATRIX_HOST_LOAD_START" \
	  --env FLARESOLVERR_URL=http://flaresolverr:8191/v1 \
	  --env PLAYWRIGHT_URL=http://node:3000 \
	  $(SOURCE_MATRIX_IMAGE) php tools/source-matrix.php $(if $(SITE),--site=$(SITE),); \
	MATRIX_ENDED_AT_UTC="$$(date -u +%Y-%m-%dT%H:%M:%SZ)"; \
	MATRIX_HOST_LOAD_END="$$(uptime | sed -E 's/^.*load averages?: //; s/,/ /g; s/^[[:space:]]+//; s/[[:space:]]+$$//')"; \
	docker run --rm --network "$$CRAWLERX_NETWORK_NAME" \
	  --volume "$(CURDIR):/app" --workdir /app \
	  --env "CRAWLERX_MATRIX_ENDED_AT_UTC=$$MATRIX_ENDED_AT_UTC" \
	  --env "CRAWLERX_MATRIX_HOST_LOAD_END=$$MATRIX_HOST_LOAD_END" \
	  $(SOURCE_MATRIX_IMAGE) php tools/source-matrix.php --finalize-report

bench: fetch-up
	node tools/benchmark/run.mjs $(if $(METHODS),--methods=$(METHODS),) $(if $(COUNT),--count=$(COUNT),)

canary: fetch-up
	node tools/canary/run.mjs $(if $(SITE),--site=$(SITE),)

snapshots:
	node tools/fixtures/capture.mjs --manifest $(if $(SITE),--site=$(SITE),)

ci:
	$(PHP) composer ci
