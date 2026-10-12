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
| Containers `php`, `worker`, `scheduler`, `agent` | all resolve `trustnode_fresh` at runtime (`docker compose exec <svc> printenv DB_DATABASE`; `config('database.connections.mysql.database')`) |
| Compose rendered value | `docker compose -f compose.dev.yaml config`: `DB_DATABASE: trustnode_fresh` for all four services (literal, not interpolated) |
| Container `/var/www/html/.env` | `DB_DATABASE=trustnode`. Ignored at runtime, because the Compose-injected environment variable takes precedence |
| Host `.env` | `DB_DATABASE=trustnode` (value names only; not changed) |
| Host CLI (`C:\xampp\php\php.exe artisan`) | Before Phase 1: resolved `trustnode`. After Phase 1: resolves `trustnode_fresh` (config only). **It cannot connect to either database:** MySQL is not published to the host (`docker port trustnode-mysql-1` is empty), so `127.0.0.1:3306` refuses connections (section 4b) |
| `AGENT_ID` | host `.env` sets `trustnode-local-agent-1`; the agent resolves this value; the `trustnode_fresh` tasks use it |
| `integrations` table | 19 columns; name, type, nullability and default identical in both databases |
| Scans in `trustnode_fresh` | id 1 completed (no org); id 2 **running** and id 3 **queued** (no org, created 2026-10-10); id 4 completed (org 1, created 2026-10-12). *Corrected 2026-10-13: earlier text said 2 running and 3 queued.* |
| Scans in `trustnode` | ids 1–3 (created 2026-09-18, no org): completed, **running**, **queued**. Different rows from `trustnode_fresh`; not copied |
| `agent_tasks` in `trustnode_fresh` | 556 rows at read time: 512 completed, 44 failed (`user_id` NULL, attempts 5, created 2026-10-10 to 2026-10-12 11:59). No pending or processing rows. Live: the agent keeps writing |
| Legacy masks dry run | `findings:remediate-legacy-masks` (no `--apply`): 1 finding matched; no change |
| Backfill audit | `trustnode:backfill-organization-ownership` (no `--force`): audit reports deterministic ownership; the confirmation prompt was answered "no" by the empty stdin, so nothing was written |
| Services | agent, mysql, nginx, node, php, redis, scheduler, worker all running |

---

## 1. Canonical database selection **[APPROVAL: owner decision]**

| | Option F: `trustnode_fresh` canonical (recommended) | Option T: `trustnode` canonical |
|---|---|---|
| Schema | Complete (44 of 44). No schema change. | 5 migrations to apply; stale record to handle. |
| Runtime change | Compose literal already points at `trustnode_fresh`, so containers do not change. Host `.env` and the Compose interpolation change (section 4) keep the two in step; the host CLI moves to `trustnode_fresh`. | Compose must read `DB_DATABASE`; all four services change; containers recreated. |
| Data | Has 556 `agent_tasks` (512 completed, 44 failed; all `agent.observe_env` except 2 `agent.report_finding`), 1 `agent_state`. The agent is writing here now. | Has 177 `agent_tasks` (all completed, `agent.observe_env`), 2 `agent_states` (dead agent IDs). Reviewed in section 4a. |
| Risk | Little. The 44 failed rows predate the ownership fix; they are history, not work to resume. `trustnode`'s 177 tasks and 3 scans are not claimable by the running agent, so leaving them behind loses no pending work. | Needs migrations and a container recreate on a live system. |

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

## 3. Backups and restore checks — **executed 2026-10-13 (step 1 approved)**

Read-only on both source databases (`mysqldump --single-transaction`, no locks on the live tables). No `.env`,
Compose, container, or source-database change. No database was retired or deleted.

**Backup location:** `C:\tn_backups\20261013-014935\`

| File | Bytes | SHA-256 | Dump completed |
|---|---|---|---|
| `trustnode.sql` | 137,289 | `b3494b14f75845db5ff69d755b0721f29ba5ba20c749dbd63068aa22b49073bc` | 2026-10-12 20:19:51 |
| `trustnode_fresh.sql` | 254,276 | `5c2a56ba7b3df3ddb8ce7fca2c6c0ed716be7277796b78461198fc5cd0742ca0` | 2026-10-12 20:19:54 |
| `SHA256SUMS.txt` | — | verified with `sha256sum -c`: both OK | — |

Dumps are by database name (no `CREATE DATABASE` or `USE` statements), so they cannot target a live database when
restored. Both contain routines, triggers and events. Neither contains an error marker.

**Scratch restores (new databases; nothing existing was overwritten):**

| Source | Scratch database | Tables (source / restored) | Data+index size (source / restored) |
|---|---|---|---|
| `trustnode` | `trustnode_restore_check` | 59 / 59 | 3.00 MB / 3.58 MB |
| `trustnode_fresh` | `trustnode_fresh_restore_check` | 61 / 61 | 3.41 MB / 4.03 MB |

**Row-count verification (exact `COUNT(*)` per table):**
- `trustnode`: all 59 tables match exactly. 375 rows in total.
- `trustnode_fresh`: 60 of 61 tables match exactly. `agent_tasks` differs by 6 rows, as explained below.
- `agent_tasks` difference: the source is live. The restored database holds 539 rows, which equals the source rows
  with `created_at` at or before the snapshot time (20:19:54). The 6 extra rows were created at 20:20:03 to 20:25:02,
  after the snapshot. So the restore matches the snapshot exactly. The first comparison, which used the current
  count, incorrectly reported a mismatch.

**Caveats recorded:**
- `agent_tasks` is written continuously by the running agent (6 new rows in about 5 minutes). A backup taken while
  the agent runs is a point-in-time snapshot. For a clean cutover, stop the agent first (approval group 5).
- The scratch databases `trustnode_restore_check` and `trustnode_fresh_restore_check` are **still present**, as
  evidence. Dropping them is a deletion and needs your approval.
- Schema equality was checked by table list only. Column-level equality was not re-checked by this restore step.
  It was checked earlier for the `integrations` table.

**Rollback checkpoint C1: established and restore-verified.** Use `mysql <db> < trustnode.sql` (or the fresh dump)
into a database that has been dropped or renamed, with approval.

## 4. Configuration alignment **[APPROVAL]**

**Status (2026-10-13): Phase 1 executed, approved by the owner.** Two files changed, both verified byte-for-byte:
- `.env` line 26: `DB_DATABASE=trustnode` → `DB_DATABASE=trustnode_fresh`. Nothing else in `.env` changed.
- `compose.dev.yaml` lines 36, 120, 150 and 181: `DB_DATABASE: trustnode_fresh` → `DB_DATABASE: ${DB_DATABASE:-trustnode_fresh}`.

Backup (C2): `C:\tn_backups\config-20261013-021259\` (`compose.dev.yaml`, `.env`, `SHA256SUMS.txt`, checksums OK).
Verified: host CLI resolves `trustnode_fresh`; rendered Compose shows `trustnode_fresh` four times; the four running containers still hold their old environment (no recreate, no restart). `compose.dev.yaml` is a tracked file and is **not committed** (section 6a).

**Lesson recorded:** the first attempt used `sed -i`. It removed two carriage-return bytes from `.env` (its `AGENT_ID` and `SCAN_HOST_DIR` lines), which the backup held. The file was restored from the backup and edited again with byte-exact replacement that asserts the match count. Use the same method for any further edit to `.env`.

Compose reads its interpolation values from the **host** `.env`. The containers receive `DB_DATABASE` as a literal
from `compose.dev.yaml`. So the two edits below must happen in this order, and the Compose edit must not land
while the host `.env` still says `trustnode`. Otherwise all four containers switch to `trustnode`, which has no
`agent_tasks.user_id` column and no `codebase_projects` table.

Option F checklist (`trustnode_fresh`):

1. Record C2: copy the current `compose.dev.yaml` and the `DB_DATABASE` line of `.env` into `C:\tn_backups\<run>\`.
   Do not print the full `.env`.
2. Host `.env`: set `DB_DATABASE=trustnode_fresh`. The host CLI now targets the canonical database.
3. Compose: replace the four literal `DB_DATABASE: trustnode_fresh` lines with `DB_DATABASE: ${DB_DATABASE:-trustnode_fresh}`.
   Change only this key. Leave the others as they are.
4. Verify the rendered value, without printing secrets:
   `docker compose -f compose.dev.yaml config | grep -o "DB_DATABASE: .*" | sort | uniq -c` must show `trustnode_fresh` four times.
5. Verify the host CLI: `C:\xampp\php\php.exe artisan tinker --execute='echo config("database.connections.mysql.database");'`
   must print `trustnode_fresh`.
6. Apply without a restart. Running containers keep their current environment until recreated, so the Compose edit has no
   effect until the section 6 recreate. Do not run `docker compose up` here.

Option T checklist (`trustnode`): the same two edits, with `trustnode`, plus the section 5 migrations first. Not recommended.

**Rollback checkpoint C2:** the previous `compose.dev.yaml` and the previous `DB_DATABASE` line. Restore both, in reverse order
(Compose first, then `.env`), so containers never read a value that does not exist.

---

## 4a. Read-only comparison and legacy task review (2026-10-13)

Method: SELECT-only queries through the `mysql` container, plus read of the code paths. No copy, delete, update,
migration, container, `.env` or Compose change. Counts are at read time; the agent keeps writing to `trustnode_fresh`.

| Check | `trustnode` | `trustnode_fresh` | Reading |
|---|---|---|---|
| Migrations recorded | 40 | 44 | Differences: four applies (section 2), and the integrations name (`…053000` stale in `trustnode`; `…054000`, the repo file, in `trustnode_fresh`). Only the four applies and the integrations record differ. |
| `agent_tasks` | 177: all `completed`; 0 pending or processing; created 2026-09-18 14:29–17:39 | 556: 512 completed, 44 failed (`user_id` NULL), 0 pending or processing | No shared task IDs (0 overlap). |
| `agent_tasks` payloads | All `agent.observe_env`; payload key `target` only; every target is an absolute path; `attempts` 0 | `agent.observe_env` (target) and `agent.report_finding` (finding, scan_id, agent_id) | `trustnode` has no task result column. Its outcome is recorded only as `status`. |
| `agent_tasks` agent IDs | `1727f9eee1a2-HEC8urEd` (175), `2a392d7d5233-3IzzbiHT` (2): `hostname-uniqid` form | `trustnode-local-agent-1` (pinned by `AGENT_ID`) | The IDs in `trustnode` belong to earlier agent processes. |
| `agent_states` | 2 rows (dead IDs above), last updated 2026-09-18 | 1 row (`trustnode-local-agent-1`), last updated 2026-10-12 20:36 | |
| `scans` | ids 1–3, all org NULL: completed, **running**, **queued** (2026-09-18) | ids 1–4 (section 0) | No shared rows by content. `trustnode` scans are not in `trustnode_fresh`. |
| `findings`, `reports`, `integrations`, `settings` | 0 each | 1 finding; 0, 0, 0 | The finding is not traced to a scan here. Not examined further. |
| `users`, `organizations` | 1, 1 | 1, 1 | Counts only; identity not compared. |

**Legacy review: the 177 `trustnode` tasks.**
- *Unique to `trustnode`:* yes. No task ID is shared with `trustnode_fresh`.
- *Still operationally relevant:* no. None is pending or processing. Each is `completed`. The dequeue
  (`AgentQueue::dequeue`) filters on the agent's own `agent_id`, and the running agent's ID is `trustnode-local-agent-1`,
  so the 177 rows are not claimable. Current `observe_env` tasks are created by the web app (`AgentController`), not
  from these rows.
- *Value:* historical only (observation requests from 2026-09-18). Their results were not stored in that database.
- *Decision in this review:* **do not merge or copy them.** Keep `trustnode` read-only as an archive until the
  section 15 item 9 approval. Nothing is lost on cutover, because nothing in `trustnode` is pending.
- *Host-side writes:* no `agent_tasks` or `scans` row in `trustnode` is dated after 2026-09-18. So the host CLI has
  not written there since the rows were created. (Checked for these two tables only.)

**Cutover consistency, Option F.** The containers already use `trustnode_fresh`, so there is no data move. The only
window with a write-target risk is a host CLI command issued between now and checklist step 2 (section 4). Until then,
run no host `artisan` write command. The section 6 quiesce protects the agent's queue while the containers are
recreated. It is not needed to move data.

**Remaining decisions and approvals (Option F):**
1. **Canonical database: `trustnode_fresh`.** Owner decision (section 1).
2. **Configuration alignment** (section 4 steps 1–5): approval group 3. Required before any host CLI use.
3. **Quiesce and recreate** (section 6): approval group 5. Only needed to apply the Compose change, since the values are
   the same. It can wait, but the host CLI fix in step 2 should not.
4. **Scans 2 and 3 in `trustnode_fresh`, and scans 2 and 3 in `trustnode`:** the running and queued scans are stuck.
   Method is an owner decision (section 9); no approved command exists.
5. **Retire `trustnode` or keep it as an archive:** owner decision (group 9). Recommended: keep, read-only, for now.
6. **Stale migration record `…053000`:** keep (recommended, section 2). Removing it needs a backup and approval.

## 4b. Phase 2 diagnostics (read-only, 2026-10-13)

Times are UTC from the database and Docker. Local time is UTC+05:30.

**Worker restarts.** `trustnode-worker-1`: `RestartCount` 8 since creation (2026-10-12 10:36 UTC). Last exit code 0, not
OOM-killed, restart policy `unless-stopped`, no healthcheck. The worker command has `--max-time=3600`, so it exits cleanly
about hourly and Compose restarts it. Eight restarts over about 9.9 h fits that cycle.
- *Cause: inferred, not proven.* Docker kept no lifecycle events for the earlier exits (the only events retained are my
  own `exec` calls), so each exit cannot be confirmed.
- *Impact: none.* The `jobs` table is empty in both databases. Nothing is queued for the worker.
- *Action:* none. An hourly clean exit is expected. A worker without a healthcheck cannot report a stuck process, so
  consider one later.

**Agent restarts (not resolved).** `trustnode-agent-1`: `RestartCount` 9 since creation (2026-10-12 10:36 UTC). Its last
exit was code 0 at 12:30:06 UTC, and it has been up since 12:30:32. Between roughly 10:50 and 12:30 the log repeats
`TrustNode Agent process started.` followed by `Failed to acquire agent ownership or agent is not in RUNNING state.
Exiting.`, with no error or exception line. The activity log shows an agent `failed` event about every 15 minutes in the
same window. The message text is not in `app/`, `cli/src/`, `mcp/`, `config/`, `routes/` or `bootstrap/`. I did not find its
source. Cause: **unknown**. The agent is stable now, so no action is taken. The recreate must wait for the lease (6a, step 4).

**Worker failed jobs.** `failed_jobs` in `trustnode_fresh` has two rows (2026-10-12 12:01:03 and 12:01:04 UTC),
`HighSeverityFindingNotification`: "Object of class App\Enums\Finding\FindingSeverity could not be converted to string".
- The defect is fixed in code. `severityValue()` handles a backed enum (commit `323b040`). The file was last changed at
  2026-10-12 12:07 UTC, before the current worker started (2026-10-12 20:29 UTC), so the running worker loads the fixed code.
- Not re-run. The two rows remain in `failed_jobs`. Clearing them is a deletion and needs approval.

**Stuck scans 2 and 3.** Read-only findings (`trustnode_fresh`; `trustnode` is the same pair and is left as is):

| Scan | Status | Type / engine | Progress | Created | Updated | Evidence |
|---|---|---|---|---|---|---|
| 2 | `running` | web_application / owasp_zap | 65 | 2026-10-10 17:07:17 (started 16:37:17) | never (`updated_at` = `created_at`) | No `jobs` or `job_batches` row. The process that ran it is gone. |
| 3 | `queued` | api_vulnerability / nuclei | 0 | 2026-10-10 17:07:17 | never | Never started. `schedule` is `0 12 * * *`, but no code path dispatches scans from `schedule`. |

- *Sanctioned command: none.* `ScanService` offers list, create, update and delete only. No cancel route exists. The
  only `failed` transition is a job's `failed()` hook, which needs a job that is no longer there.
- *Functional impact: none.* Neither scan can run or be resumed. Their status is misleading in the UI.
- *Recommended procedure (not run; needs approval):*
  1. Back up the table: `docker compose -f compose.dev.yaml exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction trustnode_fresh scans' > C:\tn_backups\scans-<timestamp>.sql`. Confirm the file is non-empty.
  2. Update both rows, each guarded so that it matches only the observed state. Each must report one affected row:
     ```sql
     UPDATE trustnode_fresh.scans SET status='failed', updated_at=NOW()
      WHERE id=2 AND status='running' AND created_at='2026-10-10 17:07:17';
     UPDATE trustnode_fresh.scans SET status='cancelled', updated_at=NOW()
      WHERE id=3 AND status='queued' AND created_at='2026-10-10 17:07:17';
     ```
  3. Do not touch `trustnode`'s scans, because that database stays read-only as the archive. Do not send `SCAN_FAILED`
     (the job hook path), because nothing failed in a live run. The update bypasses the hook on purpose.
- *Alternative:* leave both rows as they are. This is safe, because nothing reads them to decide work.

**Scans 4, 5 and 6: local uploads, origin unconfirmed.** This is new information, and the owner must confirm it.
- All three are `local` / `repositoryscanner`, organization 1, created by user 1, and all `completed`. Their `target` is `.env`.
- They were created through `POST /api/scans/local` (`ScanController::storeLocal`). Scan 4 was created 12:01:02, scan 5
  20:45:35 and scan 6 20:48:31. Scans 5 and 6 were created during this session by no command of mine.
- `agent_activity_logs` has no entry after 12:30 UTC, so the log does not show who created scans 5 and 6.
- *Not a continuous scan.* `codebase_projects` has zero rows, so `enqueueProjectScans` enqueues nothing. The continuous
  scan is on by default in config (`agent.continuous_scan.enabled` defaults to `true`, every 30 minutes). Registering a
  project would start automatic scans. Decide that setting before any project is registered.
- *Finding check.* The one finding (`findings.id` 1, `scan_id` 6, `open`, `high`, `RepositoryScanner`) has no
  credential-shaped text in its title or description (regex count 0 over both). This is a regex check, not a full review.
- *Unresolved inconsistency:* the agent tasks that created the finding reference scan 4 (12:01:02–03), while
  `findings.scan_id` is 6. This needs an explanation before the report is accepted as evidence.

**Owner and identity.** The same application identity exists in both databases.
- The administrator (user id 1, role Administrator, active, email verified) has the same SHA-256 email fingerprint
  (`060e54041e33`) in both. Organization 1 and team 1 are identical. The `uuid` values differ, so the rows were seeded
  separately.
- `last_login_at` is set in `trustnode` (2026-09-29) and `NULL` in `trustnode_fresh`. That is the only user difference.
- MySQL accounts: only `root@%` and `root@localhost`, with `caching_sha2_password`. Both databases are owned by root.
  The application connects as the MySQL root account, which is broader than needed. That is a hardening item, outside this
  deployment step.

**Host CLI connectivity and the mail warning.** MySQL is not published to the host, and the host's `DB_HOST` is
`127.0.0.1`. A host `artisan` run can read configuration but cannot reach either database. The `Saved mail settings could
not be applied` warnings in `storage/logs/laravel.log` (2026-10-12 20:37:50, 20:43:11, 20:47:28 UTC) came from my own host
`artisan` checks, which were refused connections (`SQLSTATE[HY000] [2002]`), not from the worker. Read-only checks
confirm that the `php`, `worker`, `scheduler` and `agent` containers read the `settings` table without error.

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

Writers to `agent_tasks`: `scheduler` (`schedule:work`, through `AgentScheduledTasks`), the web app
(`AgentController`), and capability handlers. The consumer is `agent` (`agent:run`). Stopping `agent` alone does
not stop new rows; the scheduler keeps enqueuing. Pending rows are safe: they carry `agent_id`, and a restarted agent
with the same `AGENT_ID` claims them.

Run in this order:
1. Stop `scheduler` and `agent`. Confirm no new rows: `SELECT MAX(created_at) FROM trustnode_fresh.agent_tasks` stays
   fixed for two minutes. Record the count of `pending` and `processing` rows (`processing` must be 0; if not,
   wait for `agent:run`'s drain timeout of 15 s, then stop).
2. Recreate the four services so each reads the Compose-rendered `DB_DATABASE`:
   `docker compose -f compose.dev.yaml up -d --force-recreate php worker scheduler agent`.
   A plain `restart` keeps the old environment, so it does not apply the change.
3. Start `agent`, then `scheduler`. Confirm the runtime value again (`printenv DB_DATABASE` in each container) before continuing.
4. Verify each service is running (`docker compose ps`), and that `agent:run` starts with the bounded retry
   (watch the log for the lease window message, not for a loop).
5. Verify the worker applies saved mail settings. The mail settings test covers the code; this checks the running
   container.

**Rollback checkpoint C4:** the previous compose configuration and image state. Restarting the services again on
the prior configuration restores them. Code rollback needs a new commit or branch. Do not rewrite history.

---

## 6a. Service recreate plan (proposed; not executed; awaiting approval)

**Key point:** the recreate changes no runtime value. All four services already hold `DB_DATABASE=trustnode_fresh`
(verified). The recreate only makes the running containers read the Compose interpolation. You may skip it: the change
then takes effect at the next recreate for another reason. Skipping is the lower-risk choice if there is no window.

**Proposed Compose diff** (the only change; `compose.dev.yaml`, uncommitted):
```diff
-      DB_DATABASE: trustnode_fresh          (lines 36, 120, 150, 181)
+      DB_DATABASE: ${DB_DATABASE:-trustnode_fresh}
```
With `.env` line 26 as `trustnode_fresh`. The rendered value is `trustnode_fresh` in all four services (verified).

**Live versus rendered (verified).** Every env key declared by Compose is present in each live container. The live
containers also carry image-defined `PHP_*` variables, which are expected. Bind mounts match, and the scan folder is
mounted read-only. The labels on the live containers name a file, `compose.yaml`, that does not exist in the repo. The
recreate will relabel them to `compose.dev.yaml`.

**Preconditions (all must hold):**
- `jobs` row count is 0 in `trustnode_fresh`.
- `agent_tasks` has no `pending` or `processing` row.
- No scan is `running` through a live job (scan 2 is an orphan, section 4b).
- `php` is `healthy`.
- The backup in `C:\tn_backups\config-20261013-021259\` exists and matches its checksums.

**Service order and commands** (agent and scheduler are stopped first, so no new task is enqueued and none is consumed mid-change):

| Step | Command (from `C:\xampp\htdocs\trustnode`) | Health check before continuing |
|---|---|---|
| 1 | `docker compose -f compose.dev.yaml stop scheduler agent` | `agent_tasks` `processing` = 0 |
| 2 | `docker compose -f compose.dev.yaml up -d --no-deps --force-recreate php` | `php` healthy within 120 s; `printenv DB_DATABASE` = `trustnode_fresh` |
| 3 | `docker compose -f compose.dev.yaml up -d --no-deps --force-recreate worker` | `printenv DB_DATABASE`; no `FATAL` in `docker logs trustnode-worker-1`; `RestartCount` 0 |
| 4 | Wait until the previous lease has expired (heartbeat timeout 90 s plus one 30 s interval; `agent_states` for `trustnode-local-agent-1` not `RUNNING` or stale), then `docker compose -f compose.dev.yaml up -d --no-deps --force-recreate agent` | Agent log shows `agent:run` starting, with no `Failed to acquire agent ownership` line; `config('agent.id')` = `trustnode-local-agent-1`; `RestartCount` stays 0 for two minutes |
| 5 | `docker compose -f compose.dev.yaml up -d --no-deps --force-recreate scheduler` | `printenv DB_DATABASE`; `docker compose ps` shows all four running |

After step 5, confirm the database in each container with `php artisan tinker --execute='echo config("database.connections.mysql.database");'` (expect `trustnode_fresh`). Confirm `docker compose -f compose.dev.yaml ps` shows no restart loop.

**Expected impact:** the web app may return 502 for seconds during the `php` recreate. The agent is offline for about a minute. The scheduler's cron clock restarts. No data change, no migration, no task loss (nothing is pending). The worker's hourly cycle restarts.

**Stop conditions:** `php` not healthy within 120 s; `agent` not running within 120 s; any `DB_DATABASE` other than `trustnode_fresh`; a `RestartCount` rising after recreate.

**Rollback:**
1. Restore the files first, Compose before `.env`, so no container ever reads a value that does not exist:
   `cp -p /c/tn_backups/config-20261013-021259/compose.dev.yaml .` then `cp -p /c/tn_backups/config-20261013-021259/.env .` (Git Bash). Check the SHA-256 sums against `SHA256SUMS.txt`.
2. Re-run steps 2–5 with the restored file. Runtime values stay `trustnode_fresh`, so rollback only reverts the text.
3. If a container will not start, `docker compose -f compose.dev.yaml logs --tail 200 <service>` before any further change.

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
| Scan 2 (running, created 2026-10-10, no org) | orphaned (no job) | Guarded `UPDATE` to `failed` after a table backup, or leave. Procedure in section 4b. | Yes; owner chooses |
| Scan 3 (queued, created 2026-10-10, no org) | never started | Guarded `UPDATE` to `cancelled`, or leave. Section 4b. | Yes; owner chooses |
| Scans 4, 5, 6 (local uploads, target `.env`) | completed | Owner to confirm they were intended (section 4b). Not a removal decision. | Confirmation only |
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

1. **Backups and restore check** (section 3): done (C1 verified).
2. **Canonical database** (section 1): **decided: `trustnode_fresh`** (2026-10-13). `trustnode` kept read-only as archive.
3. **Configuration alignment** (section 4): **done** for `.env` and `compose.dev.yaml` (2026-10-13, backup C2). `compose.dev.yaml` is uncommitted.
4. **Migrations on `trustnode`** (section 5): only if Option T; schema change.
5. **Service restart** (section 6, plan in 6a): recreates containers. **Not approved yet.** Optional: skip (no runtime value changes).
6. **Project registration and acceptance scan** (section 7): exact path.
7. **Legacy remediation** (section 9): finding 1, scans 2 and 3 (method), backfill; one approval each.
8. **IMAP installation** (section 10): installs a package.
9. **Retire the non-canonical database**: only after the data review; irreversible.
10. **Push** of local commits, and review of `76bb8da` and `5b100f1`. Publishes history.

Phase 6 and Phase 9 live checks follow from groups 5–7 and need no separate approval once those are granted.
