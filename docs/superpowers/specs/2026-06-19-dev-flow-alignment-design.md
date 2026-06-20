# Dev Flow Alignment Design

**Date:** 2026-06-19
**Scope:** Bring Thalium's CI/CD and dev tooling into alignment with bloom and stream-delta (Option C: full alignment including Docker build, GHCR, and automated deploys).

---

## 1. Pre-commit

Add `.pre-commit-config.yaml` to the repo root with these hooks:

- **Standard hooks** (`pre-commit/pre-commit-hooks`): `trailing-whitespace`, `end-of-file-fixer`, `check-yaml`, `check-added-large-files`
- **Laravel Pint** (local hook): runs `vendor/bin/pint` on staged PHP files
- **actionlint** (`rhysd/actionlint`): validates GitHub Actions YAML
- **detect-secrets** (`Yelp/detect-secrets`): prevents credential commits; requires a committed `.secrets.baseline` generated at setup time

Excluded: PHPStan (no `phpstan.neon` baseline exists), Biome (JS surface is a single `app.js` import).

Add `laravel/pint` to `composer.json` dev dependencies. The existing `phpcs.xml` can remain for reference but Pint becomes the enforced formatter.

---

## 2. Lint CI Workflow

New file: `.github/workflows/lint-precommit.yml`

- Triggers: push to `main`, all PRs, `workflow_dispatch`
- Sets up Node (`vars.node_version`) and PHP (`vars.php_version`) with composer cache
- Runs `pre-commit run --all-files`

Replaces `devskim.yml` (removed — coverage overlaps with actionlint and detect-secrets).

---

## 3. Dependabot Updates

**`dependabot.yml` config:** Switch all three ecosystems (composer, npm, github-actions) from `interval: daily` targeting `main` to `interval: weekly` targeting `dependabot-updates`.

**`auto-rebase-dependabot.yml`:** Add missing `REBASE_TOKEN` secret, matching bloom's pattern.

**`dependabot.yml` workflow (auto-merge):** Replace the current inline workflow with `uses: istic/shared-workflows/.github/workflows/auto-merge-dependabot.yml@main`. Individual Dependabot PRs auto-merge into `dependabot-updates`; the weekly `dependabot-make-release` workflow (Section 7) handles merging that branch to `main`.

---

## 4. Dockerfile

New `Dockerfile` at repo root. Multi-stage build:

**Stage 1 — node-deps:** `node:22-alpine`, runs `npm ci`.

**Stage 2 — production:** `dunglas/frankenphp:1-php8.4-alpine` base. Additional system packages:
- `openjdk-21-jre` (PDFBox)
- `imagemagick` + `ghostscript` (thumbnail generation via Imagick)
- PHP extensions: `imagick`, `redis`, `pcntl`, `opcache`, `zip`

Build steps:
- Composer install (no-dev, optimised autoloader)
- Copy node_modules from stage 1, run `npm run build`, remove node_modules
- Create required Laravel directories, bake in Vite assets
- Set ownership to `www-data`

Runtime volumes (not baked in):
- `/mnt/rpg` — RPG library files (bind-mounted by Ansible from `/home/library/RPG/Systems`)
- Elasticsearch cert volume

Entrypoint: `docker/entrypoint.sh` — runs `php artisan migrate --force` then starts FrankenPHP.

The existing `docker/Dockerfile/` directory is retired.

Build args: `APP_VERSION`, `APP_PR_NUMBER`, `APP_BRANCH`, `APP_ENV` — set as OCI labels.

---

## 5. CI Workflow

New file: `.github/workflows/ci.yml`. Replaces `laravel.yml`.

**`test` job:** `uses: istic/shared-workflows/.github/workflows/laravel-tests.yml@main` with `php_version: vars.php_version`, `node_version: vars.node_version`, `test_command: vendor/bin/phpunit`.

**`build-and-push` job:** Needs `test`. Skipped for dependabot PRs and forks.
- Logs into GHCR, builds and pushes `ghcr.io/aquarion/thalium`
- Tags: `sha`, `staging` (PRs), `latest` + semver (release tags)
- Uses GHA cache for Docker layers

**`deploy-staging` job:** Needs `build-and-push`. Runs on non-draft PRs only (not dependabot).
- SSH into firth as system user `thalium` using `FIRTH_SSH_KEY` secret
- `cd /home/docker/thalium-staging && docker compose pull && docker compose up -d`
- Wait for container readiness, copy Vite assets, run migrations
- Concurrency group: `deploy-staging`, cancel-in-progress: true

**`deploy-production` job:** Needs `build-and-push`. Runs only when triggered with a tag (via `release.yml`).
- Same SSH pattern against `/home/docker/thalium`
- Concurrency group: `deploy-production`, cancel-in-progress: false

---

## 6. Release Workflow

New file: `.github/workflows/release.yml`. Copied from bloom with minor naming changes.

- Triggered by `workflow_dispatch` (patch/minor/major) or `workflow_call` from `dependabot-make-release`
- Creates semver tag (`v{MAJOR}.{MINOR}.{PATCH}`), generates changelog from commits since last tag, creates GitHub Release
- Calls `ci.yml` with the tag to trigger the production build and deploy

---

## 7. Dependabot Make-Release

New file: `.github/workflows/dependabot-make-release.yml`. Copied from bloom.

- Cron: Monday 03:00 UTC
- Checks if `dependabot-updates` is ahead of `main`; skips if not
- Merges `dependabot-updates` PR into `main` (creates the PR if needed, waits for checks)
- Calls `release.yml` with `patch`

---

## 8. Autopelago Changes

**Extend `firth_laravel_app` role:** Add optional `additional_volumes` list to the docker-compose template. The list supports two entry shapes:
- `{ src: <host-path>, dest: <container-path> }` — bind mount
- `{ name: <volume-name>, dest: <container-path>, external: true }` — named external volume

Both are rendered alongside the existing `storage` volume in the generated `docker-compose.yml`.

**Add Thalium to `host_vars/firth.water.gkhs.net/laravel_apps.yml`:**
```yaml
- name: thalium
  image: ghcr.io/aquarion/thalium
  backend: frankenphp
  port: <assigned port>
  app_key: <vault>
  app_url: https://thalium.example.com
  server_name: thalium.example.com
  ssl_snippet: <existing>
  worker: true          # runs php artisan queue:work (role default)
  redis:
    username: thalium
    password: <vault>
  additional_env:
    ELASTICSEARCH_HOST: <host>
    ELASTICSEARCH_PORT: "9200"
    ELASTICSEARCH_USER: <vault>
    ELASTICSEARCH_PASS: <vault>
  additional_volumes:
    - src: /home/library/RPG/Systems
      dest: /mnt/rpg
    - name: elasticsearch_certs
      dest: /usr/share/elasticsearch/config/certs
      external: true
  staging:
    port: <assigned port>
    app_key: <vault>
    app_url: https://thalium-staging.example.com
    server_name: thalium-staging.example.com
```

---

## Files Changed

| Action | Path |
|---|---|
| Add | `Dockerfile` |
| Add | `docker/entrypoint.sh` |
| Add | `.pre-commit-config.yaml` |
| Add | `.secrets.baseline` |
| Add | `.github/workflows/ci.yml` |
| Add | `.github/workflows/lint-precommit.yml` |
| Add | `.github/workflows/release.yml` |
| Add | `.github/workflows/dependabot-make-release.yml` |
| Update | `.github/dependabot.yml` |
| Update | `.github/workflows/auto-rebase-dependabot.yml` |
| Update | `.github/workflows/dependabot.yml` |
| Remove | `.github/workflows/laravel.yml` |
| Remove | `.github/workflows/devskim.yml` |
| Remove | `docker/Dockerfile/` (directory) |
| Add | `composer.json` dev dep: `laravel/pint` |
| Autopelago (separate repo) | Extend `firth_laravel_app` role + add host_vars |
