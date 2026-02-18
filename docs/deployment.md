# Deployment

This document describes how to build and deploy YiiPress to production.

## Docker Production Image

YiiPress includes a production-ready Docker image based on FrankenPHP. The production image is optimized for performance and security:

- Uses PHP 8.5 with FrankenPHP
- Includes all required PHP extensions
- Runs as non-root user (`www-data`)
- Production PHP configuration with OPcache enabled
- Multi-stage build for minimal image size
- No development dependencies

### Building Locally

Build the production Docker image:

```bash
make prod-build
```

This builds the image using the `prod` target from `docker/Dockerfile` and tags it according to the `IMAGE` and `IMAGE_TAG` variables in `docker/.env`.

Push the image to a registry:

```bash
make prod-push
```

### Automated CI/CD with GitHub Actions

The repository includes a GitHub Actions workflow (`.github/workflows/docker-build.yml`) that automatically builds and publishes the production Docker image to GitHub Container Registry (ghcr.io).

**Triggers:**
- Push to `master` branch - builds and pushes with `latest` tag
- Push tags matching `*.*.*` (e.g., `1.0.0`) - builds and pushes with semantic version tags
- Pull requests - builds only (does not push)
- Manual workflow dispatch

**Image Tags:**
The workflow automatically creates multiple tags for each build:
- `latest` - for pushes to the master branch
- `1.2.3`, `1.2`, `1` - for semantic version tags

**Registry:**
Images are published to: `ghcr.io/yiipress/engine`

**Permissions:**
The workflow uses `GITHUB_TOKEN` with `packages: write` permission to push images to GitHub Container Registry.

### Using the Published Image

Pull the latest production image:

```bash
docker pull ghcr.io/yiipress/engine:latest
```

Pull a specific version:

```bash
docker pull ghcr.io/yiipress/engine:1.0.0
```

## Production Deployment

### Using Docker Swarm

The repository includes a production compose configuration in `docker/prod/compose.yml` for Docker Swarm deployment.

Deploy to production:

```bash
make prod-deploy
```

This command:
1. Connects to the production Docker Swarm via SSH (configured in `PROD_SSH` in `docker/.env`)
2. Deploys the stack using `docker stack deploy`
3. Uses the image specified in `IMAGE` and `IMAGE_TAG` variables
4. Configures 2 replicas with rolling updates
5. Sets up Caddy reverse proxy with automatic HTTPS

**Configuration:**
Update `docker/.env` with your production settings:
```bash
PROD_HOST=yourdomain.com
PROD_SSH="ssh://your-docker-host"
IMAGE=ghcr.io/yiipress/engine
IMAGE_TAG=v1.0.0
```

### Using Other Platforms

The production Docker image can be deployed to any platform that supports Docker containers:

- **Kubernetes**: Create a deployment using the image
- **Cloud Run**: Deploy directly from the container registry
- **AWS ECS/Fargate**: Use the image in task definitions
- **Azure Container Instances**: Deploy the container image
- **DigitalOcean App Platform**: Connect to the GitHub Container Registry

## Environment Configuration

In production, configure the application using environment variables in `docker/prod/.env`:

```bash
APP_ENV=prod
YII_DEBUG=0
# Add your production configuration here
```

See [Configuration](config.md) for available options.
