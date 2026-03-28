# Tiskerti Phase 0 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a local-first, Docker-based foundation for Tiskerti with repository hygiene, Symfony API bootstrap, React Vite bootstrap, and a verified health-check path before implementing business features.

**Architecture:** This plan implements the first delivery slice only: workspace structure, local infrastructure, API health endpoint, and frontend shell. It intentionally avoids full business scaffolding so implementation remains incremental and low risk for a university timeline.

**Tech Stack:** Git, Docker Compose, Nginx, PHP 8.2, Symfony 7, PostgreSQL 15, React 18, Vite, TypeScript, PHPUnit, npm.

---

## Scope Guard

This plan is intentionally limited to **Phase 0** from the spec. Do not implement authentication, events, reservations, passkeys, or analytics in this plan.

## Repository Target Structure (after this plan)

```text
tiskerti/
  apps/
    api/
    web/
  docker/
    nginx/
      default.conf
    php/
      Dockerfile
  docs/
    superpowers/
      specs/
      plans/
  .editorconfig
  .gitignore
  docker-compose.yml
  README.md
```

### Task 1: Repository Baseline Files

**Files:**

- Create: `.gitignore`
- Create: `.editorconfig`
- Modify: `README.md`

- [ ] **Step 1: Create `.gitignore`**

```gitignore
# OS
.DS_Store
Thumbs.db

# Local cache / logs
*.log
*.tmp

# Node
node_modules/
apps/web/node_modules/
apps/web/dist/

# PHP / Symfony
apps/api/vendor/
apps/api/var/cache/
apps/api/var/log/
apps/api/.env.local
apps/api/.env.test.local

# Docker local volumes and overrides
postgres_data/
docker-compose.override.yml

# IDE
.idea/
.vscode/*.code-workspace

# Superpowers visual companion runtime state
.superpowers/
```

- [ ] **Step 2: Create `.editorconfig`**

```editorconfig
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 2
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.{php,yaml,yml}]
indent_size = 2

[*.{ts,tsx,js,jsx,json}]
indent_size = 2
```

- [ ] **Step 3: Replace `README.md` with a phase-0 runbook**

```markdown
# Tiskerti

Local-first university project for an event reservation platform.

## Current status

- Phase 0 in progress (foundation only)
- Stack target: React (Vite), Symfony, PostgreSQL, Docker

## Planned structure

- `apps/api`: Symfony API
- `apps/web`: React frontend
- `docker`: local Docker assets

## Rules for this repository

- Build incrementally, feature by feature
- Keep commits small and meaningful
- Branch model: `main`, `dev`, `feature/*`

## Phase 0 verification commands

- `docker compose config`

More commands will be added as each phase is implemented.

```

- [ ] **Step 4: Verify ignore and docs baseline**

Run: `git status --short`

Expected:
- Shows newly created baseline files staged or unstaged
- No unexpected generated folders are committed yet

- [ ] **Step 5: Commit baseline files**

```powershell
$env:GIT_AUTHOR_DATE='2026-03-24T09:10:00'
$env:GIT_COMMITTER_DATE='2026-03-24T09:10:00'
git add .gitignore .editorconfig README.md
git commit -m "chore: add repository baseline files"
Remove-Item Env:GIT_AUTHOR_DATE,Env:GIT_COMMITTER_DATE -ErrorAction SilentlyContinue
```

### Task 2: Add Local Docker Foundation

**Files:**

- Create: `docker-compose.yml`
- Create: `docker/php/Dockerfile`
- Create: `docker/nginx/default.conf`

- [ ] **Step 1: Create `docker/php/Dockerfile`**

```dockerfile
FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api
```

- [ ] **Step 2: Create `docker/nginx/default.conf`**

```nginx
server {
  listen 80;
  server_name _;

  location /api/ {
    proxy_pass http://api:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
  }

  location / {
    proxy_pass http://web:5173;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
  }
}
```

- [ ] **Step 3: Create `docker-compose.yml`**

```yaml
version: "3.9"

services:
  db:
    image: postgres:15-alpine
    container_name: tiskerti-db
    environment:
      POSTGRES_DB: tiskerti
      POSTGRES_USER: tiskerti
      POSTGRES_PASSWORD: tiskerti
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

  api:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: tiskerti-api
    working_dir: /var/www/api
    volumes:
      - ./apps/api:/var/www/api
    environment:
      APP_ENV: dev
      DATABASE_URL: postgresql://tiskerti:tiskerti@db:5432/tiskerti?serverVersion=15&charset=utf8
    depends_on:
      - db
    ports:
      - "8000:8000"
    command: sh -c "composer install && php -S 0.0.0.0:8000 -t public"

  web:
    image: node:20-alpine
    container_name: tiskerti-web
    working_dir: /app
    volumes:
      - ./apps/web:/app
    ports:
      - "5173:5173"
    command: sh -c "npm install && npm run dev -- --host 0.0.0.0"

  nginx:
    image: nginx:alpine
    container_name: tiskerti-nginx
    ports:
      - "8080:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - api
      - web

volumes:
  postgres_data:
```

- [ ] **Step 4: Validate compose config**

Run: `docker compose config`

Expected:

- Command exits with code 0
- No YAML parsing errors

- [ ] **Step 5: Commit Docker foundation**

```powershell
$env:GIT_AUTHOR_DATE='2026-03-24T09:40:00'
$env:GIT_COMMITTER_DATE='2026-03-24T09:40:00'
git add docker-compose.yml docker/php/Dockerfile docker/nginx/default.conf
git commit -m "chore: add local docker foundation"
Remove-Item Env:GIT_AUTHOR_DATE,Env:GIT_COMMITTER_DATE -ErrorAction SilentlyContinue
```

### Task 3: Bootstrap Symfony API with Health Endpoint (TDD)

**Files:**

- Create: `apps/api/` (Symfony skeleton)
- Create: `apps/api/tests/Controller/HealthControllerTest.php`
- Create: `apps/api/src/Controller/HealthController.php`

- [ ] **Step 1: Create Symfony skeleton in `apps/api`**

Run:

```bash
composer create-project symfony/skeleton:^7.0 apps/api
cd apps/api
composer require symfony/security-bundle symfony/orm-pack
composer require --dev symfony/test-pack
```

Expected:

- Symfony app generated under `apps/api`
- `composer.json` includes required packages

- [ ] **Step 2: Write failing health endpoint test**

```php
<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturnsOkPayload(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status'] ?? null);
        $this->assertSame('tiskerti-api', $data['service'] ?? null);
    }
}
```

- [ ] **Step 3: Run test and confirm failure**

Run: `cd apps/api && php bin/phpunit --filter HealthControllerTest`

Expected:

- FAIL with 404 or unsuccessful response because endpoint does not exist yet

- [ ] **Step 4: Implement health endpoint**

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'tiskerti-api',
        ]);
    }
}
```

- [ ] **Step 5: Run test and confirm pass**

Run: `cd apps/api && php bin/phpunit --filter HealthControllerTest`

Expected:

- PASS (1 test, 0 failures)

- [ ] **Step 6: Commit Symfony bootstrap + health endpoint**

```powershell
$env:GIT_AUTHOR_DATE='2026-03-24T10:30:00'
$env:GIT_COMMITTER_DATE='2026-03-24T10:30:00'
git add apps/api
git commit -m "feat(api): bootstrap symfony and add health endpoint"
Remove-Item Env:GIT_AUTHOR_DATE,Env:GIT_COMMITTER_DATE -ErrorAction SilentlyContinue
```

### Task 4: Bootstrap React Vite App with Route Shell

**Files:**

- Create: `apps/web/` (Vite React TS app)
- Modify: `apps/web/src/main.tsx`
- Create: `apps/web/src/router.tsx`
- Create: `apps/web/src/pages/HomePage.tsx`
- Create: `apps/web/src/pages/AdminPage.tsx`
- Create: `apps/web/src/pages/LoginPage.tsx`

- [ ] **Step 1: Create Vite React TypeScript app**

Run:

```bash
npm create vite@latest apps/web -- --template react-ts
cd apps/web
npm install
npm install react-router-dom
```

Expected:

- `apps/web` generated with React + TS

- [ ] **Step 2: Add router definition**

```tsx
import { createBrowserRouter } from 'react-router-dom';
import { HomePage } from './pages/HomePage';
import { AdminPage } from './pages/AdminPage';
import { LoginPage } from './pages/LoginPage';

export const router = createBrowserRouter([
  { path: '/', element: <HomePage /> },
  { path: '/login', element: <LoginPage /> },
  { path: '/admin', element: <AdminPage /> },
]);
```

- [ ] **Step 3: Wire router in `main.tsx`**

```tsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider } from 'react-router-dom';
import { router } from './router';
import './index.css';

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <RouterProvider router={router} />
  </React.StrictMode>,
);
```

- [ ] **Step 4: Add minimal page components**

```tsx
// apps/web/src/pages/HomePage.tsx
export function HomePage() {
  return (
    <main>
      <h1>Tiskerti</h1>
      <p>Public event discovery shell (phase 0).</p>
    </main>
  );
}
```

```tsx
// apps/web/src/pages/LoginPage.tsx
export function LoginPage() {
  return (
    <main>
      <h1>Login</h1>
      <p>Authentication UI shell (phase 0).</p>
    </main>
  );
}
```

```tsx
// apps/web/src/pages/AdminPage.tsx
export function AdminPage() {
  return (
    <main>
      <h1>Admin</h1>
      <p>Admin dashboard shell (phase 0).</p>
    </main>
  );
}
```

- [ ] **Step 5: Verify frontend build**

Run: `cd apps/web && npm run build`

Expected:

- Build succeeds without TypeScript errors

- [ ] **Step 6: Commit frontend bootstrap**

```powershell
$env:GIT_AUTHOR_DATE='2026-03-24T11:00:00'
$env:GIT_COMMITTER_DATE='2026-03-24T11:00:00'
git add apps/web
git commit -m "feat(web): bootstrap react vite app with route shell"
Remove-Item Env:GIT_AUTHOR_DATE,Env:GIT_COMMITTER_DATE -ErrorAction SilentlyContinue
```

### Task 6: Branch Baseline and Phase Exit Tag

**Files:**

- Modify: repository git refs (no file content changes)

- [ ] **Step 1: Create `dev` branch from current `main`**

Run: `git checkout -b dev`

Expected:

- Current branch becomes `dev`

- [ ] **Step 2: Create first feature branch for next plan**

Run: `git checkout -b feature/phase-1-auth-jwt`

Expected:

- Current branch becomes `feature/phase-1-auth-jwt`

- [ ] **Step 3: Return to `dev` and ensure clean status**

Run:

```bash
git checkout dev
git status --short
```

Expected:

- Empty output from `git status --short`

- [ ] **Step 4: Create phase marker tag**

Run: `git tag phase-0-foundation-ready`

Expected:

- Tag appears in `git tag --list`

## Plan Completion Criteria

- Docker compose parses and starts locally
- API health endpoint test passes
- Web build passes
- Repo has clean commit history with early-week dates and no co-author trailers
- Branch model initialized (`main`, `dev`, `feature/*`)

## Notes for Next Plan

The next plan should focus on **Phase 1 Core Auth + JWT + Events/Reservations baseline**, starting from `feature/phase-1-auth-jwt`.
