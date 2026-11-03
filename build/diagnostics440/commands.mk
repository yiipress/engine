# Diagnostic branch only. Run from an actual baseline/current checkout.
COMPOSE = docker compose -f docker/compose.yml -f docker/dev/compose.yml -f runtime/runner-diagnostics/image.yml

.PHONY: diagnostic-image diagnostic-image-id diagnostic-config diagnostic-runtime diagnostic-profile diagnostic-stop

diagnostic-image:
	docker build --target dev --build-arg USER_ID=$(shell id -u) --build-arg GROUP_ID=$(shell id -g) -t $(IMAGE) -f docker/Dockerfile .

diagnostic-image-id:
	docker image inspect --format '{{.Id}}' $(IMAGE)

diagnostic-config:
	$(COMPOSE) config --format json

diagnostic-run-help:
	$(COMPOSE) run --help

diagnostic-runtime:
	$(COMPOSE) run --rm --pull never -e XDEBUG_MODE=off app php runtime/runner-diagnostics/runtime.php

diagnostic-profile:
	$(COMPOSE) run --rm --pull never --name $(CONTAINER) -e XDEBUG_MODE=profile -e PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/app/runtime/runner-diagnostics/$(PHASE)-ini app php runtime/runner-diagnostics/$(PHASE)-child.php

diagnostic-stop:
	docker container rm --force $(CONTAINER)

# Static preparation check only, with an existing image and no network or image build.
diagnostic-lint:
	docker run --rm --network none -e XDEBUG_MODE=off -v $(CURDIR):/app:ro -w /app $(IMAGE) php -l build/diagnostics440/runtime.php build/diagnostics440/asset-child.php build/diagnostics440/feed-child.php

diagnostic-style:
	docker run --rm --network none -e XDEBUG_MODE=off -v $(CURDIR):/app:ro -v $(TOOLS_VENDOR):/tools/vendor:ro -w /app $(IMAGE) php /tools/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --using-cache=no --sequential --diff build/diagnostics440

diagnostic-static:
	docker run --rm --network none -e XDEBUG_MODE=off -v $(CURDIR):/app:ro -v $(TOOLS_VENDOR):/app/vendor:ro -w /app $(IMAGE) php vendor/bin/phpstan analyse --configuration=build/diagnostics440/phpstan.neon --memory-limit=1G --no-progress
