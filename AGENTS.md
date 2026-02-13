- Stick to Yii3 and PHP best practices.
- Performance should be exceptional.
- Refer to [README.md](README.md) and [/docs](docs) directory for documentation. If it could be improved or any feature is not documented yet, please change documentation.
- Refer to [roadmap.md](roadmap.md) for planned features and priorities. When suggesting or implementing a new feature, check the roadmap first. If the feature is listed, check its checkbox upon completion. If suggesting a new feature not yet on the roadmap, add it to the appropriate priority section in `roadmap.md`.
- Always align to [/docs/architecture.md](docs/architecture.md) for architecture and principles.
- For each piece of code add a test using phpunit.
- For each significant feature add benchmark using phpbench.
- For each feature add documentation.
- Do not care about running locally. Consider Docker-only environment.
- Use features of PHP version specified in `composer.json`.


# Running commands and testing website

Consider current DEV_PORT from `.env` file when testing website.

Use `make` commands to run commands and test website.
Do not try to run docker or php or composer commands directly.
