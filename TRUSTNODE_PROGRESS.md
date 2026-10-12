# TrustNode — Implementation Progress

Companion to `TRUSTNODE_IMPLEMENTATION_PLAN.md`. The canonical plan is not edited here.

**Standing authorization (recorded from owner instruction):** continue autonomously across phases for safe, in-scope work. Stop only for: starting, stopping, restarting or recreating services not already authorized; production changes; destructive data or volume operations; deleting user data or untracked files; changing secrets or `.env`; dependency installs outside the plan; Git reset, clean, checkout-over-work, commit or push; changes to product direction or repository boundaries.

## Phase 0 — Read-only audit
Done. Report delivered to owner. No code changes.

## Phase 1 — P0 local security hardening
| Item | Status | Evidence |
|---|---|---|
| Remove unauthenticated `/debug-db` | Done (working tree) | Route absent from `routes/web.php`; live `GET` returns 200 SPA shell with 0 sensitive matches |
| Loopback binding for web port 8000 | Done, runtime-verified | `docker port`: `127.0.0.1:8000`; loopback HTTP 200 |
| Loopback binding for Vite 5173 | Done, runtime-verified | `docker port`: `127.0.0.1:5173`; `/@vite/client` 200 |
| Compose canonical file and installers | Done | `compose.dev.yaml` only; installers reference it; `compose.yaml` absent |
| One-time setup, lock on owner creation | Done in code; **concurrency not runtime-verified** | `SetupController`: `Cache::lock` + owner re-check inside lock; 409 when lock held; `SetupTest` 10/10 (fake lock) |
| Cache lock backend available | Verified | `cache_locks` table present in `trustnode` and `trustnode_fresh` |
| Setup failures do not leak details | Done | Response has no `error` key; log records exception class and line only; test `test_setup_failure_does_not_leak_exception_details` |

**Phase 1 open gap:** a real parallel-request race test against a database has not been run. It would need an isolated, empty database, which the rules here do not allow me to create or reset.

## Phase 2 — Dependable developer scan workflow
In progress.

| Item | Status | Evidence |
|---|---|---|
| Directory boundary must end at a separator (project create, project scan, workspace listing) | Done in code, tests pass | `CodebaseProjectController` checks now require `mount . DIRECTORY_SEPARATOR`. Red-then-green: `test_sibling_directory_sharing_the_mount_name_prefix_is_rejected_on_create` and `test_scan_rejects_project_path_resolving_to_a_sibling_directory` failed on the old code (201 and 200), pass now. `CodebaseProjectApiTest` 18/18. |
| Agent capability boundary (`AgentScanCodebaseCapability`) | Already correct, no change | Prefixes already carry a trailing separator (line 88). Phase 0 finding P2-1 applied only to the controller. |
| Scheduled `agent.observe_env` ownership | Implemented and tested; **not deployed** (scheduler not restarted, runtime still failing) | New `App\Services\AgentScheduledTasks` resolves the lowest-id active administrator who belongs to a team, sets `TenantContext` only around the enqueue, clears it in `finally`, and enqueues nothing if no such user exists. `routes/console.php` observe closure now uses it. `AgentScheduledTasksTest` 9/9 including the real `schedule:test --name=agent.observe_env` run. |
| Scheduled continuous scan ownership | **Deliberately not changed** | It scans the whole scan mount root, not approved projects. Giving it an owner would start unapproved scans. It stays fail-closed (ownerless, rejected). Needs scoping to registered active projects first. |
| Agent-side grant for `agent:observe` | Verified by code read | Grants are added in `AgentServiceProvider::startAgent()` in the `agent:run` process, which is also the process that runs `AgentWorker::runOnce()`. The owned task therefore passes the existing READ_ONLY grant. Not runtime-verified. |

### Server-side secret redaction (owner condition before observe_env is enabled)
| Item | Status | Evidence |
|---|---|---|
| Shared redactor `App\Services\SecretRedactor` (token shapes, PEM blocks, URL credentials, bearer tokens, sensitive key assignments; fails closed) | Done, tested | `SecretRedactorTest` 9/9 |
| Scanner evidence: Secret category now a full placeholder `[REDACTED: <rule>]` (old masking kept first and last 4 characters); other categories redacted before truncation | Done, tested | Red-then-green: `test_secret_category_evidence_is_fully_redacted_without_partial_reveal` failed on old code (`AKIA************FAKE`); `test_secret_in_non_secret_category_evidence...` failed on old code (raw password returned) |
| Agent finding persistence: title, description, technical details, remediation, evidence redacted before storage | Done, tested | `test_agent_reported_finding_is_stored_without_raw_secrets_in_any_text_field` |
| API output: `FindingResource` uses the shared redactor for description, business impact, remediation, and non-secret evidence/technical details; secret-category placeholder unchanged | Done, tested | `test_api_response_redacts_token_shapes_in_non_secret_category_findings` (was 404 fixture; fixed) |
| Worker error log sanitizer | Done | `AgentWorker::sanitizeError` now also calls the redactor |
| Observed `.env` path end to end (real scanner and observe handler; queue payload captured) | Done, tested | `test_observed_env_credentials_are_not_enqueued_in_raw_form`. Note: this would pass on the old code for the AWS key alone, because old masking hid most of it. It is an end-to-end regression, not the proof; the scanner test is the proof. |
| Enum-to-string defect blocking high-severity agent findings | Fixed | `HighSeverityFindingNotification` interpolated the severity enum. Fixed via `severityValue()`. `AgentHealthApiTest` now passes. |
| Existing test assertion changed | Yes, disclosed | `AgentWorkerTest::test_secret_redaction_in_activity_logs` expected `API_KEY=***`; now expects the shared `[REDACTED]` placeholder. Secret values are still asserted absent. |

Known limits: the redactor is pattern-based. It does not catch arbitrary secrets with no key name and no known token shape. Prose like `Password: must be 12 characters` is over-redacted (safe direction). The client-side regex in `FindingDetailPage` remains as a second layer only.

## Verification log
- Full suite (acceptance gate, after Phase 1 changes): Tests 637, Assertions 1605, Errors 22, Failures 29, Deprecations 7, Skipped 3, Incomplete 1. Failing set identical to the prior run; no new failures.
- Full suite (after Phase 2 boundary batch): Tests 648, Assertions 1630, Errors 22, Failures 29, Deprecations 7, Skipped 3, Incomplete 1. Failing set identical to the prior run; no new failures.
- Full suite (after server-side redaction batch): Tests 661, Assertions 1679, Errors 21, Failures 29, Deprecations 7, Skipped 3, Incomplete 1. No new failures; one error resolved (`AgentHealthApiTest::test_agent_observation_creates_scan_record_and_propagates_scan_id`).
- Pre-existing failures are not labelled pre-existing: no committed-baseline run has been done.

- Focused results (this batch): `AgentScheduledTasksTest` 18/18; `SecretRedactionTest` 8/8; `SecretRedactorTest` 9/9; `AgentHealthApiTest::test_agent_observation_creates_scan_record_and_propagates_scan_id` passes in isolation.
- Full suite (after Tasks A–C): Tests 674, Assertions 1718, Errors 21, Failures 29, Deprecations 7, Skipped 3, Incomplete 1. Failing set identical to the prior run; no new failures.
- Pre-existing failures are not labelled pre-existing: no committed-baseline run has been done.

### Task A — Continuous scans scoped to authorized projects (done, tested)
- `AgentScheduledTasks::enqueueProjectScans()`: owner = lowest-id active administrator with a team. Projects: `status` active, `continuous_scan_enabled`, `organization_id` in the owner's organizations. Soft-deleted projects excluded (only the tenant scope is removed). One `agent.capability.execute` per project, payload `arguments.project_id`, owned by the administrator via the existing enqueue path. Skips a project that already has a pending or processing task for it (payload decoded, not `LIKE`). Fails closed with no owner or no organization.
- The scheduler closure no longer scans the mount root. It keeps the mount-existence guard and the active-agent guard.
- Tests cover: eligible project (one task, owned, `project_id`, no `target_path`), archived, continuous disabled, soft-deleted, other organization, no owner, duplicate pending, two eligible projects, and the real `schedule:test --name=agent.continuous_scan` run.
- Not run red: these tests were not run against the old closure. The old closure enqueued a `target_path` task, which the new assertions reject.
- Dropped: the old "scan completed recently" throttle keyed on the mount path. The cron interval now bounds frequency, and the pending check prevents overlap.
- Capability note: the capability's org check uses `$user->organizations()->first()`. If the owner belongs to several organizations, a project in a later one is rejected by the capability (fail closed).

### Task B — `AgentHealthApiTest` no longer writes the real `.env` (done, tested)
- The test swaps the base path to a temporary directory, asserts `base_path()` is that directory and not the real `.env`, and records the real `.env` SHA-256 before and after (the contents are never printed). The base path is restored and the temp directory removed in `finally`.
- Verified: the test passes, and the real `.env` mtime was identical before and after the isolated run (the earlier runs changed it on restore).

### Task C — Redaction coverage review (gaps found and fixed)
Real gaps found by review:
1. **Storage:** finding writers outside the agent path (scan jobs with `updateOrCreate`, imports via `FindingMapper::create`, API via `FindingService`) did not redact. Fixed once at the model: `Finding` `saving` hook redacts `title`, `description`, `technical_details`, `business_impact`, `remediation`, `evidence` on every save.
2. **Reports:** `GenerateScanReport` passes stored rows straight into the PDF template, so rows stored before this change would render raw. Fixed in memory with `Finding::redactSecrets()`. The model is not saved.
3. **Stored HTML fallback:** `ScanController::downloadReport` renders a stored `html_report`. It is now redacted before `Pdf::loadHTML`.

Tests: `test_every_finding_write_path_stores_redacted_text` (create and `updateOrCreate`), `test_legacy_stored_raw_text_is_redacted_when_the_row_is_next_saved`, `test_report_input_is_redacted_in_memory_and_the_stored_row_is_not_changed`. Not run red (reasoned against the old code, not executed).

Known limits and what is not covered:
- **Legacy rows are not rewritten.** Rows stored before this change still hold raw text until each is next saved, or until a backfill. A backfill is a data write and needs approval.
- **Legacy PDFs on disk are not regenerated.** Files written before this change, served from `file_path`, still contain raw text. Regeneration is a data action.
- **The download fallback wiring has no direct test.** It is one line. Covered by reading, not by a test.
- Pattern-based only. No universal secret detection is claimed. Prose such as `Password: must be 12 characters` is over-redacted (safe direction).

## Hazards found
- Resolved for the observed-`.env` test (Task B). The same risk pattern may exist elsewhere; not audited beyond the observe and health tests.

## Deployment plan (not performed — needs your approval)
Running processes keep the code they loaded. Nothing below has been restarted.

| Container | Why it must restart | Changes it loads |
|---|---|---|
| `scheduler` (`schedule:work`) | Loads `routes/console.php` once at start | observe_env ownership, continuous project scans |
| `agent` (`agent:run`) | Loads the worker, handler, and scanner classes once at start | `AgentWorker` sanitizer, `AgentReportFindingTaskHandler` redaction, `AbstractRegexScanner` evidence, `Finding` saving hook, owned observe tasks |
| `worker` (`queue:work`) | Loads queued notification classes once at start. `TrustNodeNotification` implements `ShouldQueue` | `HighSeverityFindingNotification` enum fix |
| `php` (nginx→php-fpm) | Usually picks up changes per request, but this is unverified if opcache does not revalidate | `FindingResource`, `ScanController`, `GenerateScanReport`, `Finding` model |

Recommended order, after approval: restart `php` first (cheap, reversible), then `worker`, then `agent`, then `scheduler`. Enable observed `.env` scanning only after the `agent` and `scheduler` restarts succeed, and verify the process state before enabling. Stop and ask if any container restart fails.

Verification to run after the restart, read-only: `docker inspect` for restart state; read `agent_states` for a fresh heartbeat; read `agent_tasks` for the observe_env task status and `user_id` (must be set); confirm no raw `.env` values in `findings` (read counts and key names only).

## Deployment record (2026-10-12, times UTC)

Authorized: restart of `php`, `worker`, `agent`, `scheduler` with `compose.dev.yaml`. Dry-run before acting: `up -d` plans no recreation (all Running, config unchanged), so loading code required `docker compose restart` of the four services. No container was recreated, and container IDs match the pre-deployment record.

| Service | Action | Result |
|---|---|---|
| `php` | restart at about 12:2x | Running, healthy. Web `/` and `/api/system/status` return 200. |
| `worker` | restart | Running, 0 restarts, 0 error lines since restart. |
| `agent` | restart at 12:28:38 | **Crash loop for about 2 minutes** (9 restarts). Cause: on startup the new process found the previous runtime's heartbeat still fresh in the cache (90 s TTL) and aborted with `Failed to acquire agent ownership`. It recovered on its own at 12:30:32 once that heartbeat expired. Now running; heartbeat advanced 12:30:34 → 12:31:07. |
| `scheduler` | **not restarted** | Not needed (see below). |

**Why the scheduler does not need a restart:** `schedule:work` runs `schedule:run` as a child process each minute (`ScheduleWorkCommand` uses `Process::fromShellCommandline`). Each child boots the application and loads the current `routes/console.php`. The scheduler's `observe_env` ownership change and continuous-scan change therefore already took effect when the file was edited. Evidence: owned observe tasks appeared at 12:01 while the scheduler container had been running since 10:36.

**Important disclosure: observation became live before redaction was active.** The owner's condition was to enable scheduled observation only after redaction was live. The file change went live when the file was edited, so owned observation ran before the agent process loaded the redaction code:
- 27 owned `observe_env` runs completed between 12:01 and 12:27, under the **pre-redaction** agent (loaded at 10:36).
- One `Secret` finding (id 1, `high`, "Exposed Private Token or Secret") was created at 12:01:03 from that code. Its evidence is 34 characters and contains a mask (`*`), which is the old partial-reveal format (first and last four characters kept). No full raw value matched the raw-pattern checks. The partial reveal is still present in the database.
- Four further owned runs completed after 12:28 (one before the agent restart, the rest under the redacted agent loaded at 12:30:32). They created no new findings or scans, because the handler skips an unchanged file.

**Verified after deployment (read-only):**
- Agent: running, no restarts since 12:30:32, heartbeat advancing.
- Owned observe tasks: every one has `user_id = 1`, which is the existing active administrator with the admin role.
- No `agent.capability.execute` tasks exist. No unapproved scan ran.
- `codebase_projects` has 0 rows. The continuous-scan scheduler has no eligible project, so it queues nothing.
- Scans created since 12:00: one, the `.env` observation scan at 12:01:02.
- Redaction in the new code is active in `php` (`FindingResource`, report paths), `worker` (notification fix), and `agent` (scanner, report handler, `Finding` saving hook, `AgentWorker` sanitizer).
- Not verified: an end-to-end live check of a redacted new finding (none was created after the agent restart, because the file was unchanged).

**Not done (needs your decision or approval):**
1. **Keep or pause scheduled `.env` observation.** It is running now under redacted code. Pausing needs a code change to the observe closure in `routes/console.php`, which I have not made.
2. **Finding id 1 holds a partial mask** from pre-redaction code. Rewriting or deleting it is a data write.
3. **Legacy rows and PDFs** still hold pre-redaction text until a backfill (data write) or regeneration.
4. **Live acceptance scan.** Needs one approved project and permission to register it in the live database. Path `external-project` is not approved.
5. **Two stuck scans** (ids 2 and 3) are untouched.

## Autonomous batch (after deployment): decisions and verified work

### Observation decision (recorded)
- **Decision: keep scheduled `.env` observation running.** Basis: the processes that persist and output findings loaded the redaction code. Each relevant file's last modification predates its process start: `php` 12:28:11 (files modified 12:08–12:21), `worker` 12:28:37 (files 12:07–12:21), `agent` 12:30:32 (files 12:08–12:21, including `console.php` at 12:19). The redactor is also present in the agent container (synthetic input returns `password=[REDACTED]`).
- Exposure during the window: 27 owned runs (12:01–12:27) ran under pre-redaction code. They produced one finding (id 1), with partial-mask evidence.
- Owned runs since 12:28 (4 completed) created no new finding or scan. The observe handler skips an unchanged file.

### Finding id 1 (legacy partial mask)
- Metadata: `high`, `open`, `Secret`, created 12:01:03. Evidence is 34 characters with 26 asterisks, so 8 visible characters remain (the old first-four and last-four shape). `technical_details` is not masked. The finding's activity log holds no masked value. No `agent_tasks` payload carries evidence text. No `reports` or `scan_reports` rows exist, so no legacy PDF exists.
- Code remediation (done, tested): `Finding::hasLegacyPartialMask()` (Secret category only, exact old shape) and `redactLegacyPartialMask()` (in memory). Artisan command `findings:remediate-legacy-masks`: dry run by default; prints ids and counts only; `--apply` replaces the evidence with `[REDACTED: legacy partial mask]` and writes an activity entry (`legacy_evidence_redacted`) with no value. `LegacyMaskRemediationTest` 7/7.
- Live dry run (read-only, inside the `php` container): `1 finding(s) matched: 1`. **Not applied.** Applying is a single-row data write and needs your explicit authorization (see the question at the end).
- Residual exposure until applied: the first and last four characters of one credential remain in `findings.evidence` for id 1.

### Stuck scans 2 and 3 (lifecycle inspection, not changed)
- Scan 2: `web_application`, engine `owasp_zap`, target `https://auth.internal/sso/login`, status `running`, started 2026-10-10 16:37, no organization, created by user 1.
- Scan 3: `api_vulnerability`, engine `nuclei`, target `corp.internal`, status `queued`, no organization, created by user 1.
- Neither can execute. `owasp_zap` and `nuclei` exist only as enum values, with no implementation. The `jobs` table is empty and Redis has no queue keys. No code path picks these rows up.
- Their targets are not authorized anywhere in this project. They are a data-integrity issue, not an active scan risk.
- The existing stale-scan cleanup (`RepositoryController::scan`) only handles repository scans with a `repository_id`, so it does not apply to these rows. Changing them is a data write and needs your authorization.

### Test and code fixes (verified)
- `RepositoryScanLifecycleTest` 7/7: the fixture gave the admin no membership in the repository's organization, so `TenantScope` correctly returned 404. The admin is now made a member of it, as a real user would be. This does not weaken the tenant check.
- `AgentOrchestrationTest` 15/15: the tenant context is set immediately before each capability `execute()`. `Queue::after` clears it when any sync job finishes, and `start()` dispatches one in tests. The worker sets the context right before each handler, so the product path is not affected.
- `ScanController::downloadReport`: a hidden scan now returns 404, not 403, matching every other endpoint. The creator comparison uses integer casts, and a missing user fails closed. `TenantIsolationTest` 7/7.

### Full suite (after these batches)
- Tests 681, Assertions 1756, Errors 19, Failures 20, Deprecations 7, Skipped 3, Incomplete 1. No new failures. 11 failures resolved (39 remain, down from 50).
- Remaining groups: finding identity, lifecycle, baseline, and counts (about 18; Phase 2 persistence, investigating next); Agent bootstrap and takeover (13; Phase 4, and these tests encode the takeover rule behind the restart loop); integrations and mail settings (5); IMAP (1; needs the `webklex/php-imap` vendor install, which requires approval); and two scan-relationship and posture tests.

### Finding persistence and visibility fixes (verified)
- **Real product defect fixed:** `ScanService::create()` never set `organization_id`, but `storeLocal()` did. Every scan started through `POST /api/scans` (the wizard path) had no organization, so its findings were invisible even to the user who started it under `TenantScope`. Fix: default the organization from the creator's current organization or first team, the same rule `storeLocal()` uses. Regression test: `ScanE2ETest::test_scan_created_through_the_api_is_assigned_to_the_users_organization` (3/3 in the class). Not run red.
- **Fixture corrections (not product changes):** identity, lifecycle, baseline, finding-count, posture, scan-management, and E2E tests created scans and users without the membership that production gives them. They now add the owner's organization and team, as setup does. Direct `handle()` calls in these tests now set the tenant context from `getTenantId()`, as the queue listener does.
- **Known inconsistency, not changed:** finding visibility is derived only from the scan's organization. A scan with no organization (legacy rows) is visible to its creator through the scan's creator fallback, but its findings are not. That is the current `TenantScope` design. Fixing it means changing a security boundary, so it is recorded for your decision.
- `RepositoryScanLifecycleTest` 7/7, `FindingIdentityVerificationTest` 6/6, `FindingLifecycleIntelligenceTest` 7/7, `ScanBaselineRegressionTest` 5/5, `ScanFindingCountTest` 1/1, `ScanManagementTest` 5/5, `SecurityPostureDashboardTest` 6/6, `ScanE2ETest` 3/3.
- **Shared test-base fix:** `tests/TestCase.php` now clears the static `TenantContext` before and after every test. `TenantContext` is static, so one test's tenant leaked into another (this made `AgentHealthApiTest::test_api_tenant_isolation_for_activity_timeline` order-dependent). Production code is not affected.

### Organization and team scope audit (per owner direction, no expansion)
Question: why are organizations and teams involved in project authorization and scheduled scans?

Evidence (file and line):
- **Project creation:** `CodebaseProjectController::store` (about lines 112–125) finds the user's team, takes its `organization_id`, and returns 403 if there is none. The project is stored with that organization.
- **Project scan:** `CodebaseProjectController::scan` checks the mount boundary and an active agent. It does not check organization directly; `TenantScope` applies on the project lookup.
- **Scheduled project scans:** `AgentScheduledTasks::enqueueProjectScans` uses the local administrator's organizations and selects only projects in them (`AgentScheduledTasks.php` about lines 87–100). No organization is created or switched.
- **Capability execution:** `AgentScanCodebaseCapability` (lines 76, 94) takes the owner's first organization and rejects a project whose organization differs.
- **Visibility:** `TenantScope` (`app/Models/Scopes/TenantScope.php`) shows organization-owned rows only through team membership. `Finding` and `ScanReport` are derived through their scan's organization.
- **Scan creation:** `ScanService::create` (fixed above) and `storeLocal()` assign the creator's team organization.
- **Organization-management surface:** no organization, team, or switching routes exist. `routes/api.php` has only the invitation accept/show routes (lines 59–60). `current_organization_id` is referenced in three places and is not defined on the model or in any migration, so it is always null and falls back to the team.

Classification:

| Component | Class | Basis |
|---|---|---|
| Default organization and default team created by setup | **Required security/compatibility infrastructure (keep)** | `TenantScope` visibility, project creation, and capability checks all depend on it. Setup creates it for the single admin. |
| Team membership of the local admin | **Required security infrastructure (keep)** | It is the only way `TenantScope` grants the admin access to the admin's own data. Removing it would weaken or rewrite the boundary. |
| `TenantScope` and `organization_id` columns | **Required security boundary (keep)** | Owner instruction: do not remove, bypass, or weaken. |
| Creator fallback for NULL-organization rows | **Legacy compatibility (keep, document)** | Covers rows created before organizations were assigned. |
| Finding and report visibility derived only from scan organization | **Unclear, needs your decision** | Causes legacy NULL-organization scans to show without their findings. |
| Invitation accept and show routes | **Unnecessary for the single-developer product (candidate to remove, needs your decision)** | Only purpose is adding users to an organization. Multi-user provisioning is outside the workflow. |
| `current_organization_id` references | **Legacy, unnecessary (dead)** | Never defined; always null. Removal is safe but is a cleanup, not required now. |
| Multi-organization behaviour (`organizations()` returns all; capability uses `first()`) | **Unnecessary product complexity, safe today** | Single-admin installs have one organization. A second organization would need a product decision. |

Recommendation (smallest change, no expansion):
1. Keep the setup-created default organization and team as the single local tenant. Keep `TenantScope`, `organization_id`, and team membership unchanged.
2. Keep scheduled tasks owned by the local administrator, and keep project selection limited to the administrator's organization. No new users, organizations, or switching are added.
3. Do not add any organization, team, or invitation UI. Leave the invitation routes in place until you decide whether they should be removed.
4. Decide on the finding-visibility inconsistency for legacy NULL-organization scans. Options: a one-time backfill of those scans to the default organization (a data write), or a code change to `TenantScope`. Both need your approval.

No database migration, no data change, and no authorization change was made in this audit.

## Test suite status (after these batches)
- Full suite: Tests 682, Assertions 1860, Errors 14, Failures 5 (19 failing; 0 new against the previous run; 20 resolved in this batch). `.env` was not touched.
- Remaining 19, by group and ownership:
  - Agent bootstrap and takeover (13). Phase 4. The takeover rule caused the restart loop; a fix needs its own design.
  - Integrations (3) and mail settings (2). Outside the developer workflow.
  - IMAP (1). Needs `composer install` to restore `webklex/php-imap` (approval required).

## Git commit step (requested): blocked pending a decision, no commits made
Audit (read-only), `main` tracking `origin/main`, HEAD `4e31343`, no commits created:
- 97 status entries: 7 staged, 49 modified unstaged, 24 untracked, plus 2 partially staged. Most modifications and untracked files predate this session (the first snapshot already listed about 50 modified and about 25 untracked, including the CodebaseProject model, migrations, Integration/Mail services and frontend pages).
- Secret scan of every added diff line and untracked file: no real secrets. Only hits are synthetic `AKIAFAKE…`-shaped fixtures in `tests/Feature/SecretRedactionTest.php` and `tests/Unit/SecretRedactorTest.php`. Those are deliberate, but a push-time secret scanner may flag them.
- Artifacts that should not be committed without a decision: `console.json` (0 bytes), `public/test_report.pdf` (1203 bytes, in the web root), `trustnode.png` (4.2 MB), and `TRUSTNODE_IMPLEMENTATION_PLAN.md` (the owner's plan document). `.env` is git-ignored.
- `compose.dev.yaml` contains literal development database credentials that were already in HEAD. This change adds no new credential.

Why I did not commit:
1. **Mixed hunks.** Several files I edited also contain pre-existing user changes. `TenantScope` (24 insertions), `ScanController` (`downloadReport` rewrite already present), `AgentWorker`, `routes/console.php`, `AbstractRegexScanner`, `FindingResource`, and `CodebaseProjectController` (staged, with my unstaged hunks). Staging only my hunks would need hunk-level patches, and `git add -p` is interactive here.
2. **Commits would not build alone.** `AgentScheduledTasks` and the project-scope tests depend on `CodebaseProject` and its factory and migration, which are untracked user files. Committing my files without them would produce a broken commit.
3. **Unreviewed pre-existing work.** About 45 modified files and about 25 untracked files belong to agent, integration, mail, and frontend work I did not write and have not reviewed. Committing it under one of my messages would misstate what it is.

Decision needed (one question): **which commit strategy?**
- (A) One baseline commit of all current work, excluding `console.json`, `public/test_report.pdf`, `trustnode.png`, and the plan document, followed by focused commits for this session's changes; or
- (B) Only this session's changes as focused commits, using patch-based hunk staging, with the untracked CodebaseProject files included in the project-scope commit. This is slower and carries more risk of a mis-split.

## Next task
- Phase 3, finding-lifecycle verification, once the commit strategy is settled. Organization scope stays unchanged.

## Open decisions (approval-required, not performed)
1. **Apply the finding id 1 remediation** (one row, `--apply`). Data write.
2. **Stuck scans 2 and 3:** mark failed with a reason, or leave as is. Data write.
3. **Live acceptance scan:** needs one approved project and permission to register it. `external-project` is not approved.
4. **Legacy data:** backfill of finding rows (data write). No legacy PDFs exist, so no regeneration is needed.
5. **IMAP dependency:** `composer install` to restore `webklex/php-imap` (dependency install).

## Decisions
- **Answered:** scheduled `agent.observe_env` runs under the existing local admin identity, with normal authorization enforced, after server-side redaction.

## Commits (local only, not pushed; `origin/main` unchanged at `4e31343`)
Strategy (owner decision): Option B with safety-first changes. Focused commits only for reviewed, complete work. Mixed or ambiguous files left uncommitted.

| Commit | Message | Verified on the index state (before commit) |
|---|---|---|
| `5fc9df5` | feat(projects): register codebase projects inside the scan mount | `CodebaseProjectApiTest`, `CodebaseProjectTest`, `AgentScanCodebaseCapabilityTest`: 47 of 47 |
| `323b040` | feat(security): redact secrets server-side before findings are stored or shown | `SecretRedactorTest` 9 and `SecretRedactionTest` 8 pass (17 of 17). Baseline: HEAD export fails the same 7 pre-existing tests in `AgentObserveEnvTaskHandlerTest` and `FindingManagementTest` |
| `4d27bfe` | feat(findings): add a dry-run command to remediate legacy partial masks | `LegacyMaskRemediationTest` 7 of 7 |
| `fd0ac88` | fix(security): bind local web and dev-server ports to loopback; harden first-run setup | `SetupTest` 10 of 10 and `LocalSurfaceHardeningTest` 6 of 6 |
| `af4d20c` | fix(scans): assign new scans to the creator's organization | `ScanE2ETest` 3 of 3 |
| `9e793ca` | test: give fixtures the membership and tenant context production provides | 37 of 37 across seven classes |

**Correction to commit `323b040`:** its message says `SecretRedactionTest` is "5 of 5". The file has 8 tests, and all 8 pass. The message is not amended, because existing history is not rewritten.

**Full-suite attribution (exports of committed trees):**
- Base `4e31343`: 561 tests, 75 unique failing.
- New HEAD `9e793ca`: 644 tests, 49 unique failing. **No new failures versus base.** 26 previously failing tests are now fixed in the committed state.
- Working tree: 682 tests, 19 failing. The extra passes come from your uncommitted fixes in agent, notification, and integration code. They are not in these commits.

**Not committed, with reasons:**
1. **Scheduled ownership and project-scoped continuous scanning** (`AgentScheduledTasks.php`, `routes/console.php`, `AgentScheduledTasksTest.php`). Blocked. The ownership feature depends on uncommitted pre-existing work: the worker's ownership enforcement and the `user_id` write in `AgentQueue`, the `agent_tasks.user_id` migration, and `routes/console.php`'s continuous block, which has no HEAD version.
2. **Worker log-redaction line** (`AgentWorker::sanitizeError`) and its test (`AgentWorkerTest`). Blocked. My change is one line inside a whole method that is uncommitted pre-existing work.
3. **Download fallback and 404 change** (`ScanController::downloadReport`). Blocked. Mixed with your uncommitted `downloadReport` rewrite.
4. **Tenant-scope and organization changes** (`TenantScope.php`). Pre-existing uncommitted work. Not reviewed or committed by me.
5. **Test files with mixed changes** (`AgentOrchestrationTest`, `FindingManagementTest`, `AgentHealthApiTest`). Each contains your uncommitted changes alongside mine.
6. **Pre-existing unrelated work** (integration, mail, Agent orchestration, frontend, Docker, composer) and untracked artifacts (`console.json`, `public/test_report.pdf`, `trustnode.png`, `TRUSTNODE_IMPLEMENTATION_PLAN.md`). Left untouched, as directed.

**Security scan:** no real secrets in added diff lines or in the new files. The only token-shaped strings are the deliberate synthetic fixtures (`AKIAFAKE…`) in `SecretRedactorTest` and `SecretRedactionTest`. A push-time secret scanner may flag them. This is test data, not a credential.

**Remaining staged, unstaged, and untracked state:** the index holds the 7 previously staged files, now committed; no other staged changes. Unstaged and untracked work remains as listed above.

## Next steps
- Commit scheduled ownership and worker redaction once the pre-existing dependencies (`AgentQueue`, `AgentWorker` ownership, `agent_tasks` migration, `ScanController`, and `TenantScope`) are reviewed and approved for commit.
- Phase 3 finding-lifecycle verification: the lifecycle, identity, baseline, count, and posture tests now pass at HEAD (`9e793ca`) for the committed state. Remaining Phase 3 acceptance is blocked on the mixed-file decisions above.

## Session log: Git state, agent lease, and the next steps

### Git relationship (verified read-only)
- Local `main` = `origin/main` + 1 local commit. `origin/main` is at `5b100f1`. Local is not behind.
- Local-only commit: `2116dd0` (scan workflow test) and `12e2627` (agent test fixes). Both are unpushed.
- Unexpected remote history (reflog: "update by push"): `76bb8da` (18:56) and `5b100f1` (18:59). I did not push either. `5b100f1` is the sweeping commit. It contains the whole working tree that was uncommitted, including the four artifacts (`console.json`, `public/test_report.pdf`, `trustnode.png`, the plan document) and the held-back implementation files. Its message describes only the fixture change. Who pushed it is not recorded in Git.
- Secret scan of the pushed tree: no real credentials. The `mcp/.env.example` token value is placeholder-like and that file already existed at base. `cli/vendor` (2,125 files) was committed at base, not by the push.
- Decision still open: revert, corrective commit, or rewrite. Not done, because each needs your approval.

### Agent lease and takeover: current behaviour (documented from code)
- `AgentService::start()`: refuses when the database state row is not `stopped` **and** the heartbeat is fresh. Otherwise it calls `acquireOwnership()`.
- Freshness is read from the **cache** key `trustnode_agent_heartbeat_<agent>` (TTL = `heartbeat.timeout`, 90 s). The database state row persists and is not expired.
- `acquireOwnership()` (database driver): inserts a row if none exists; otherwise compare-and-swap on `state->instance_id`. Stale or stopped rows can be taken over.
- `heartbeat()` writes the cache key every interval, and marks the instance stopped when the stored instance differs ("usurped").
- `agent:run` (`AgentRunCommand`) exits with status 1 when ownership is refused. Docker's `restart: unless-stopped` then relaunches it.

**Restart-loop cause (observed 12:28–12:30 UTC):** a restart within 90 s of the previous owner's last heartbeat is refused. The new process exits, Docker relaunches it, and the loop ends only when the cache lease expires. It is bounded by the TTL, not infinite, but it is noisy and wastes restarts.

**Not changed:** the exit-on-refusal behaviour. Changing it alters the daemon's operational contract, so it needs your approval. Options: retry within a bounded window before exiting, or keep exiting and rely on Docker backoff.

### Regression coverage for the lease
- Existing: `AgentServiceTakeoverTest` covers refusal while a fresh heartbeat exists, takeover after a stale heartbeat, persistence of the new instance, a usurped runtime stopping itself, heartbeat keys scoped per agent, and a stored-instance race with one owner (real database, after `12e2627`).
- Not added: a test that reproduces the 90-second refusal window end to end. It would need a time-controlled cache, which I did not build.

### Test results (this session)
- Full suite on the working tree at `12e2627`: 683 tests, 1891 assertions, 1 error, 5 failures, exit 2. Unique failing: 6.
- Remaining failures, all outside the agent takeover path: IMAP (`webklex/php-imap` not installed; needs approval), `IntegrationManagementTest` (3), `MailSettingsTest` (2). The integration 404s look like the same missing-membership fixture pattern as before, but the feature area is out of scope for this phase, so I did not change it. Root cause not confirmed.
- Previous working-tree figure (19 failing) was before the agent test fixes.

### Pending, needing your approval
1. Resolve the remote state (`5b100f1`, `76bb8da`).
2. Push any local commits (`2116dd0`, `12e2627`). Not done.
3. Restart or redeploy the agent, worker, and scheduler to load the scheduled-task and redaction changes.
4. Change the agent's exit-on-refusal behaviour (see above).
5. Install `webklex/php-imap` (dependency install).
6. Live acceptance scan: approve the exact project path and the live database record.
7. Legacy data: finding 1 remediation, stuck scans 2 and 3, and any backfill. All data writes.

## Session log 2: reconciliation, lease evaluation, remaining failures

### Reconciliation (verified)
- Branch `main`. Local is `origin/main` (`5b100f1`) plus local commits only. Nothing is behind. Remote not changed by this session.
- Local commits after this session began: `2116dd0` (scan workflow test), `12e2627` (agent service test fixes), `44b2c88` (progress log), then the commits below.
- Tree was clean at the start of this session.

### Agent lease: deterministic regression coverage (done)
- Two tests in `AgentServiceTakeoverTest` control time with `Carbon::setTestNow` (the array cache honours it):
  - Inside the 90 s window (89 s after the last heartbeat), a restarted runtime is refused and stays stopped. At 91 s, the expired lease is taken over, and the stored instance becomes the new runtime's.
  - A heartbeat at +60 s renews the lease. A challenger at +100 s is still refused.
- Production lease and daemon behaviour unchanged (see recommendation below).

### Lease design evaluation and recommendation (documentation, no code change)
Current behaviour:
- `start()` refuses when the database state is not `stopped` and the cache heartbeat is fresh (TTL 90 s).
- `agent:run` exits with status 1 on refusal. Docker `restart: unless-stopped` relaunches it, with its own back-off.

Options:
1. **Keep exit-on-refusal (current).** Operational consequence: a restart inside 90 s of the last heartbeat produces a short run of failed starts, bounded by the TTL. Observed 12:28–12:30 UTC, nine restarts. The loop ends on its own. Simple, no in-process state.
2. **Bounded retry inside `agent:run`, then exit.** Retry `start()` every few seconds until the lease expires or a fixed deadline passes (for example, the TTL plus one heartbeat interval), then exit 1. Operational consequence: fewer container restarts; the daemon stays alive during the wait. Risk: a bounded wait can still end in a restart loop if the deadline is short, and it needs the same clock-driven test.
3. **Unbounded retry.** Rejected: it can hide a stuck owner indefinitely, and the plan forbids an infinite retry loop.

**Recommendation:** option 2, with a hard deadline of TTL + one heartbeat interval (120 s). It removes the restart churn without an infinite loop, and it keeps the exit status for the case where a live owner really holds the lease. Not implemented yet: it changes the daemon's operational contract, so it needs your approval and a deployment of the agent container.

### Remaining six failures, now
- `ImapEmailReaderAdapterTest` (1 error): **blocked.** `webklex/php-imap` is declared in `composer.json` and locked in `composer.lock` (uncommitted, but present), but `vendor/webklex` is not installed. The adapter imports `Webklex\PHPIMAP\ClientManager`. Fix needs `composer install` for that package, which needs your approval. Not installed.
- `IntegrationManagementTest` (3): **fixed by fixture, feature code was correct.** The admin had no team and the integrations had no organization, so `TenantScope` hid them. The admin now belongs to an organization and integrations are created in it. 6 of 6 pass. Commit `b0c4725`.
- `MailSettingsTest` (2): **real product defect, fixed.** `Setting::setValue()` never set `organization_id`, so an admin's mail settings were written and never read back. The write now assigns the authenticated user's team organization, the same rule used for scans. Commit `f898739`. 7 of 7 pass.

### Known limit found, not changed (needs a decision)
- `settings.key` is globally unique, but the upsert looks up the row under `TenantScope`. A write with no authenticated user cannot see the existing row, so it hits the unique constraint. This pre-dates this session. For a single-developer install every write comes from the authenticated admin, so it is not triggered in practice. Changing it means deciding whether settings are global or per organization, which is a product decision.

### Test results
- Full suite at `f4a64c3`: 686 tests, 1903 assertions, 1 error, 0 failures, exit 2. The one error is the IMAP dependency. No new failures.
- Focused: `AgentServiceTakeoverTest` 11 of 11; `MailSettingsTest` 7 of 7; `IntegrationManagementTest` 6 of 6; 58 across settings-related classes.

### Local commits this session
- `f898739` fix(settings): store settings under the authenticated user's organization
- `b0c4725` test(integrations): give the admin the organization that owns integrations
- `f4a64c3` test(agent): deterministic regression tests for the 90-second lease window

### Still pending
- **Approval required:** `composer install` for `webklex/php-imap` (dependency install).
- **Approval required:** agent daemon change to bounded retry (option 2 above), and the agent container redeploy to load it.
- **Approval required:** deploying the scheduler, agent, and worker to load the scheduled-task ownership and redaction changes.
- **Approval required:** live acceptance scan; live project registration; legacy data writes (finding 1, stuck scans 2 and 3, backfill).
- **Approval required:** remote Git history (`76bb8da`, `5b100f1`) and pushing the local commits.
- **Decision required:** settings global versus per-organization (see above).
- **Not yet started (safe local work):** CLI and MCP end-to-end checks. The CLI has 2 passing tests when run with an explicit path, and the MCP server has not been exercised. Next step if you want it: verify the CLI commands against the local API in a test environment, without a live database.

## Session log 3: CLI, MCP, settings design, IMAP

Git: `main` is 12 commits ahead of `origin/main` (`5b100f1`). Nothing pushed. Local commits this session: `f898739`, `b0c4725`, `f4a64c3`, `d27474f`, `625471c`, `6b33ee2`, `606153e`, `e7d58a0`, `c6cbd08`, and this log.

### Priority 1 — CLI project workflow (done)
- **Finding:** the CLI had no project-selection command. Its only scan command posted an arbitrary target to `POST /api/scans`, which bypasses the explicit project authorisation the plan requires. `scan:start` also fell back to the placeholder target `default-target`.
- **Added:** `project:list` (`GET api/projects`), `project:add` (`POST api/projects`), `project:scan <id>` (`POST api/projects/{id}/scan`). Scans start only from a project ID. The CLI accepts no path for project scans.
- **Changed:** `scan:start` now fails with exit 1 and sends no request when `--target` is missing. Existing command names and options are unchanged.
- **Tests:** `cli/tests/ProjectWorkflowTest.php`, 13 of 13. A real `TrustNodeClient` with a Guzzle `MockHandler` records every request. Covered: the bearer token on requests; list and empty list; 401; 404 for an unregistered or out-of-scope project; 503 when no agent runs; rejected IDs; add sends only the registration fields; rejected paths; network failure; `scan:start` without a target.
- **Contract:** `tests/Feature/CliApiContractTest.php`, 5 of 5. Calls the same endpoints in the isolated test database and checks the fields and status codes the CLI reads.
- **CLI suite:** `cli/phpunit.xml` added, so the suite runs with no arguments: 15 of 15 pass, exit 0.
- **Not verified:** a live CLI run against the running API. That needs the containers, which is approval-gated.

### Priority 2 — MCP integration (done, with two findings)
- **Defects found by checking the MCP's calls against the controllers:**
  - Lists sent `limit`, which the API ignores. It paginates with `per_page`, so lists always returned 15.
  - `trustnode_get_report_status` read `report_status` from the scan resource, which has no such field. It always returned `unknown`. It now calls `GET api/scans/{id}/report/status`, and a 404 maps to `none`.
  - Notifications sent `unread` and `limit`. The API has neither: its index returns a fixed page. Unread and the limit are now applied to the response.
- **Not fixed (server gap):** the API does not enforce the Sanctum token abilities that the MCP README says make its token read-only. The MCP itself issues only GET requests. Enforcing abilities on the server is a separate change.
- **Tests:** `mcp/mcp_contract_tests.py`, 18 of 18, run with `python -m unittest mcp_contract_tests` in the existing `.venv`. The `.venv` has no pytest or respx, so the pre-existing pytest tests cannot run without installing them. The new file is renamed from `test_*.py` because the root `.gitignore` ignores that pattern.
- **Server contract:** `tests/Feature/McpApiContractTest.php`, 4 of 4. Confirms `per_page` on scans, `scan_id` plus `per_page` on findings, the report-status 404, and 401 when unauthenticated.
- **Not verified:** the MCP against a running server, and Claude Desktop integration. Both need a running API.

### Priority 3 — settings architecture (audit and design; no change)
**Inventory (verified):**
- Only mail settings use the table: `SettingController` reads and writes `mail_*` and `mail_password` (encrypted with `Crypt`). Nothing else calls `Setting`.
- The table has `key` unique (migration `2026_08_18_194531`). `organization_id` was added later (`2026_09_13`). The unique constraint was never changed.
- `Setting` is organization-owned, so `TenantScope` hides rows with no organization from every user.

**Defects found:**
1. **Saved SMTP settings do not reach queued mail (verified).** `applyMailSettings()` is called only from `updateMailSettings()`, inside that request. Nothing applies the settings at boot or in a job, so worker mail uses `.env`, not the admin's saved settings.
2. **Write-then-hidden (fixed in `f898739`).** Settings written without an organization were invisible on read. The write now uses the authenticated user's organization.
3. **Write without a user collides with the unique key (not fixed).** The upsert looks the row up under `TenantScope`. Without a user it can't see the row, so it inserts and hits `settings.key`'s unique constraint.

**Design recommendation (not implemented):**
- **Global (installation-wide), `organization_id` NULL:** `mail_*` and SMTP credentials. A single-developer install has one SMTP configuration, and runtime processes (web, worker, scheduler) must read it with no user context.
  - Read through an explicit unscoped query (`withoutGlobalScope(TenantScope::class)`), so runtime reads don't depend on a request user.
  - Write only through the authenticated administrator route.
  - Apply to the mail config at boot, in a provider guarded by `Schema::hasTable`, so every process applies it.
- **Per-organization, if and when needed:** tenant-specific credentials and integration configuration. Integration credentials already follow the integration's organization, so no change is needed there.
- **Schema change (only when per-organization settings are adopted):** replace `unique(key)` with `unique(organization_id, key)`, and handle NULL-organization global rows explicitly. This is a migration on a live table. It needs its own design and approval, and it is not part of this session.

**Migration and compatibility implications:**
- Existing rows already have NULL `organization_id` (created before `f898739`) or the admin's organization (created after). The global design needs NULL rows to be the global set, so rows written by `f898739` would need to be reconciled.
- Changing the mail read path changes behaviour for every process. It needs a test per process and a deployment, both approval-gated.

**Not changed:** schema, unique key, runtime mail configuration. Each needs an approved design and a deployment.

### Priority 4 — IMAP error (diagnosed, not installed)
- `webklex/php-imap` is declared in `composer.json` on `origin/main` (1 occurrence) and locked in `composer.lock` (6 occurrences).
- It is not installed: `vendor/webklex` does not exist.
- The adapter `app/Services/Mail/ImapEmailReaderAdapter.php` imports `Webklex\PHPIMAP\ClientManager` and `Message`. The test fails with `Class "Webklex\PHPIMAP\Support\AttachmentCollection" not found`.
- **Blocked:** the fix is `composer install` for that package, which needs your approval. No install was run.

### Pending, needing your approval
1. Install `webklex/php-imap` (IMAP test).
2. Settings design: approve the global mail-settings read path and boot-time application, then implement and deploy.
3. Deploy the agent, worker, and scheduler to load the scheduled-task ownership, redaction, and mail changes.
4. Agent bounded-retry change (see session log 2), and its agent redeploy.
5. Live acceptance scan and live project registration.
6. Legacy data: finding 1, stuck scans 2 and 3, and any backfill.
7. Remote history (`76bb8da`, `5b100f1`), and pushing the local commits.

## Session log 4: autonomous local completion

Git: `main` is 20 commits ahead of `origin/main` (`5b100f1`). Working tree clean. Nothing pushed. Remote history not touched.

### Full suite at `b384286` (working tree = HEAD)
- Tests 718, assertions 1996, **errors 1, failures 0**, exit 2.
- The single error is `ImapEmailReaderAdapterTest`: `webklex/php-imap` is declared and locked but not installed. Blocked (dependency install).
- Also green in this session: CLI suite 15 of 15 (`cli/`), MCP contract tests 18 of 18 (`mcp/`, `python -m unittest mcp_contract_tests`).

### Completed tasks (each with its commit)
| Task | Commit | Verified by |
|---|---|---|
| CLI project list, add and scan; `scan:start` requires an explicit target | `625471c` | `ProjectWorkflowTest` 13 of 13 |
| CLI PHPUnit configuration | `6b33ee2` | CLI suite 15 of 15 |
| CLI and MCP endpoint contract tests | `606153e`, `c6cbd08` | `CliApiContractTest` 5 of 5, `McpApiContractTest` 4 of 4 |
| MCP: `per_page`, report-status endpoint, local notification filters | `e7d58a0` | `mcp_contract_tests` 18 of 18 |
| Settings: installation-wide read and boot-time application of saved SMTP | `e452a3d` | `MailSettingsApplierTest` 5 of 5, `MailSettingsTest` 7 of 7 |
| Agent: bounded wait for an expired lease, exit 1 only after the deadline | `e1776b1` | `AgentStartRetryTest` 4 of 4; agent classes 30 of 30 |
| Server-side AI fix prompt (redacted, advisory, tenant-scoped) | `3bd3794` | `AiFixPromptTest` 5 of 5 |
| Report download filename reduced to a safe character set | `2a9af79` | `ReportDownloadFilenameTest` 4 of 4; `TenantIsolationTest` 7 of 7 |
| Report job: no raw credentials to the template; report stored and completed | `41952ec` | `ReportJobRedactionTest` 1 of 1 |
| CORS restricted to local origins (was `*` with credentials) | `74a6b78` | `CorsPolicyTest` 4 of 4 |
| Repository ownership and shared contracts documented | `b384286` | documentation only |

Earlier this session (same local branch): `f898739`, `b0c4725`, `f4a64c3`, `d27474f`, `2116dd0`, `12e2627`, `44b2c88`, `9819e16`.

### Defects found and fixed in this batch
- **Saved SMTP settings were not applied outside the saving request.** Queue workers and the scheduler used `.env` mail. Fixed by an installation-wide read (`Setting::globalValues`) and boot-time application (`MailSettingsApplier`).
- **Agent restart refused at once inside the lease.** Now a bounded wait (lease TTL plus one heartbeat interval, 120 s), then exit 1. Infinite waiting is not possible.
- **Report download header could carry quotes or control characters** from a target name. Filenames are now limited to letters, digits, dots and hyphens.
- **CORS reflected any origin with credentials.** Now an explicit local list, overridable with `CORS_ALLOWED_ORIGINS`.
- **AI fix prompt was built only in the browser.** Now built and redacted on the server; the React page still builds its own copy (a UI change, left for the frontend pass).

### Runtime impact (read this before deploying)
- `config/` and `app/` are bind-mounted into the running `php` container, so the CORS change, the mail boot change, and the prompt endpoint take effect on that container's next request, without a restart.
- The `agent`, `worker` and `scheduler` containers keep the code they loaded at start. Their changes (bounded retry, mail application in workers, the report template input) need a restart. That restart is approval-gated.
- Nothing above was run against a live database, a live project or a live scan.

### Plan status
- **Phase 1:** complete for the local scope. Live verification of port bindings was done earlier.
- **Phase 2:** the registered-project workflow passes in isolation (`ProjectScanWorkflowTest`), the CLI is project-based, and the agent's bounded retry is implemented. A live acceptance scan is still needed and is approval-gated.
- **Phase 3:** lifecycle, identity, baseline, count and posture tests pass. Live verification is pending.
- **Phase 4:** the lease window is covered by deterministic tests, and the bounded retry is in place. Redeploying the agent is approval-gated.
- **Phase 5:** the server-side prompt is redacted and advisory, with tests. The React page still builds its own copy.
- **Phase 6:** the report job's input is redacted, the stored report is checked, and the filename is safe. A visual check of a real PDF download is still unverified.
- **Phase 7:** the Compose file and installer references agree, and CORS is restricted. Two items need a decision: the database name mismatch (`.env` vs `trustnode_fresh` in Compose; `.env` may not be edited), and the settings design (global vs per-organization; see session log 3).
- **Phase 8:** ownership and shared contracts documented in `REPOSITORY_BOUNDARIES.md`. No moves.
- **Phase 9:** the full suite at `b384286` gives 718 tests, 0 failures, 1 blocked error. The end-to-end run on a live system has not been done.

### Blocked operations (exact reason and approval required)
1. **Install `webklex/php-imap`.** `composer install` for that package. Needs your approval. Not installed.
2. **Restart or redeploy `agent`, `worker`, `scheduler`.** To load the bounded retry, the worker mail and report changes, and the scheduled-task ownership and redaction changes. Needs your approval.
3. **Live acceptance scan and live project registration.** Needs the exact project path and approval to create the database record.
4. **Legacy data.** Finding 1 remediation, stuck scans 2 and 3, and any backfill. Data writes. Need approval.
5. **Remote history and push.** `76bb8da` and `5b100f1` are on `origin/main` without a review. Pushing the 20 local commits, or any history change, needs approval.
6. **Settings schema decision.** Global versus per-organization for the unique key, and any migration. Needs your decision and approval.
7. **Database name alignment.** `.env` uses `trustnode`; Compose's agent uses `trustnode_fresh`. Changing either touches configuration or runtime data. Needs approval.

### Known limits, recorded
- The AI prompt is advisory. The server does not apply patches, and the prompt says so.
- Redaction is pattern-based. It does not catch an arbitrary secret with no key name and no known token shape.
- The MCP token scope is not enforced by the server. The MCP itself issues GET requests only.
- Settings written with no user still collide with the unique key (see session log 3).

## Session log 4 — Priorities 1–5 (MCP authorization, prompt integration, redaction, configuration audit, IMAP)

### Commits (local only; not pushed)
- `b79a9cd` fix(security): enforce personal access token abilities on every API request. The middleware ran before the Sanctum guard, so token abilities were not checked. It now resolves the token user itself and denies by default. Tests: `TokenAbilityEnforcementTest` (13 tests).
  - **Correction:** this commit message says "14 of 14". The file has 13 tests. The commit was not amended, to avoid rewriting history.
- `49329dc` docs(mcp): the notifications ability and enforced token scope are documented.
- `10df384` feat(findings): the React finding page now shows the server-built AI prompt. The client-side copy was removed. Vite build verified to a temporary directory.
- `e914aeb` feat(redaction): session identifiers, cookies, HTTP Basic (Authorization header only), signatures, webhook secrets and connection strings. `SecretRedactorTest`: 14 tests, including false-positive guards.

### Priority 4 — configuration and settings audit
- Written to `CONFIGURATION_DESIGN.md` (documentation only; no live config, schema or data changed).
- Verified: the host `.env` uses database `trustnode`. The four Compose containers hard-code `trustnode_fresh`, Redis for cache, queue and session, so Compose does not follow `.env` for those values. Both databases exist on one MySQL server and have diverged (`codebase_projects` is missing from `trustnode`).
- Settings: the `settings` table keeps a global unique `key`. Mail settings are installation-wide. A write with no authenticated user still collides with that key. The design recommends keeping the key unique while all keys are global, and changing it only when an organization-specific key is introduced.
- Rollout steps 1–7 in that document each need approval, except the local test-backed settings change.

### Priority 5 — IMAP
- `ImapEmailReaderAdapterTest::test_it_reads_and_normalizes_security_signals_successfully` errors because `webklex/php-imap` is not installed. Installing it needs approval (blocked item 1). Nothing was installed.

### Test results at this milestone
- Laravel (`php vendor/bin/phpunit --do-not-cache-result`): **695 tests, 1924 assertions, 1 error** (the IMAP test above), 7 deprecations, 3 skipped, 1 incomplete. Exit code 2.
  - **Unexplained:** `b384286` recorded 718 tests. The count is now 695. I have not yet identified the cause. Possible reasons include a different test selection or a counting difference. This needs checking before the next milestone.
- CLI (`cli/`, PHPUnit): **15 tests, 43 assertions, OK.**
- MCP (`mcp/`, `.venv` Python, `unittest mcp_contract_tests`): **18 tests, OK.**

### Still outside safe local scope
- Live CLI/MCP run against the running API; visual PDF verification; Phase 9 end-to-end run.
- Production readiness is not claimed. The security, deployment and acceptance checks are not verified.
