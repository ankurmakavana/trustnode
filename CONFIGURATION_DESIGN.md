# TrustNode — Runtime configuration and settings: audit and design

Status: **audit and design only.** No configuration, schema, data or runtime change is made by this document.
Each rollout step below needs approval where the step is marked.

Recovery note: an earlier version of this file was present but untracked and was overwritten before it was committed.
Its content could not be recovered. The transcript has no copy of it, the file-history backup for that write holds no content,
no other copy exists on disk, and the two stash entries contain no untracked file. This version is the only copy.

Source of truth: `TRUSTNODE_IMPLEMENTATION_PLAN.md` (Phase 7, and the non-negotiable rules).

## 1. Runtime configuration sources (verified)

Non-secret values only. Secret values (passwords, keys, tokens) are never recorded here.

| Setting | Host `.env` (used by host PHP and host `artisan`) | Containers (`compose.dev.yaml`: php, worker, scheduler, agent) | Framework default |
|---|---|---|---|
| `DB_CONNECTION` | `mysql` | `mysql` | `sqlite` |
| `DB_HOST` | `127.0.0.1` (MySQL is **not published** to the host) | `mysql` (service name) | `127.0.0.1` |
| `DB_DATABASE` | **`trustnode`** | **`trustnode_fresh`** | `laravel` |
| `CACHE_STORE` | `database` | `redis` | `database` |
| `QUEUE_CONNECTION` | `database` | `redis` | `database` |
| `SESSION_DRIVER` | `database` | `redis` | `database` |
| `REDIS_HOST` | `127.0.0.1` (Redis is not published) | `redis` | `127.0.0.1` |
| `SCAN_HOST_DIR` | `C:\xampp\htdocs\external-project` | read through `.env` by Compose | `./storage/scan-mount` |
| `CORS_ALLOWED_ORIGINS` | unset | unset | local origins (`config/cors.php`) |

**Precedence:** Compose reads the host `.env` for variable substitution, but it hard-codes its own `environment:`
block for each service. Those hard-coded values override `.env` inside the containers. So the containers do not
follow `.env` for the database name, cache, queue or session.

## 2. The `trustnode` / `trustnode_fresh` mismatch (verified)

Both databases exist on the same MySQL server. They have diverged.

| | `trustnode` | `trustnode_fresh` |
|---|---|---|
| Used by | host `.env`; host-side `artisan` (cannot reach MySQL today) | every container |
| Users / organizations / teams | 1 / 1 / 1 | 1 / 1 / 1 |
| `codebase_projects` table | **missing** (migration not applied) | present, 0 rows |
| Scans | 3 | 3 |
| `agent_tasks` | 177 | 34 (all `agent.observe_env`, all failed before the ownership fix) |
| `agent_states` | 2 | 1 |

Migration state (read-only comparison of the `migrations` tables, Session log 5):

- `trustnode_fresh` has 5 migrations that `trustnode` does not: `2026_08_11_054000_create_integrations_table`
  (a different name from the one `trustnode` records), `2026_09_26_112502_add_agent_id_to_findings_table`,
  `2026_10_06_095121_add_user_id_to_agent_tasks_table`, `2026_10_06_142416_create_agent_activity_logs_table`,
  and `2026_10_07_113911_create_codebase_projects_table`.
- `trustnode` records `2026_08_11_053000_create_integrations_table`, which has no file in the repo. It is a stale
  record, probably from an earlier rename of that migration.
- Table counts: `trustnode` 59, `trustnode_fresh` 61. `agent_activity_logs` and `codebase_projects` exist only in
  `trustnode_fresh`. `findings.agent_id` and `agent_tasks.user_id` exist only in `trustnode_fresh`.
- The `integrations` table has the same columns in both databases.

Consequences:
- Anything run from the host with `.env` writes to a database the running agent does not read.
- The `trustnode` database lacks the project schema the workflow needs. A host-side project command would
  fail there.
- Neither database is authoritative today. Choosing one is a data decision, not a code change.

## 3. Cache, queue and session

- Containers use Redis for all three. The host uses the database for all three. Both work, but the database
  cache uses the `cache_locks` table, and the host and container share no queue or session state.
- The setup lock (`SetupController`) and the agent's heartbeat both use the cache. The heartbeat lease is
  therefore per cache store. A host process and a container do not see each other's lease. This matters
  if an operator runs `agent:run` on the host while the container is running.
- Redis is not published to the host, so host-side `artisan` cannot use the container queue.

## 4. Settings: global versus organization-specific

### 4.1 Current behaviour (verified)

- The `settings` table has `key` **unique** (migration `2026_08_18_194531`). `organization_id` was added later
  (`2026_09_13`) and the unique constraint was not changed.
- `Setting` is organization-owned, so `TenantScope` hides rows that have no organization from every user.
- Only mail settings use the table (`SettingController`, `MailSettingsApplier`).
- Writes by an authenticated user now assign that user's team organization (`f898739`).
- A write with **no** authenticated user cannot see the existing row under the scope, so it inserts and hits the
  unique key on `key` (verified by `MailSettingsTest`, documented in the progress log).
- Runtime reads of mail settings use `Setting::globalValues()`, which bypasses the scope (`e452a3d`).

### 4.2 Classification

| Setting | Class | Reason |
|---|---|---|
| `mail_*`, SMTP host/port/username/encryption, `mail_password` | **Installation-wide** | One SMTP configuration per installation. Runtime processes need it with no user. |
| `mail_from_address`, `mail_from_name` | Installation-wide | Same reason. |
| Future: retention, scan defaults, feature switches | Installation-wide unless proven otherwise | Single-developer product. |
| Integration credentials (`IntegrationCredential`) | **Organization-owned** (via the integration) | Already encrypted and bound to their integration. No change needed. |
| Future: per-project scan profile, per-organization notification channel | Organization-owned | Belongs to a tenant, if and when tenants exist. |

### 4.3 Recommendation

1. **Keep mail and other installation-wide settings global**: `organization_id` NULL, read with
   `Setting::globalValues()`, written only by an authenticated administrator.
2. **Do not make the unique key composite yet.** `unique(key)` is correct while every key is global.
   Changing it to `unique(organization_id, key)` is needed only when an organization-specific key is first
   introduced. That change is a schema migration and needs its own approval.
3. **Fix the writes without a user**: when no user is authenticated, write global keys with an explicit
   unscoped lookup (`withoutGlobalScope(TenantScope::class)`), since the key is global. Organization-owned keys
   without a user stay unwritable (fail closed).
4. **Keep the existing rule** that an authenticated write assigns the user's team organization. For global keys
   this is stored but not used for visibility, since reads are unscoped.

### 4.4 Migration and compatibility

- Rows written before `f898739` have NULL `organization_id` (global, correct). Rows written after have the
  administrator's organization. Both are read by `globalValues()`, so no data migration is needed for reads.
- Global keys should be written with NULL organization so the table's meaning is consistent. Doing that
  means changing `setValue` for global keys, which is a behaviour change. It needs a test per key and a backfill
  of the post-`f898739` rows. The backfill is a data write and needs approval.
- If per-organization keys are added later: (a) add a composite unique index on `(organization_id, key)` with
  NULL handling, (b) keep the old unique key until every row is migrated, (c) drop it last. Each step is a
  separate, reversible migration.

## 5. Recommended configuration design (single installation)

1. **One database name, one place.** Use `trustnode` as the canonical database, because it holds the setup
   user and the host configuration points at it. Compose should read `DB_DATABASE` from `.env` (for example
   `${DB_DATABASE:-trustnode}`) instead of a literal. This needs an approved edit to `compose.dev.yaml`.
2. **One data store per runtime, shared by all processes.** Use Redis for cache, queue and session in every
   process that shares the installation. Host-side `artisan` should run inside the `php` container
   (`docker compose exec php php artisan ...`), as the installers already do. That keeps the host and the
   containers on the same queue and lease.
3. **Schema reconciliation before data.** Bringing `trustnode` up to date needs a per-migration plan. The full plan is in
   `DEPLOYMENT_RUNBOOK.md` (section 2). In short: the `integrations` migration `2026_08_11_054000` is guarded by
   `Schema::hasTable('integrations')`, and the table already exists in `trustnode` with identical columns, so it is a
   no-op there. The other four pending migrations create or alter objects that are absent, so they must be applied.
   Do this only after a backup and approval. Do not copy `trustnode_fresh` into `trustnode` without a reviewed merge
   plan, because the two diverged in both schema and data.
   *Correction:* an earlier version of this section said the integrations migration would fail on `trustnode`. That was
   wrong; the guard prevents it.
4. **Retire `trustnode_fresh` last.** Keep it until the canonical database has been verified for the full
   workflow. Then remove it with approval.

## 6. Rollout plan (each step approval-gated unless stated)

| Step | Action | Approval | Verification |
|---|---|---|---|
| 0 | This design reviewed | owner | — |
| 1 | Make compose read `DB_DATABASE` from `.env`; keep the default `trustnode` | **yes** (edits runtime config) | `docker compose config` shows one database name; no service is recreated by the edit alone |
| 2 | Back up both databases | **yes** (data) | backup files exist and restore to a scratch database |
| 3 | Apply pending migrations to `trustnode` | **yes** (schema) | migration status clean; `codebase_projects` present |
| 4 | Verify the full workflow against `trustnode` in a non-live way | **yes** (scan run) | isolated tests pass; a live run only after the acceptance approval |
| 5 | Retire `trustnode_fresh` | **yes** (irreversible) | nothing reads it for a full cycle |
| 6 | Settings: write global keys unscoped; document the global keys in code | no (local code, tested) | `MailSettingsTest`; a test per global key |
| 7 | Composite unique key, only if organization-specific keys are needed | **yes** (schema) | migration tested on a copy |

## 7. What this document does not decide

- Which database should be canonical. The recommendation is `trustnode`, but the data trade-off (1 user,
  177 vs 34 agent tasks, schema version) is a decision for the owner.
- Whether the containers should keep Redis for session, or switch to the database. Both work; Redis is the
  current container choice and is recommended for the shared-lease reason above.
