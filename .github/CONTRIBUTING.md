# Contributing to YiiPress

Thank you for helping improve YiiPress.

## Before you start

- Check [the roadmap](../roadmap.md), existing issues, and pull requests.
- Follow the architecture and principles in [docs/architecture.md](../docs/architecture.md).
- Target the PHP version declared in `composer.json` and follow Yii 3 conventions.

## Development

YiiPress development is Docker-only. Use the project `make` targets instead of invoking Docker, PHP, or Composer directly.

```shell
make -- composer install --no-progress --no-interaction
make test
make phpstan
make composer-dependency-analyser
```

Add PHPUnit coverage for code changes, a PHPBench benchmark for significant features, and user-facing documentation where applicable. Automatic CI formatting applies Rector and PHP CS Fixer to writable pull-request branches.

Keep commits focused and explain the behavior and verification in the pull request.
