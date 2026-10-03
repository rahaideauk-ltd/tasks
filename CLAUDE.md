# Growth Tasks (Laravel 13 + MySQL)

Client onboarding → data sync (homepage, GSC, GA4, Clarity) → rules + Claude generate draft tasks in dynamic categories → admin publishes rounds → client answers (done / not done + reason) → admin reviews → repeat.

- Services live in `app/Services`; orchestration is `ProjectAnalyzer`, background entry point is `Jobs/AnalyzeProject`.
- Task statuses are constants on `App\Models\Task`; drafts are `round = 0`.
- Internal knowledge (admin-only, never on the portal): `ProjectFact` (dynamic fields, any `kind`), `projects.brief` (admin-written, Claude reads only), `projects.memory` (Claude rewrites it each analysis; every version in `memory_revisions`), `Skill` (playbooks, global or per project; tasks link via `skill_id`). All of it is fed to Claude in `AiTaskGenerator::generate`.
- UI strings: write English in Blade with `__()`, add the Persian translation to `lang/fa.json`. Status/priority labels live under `status.*` / `priority.*` in both `lang/*.json`.
- No asset build: Tailwind + Alpine from CDN. Keep it that way unless asked.
- Local dev can use SQLite (`DB_CONNECTION=sqlite`); production is MySQL. Run `vendor/bin/pint` before committing.
