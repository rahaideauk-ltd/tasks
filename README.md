# Growth Tasks

A small Laravel + MySQL app that turns a business owner's website into a living task list.

1. The client opens `/new`, enters the business name, site URL, description and goals, and gets a **private link** (`/p/{token}`).
2. The system fetches the homepage, pulls data from **Google Search Console**, **GA4** and **Microsoft Clarity** (once the client connects them from their page), runs a **rule engine** and asks **Claude** for the next round of tasks. Categories (SEO, sales, UX, content, …) are decided dynamically by the AI per business.
3. The admin reviews the drafts at `/admin`, edits or adds tasks, and publishes them as a **round**.
4. The client sees the tasks grouped by category and answers each one: **I did it** (with a link/note) or **I didn't / can't** (reason required).
5. The admin approves, sends back with feedback, reopens or drops each answer, then runs the analysis again for the next round.

UI is bilingual (Persian RTL / English) with a switch in the header.

## Requirements

- PHP 8.3+, Composer
- MySQL 8 (or MariaDB 10.6+)
- No Node build step: Tailwind and Alpine load from CDN.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_*, APP_URL, and the keys below
php artisan migrate
php artisan admin:create you@example.com 'a-strong-password'
php artisan serve          # http://localhost:8000
```

Admin panel: `/admin` · Client onboarding: `/new`

## Keys and integrations

| Variable | Where to get it |
|---|---|
| `ANTHROPIC_API_KEY` | console.anthropic.com → API keys. Without it only the rule engine runs. |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google Cloud Console → APIs & Services → Credentials → OAuth client (Web). Enable **Google Search Console API**, **Google Analytics Data API** and **Google Analytics Admin API**. Add the redirect URI `{APP_URL}/auth/google/callback`. While the OAuth consent screen is in *Testing*, add each client's Google email as a test user, or publish the app. |
| Clarity API token | The client generates it in Clarity → project → Settings → Data Export → *Generate new API token*, and pastes it on their page. The export API returns the last 1–3 days and allows 10 requests per project per day. |

Google tokens and Clarity tokens are stored encrypted (`APP_KEY`).

## How analysis works

`App\Services\ProjectAnalyzer`:

- `sync()` fetches the homepage (`SiteCrawler`), Search Console (`GoogleService::fetchSearchConsole`), GA4 (`fetchGa4`) and Clarity (`ClarityService`). Per-source errors are stored in `projects.data.errors`, never thrown.
- `analyze()` runs `RulesEngine` (deterministic checks such as low CTR pages, striking-distance queries, rage clicks, missing HTTPS/viewport/meta) and `AiTaskGenerator` (Claude with a JSON schema: categories + bilingual tasks + a short assessment). Results become **draft** tasks; duplicates by title are skipped.
- Drafts are published as the next round from the admin page, or automatically with `TASKS_AUTO_PUBLISH=true`.

Analysis runs through the `AnalyzeProject` job (`AnalyzeProject::start()`). With `QUEUE_CONNECTION=sync` it runs right after the HTTP response inside the web process, so PHP-FPM's `request_terminate_timeout` must allow a few minutes; with `database` run `php artisan queue:work` (recommended in production). A run with no progress for 15 minutes is treated as stuck and can be started again.

The homepage crawler only fetches public `http(s)` addresses on ports 80/443 (private, loopback and reserved IPs are refused, on every redirect). `POST /projects` is limited to 5 per hour per IP.

## Task lifecycle

```
draft ──publish──▶ todo ──client──▶ submitted ──admin──▶ approved
                     ▲                 │                 └──▶ rejected ──client──▶ submitted
                     │                 └──▶ not_done ──admin──▶ todo (reopen) | dropped
```

## Project layout

```
app/Models            Project, Category, Task
app/Services          GoogleService, ClarityService, SiteCrawler, RulesEngine, AiTaskGenerator, ProjectAnalyzer
app/Jobs              AnalyzeProject
app/Http/Controllers  OnboardingController, PortalController, GoogleAuthController, Admin/*
resources/views       components/layouts/app, onboarding, portal, admin
lang/fa.json          Persian UI strings (English strings are the keys)
```
