# Dev Flow Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring Thalium's CI/CD and dev tooling into full alignment with bloom and stream-delta — pre-commit, lint CI, Dockerfile, GHCR-based deploys, and automated releases.

**Architecture:** Pre-commit enforces code style locally and in CI. A new Dockerfile builds a self-contained FrankenPHP + Octane image pushed to GHCR. GitHub Actions workflows handle testing, building, staging deploys on PRs, and production deploys on release tags. Releases are created manually or automatically via a weekly Dependabot merge cron.

**Tech Stack:** Laravel 12, FrankenPHP, Laravel Octane, Docker/GHCR, GitHub Actions, pre-commit, Laravel Pint, detect-secrets, actionlint.

---

## File Map

| Action | Path | Purpose |
|---|---|---|
| Modify | `composer.json` | Add `laravel/pint`, `laravel/octane`; remove `friendsofphp/php-cs-fixer` |
| Add | `.pre-commit-config.yaml` | Pre-commit hook definitions |
| Add | `.secrets.baseline` | detect-secrets baseline (generated) |
| Add | `Dockerfile` | Multi-stage production image |
| Add | `docker/entrypoint.sh` | Container startup script |
| Add | `.github/workflows/lint-precommit.yml` | CI lint via pre-commit |
| Add | `.github/workflows/ci.yml` | Tests + build + deploy |
| Add | `.github/workflows/release.yml` | Semver tagging + GitHub release |
| Add | `.github/workflows/dependabot-make-release.yml` | Weekly automated dependency release |
| Modify | `.github/dependabot.yml` | Switch to weekly + `dependabot-updates` branch |
| Modify | `.github/workflows/auto-rebase-dependabot.yml` | Add `REBASE_TOKEN` secret |
| Replace | `.github/workflows/dependabot.yml` | Use shared auto-merge workflow |
| Delete | `.github/workflows/laravel.yml` | Replaced by `ci.yml` |
| Delete | `.github/workflows/devskim.yml` | Replaced by pre-commit hooks |
| Delete | `docker/Dockerfile/` | Replaced by root `Dockerfile` |

---

## Task 1: Pre-commit Setup

**Files:**
- Modify: `composer.json`
- Add: `.pre-commit-config.yaml`
- Add: `.secrets.baseline`

- [ ] **Step 1: Add Pint and Octane to composer.json, remove php-cs-fixer**

Replace the `require-dev` block and `scripts` in `composer.json`:

```json
"require": {
    "php": "^8.4",
    "laravel/framework": "^12.2",
    "laravel/horizon": "^5.30",
    "laravel/octane": "^2.0",
    "laravel/tinker": "^3.0.0",
    "laravel/ui": "^4.6",
    "mailerlite/laravel-elasticsearch": "^11.2",
    "mobiledetect/mobiledetectlib": "^4.8",
    "nunomaduro/collision": "^8.6",
    "squizlabs/php_codesniffer": "^4.0"
},
"require-dev": {
    "barryvdh/laravel-debugbar": "^4.0",
    "fakerphp/faker": "^1.23",
    "laravel/pail": "^1.2.2",
    "laravel/pint": "^1.0",
    "mockery/mockery": "^1.6",
    "phpunit/phpunit": "^11.5.3",
    "spatie/laravel-ignition": "^2.9"
},
```

Add a `lint` script alongside the existing scripts:

```json
"scripts": {
    "lint": "pint",
    "post-autoload-dump": [
        "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
        "@php artisan package:discover --ansi"
    ],
    "post-root-package-install": [
        "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
    ],
    "post-create-project-cmd": [
        "@php artisan key:generate --ansi"
    ],
    "dev": [
        "Composer\\Config::disableProcessTimeout",
        "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite"
    ]
}
```

- [ ] **Step 2: Install the new deps inside Docker**

```bash
./dartisan composer update laravel/pint laravel/octane
./dartisan composer remove --dev friendsofphp/php-cs-fixer
```

Expected: `composer.lock` updated, no errors.

- [ ] **Step 3: Create `.pre-commit-config.yaml`**

```yaml
repos:
  - repo: https://github.com/pre-commit/pre-commit-hooks
    rev: v4.6.0
    hooks:
      - id: trailing-whitespace
      - id: end-of-file-fixer
      - id: check-yaml
      - id: check-added-large-files

  - repo: local
    hooks:
      - id: laravel-pint
        name: Laravel Pint
        entry: vendor/bin/pint
        language: system
        types: [php]
        pass_filenames: true

  - repo: https://github.com/rhysd/actionlint
    rev: v1.7.11
    hooks:
      - id: actionlint

  - repo: https://github.com/Yelp/detect-secrets
    rev: v1.5.0
    hooks:
      - id: detect-secrets
        args: ["--baseline", ".secrets.baseline"]
        exclude: package.lock.json
```

- [ ] **Step 4: Generate the secrets baseline**

Requires `detect-secrets` installed locally (`pip install detect-secrets`).

```bash
detect-secrets scan > .secrets.baseline
```

Review `.secrets.baseline` to confirm no real secrets are listed — if any are, rotate them before committing. Any false positives are expected (env example values, test fixtures).

- [ ] **Step 5: Install pre-commit and verify hooks run**

```bash
pip install pre-commit
pre-commit install
pre-commit run --all-files
```

Expected: hooks run; Pint may reformat some files, trailing-whitespace may fix some lines. Rerun until clean.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock .pre-commit-config.yaml .secrets.baseline
git commit -m "⚙️ Add pre-commit hooks: Pint, actionlint, detect-secrets"
```

---

## Task 2: Lint CI Workflow

**Files:**
- Add: `.github/workflows/lint-precommit.yml`
- Delete: `.github/workflows/devskim.yml`

- [ ] **Step 1: Create `.github/workflows/lint-precommit.yml`**

```yaml
name: "[Lint] Pre-commit"

on:
  push:
    branches: [main]
  pull_request:
    branches: ["**"]
  workflow_dispatch:

permissions:
  contents: read

jobs:
  pre-commit:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v6

      - name: Use Node.js
        uses: actions/setup-node@v6
        with:
          node-version: "${{ vars.node_version }}"
          cache: npm

      - name: Install Node dependencies
        run: npm ci

      - name: Configure PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "${{ vars.php_version }}"
          extensions: redis, xml, cli
          tools: composer

      - name: Get composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> "$GITHUB_OUTPUT"

      - name: Cache Composer dependencies
        uses: actions/cache@v5
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install Composer dependencies
        run: composer install --prefer-dist

      - name: Run pre-commit
        uses: pre-commit/action@v3.0.1
```

- [ ] **Step 2: Verify the workflow file is valid**

```bash
actionlint .github/workflows/lint-precommit.yml
```

Expected: no errors.

- [ ] **Step 3: Delete `devskim.yml`**

```bash
rm .github/workflows/devskim.yml
```

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/lint-precommit.yml .github/workflows/devskim.yml
git commit -m "⚙️ Add lint-precommit CI workflow, remove devskim"
```

---

## Task 3: Dependabot Config and Workflow Updates

**Files:**
- Modify: `.github/dependabot.yml`
- Modify: `.github/workflows/auto-rebase-dependabot.yml`
- Replace: `.github/workflows/dependabot.yml`

- [ ] **Step 1: Update `.github/dependabot.yml` to target `dependabot-updates` weekly**

```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
    target-branch: "dependabot-updates"

  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"
    target-branch: "dependabot-updates"

  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
    target-branch: "dependabot-updates"
```

- [ ] **Step 2: Update `.github/workflows/auto-rebase-dependabot.yml` to pass `REBASE_TOKEN`**

```yaml
name: "[Auto] Rebase dependabot-updates onto main"

on:
  schedule:
    - cron: "0 1 * * *"
  workflow_dispatch:

permissions: {}

jobs:
  rebase:
    uses: istic/shared-workflows/.github/workflows/auto-rebase-dependabot.yml@main
    permissions:
      contents: write
      pull-requests: write
    secrets:
      REBASE_TOKEN: ${{ secrets.REBASE_TOKEN }}
```

- [ ] **Step 3: Replace `.github/workflows/dependabot.yml` with shared auto-merge**

```yaml
name: "[Auto] Merge Dependabot Updates"

on:
  pull_request:
    branches: [dependabot-updates]

permissions: {}

jobs:
  automerge:
    uses: istic/shared-workflows/.github/workflows/auto-merge-dependabot.yml@main
    permissions:
      pull-requests: write
      contents: write
```

- [ ] **Step 4: Validate all three files**

```bash
actionlint .github/dependabot.yml .github/workflows/auto-rebase-dependabot.yml .github/workflows/dependabot.yml
```

Expected: no errors (note: `dependabot.yml` is config not a workflow, actionlint will skip it).

- [ ] **Step 5: Commit**

```bash
git add .github/dependabot.yml .github/workflows/auto-rebase-dependabot.yml .github/workflows/dependabot.yml
git commit -m "⚙️ Switch dependabot to weekly dependabot-updates branch flow"
```

---

## Task 4: Dockerfile and Entrypoint

**Files:**
- Add: `Dockerfile`
- Add: `docker/entrypoint.sh`

**Context:** The existing `docker/Dockerfile/app.Dockerfile` builds from `isticco/thalium_base:v1` (a private base image with Java, Imagick, php-fpm). The new Dockerfile uses `dunglas/frankenphp` as the base, installs the same system deps, and serves the app via Laravel Octane.

PDFBox jar is downloaded at build time from the Apache CDN (matching the existing `docker/pdfbox/install_pdfbox.sh` pattern). The PDFBox binary path `/usr/share/java/pdfbox.jar` is hardcoded in `app/Service/PDFBoxService.php:13` — do not change it.

The `DOCKER_PDF_LIBRARY` env var (used by the `libris` filesystem disk in `config/filesystems.php`) is set at runtime by Ansible — it is not baked into the image. In production, Ansible sets it to `/mnt/rpg`.

- [ ] **Step 1: Create `docker/entrypoint.sh`**

```sh
#!/bin/sh
set -e

if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] ERROR: APP_KEY is not set." >&2
    exit 1
fi

echo "[entrypoint] Creating storage directories..."
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         storage/app/thumbnails

echo "[entrypoint] Caching config..."
php artisan config:cache || {
    echo "[entrypoint] ERROR: config:cache failed" >&2
    exit 1
}

echo "[entrypoint] Caching views..."
php artisan view:cache || {
    echo "[entrypoint] ERROR: view:cache failed" >&2
    exit 1
}

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force || {
        echo "[entrypoint] ERROR: migrate failed" >&2
        exit 1
    }
fi

echo "[entrypoint] Starting Octane..."
exec php artisan octane:start \
    --server="${OCTANE_SERVER:-frankenphp}" \
    --host=0.0.0.0 \
    --port="${OCTANE_PORT:-8000}"
```

Make it executable:

```bash
chmod +x docker/entrypoint.sh
```

- [ ] **Step 2: Create `Dockerfile` at repo root**

```dockerfile
FROM node:22-alpine AS node-deps
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci

FROM dunglas/frankenphp:1-php8.4-alpine
WORKDIR /var/www/html

ARG APP_ENV=production

# System dependencies
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    jq \
    openjdk21-jre-headless \
    imagemagick \
    imagemagick-dev \
    ghostscript \
    ghostscript-fonts \
    && install-php-extensions \
        imagick \
        redis \
        pcntl \
        opcache \
        zip

# PDFBox jar (version 3.x)
RUN mkdir -p /usr/share/java \
    && PDFBOX_VERSION=$(curl -fs https://projects.apache.org/json/projects/pdfbox.json \
         | jq -r '[.release[] | select(.revision | test("^3")) | .revision][0]') \
    && echo "Installing PDFBox ${PDFBOX_VERSION}" \
    && curl -fL "https://dlcdn.apache.org/pdfbox/${PDFBOX_VERSION}/pdfbox-app-${PDFBOX_VERSION}.jar" \
         -o /usr/share/java/pdfbox.jar

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Node dependencies + Vite build
COPY --from=node-deps /var/www/html/node_modules node_modules
COPY . .
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan package:discover --ansi \
    && APP_ENV=$APP_ENV npm run build \
    && rm .env \
    && rm -rf node_modules

# Permissions
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
             storage/logs storage/app/public storage/app/thumbnails bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data

ENV OCTANE_PORT=8000
EXPOSE ${OCTANE_PORT}

ARG APP_VERSION=dev
ARG APP_PR_NUMBER=
ARG APP_BRANCH=

ENV APP_VERSION=$APP_VERSION
ENV APP_PR_NUMBER=$APP_PR_NUMBER
ENV APP_BRANCH=$APP_BRANCH

LABEL org.opencontainers.image.version=$APP_VERSION \
      org.opencontainers.image.revision=$APP_PR_NUMBER \
      org.opencontainers.image.ref.name=$APP_BRANCH

ENTRYPOINT ["/entrypoint.sh"]
```

- [ ] **Step 3: Add `DOCKER_PDF_LIBRARY` to `.env.example`**

Add to `.env.example` under the existing `LIBRARY_URL` line:

```
DOCKER_PDF_LIBRARY=/mnt/rpg
```

- [ ] **Step 4: Test the Docker build locally**

```bash
docker build -t thalium:local .
```

Expected: build completes without error. PDFBox version printed during build. If `imagemagick-dev` causes issues on Alpine with the `imagick` extension, try `apk add --no-cache imagemagick imagemagick-libs` instead.

- [ ] **Step 5: Add a `.dockerignore` to keep the image lean**

```
.git
.github
node_modules
storage/app
storage/logs
storage/framework
bootstrap/cache
docker/Dockerfile
```

- [ ] **Step 6: Commit**

```bash
git add Dockerfile docker/entrypoint.sh .env.example .dockerignore
git commit -m "🎇 Add FrankenPHP/Octane Dockerfile and entrypoint"
```

---

## Task 5: CI Workflow

**Files:**
- Add: `.github/workflows/ci.yml`
- Delete: `.github/workflows/laravel.yml`

This replaces the existing `laravel.yml` (which just ran tests via shared workflow). The new `ci.yml` tests, builds, pushes to GHCR, and deploys.

The deploy jobs SSH into firth using the `thalium` system user (provisioned by Ansible). The deploy directories are `/home/docker/thalium` (production) and `/home/docker/thalium-staging` (staging) — Ansible creates these.

- [ ] **Step 1: Create `.github/workflows/ci.yml`**

```yaml
name: CI

on:
  push:
    branches: [dependabot-updates]
  pull_request:
    branches: ["**"]
    types: [opened, synchronize, reopened]
  workflow_dispatch:
  workflow_call:
    inputs:
      tag:
        description: "Release tag to build and deploy"
        type: string
        required: true

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}
  APP_NAME: thalium

permissions: {}

jobs:
  test:
    uses: istic/shared-workflows/.github/workflows/laravel-tests.yml@main
    with:
      php_version: ${{ vars.php_version }}
      node_version: ${{ vars.node_version }}
      test_command: "vendor/bin/phpunit"
    secrets: inherit # pragma: allowlist secret

  build-and-push:
    needs: [test]
    runs-on: ubuntu-latest
    if: |
      github.ref != 'refs/heads/dependabot-updates' &&
      (github.event_name != 'pull_request' || github.event.pull_request.head.repo.full_name == github.repository)
    permissions:
      contents: read
      packages: write

    steps:
      - name: Checkout
        uses: actions/checkout@v6
        with:
          ref: ${{ inputs.tag || github.ref }}

      - name: Log in to GitHub Container Registry
        uses: docker/login-action@v4
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v6
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=semver,pattern={{version}},value=${{ inputs.tag || github.ref_name }},enable=${{ inputs.tag != '' || startsWith(github.ref, 'refs/tags/v') }}
            type=semver,pattern={{major}}.{{minor}},value=${{ inputs.tag || github.ref_name }},enable=${{ inputs.tag != '' || startsWith(github.ref, 'refs/tags/v') }}
            type=sha
            type=raw,value=latest,enable=${{ inputs.tag != '' || startsWith(github.ref, 'refs/tags/v') }}
            type=raw,value=staging,enable=${{ github.event_name == 'pull_request' }}

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v4

      - name: Build and push
        uses: docker/build-push-action@v7
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
          build-args: |
            APP_VERSION=${{ inputs.tag || github.ref_name }}
            APP_PR_NUMBER=${{ github.event.pull_request.number }}
            APP_BRANCH=${{ github.head_ref }}
            APP_ENV=${{ github.event_name == 'pull_request' && 'staging' || 'production' }}

  deploy-staging:
    needs: build-and-push
    if: github.event_name == 'pull_request' && github.actor != 'dependabot[bot]' && !github.event.pull_request.draft
    runs-on: ubuntu-latest
    concurrency:
      group: deploy-staging
      cancel-in-progress: true
    permissions:
      contents: read

    steps:
      - name: Deploy staging
        uses: appleboy/ssh-action@v1
        with:
          host: firth.water.gkhs.net
          username: ${{ env.APP_NAME }}
          key: ${{ secrets.FIRTH_SSH_KEY }}
          script: |
            set -euo pipefail
            cd /home/docker/${{ env.APP_NAME }}-staging
            timeout 300 docker compose pull || {
              echo "ERROR: docker compose pull failed or timed out"
              exit 1
            }
            docker compose up -d || {
              echo "ERROR: docker compose up -d failed"
              docker compose ps
              docker compose logs --tail=50
              exit 1
            }
            for i in $(seq 1 30); do
              if docker compose exec -T app php artisan --version > /dev/null 2>&1; then
                echo "Container ready after ${i} attempt(s)"
                break
              fi
              if [ "$i" -eq 30 ]; then
                echo "ERROR: Container failed to become ready after 30 attempts"
                docker compose logs --tail=50 app
                exit 1
              fi
              echo "Attempt ${i}/30: waiting..."
              sleep 2
            done
            docker compose exec -T app test -d /var/www/html/public/build/assets || {
              echo "ERROR: build assets not found in container"
              exit 1
            }
            rm -rf public/build/assets && mkdir -p public/build \
              && docker compose cp app:/var/www/html/public/build/assets public/build/
            docker compose exec -T app php artisan migrate --force || {
              echo "ERROR: Migration failed"
              exit 1
            }

  deploy-production:
    needs: build-and-push
    if: inputs.tag != ''
    runs-on: ubuntu-latest
    concurrency:
      group: deploy-production
      cancel-in-progress: false
    permissions:
      contents: read

    steps:
      - name: Deploy production
        uses: appleboy/ssh-action@v1
        with:
          host: firth.water.gkhs.net
          username: ${{ env.APP_NAME }}
          key: ${{ secrets.FIRTH_SSH_KEY }}
          script: |
            set -euo pipefail
            cd /home/docker/${{ env.APP_NAME }}
            timeout 300 docker compose pull || {
              echo "ERROR: docker compose pull failed or timed out"
              exit 1
            }
            docker compose up -d || {
              echo "ERROR: docker compose up -d failed"
              docker compose ps
              docker compose logs --tail=50
              exit 1
            }
            for i in $(seq 1 30); do
              if docker compose exec -T app php artisan --version > /dev/null 2>&1; then
                echo "Container ready after ${i} attempt(s)"
                break
              fi
              if [ "$i" -eq 30 ]; then
                echo "ERROR: Container failed to become ready after 30 attempts"
                docker compose logs --tail=50 app
                exit 1
              fi
              echo "Attempt ${i}/30: waiting..."
              sleep 2
            done
            docker compose exec -T app test -d /var/www/html/public/build/assets || {
              echo "ERROR: build assets not found in container"
              exit 1
            }
            rm -rf public/build/assets && mkdir -p public/build \
              && docker compose cp app:/var/www/html/public/build/assets public/build/
            docker compose exec -T app php artisan migrate --force || {
              echo "ERROR: Migration failed"
              exit 1
            }
```

- [ ] **Step 2: Validate the workflow**

```bash
actionlint .github/workflows/ci.yml
```

Expected: no errors.

- [ ] **Step 3: Delete `laravel.yml`**

```bash
rm .github/workflows/laravel.yml
```

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/ci.yml .github/workflows/laravel.yml
git commit -m "⚙️ Add CI workflow: tests, Docker build, staging and production deploy"
```

---

## Task 6: Release Workflow

**Files:**
- Add: `.github/workflows/release.yml`

- [ ] **Step 1: Create `.github/workflows/release.yml`**

```yaml
name: Release

on:
  workflow_dispatch:
    inputs:
      version_bump:
        description: "Version bump type"
        required: true
        type: choice
        options:
          - patch
          - minor
          - major
  workflow_call:
    inputs:
      version_bump:
        type: string
        description: "Version bump type"
        required: true

permissions: {}

jobs:
  tag:
    runs-on: ubuntu-latest
    permissions:
      contents: write
    outputs:
      next_version: ${{ steps.next_version.outputs.next_version }}

    steps:
      - name: Checkout repository
        uses: actions/checkout@v6
        with:
          fetch-depth: 0
          ref: main

      - name: Get latest tag
        id: get_tag
        run: |
          set -euo pipefail
          LATEST_TAG=$(git tag -l "v*.*.*" | sort -V | tail -n 1)
          if [ -z "$LATEST_TAG" ]; then
            LATEST_TAG="v0.0.0"
            echo "No existing tags found, starting from $LATEST_TAG"
          else
            echo "Latest tag: $LATEST_TAG"
          fi
          echo "latest_tag=$LATEST_TAG" >> "$GITHUB_OUTPUT"

      - name: Calculate next version
        id: next_version
        env:
          LATEST_TAG: ${{ steps.get_tag.outputs.latest_tag }}
          VERSION_BUMP: ${{ inputs.version_bump }}
        run: |
          set -euo pipefail
          VERSION=${LATEST_TAG#v}
          IFS='.' read -r MAJOR MINOR PATCH <<< "$VERSION"
          case "$VERSION_BUMP" in
            major) MAJOR=$((MAJOR + 1)); MINOR=0; PATCH=0 ;;
            minor) MINOR=$((MINOR + 1)); PATCH=0 ;;
            patch) PATCH=$((PATCH + 1)) ;;
            *) echo "Invalid version_bump: $VERSION_BUMP"; exit 1 ;;
          esac
          NEXT_VERSION="v${MAJOR}.${MINOR}.${PATCH}"
          echo "Next version: $NEXT_VERSION"
          echo "next_version=$NEXT_VERSION" >> "$GITHUB_OUTPUT"

      - name: Create and push tag
        env:
          NEXT_VERSION: ${{ steps.next_version.outputs.next_version }}
        run: |
          set -euo pipefail
          git config user.name "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"

          LS_REMOTE_OUTPUT=$(git ls-remote --tags origin "$NEXT_VERSION")
          if echo "$LS_REMOTE_OUTPUT" | grep -qF "refs/tags/$NEXT_VERSION"; then
            echo "ERROR: Tag $NEXT_VERSION already exists on remote."
            exit 1
          fi

          git tag -a "$NEXT_VERSION" -m "Release $NEXT_VERSION"
          git push origin "$NEXT_VERSION"
          echo "Created and pushed tag: $NEXT_VERSION"

      - name: Generate release notes
        id: release_notes
        env:
          LATEST_TAG: ${{ steps.get_tag.outputs.latest_tag }}
          NEXT_VERSION: ${{ steps.next_version.outputs.next_version }}
          REPOSITORY: ${{ github.repository }}
        run: |
          set -euo pipefail
          if [ "$LATEST_TAG" == "v0.0.0" ]; then
            COMMITS=$(git log --pretty=format:"- %s (%h)" --no-merges)
          else
            if ! git rev-parse --verify "$LATEST_TAG" > /dev/null 2>&1; then
              echo "ERROR: Tag $LATEST_TAG not found"
              exit 1
            fi
            COMMITS=$(git log "${LATEST_TAG}..HEAD" --pretty=format:"- %s (%h)" --no-merges)
          fi
          [ -z "$COMMITS" ] && COMMITS="No notable changes."
          TAG_SUFFIX="${NEXT_VERSION#v}"
          {
            printf '## What'\''s Changed\n\n'
            printf '%s\n' "${COMMITS}"
            printf '\n## Docker Image\n\n'
            printf '```bash\n'
            printf 'docker pull ghcr.io/%s:%s\n' "${REPOSITORY}" "${TAG_SUFFIX}"
            printf 'docker pull ghcr.io/%s:latest\n' "${REPOSITORY}"
            printf '```\n\n'
            printf '**Full Changelog**: https://github.com/%s/compare/%s...%s\n' \
              "${REPOSITORY}" "${LATEST_TAG}" "${NEXT_VERSION}"
          } > release_notes.md

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v3
        with:
          tag_name: ${{ steps.next_version.outputs.next_version }}
          name: Release ${{ steps.next_version.outputs.next_version }}
          body_path: release_notes.md
          draft: false
          prerelease: false

  ci:
    needs: tag
    uses: ./.github/workflows/ci.yml
    permissions:
      contents: read
      packages: write
    with:
      tag: ${{ needs.tag.outputs.next_version }}
    secrets: inherit # pragma: allowlist secret
```

- [ ] **Step 2: Validate**

```bash
actionlint .github/workflows/release.yml
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/release.yml
git commit -m "⚙️ Add release workflow: semver tagging and GitHub releases"
```

---

## Task 7: Dependabot Make-Release

**Files:**
- Add: `.github/workflows/dependabot-make-release.yml`

- [ ] **Step 1: Create `.github/workflows/dependabot-make-release.yml`**

```yaml
name: "[Dependabot] Make Release"

on:
  schedule:
    - cron: "0 3 * * 1" # Monday 03:00 UTC
  workflow_dispatch:
    inputs:
      merge_dependabot:
        description: "Merge dependabot-updates into main first"
        required: false
        default: true
        type: boolean
      version_bump:
        description: "Version bump type"
        required: true
        default: "patch"
        type: choice
        options:
          - patch
          - minor
          - major

permissions: {}

jobs:
  check-for-updates:
    runs-on: ubuntu-latest
    permissions:
      contents: read
    outputs:
      has_updates: ${{ steps.check.outputs.has_updates }}
      version_bump: ${{ steps.params.outputs.version_bump }}

    steps:
      - uses: actions/checkout@v6
        with:
          fetch-depth: 0

      - name: Resolve parameters
        id: params
        env:
          EVENT_NAME: ${{ github.event_name }}
          INPUT_VERSION_BUMP: ${{ inputs.version_bump }}
        run: |
          if [ "$EVENT_NAME" = "schedule" ]; then
            echo "version_bump=patch" >> "$GITHUB_OUTPUT"
          else
            echo "version_bump=$INPUT_VERSION_BUMP" >> "$GITHUB_OUTPUT"
          fi

      - name: Check if dependabot-updates is ahead of main
        id: check
        run: |
          git fetch origin
          if ! git ls-remote --exit-code origin dependabot-updates > /dev/null 2>&1; then
            echo "dependabot-updates branch does not exist, skipping"
            echo "has_updates=false" >> "$GITHUB_OUTPUT"
            exit 0
          fi
          AHEAD=$(git rev-list --count origin/main..origin/dependabot-updates)
          echo "dependabot-updates is $AHEAD commits ahead of main"
          if [ "$AHEAD" -gt 0 ]; then
            echo "has_updates=true" >> "$GITHUB_OUTPUT"
          else
            echo "No new commits in dependabot-updates, skipping"
            echo "has_updates=false" >> "$GITHUB_OUTPUT"
          fi

  merge-dependabot:
    needs: check-for-updates
    if: |
      needs.check-for-updates.outputs.has_updates == 'true' &&
      (github.event_name == 'schedule' || inputs.merge_dependabot == true)
    runs-on: ubuntu-latest
    permissions:
      contents: write
      pull-requests: write
      checks: read

    steps:
      - name: Merge dependabot-updates PR into main
        env:
          GH_TOKEN: ${{ github.token }}
          GH_REPO: ${{ github.repository }}
        run: |
          PR_STATE=$(gh pr view dependabot-updates --json state --jq '.state' 2>/dev/null || echo "NOT_FOUND")
          if [ "$PR_STATE" != "OPEN" ]; then
            echo "No open PR for dependabot-updates (state: $PR_STATE), creating one..."
            gh pr create --base main --head dependabot-updates \
              --title "chore: bump dependencies" \
              --body "Automated dependency updates"
          fi
          PR_IS_DRAFT=$(gh pr view dependabot-updates --json isDraft --jq '.isDraft' 2>/dev/null || echo "false")
          if [ "$PR_IS_DRAFT" = "true" ]; then
            gh pr ready dependabot-updates
          fi
          gh pr checks dependabot-updates --watch --fail-fast
          gh pr merge dependabot-updates --merge

  create-release:
    needs: [check-for-updates, merge-dependabot]
    if: |
      !failure() && !cancelled() &&
      (github.event_name == 'workflow_dispatch' || needs.check-for-updates.outputs.has_updates == 'true')
    permissions:
      contents: write
      packages: write
    uses: aquarion/thalium/.github/workflows/release.yml@main
    with:
      version_bump: ${{ needs.check-for-updates.outputs.version_bump }}
    secrets: inherit # pragma: allowlist secret
```

- [ ] **Step 2: Validate**

```bash
actionlint .github/workflows/dependabot-make-release.yml
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/dependabot-make-release.yml
git commit -m "⚙️ Add dependabot-make-release: weekly automated dependency releases"
```

---

## Task 8: Cleanup

**Files:**
- Delete: `docker/Dockerfile/` directory

- [ ] **Step 1: Remove the old Docker build artefacts**

The files under `docker/Dockerfile/`, `docker/nginx/`, `docker/php-fpm/`, and `docker/scheduler-app/` were for the old php-fpm + nginx setup. They are superseded by the root `Dockerfile` and `docker/entrypoint.sh`. Remove them:

```bash
rm -rf docker/Dockerfile docker/nginx docker/php-fpm docker/scheduler-app
```

Keep `docker/pdfbox/install_pdfbox.sh` for reference (the logic is now in the `Dockerfile` `RUN` step).

- [ ] **Step 2: Verify pre-commit still passes after cleanup**

```bash
pre-commit run --all-files
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
git add docker/
git commit -m "❌ Remove old php-fpm/nginx Docker build artefacts"
```

---

## Task 9: Extend firth_laravel_app Role (autopelago repo)

**Files:**
- Modify: `roles/firth_laravel_app/tasks/app.yml`
- Modify: `roles/firth_laravel_app/tasks/staging.yml`
- Modify: `roles/firth_laravel_app/templates/docker-compose.yml.j2`

Work in the `aquarion/autopelago` repo. Create a branch before committing.

- [ ] **Step 1: Add `additional_volumes` to `fla_ctx` in `tasks/app.yml`**

Add one line after `additional_files` in the `set_fact` block:

```yaml
      additional_files: "{{ firth_laravel_app_item.additional_files | default([]) }}"
      additional_volumes: "{{ firth_laravel_app_item.additional_volumes | default([]) }}"
      system_user: "{{ firth_laravel_app_item.name }}"
```

- [ ] **Step 2: Add `additional_volumes` to the staging context in `tasks/staging.yml`**

Add one line after the `additional_files` line in the staging `set_fact` block:

```yaml
      additional_files: "{{ fla.staging.additional_files | default(fla.additional_files | default([])) }}"
      additional_volumes: "{{ fla.staging.additional_volumes | default(fla.additional_volumes | default([])) }}"
      system_user: "{{ fla.name }}"
```

- [ ] **Step 3: Update `templates/docker-compose.yml.j2` — volume mounts in app service**

After the existing `additional_files` volume entries in the `app` service, add:

```jinja2
{% for file in fla_ctx.additional_files %}
      - {{ docker_root }}/{{ fla_ctx.name }}/secrets/{{ file.dest }}:{{ fla_ctx.workdir }}/storage/app/{{ file.dest }}:ro
{% endfor %}
{% for vol in fla_ctx.additional_volumes %}
{% if vol.src is defined %}
      - {{ vol.src }}:{{ vol.dest }}
{% else %}
      - {{ vol.name }}:{{ vol.dest }}
{% endif %}
{% endfor %}
```

- [ ] **Step 4: Add the same volume mounts to the worker service**

The worker service block has the same `additional_files` loop. Add the same `additional_volumes` loop immediately after it:

```jinja2
{% for file in fla_ctx.additional_files %}
      - {{ docker_root }}/{{ fla_ctx.name }}/secrets/{{ file.dest }}:{{ fla_ctx.workdir }}/storage/app/{{ file.dest }}:ro
{% endfor %}
{% for vol in fla_ctx.additional_volumes %}
{% if vol.src is defined %}
      - {{ vol.src }}:{{ vol.dest }}
{% else %}
      - {{ vol.name }}:{{ vol.dest }}
{% endif %}
{% endfor %}
```

- [ ] **Step 5: Add named volumes to the top-level `volumes:` section**

After `storage:` in the top-level volumes block:

```jinja2
volumes:
  storage:

{% for vol in fla_ctx.additional_volumes %}
{% if vol.name is defined %}
  {{ vol.name }}:
{% if vol.external | default(false) %}
    external: true
    name: {{ vol.name }}
{% endif %}
{% endif %}
{% endfor %}
```

- [ ] **Step 6: Verify with ansible-lint**

```bash
ansible-lint roles/firth_laravel_app/
```

Expected: no errors (warnings about `var-naming[no-role-prefix]` on the `fla`/`fla_ctx` facts are pre-existing and expected).

- [ ] **Step 7: Commit to autopelago**

```bash
git add roles/firth_laravel_app/tasks/app.yml \
        roles/firth_laravel_app/tasks/staging.yml \
        roles/firth_laravel_app/templates/docker-compose.yml.j2
git commit -m "🎇 firth_laravel_app: add additional_volumes support"
```

---

## Task 10: Add Thalium to host_vars and GitHub Secrets (autopelago repo)

**Files:**
- Modify: `host_vars/firth.water.gkhs.net/laravel_apps.yml`
- Modify: `host_vars/firth.water.gkhs.net/vault.yml` (encrypted — use `ansible-vault edit`)

**Context:** Ports 8000/8001 are bloom prod/staging. Use 8002/8003 for Thalium. The `ssl_snippet` must match an existing file in `roles/firth_nginx/templates/snippets/` — check what snippet covers `thalium.aquarionics.com` and use that name. Elasticsearch runs on the host; `host.docker.internal` resolves to it from inside the container.

- [ ] **Step 1: Add Thalium vault secrets**

```bash
ansible-vault edit host_vars/firth.water.gkhs.net/vault.yml
```

Add:

```yaml
vault_thalium_app_key: "base64:..."          # generate: php artisan key:generate --show
vault_thalium_staging_app_key: "base64:..."  # generate separately
vault_thalium_redis_password: "..."          # generate a random password
vault_thalium_staging_redis_password: "..."  # generate a random password
vault_thalium_es_user: "..."                 # from existing Elasticsearch config
vault_thalium_es_pass: "..."                 # from existing Elasticsearch config
```

- [ ] **Step 2: Add Thalium to `laravel_apps.yml`**

Append to `firth_laravel_app_apps`:

```yaml
  - name: thalium
    image: ghcr.io/aquarion/thalium
    image_tag: latest
    backend: octane
    port: 8002
    server_name: thalium.aquarionics.com
    app_url: https://thalium.aquarionics.com
    app_key: "{{ vault_thalium_app_key }}"
    app_name: Thalium
    ssl_snippet: aquarionics  # verify this snippet exists in firth_nginx/templates/snippets/
    github_repo: aquarion/thalium
    github_deploy_token: "{{ laravel_apps_deploy_token_aquarion }}"
    ghcr_username: "{{ ghcr_username }}"
    ghcr_token: "{{ ghcr_token }}"
    worker: true
    redis:
      username: thalium
      password: "{{ vault_thalium_redis_password }}"
    additional_env:
      ELASTICSEARCH_HOST: host.docker.internal
      ELASTICSEARCH_PORT: "9200"
      ELASTICSEARCH_SCHEME: https
      ELASTICSEARCH_USER: "{{ vault_thalium_es_user }}"
      ELASTICSEARCH_PASS: "{{ vault_thalium_es_pass }}"
      DOCKER_PDF_LIBRARY: /mnt/rpg
      LIBRARY_URL: https://thalium.aquarionics.com/_libris/
    additional_volumes:
      - src: /home/library/RPG/Systems
        dest: /mnt/rpg
      - name: elasticsearch_certs
        dest: /usr/share/elasticsearch/config/certs
        external: true
    staging:
      image_tag: staging
      port: 8003
      server_name: thalium.istic.dev
      app_url: https://thalium.istic.dev
      app_key: "{{ vault_thalium_staging_app_key }}"
      ssl_snippet: istic_dev
      redis:
        username: thalium_staging
        password: "{{ vault_thalium_staging_redis_password }}"
      additional_env:
        ELASTICSEARCH_HOST: host.docker.internal
        ELASTICSEARCH_PORT: "9200"
        ELASTICSEARCH_SCHEME: https
        ELASTICSEARCH_USER: "{{ vault_thalium_es_user }}"
        ELASTICSEARCH_PASS: "{{ vault_thalium_es_pass }}"
        DOCKER_PDF_LIBRARY: /mnt/rpg
        LIBRARY_URL: https://thalium.istic.dev/_libris/
```

- [ ] **Step 3: Verify SSL snippet exists**

```bash
ls roles/firth_nginx/templates/snippets/
```

Confirm a snippet exists for `thalium.aquarionics.com`. If not, add one following the existing pattern before running the playbook.

- [ ] **Step 4: Commit to autopelago**

```bash
git add host_vars/firth.water.gkhs.net/laravel_apps.yml \
        host_vars/firth.water.gkhs.net/vault.yml
git commit -m "⚙️ Add thalium to firth laravel apps"
```

---

## Task 11: Add GitHub Secrets and Variables, Run Ansible

- [ ] **Step 1: Add repository secrets to `aquarion/thalium`**

In GitHub → Settings → Secrets and variables → Actions:

- `FIRTH_SSH_KEY` — private key for the `thalium` system user on firth (generated by Ansible when it creates the user; retrieve from the server or Ansible vault)
- `REBASE_TOKEN` — PAT with `repo` and `workflow` scopes (reuse the one from other aquarion repos if already created)

- [ ] **Step 2: Add repository variables to `aquarion/thalium`**

In GitHub → Settings → Secrets and variables → Actions → Variables:

- `php_version` = `8.4`
- `node_version` = `22`

- [ ] **Step 3: Run the Ansible playbook against firth**

```bash
cd /path/to/autopelago
ansible-playbook firth.yml --tags firth_laravel_app --limit firth.water.gkhs.net
```

Expected: system user `thalium` created, Redis user provisioned, GHCR login configured, docker-compose files templated for production and staging, nginx vhosts deployed.

- [ ] **Step 4: Verify the containers start**

SSH into firth and confirm:

```bash
ssh thalium@firth.water.gkhs.net
cd /home/docker/thalium
docker compose pull
docker compose up -d
docker compose logs app --tail=30
```

Expected: container starts, entrypoint runs migrations, Octane begins serving on port 8002.

- [ ] **Step 5: Open a PR in thalium to trigger the first staging deploy**

Push the `feature/dev-flow-alignment` branch to GitHub and open a PR against `main`. This triggers `ci.yml` which builds the image and deploys to staging on firth.

Verify:
- CI passes all jobs
- `https://thalium.istic.dev` returns the Thalium UI
- `https://thalium.istic.dev/_thumbnails/` is accessible (thumbnail storage working)
- Horizon dashboard accessible at `https://thalium.istic.dev/horizon`
