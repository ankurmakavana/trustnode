# TrustNode — deployment runbook (prepared, not executed)

Status: **prepared only.** Nothing in this runbook has been executed against a live database, running service,
live project, or live scan. Steps marked **[APPROVAL]** need explicit approval before they run. Commands use
placeholders. No secret values are recorded here.

Companion documents: `TRUSTNODE_IMPLEMENTATION_PLAN.md` (phase gates), `TRUSTNODE_PROGRESS.md` (evidence log),
`CONFIGURATION_DESIGN.md` (configuration and settings design).

---

## 0. Verified state (read-only evidence, 2026-10-13)

| Item | Evidence |
|---|---|
| Migration files in repo | 44 (`database/migrations`) |
| `trustnode_fresh` applied | 44 of 44; nothing pending |
| `trustnode` applied | 40 of 44; 5 pending; 1 stale record (`2026_08_11_053000_create_integrations_table`, no file in repo or history) |
| Containers `php`, `worker`, `scheduler`, `agent` | all use `DB_DATABASE=trustnode_fresh` (`compose.dev.yaml`) |
| Host `.env` | `DB_DATABASE=trustnode` (value names only; not changed) |
| `integrations` table | 19 columns; name, type, nullability and default identical in both databases |
| Scans in `trustnode_fresh` | 1 completed (no org, creator 1); **2 running** and **3 queued** (no org, created 2026-10-10); 4 completed (local, org 1) |
| Legacy masks dry run | `findings:remediate-legacy-masks` (no `--apply`): 1 finding matched; no change |
| Backfill audit | `trustnode:backfill-organization-ownership` (no `--force`): audit reports deterministic ownership; the confirmation prompt was answered "no" by the empty stdin, so nothing was written |
| Services | agent, mysql, nginx, node, php, redis, scheduler, worker all running |

---

## 1. Canonical database selection **[APPROVAL: owner decision]**

| | Option F: `trustnode_fresh` canonical (recommended) | Option T: `trustnode` canonical |
|---|---|---|
| Schema | Complete (44 of 44). No schema change. | 5 migrations to apply; stale record to handle. |
| Runtime change | Host `.env` only (`DB_DATABASE` → `trustnode_fresh`). Containers already use it. | Compose must read `DB_DATABASE`; all four services change; containers recreated. |
| Data | Has the 34 `agent_tasks` (all `agent.observe_env`, all failed before the ownership fix), 1 `agent_state`. | Has 177 `agent_tasks`, 2 `agent_states`. Its data must be reviewed before it is trusted. |
| Risk | `trustnode`'s 177 tasks are left behind unless reviewed and merged. | Needs migrations and a container recreate on a live system. |

**Recommendation: Option F.** The earlier design (`CONFIGURATION_DESIGN.md`) recommended `trustnode`, because the
host `.env` points there. The evidence has changed that: `trustnode_fresh` is the only database with the full
schema (including `codebase_projects`, which the registered-project workflow needs), and it is the one the running
services use. Choosing it changes the least. Either way, review the data in `trustnode` before retiring it. The
choice is the owner's.

---

## 2. Migration-by-migration reconciliation

Each row was checked against the repo file, the git history, and the live schema (read-only).

| Migration | `trustnode` | `trustnode_fresh` | Decision | Justification |
|---|---|---|---|---|
| `2026_08_11_054000_create_integrations_table` | pending; `integrations` exists with identical columns | applied | **Record only (no-op)** | The file's `up()` is guarded by `Schema::hasTable('integrations')`. The table exists with identical definitions (19 columns, types, nullability and defaults compared). Running `migrate` here does nothing except record it. |
| `2026_08_12_160000_update_integrations_table` | applied | applied | None | Already recorded in both. |
| `2026_09_26_112502_add_agent_id_to_findings_table` | pending; `findings.agent_id` absent | applied | **Apply** | Adds a nullable column and index after `finding_id`, which exists. |
| `2026_10_06_095121_add_user_id_to_agent_tasks_table` | pending; `agent_tasks.user_id` absent | applied | **Apply** | Adds a nullable column after `agent_id`, which exists. |
| `2026_10_06_142416_create_agent_activity_logs_table` | pending; table absent | applied | **Apply** | Creates the table. Foreign keys go to `organizations` and `users`, which exist in `trustnode`. |
| `2026_10_07_113911_create_codebase_projects_table` | pending; table absent | applied | **Apply** | Creates the table. Foreign keys go to `organizations` and `users`. Unique key on `(organization_id, path)`. |
| `2026_08_11_053000_create_integrations_table` (record only) | recorded; no file in repo or git history | not recorded | **Leave in place** (recommended) | Laravel ignores it for `migrate`, but a `migrate:rollback` of that batch would fail on a missing class. Remove the record only after a backup and approval. |

**Ordering on `trustnode`:** the integrations no-op runs first (by file name), then the four applies. This is
safe on MySQL because each `up()` checks or creates only objects that are absent.

**What is verified and what is not:** the five pending migrations run cleanly on SQLite, because the test suite
migrates a fresh database at every run (739 tests pass). They have **not** been run on MySQL against a copy of the
`trustnode` data. That is the first thing to do after backup, using `migrate --pretend` to read the SQL first.

**If Option F is chosen:** no migrations are needed. `trustnode` is retired later, after its data review.

---

## 3. Backups and restore checks **[APPROVAL]**

1. Dump both databases to a directory outside the repo, for example `C:\tn_backups\<timestamp>\`. Record SHA-256
   checksums of each dump.
2. Restore check: load each dump into a scratch database and compare row counts with the source. A restore check
   creates a new database on the server, which is a server change and needs the same approval.
3. Stop if any dump is empty, a checksum does not match, or a restore check fails.

**Rollback checkpoint C1:** the verified dumps. Nothing after this point is considered reversible without them.

---

## 4. Configuration alignment **[APPROVAL]**

1. Compose: replace the literal `DB_DATABASE` in the four services with the chosen name, read from `.env`
   (for example `${DB_DATABASE:-trustnode_fresh}`). Only this key changes.
2. Host `.env`: set `DB_DATABASE` to the chosen name. Do not print the file.
3. Check the rendered configuration shows one database name, without printing secret values:
   `docker compose -f compose.dev.yaml config | grep DB_DATABASE`.

**Rollback checkpoint C2:** the previous `compose.dev.yaml` and `.env` values, recorded before the edit.

---

## 5. Migrations **[APPROVAL]**

Only for Option T. For Option F, verify `migrate:status` shows nothing pending.

1. `docker compose exec php php artisan migrate:status` (read-only). Record the output.
2. `docker compose exec php php artisan migrate --pretend` (prints SQL, changes nothing). Review it.
3. `docker compose exec php php artisan migrate`. Expect the integrations migration to be recorded as a no-op,
   then four applies.
4. Verify: the four tables and columns exist; `migrate:status` shows nothing pending; row counts are unchanged for
   every existing table.

**Rollback checkpoint C3:** restore `trustnode` from the C1 dump. Do not run `migrate:rollback`; the stale record
would break it.

---

## 6. Coordinated service restart **[APPROVAL]**

Run in this order:
1. Stop `agent` so no task starts during the change.
2. Restart `php`, `worker`, `scheduler`.
3. Start `agent`.
4. Verify each service is running (`docker compose ps`), and that `agent:run` starts with the bounded retry
   (watch the log for the lease window message, not for a loop).
5. Verify the worker applies saved mail settings. The mail settings test covers the code; this checks the running
   container.

**Rollback checkpoint C4:** the previous compose configuration and image state. Restarting the services again on
the prior configuration restores them. Code rollback needs a new commit or branch. Do not rewrite history.

---

## 7. Live project registration and acceptance scan **[APPROVAL: exact path and project]**

1. `project:add <path inside SCAN_HOST_DIR>`. Record the project ID.
2. `project:list`: confirm the project is registered, with the expected path.
3. `project:scan <project ID>`. Record the scan ID.
4. Acceptance: the scan completes; findings persist; they appear in the CLI and in the platform.
5. Negative check: request a scan of a path that is not registered. It must be rejected.

**Stop condition:** the scan reads or writes anything outside the approved path, or the agent writes to source.

**Rollback checkpoint C5:** the scan and its findings can be removed by the owner's decision. Record their IDs.

---

## 8. Finding, report and notification verification

1. Findings: check counts, severities, and lifecycle against the database rows for the scan.
2. Report: `report:status <scan>`, then `report:download <scan>`. Open the PDF. Check the page count, the severity
   totals against the findings, and that no credential-shaped text appears (search the text with PyMuPDF, as in the
   local inspection).
3. Authorization: a user from another organization, or without `scans.view`, gets 404 or 403 (covered by
   `TenantIsolationTest` and `ReportDownloadAuthorizationTest`; repeat once live).
4. Notifications: a report-ready notification appears for the requester.

---

## 9. Legacy record remediation **[APPROVAL, per item]**

| Item | State | Action | Approval |
|---|---|---|---|
| Finding 1 (legacy partial mask) | dry run matches 1 | `findings:remediate-legacy-masks --apply` after reviewing the dry run | Yes |
| Scan 2 (running, created 2026-10-10, no org) | stuck | **Owner decision:** mark failed, or leave. No approved command exists for this. | Yes; decide the method |
| Scan 3 (queued, created 2026-10-10, no org) | stuck | Same as scan 2 | Yes; decide the method |
| Organization ownership backfill | audit clean on `trustnode_fresh` | Run `trustnode:backfill-organization-ownership` **without `--force`**, and answer the prompt interactively | Yes |

**Rollback checkpoint C6:** the C1 dumps, taken before any of these.

---

## 10. IMAP installation and retest **[APPROVAL]**

1. `webklex/php-imap` is declared and locked. Install the locked version only: `composer install` (not
   `composer update`), run inside the container or on the host as approved.
2. Run `ImapEmailReaderAdapterTest`. It must pass without changing its assertions.
3. Rollback: the vendor directory can be restored from the previous state, and the test then errors again. Record
   the lock file hash before and after.

---

## 11. Phase 6 PDF acceptance

Local checks already done, on a synthetic report: 32 pages (after keeping finding cards whole, `bc0e7be`), 30
findings, severity totals 6/6/6/6/6, no raw synthetic credentials in the text, and the cover, summary, a finding page
and the last page checked visually. Pages 4–31 were checked by text only. This is evidence about the template,
**not** an acceptance check.

Live: download a real report for the approved scan, then:
1. Open it, and count pages and check pagination (no heading left alone at a page foot).
2. Compare severity totals against the findings.
3. Search the text for credential-shaped strings.
4. Render the cover, summary, and two finding pages to images and view them.
5. Known residual defects: the summary's posture-change label can overrun the margin by a few pixels;
   "Visibility UNKNOWN" appears when the repository visibility is not stored; the engine label reads
   `REPOSITORYSCANNER` because the stored value has no underscore.

---

## 12. Phase 9 acceptance

Run once, after sections 4–8 pass:
1. Full Laravel suite, CLI suite, MCP contract tests. Record exact counts and exit codes.
2. One controlled workflow, end to end: register project → scan → findings → report → notification → AI prompt
   (server endpoint) → MCP read-only query.
3. Record each step's result and IDs in `TRUSTNODE_PROGRESS.md`.

---

## 13. Rollback checkpoints, in order

| Checkpoint | Taken before | Restores |
|---|---|---|
| C1 | Any change | Both databases (dumps) |
| C2 | Config edit | Compose and `.env` values |
| C3 | Migrations | `trustnode` data and schema |
| C4 | Restart | Service configuration |
| C5 | Live scan | Scan and finding rows (by ID) |
| C6 | Legacy remediation | Finding and scan rows (dumps) |

Git: no rollback rewrites history. If code must return to an earlier state, create a new commit or branch with
owner approval.

---

## 14. Stop conditions (any one stops the run)

- A backup is empty, its checksum does not match, or its restore check fails.
- A migration fails or the `--pretend` SQL is not what was expected.
- A service does not return to running after a restart.
- A scan touches a path outside the approved project, or the agent writes source files.
- A credential-shaped string appears in a report, a finding, or a notification.

---

## 15. Approvals required, grouped

Approve each group separately. Each is irreversible or changes live state, and no group depends on the others
being approved.

1. **Backups and restore check** (section 3): writes dump files; creates a scratch database.
2. **Canonical database** (section 1): choice of F or T.
3. **Configuration alignment** (section 4): Compose and `.env` edit.
4. **Migrations on `trustnode`** (section 5): only if Option T; schema change.
5. **Service restart** (section 6): recreates containers.
6. **Project registration and acceptance scan** (section 7): exact path.
7. **Legacy remediation** (section 9): finding 1, scans 2 and 3 (method), backfill; one approval each.
8. **IMAP installation** (section 10): installs a package.
9. **Retire the non-canonical database**: only after the data review; irreversible.
10. **Push** of local commits, and review of `76bb8da` and `5b100f1`. Publishes history.

Phase 6 and Phase 9 live checks follow from groups 5–7 and need no separate approval once those are granted.
