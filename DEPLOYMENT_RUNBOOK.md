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
| Services (**corrected 2026-10-13 05:00 UTC**) | **All stopped.** Every `trustnode-*` container is `exited`. `mysql`, `worker`, `scheduler`, `agent` and `node` exited with code 137; `php`, `redis` and `nginx` with code 0. All stopped between 2026-10-12 21:23:47 and 21:24:02 UTC, in the order of a `docker compose stop`. The cause is not recorded. The earlier "all running" text was stale. Nothing was started or changed in this review. The container environment values in this table were verified before the shutdown. |
| MySQL shutdown | The log ends at `Received SHUTDOWN from user <via user signal>` (21:23:58 UTC). There is no `Shutdown complete` line before the kill at 21:24:02. Treat it as an unclean stop. Expect InnoDB crash recovery on the next start (not yet observed). |

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
Verified: host CLI resolves `trustnode_fresh`; rendered Compose shows `trustnode_fresh` four times; the four running containers still hold their old environment (no recreate, no restart). `compose.dev.yaml` is committed locally as `bb20d68` (not pushed; the commit contains only the four-line change).

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

**Agent restarts (cause identified 2026-10-13).** `trustnode-agent-1` `RestartCount` 9. Docker's stdout log (`docker logs`) has 11 `TrustNode Agent process started.` lines: one at 10:37:03 UTC (first start), then nine cycles between 12:29:05 and 12:30:37 UTC (about 92 s). Each cycle ends with `Failed to acquire agent ownership or agent is not in RUNNING state. Exiting.`. The run at 12:30:32 started and ran until shutdown. The earlier text placed the loop between 10:50 and 12:30. The stdout log does not show that window, so that statement is withdrawn.
- *Cause.* The exit text comes from commit `e47f9d7`. The process exits at once when the previous owner's lease is still valid. The nine cycles match that lease expiring at about 12:30 UTC (heartbeat timeout 90 s plus one 30 s interval). Commit `e1776b1` (2026-10-12 15:27 UTC) replaced the immediate exit with a bounded wait (`AgentStartRetry`). The old message is no longer in the source.
- *Consequence.* The agent container started at 12:30:32 UTC and ran until shutdown on code from before `e1776b1`. The bounded wait has never run in a container. Its first run is recreate step 5 (section 6a).
- *Related errors.* The container's `laravel.log` has 208 `Task ownership context is missing` lines between 10:00 and 12:59 UTC. The timing is consistent with the 44 failed `agent_tasks` rows (`user_id` NULL, created up to 11:59 UTC on 2026-10-12). That link is not proven. The last `AgentWorker: Task execution failed` line is at 12:00:16 UTC.

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
- *Preparation (2026-10-13; nothing applied).* The before-state of both rows was taken from the 20:19:54 UTC snapshot into `C:\tn_backups\investigation-20261013\scans-2-3-before-state.txt`. The guarded SQL is in `scans-2-3-proposal.sql`: a STEP 0 recheck, one transaction with a one-row check on each UPDATE, a post-check, and a rollback. The live recheck (no active job; scan status unchanged) cannot run until the mysql service is started, so it is still open. The snapshot shows `jobs` and `job_batches` empty. That is a snapshot, not a live check.

**Scans 4, 5 and 6: created by the agent's `.env` observer, not by an upload (corrected 2026-10-13).** The earlier note that they came from `POST /api/scans/local` is wrong. The name (`Agent Security Observation: .env`), engine (`repositoryscanner`) and description match `AgentObserveEnvTaskHandler`, which creates them.
- *Mechanism.* `routes/console.php` enqueues `agent.observe_env` for `base_path('.env')` **every minute**, if an agent is `running` and none is pending. No config setting disables it. The handler hashes the whole file. If the hash differs from the cached value (Redis in the containers), it creates a scan, marks it `completed` after scanning, and enqueues one `agent.report_finding` task per detected secret. Any change to `.env` causes a scan, even a one-line change.
- *Timeline (UTC; container `laravel.log` and the 20:19 snapshot).*

| Scan | Created | Change detected (log) | Findings reported (log) | Trigger |
|---|---|---|---|---|
| 4 | 12:01:02 | 12:01:02 | Two tasks, both `scan_id` 4 (`.env` lines 28 and 75), stored at 12:01:03 | Not established. Either a `.env` change, or the first observation with an empty cache (a missing hash counts as a change). |
| 5 | 20:45:35 (earlier live read) | 20:44:32 | Two, at 20:45:58 | Phase 1 `.env` edit (see below) |
| 6 | 20:48:31 | 20:48:31 | Two, at 20:48:48 and 20:48:49 | Phase 1 `.env` edit (see below) |

- *Attribution (my earlier work).* Scans 5 and 6 were triggered by my own edits to `.env` during Phase 1 (section 4): the `sed -i` attempt, the restore from backup, and the byte-exact re-edit. The earlier statement that they were created "by no command of mine" was wrong. The timing fits, but the file state at each observation was not recorded, so this is strongly indicated, not proven. The scan 5 time differs by 63 s between the earlier live read and the log. That discrepancy is unresolved.
- *Why finding 1 is linked to scan 6.* `AgentReportFindingTaskHandler` finds an existing finding by fingerprint and overwrites `scan_id` with the reporting scan's id. Scan 4 created finding 1. Scans 5 and 6 reported the same fingerprint, so each overwrote `scan_id`; the last one is 6. `findings.scan_id` therefore records the most recent observer, not the origin. The origin task belongs to scan 4. At the 20:19:54 UTC snapshot, finding 1 had `scan_id` 4.
- *The two secrets merged.* Lines 28 and 75 share one fingerprint, so they merged into finding 1. The row keeps line 28's evidence. The line 75 value has no finding row. Its only record is the `agent_tasks` payload.
- *Credential exposure.* Finding 1's `evidence` stores a masked value that keeps the first 4 and last 4 characters. The same masked values appear in both `agent_tasks` payloads in the snapshot, and in both dumps under `C:\tn_backups`. `.env` was not read, and the full values were not seen. So partial values (8 characters each) of two credentials reached the database and backup files. **Treat both credentials as exposed and rotate them** (owner action). Full masking would keep no characters. The title and description contain no credential-shaped text (earlier regex check).
- *Authorization.* No user requested these scans, and no approval record covers them. The observer is designed behaviour: it scans only the app's own `.env`, and no path-boundary error was logged for that target. Whether it should run at all is an owner decision. Preserve all three scans and their rows. Do not delete or rescan.
- *Continuous project scan.* `AGENT_CONTINUOUS_SCAN` (default `true`; not set in the host `.env`) and `AGENT_SCAN_INTERVAL` (default 30) control only project scans (`enqueueProjectScans`). They do **not** control `agent.observe_env`. No project is registered (`codebase_projects` = 0), so nothing is enqueued today. Before any project is registered (section 7), the owner must decide whether to disable it. Proposed change, **not applied**: add `AGENT_CONTINUOUS_SCAN=false` to the host `.env` with a byte-exact edit that keeps CRLF, then restart `worker`, `scheduler` and `agent`. Verify with `printenv AGENT_CONTINUOUS_SCAN` in each. Rollback: remove the line (the default is `true`), then restart the same services.
- *Observer guard (2026-10-13).* Implemented and default off: `AGENT_OBSERVE_ENV_ENABLED` must be the word `true` to enable it. Section 4c has the details.
- *Finding check.* A regex check over the title and description found no credential-shaped text. The masked evidence above is the exception. This is a regex check, not a full review.
- *Still open:* the cause of scan 4; the 63 s scan 5 discrepancy; whether Redis still holds the `.env` hash (Redis is stopped, so it cannot be read now).

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

## 4c. .env observer guard and credential remediation (2026-10-13)

**Guard (code committed locally; nothing live changed).**
- `config/agent.php` sets `observe_env.enabled` to `env('AGENT_OBSERVE_ENV_ENABLED', false) === true`. The observer is off unless the value is the word `true`. `1`, `yes`, `on` and any other value leave it off.
- Three guard points, each checked before anything is read, enqueued or stored: the schedule closure in `routes/console.php` (every minute); `AgentScheduledTasks::enqueueAsLocalAdministrator` (the only enqueue path for `agent.observe_env`); and `AgentObserveEnvTaskHandler::handle()`, which refuses tasks already in the queue without reading the file.
- The guard is independent of `AGENT_CONTINUOUS_SCAN`. Disabling continuous scanning never disabled the observer, and enabling the observer does not depend on it.
- Redaction: `AgentReportFindingTaskHandler` replaces any Secret-category evidence with `[REDACTED: <rule id>]`, whatever the sender sends. `SecretScanner` already did this since `323b040` (2026-10-12 18:45 +05:30). The partial mask came from agent code that was still running before that commit.
- Tests: `tests/Unit/AgentObserveEnvGuardTest.php` (6 tests: strict parse, the word `true` only, policy, continuous-scan independence, queued task refused without reading the file, enqueue refused) and `SecretRedactionTest::test_secret_category_evidence_from_an_agent_is_never_stored_as_text`. The focused set (8 files, 74 tests) passes. Full suite: 750 tests, 2 errors, 7 deprecations, 3 skipped, 1 incomplete. The 2 errors are in ScanE2ETest (teams.slug UNIQUE collision during the full run). That file passes alone (3 tests). Whether the errors occur before this change is not verified.
- `phpunit.xml` sets the flag to `(true)` so the existing observer tests stay on. PHPUnit turns a literal `true` into `1`, which the strict check correctly rejects. `AgentHealthApiTest` replaces the whole `agent` config array, so its setUp now states the flag explicitly.
- **Not deployed.** The containers are stopped. When the stack starts, the bind-mounted code applies at once, and the observer stays off unless `AGENT_OBSERVE_ENV_ENABLED=true` is set.
- To re-enable later (owner decision): set `AGENT_OBSERVE_ENV_ENABLED=true` in `.env` (byte-exact edit), then restart `scheduler` and `agent`. To disable: remove the line.

**Stored locations (names and counts only; no values recorded).**

| Location | What it holds | Action | Approval |
|---|---|---|---|
| `findings` row 1 (live `trustnode_fresh`) | Masked evidence for two `.env` values (lines 28 and 75) | `php artisan findings:remediate-legacy-masks`: dry run, then `--apply`. The command matches only the legacy shape and has tests (`LegacyMaskRemediationTest`). | Yes |
| `agent_tasks`, type `agent.report_finding` (live) | Masked evidence in `payload`. Two tasks in the 20:19 snapshot, plus the scan 5 and 6 tasks, which are not in it | Guarded payload update, one row per task ID, after a table dump. Or leave as history. | Yes |
| `C:\tn_backups\20261013-014935\trustnode_fresh.sql` | Two masked-evidence occurrences (finding insert and task payloads) | Preserve. Do not rewrite. Restrict access. | Owner |
| `C:\tn_backups\20261013-014935\trustnode.sql` | 0 occurrences | None | Not needed |
| `C:\tn_backups\config-20261013-021259\.env` | A full copy of `.env`, every key and value, **unmasked** | Preserve. Restrict access (owner). Treat its values as exposed for rotation. | Owner |
| `C:\tn_scratch\evidence-20261013\agent-logs\laravel.log`, `agent-stdout.log` | 0 occurrences of the masked shape. Contains log text | Preserve. Handle with care. | Not needed |
| `C:\tn_backups\investigation-20261013\` | 0 occurrences | Preserve | Not needed |
| Repository and host `storage/` (excluding `vendor`, `node_modules`, `.git`) | 0 files | None | Not needed |
| `reports` table | 0 rows in the snapshot. The observer creates no reports | None | Not needed |
| `trustnode-storage` volume logs | Not checked. Containers are stopped. Only the agent copy was checked | Count-only check after start | Not needed |
| Redis (`agent_observe_env_hash_*`) | A SHA-256 of the file, not its text | None | Not needed |

**Credential names and safe rotation (no values shown).**

| Name | Where its value appears | Consumer | Notes |
|---|---|---|---|
| `DB_PASSWORD` (`.env` line 28) | `.env`; `findings` and `agent_tasks` evidence (masked); backups | Containers get `DB_PASSWORD: root` from the tracked `compose.dev.yaml`, which overrides `.env`. The host CLI cannot reach MySQL. | **Rotating the `.env` value changes nothing at runtime.** The effective database password is the committed `root`, and MySQL's root password is also `root` (`MYSQL_ROOT_PASSWORD`). That is a separate hardening item (secret injection, then a live `ALTER USER`, which needs approval). |
| `TRUSTNODE_API_TOKEN` (`.env` line 75) | Same locations | Host CLI (`cli/src/Config/ConfigManager.php`) and MCP clients (`mcp/README.md`). It is a Sanctum personal access token. | Revoke the old token on the platform after the new one is in place. Then update the CLI and MCP configs. |
| `APP_KEY`, `REDIS_PASSWORD`, `MAIL_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | `.env` and the full `config` backup copy only. The scanner did not flag them, and they are not in findings or tasks | Containers and config | Rotation is the owner's decision. The config backup holds them in full, so treat them as exposed if that backup is. `APP_KEY` rotation breaks existing encrypted data unless a re-encryption plan exists. Do not rotate it casually. |

**Rotation procedure (owner; the assistant does not rotate or edit `.env`):**
1. Confirm consumers by name only (`grep -rn "<NAME>" app config cli/src mcp`). Done for the two flagged names.
2. Create the new value at its source first (MySQL user, Sanctum token, AWS IAM key). Keep the old value valid.
3. Stop the services that read the value, once the stack start is approved, so none still holds the old value.
4. Edit `.env` byte by byte, with a match-count assertion. Never `sed -i`: the file has 2 CRLF lines and 75 lines in total, and `sed -i` removed the CR bytes once already (section 4).
5. Start the services. Confirm the variable is read by name only, then run the health checks.
6. Revoke the old value at its source. Leave the backups unchanged, and note that the values in them are revoked.
7. Clear the stored masked evidence (table above), with approval.

Revoke only after the new value works. Stop condition: any service fails to start with the new value.

**Stack state.** The stack stays stopped. Starting it is a live action (section 6a).

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

> Superseded for the current state by section 6a. Since 2026-10-12 21:23 UTC, every container is stopped, so there is nothing to stop first.

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

## 6a. Service recreate plan (revised 2026-10-13; not executed; awaiting approval)

**What changed.** The plan was written for running services. Since 2026-10-12 21:23 UTC every `trustnode-*` container has been stopped (section 0). There is nothing to stop, so the sequence now **starts** the stack on the committed configuration (`bb20d68`). Starting is a live action and needs approval (group 5).

**Compose change (committed locally as `bb20d68`, not pushed).** Four `DB_DATABASE` values now read `${DB_DATABASE:-trustnode_fresh}`. Rendered value: `trustnode_fresh` for all four services (verified 2026-10-13 with `docker compose config`). Host `.env` value: `trustnode_fresh`.

**State captured before this plan (read-only; nothing started):**

| Item | State |
|---|---|
| Containers | All `exited` (section 0). |
| Restart counts | `worker` 8, `agent` 9, all others 0 (`docker inspect`). |
| Health at shutdown | `mysql`, `php`, `redis` `unhealthy`. `nginx`, `node`, `worker`, `scheduler`, `agent` have no healthcheck. |
| MySQL shutdown | Log ends at `Received SHUTDOWN from user <via user signal>` (21:23:58 UTC). No `Shutdown complete` line before the kill (21:24:02). Expect InnoDB crash recovery on start. Not yet observed. |
| Agent | Last start 12:30:32 UTC, on code from before `e1776b1`. Stdout ends with the shutdown. |
| Ownership lease (`agent_states`) | Not readable while mysql is down. The heartbeat stopped at about 21:23 UTC, more than seven hours ago. The lease is far past its 90 s timeout, so it should be expired. Confirm in step 2. |
| Queue (`agent_tasks`) | 20:19 UTC snapshot: 0 pending, 0 processing. Live count unknown. |
| `jobs`, `job_batches` | Empty in the 20:19 snapshot. Live count unknown. |
| Backups | C1 `C:\tn_backups\20261013-014935\` and C2 `C:\tn_backups\config-20261013-021259\`: `sha256sum -c` OK on 2026-10-13. |
| Evidence | Agent logs copied to `C:\tn_scratch\evidence-20261013\` (outside the repo; contains log text, so handle with care). Observer timeline and the scans 2–3 before-state are in `C:\tn_backups\investigation-20261013\`. |

**Preconditions** (verify live in step 2; any failure stops the run):
- **Guard present (verified locally 2026-10-13):** `AGENT_OBSERVE_ENV_ENABLED` is not set to `true` in `.env`, so the `.env` observer stays off (section 4c). Confirm with the flag check (name and value `true`/other only), not by printing `.env`.
- `jobs` = 0 and `job_batches` = 0.
- `agent_tasks` has no `pending` or `processing` row. A pending row is claimable by the agent after restart; review it before step 5.
- `agent_states` for `trustnode-local-agent-1` is not `running`, or its heartbeat is older than `agent.heartbeat.timeout` (90 s).
- Scans 2 and 3 are unchanged, or the approved SQL in `scans-2-3-proposal.sql` has been applied first.
- C1 and C2 checksums still match.

**Step 1 detail (mysql only; needs approval; not run).** Exact command: `docker compose -f compose.dev.yaml up -d mysql`. Health check: `docker inspect -f '{{.State.Health.Status}}' trustnode-mysql-1` returns `healthy` within 120 s. Log check: `docker logs trustnode-mysql-1 2>&1 | tail -50` shows InnoDB recovery and `ready for connections`, and no `[ERROR]` or `Assertion` line. Stop if the log shows unresolved corruption (for example `InnoDB: Database page corruption` or a failed recovery) or the container exits. Do not run migrations, change data, clear jobs or run the scans 2–3 SQL during recovery.

**Command sequence (for approval; run from `C:\xampp\htdocs\trustnode`):**

| Step | Command | Check before the next step |
|---|---|---|
| 1 | `docker compose -f compose.dev.yaml up -d mysql` | Only `mysql` starts; its configuration did not change. `docker logs trustnode-mysql-1` shows crash recovery, then `ready for connections`, with no `[ERROR]`. Healthy within 120 s. |
| 2 | `docker compose -f compose.dev.yaml up -d redis`, then SELECT-only checks through `docker compose exec -T mysql` for the preconditions above | All preconditions hold. Otherwise stop. |
| 3 | `docker compose -f compose.dev.yaml up -d --no-deps php` | `php` healthy within 120 s. `docker compose exec -T php printenv DB_DATABASE` = `trustnode_fresh`. |
| 4 | `docker compose -f compose.dev.yaml up -d --no-deps worker` | `printenv DB_DATABASE`. No `FATAL` in `docker logs trustnode-worker-1`. `RestartCount` 0 after 2 min. |
| 5 | `docker compose -f compose.dev.yaml up -d --no-deps agent` | `TrustNode Agent process started.` once, then `ownership acquired`. No `Exiting` line and no loop. `RestartCount` 0 after 2 min. |
| 6 | `docker compose -f compose.dev.yaml up -d --no-deps scheduler` | `printenv DB_DATABASE`. The scheduler log shows no error from `agent.observe_env`. |
| 7 | `docker compose -f compose.dev.yaml up -d --no-deps nginx`; `node` only if approved | `nginx` serves the app. `docker compose exec -T php php artisan tinker --execute='echo config("database.connections.mysql.database");'` prints `trustnode_fresh`. |
| 8 | `docker compose -f compose.dev.yaml ps` | Required services running; no restart loop. Re-run the scan count query: no new `.env` observer scan unless `.env` changed on purpose. |

`--no-deps` keeps the order above as the only order. Do not run `docker compose up` for the whole stack.

**Expected impact:**
- The database goes from stopped to running, and MySQL runs crash recovery.
- The queue and observers resume. The scheduler enqueues `agent.observe_env` every minute.
- An observer scan is created only if the `.env` hash differs from the Redis cache. If the Redis cache was lost, one extra scan is expected (it would be scan 7, with its findings merged into finding 1). If the cache persisted, none is expected.
- The agent runs the current startup code (`e1776b1`) for the first time in a container. Its lease should already be expired.
- The web app is unavailable until `nginx` starts.
- No migration, no data change, no deletion.

**Stop conditions:**
- `mysql` not healthy within 120 s, or `[ERROR]` in its log.
- Any precondition in step 2 fails.
- `DB_DATABASE` other than `trustnode_fresh` in any container.
- Any service's `RestartCount` rises within 2 min, or `agent` exits twice within 5 min.
- A new `.env` observer scan appears, unless `.env` was changed on purpose.
- A `pending` agent task appears that cannot be explained.

**Rollback:**
1. Stop what this run started, in reverse: `docker compose -f compose.dev.yaml stop node nginx scheduler agent worker php redis mysql` (omit `node` if it was not started). Stop does not remove containers or volumes. This returns the stack to the state in section 0.
2. Config: the Compose change is the committed `bb20d68`. Its values equal the previous ones, so no restore is needed. Reverting it is a new commit (`git revert`), which needs approval. Do not rewrite history. If the `.env` or Compose file must be restored, use `cp -p` from C2 (Compose first, then `.env`) and verify the checksums.
3. Do not run `migrate:rollback`. Do not drop or restore a database unless the C1 dump restore is approved.
4. If mysql fails crash recovery: stop it, leave the `trustnode-db-data` volume untouched, and wait for approval before any restore from C1 into a new database.

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
| Scan 2 (running, created 2026-10-10, no org) | orphaned (no job) | Guarded `UPDATE` to `failed` after a table backup, or leave. Procedure in section 4b; SQL with the STEP 0 recheck and rollback in `C:\tn_backups\investigation-20261013\scans-2-3-proposal.sql`. | Yes; owner chooses |
| Scan 3 (queued, created 2026-10-10, no org) | never started | Guarded `UPDATE` to `cancelled`, or leave. Section 4b. | Yes; owner chooses |
| Scans 4, 5, 6 (`.env` observer, not uploads; section 4b) | completed | Not user-requested. The observer is now off by default (section 4c). Rotate the two credentials (owner). Clear the masked evidence after rotation (section 4c). No deletion. | Owner decision and rotation |
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
3. **Configuration alignment** (section 4): **done** for `.env` and `compose.dev.yaml` (2026-10-13, backup C2). `compose.dev.yaml` is committed locally as `bb20d68` (not pushed).
4. **Migrations on `trustnode`** (section 5): only if Option T; schema change.
5. **Start the stack and recreate services** (section 6a, revised 2026-10-13): every container is stopped, so this starts them on the committed configuration. **Not approved.**
6. **Project registration and acceptance scan** (section 7): exact path.
7. **Legacy remediation** (section 9): finding 1, scans 2 and 3 (method), backfill; one approval each.
8. **IMAP installation** (section 10): installs a package.
9. **Retire the non-canonical database**: only after the data review; irreversible.
10. **Push** of local commits, and review of `76bb8da` and `5b100f1`. Publishes history.
11. **Scans 2 and 3 status update** (section 4b; SQL in `C:\tn_backups\investigation-20261013\scans-2-3-proposal.sql`): the recheck needs the mysql service running. **Not approved.**
12. **Credential rotation** (section 4c): rotating `DB_PASSWORD` and `TRUSTNODE_API_TOKEN` is an owner action. The assistant does not rotate or edit `.env`. **Not approved.**
13. **Continuous project scan setting** (section 4b): proposed `AGENT_CONTINUOUS_SCAN=false`, documented, **not applied**. Decide before any project registration (group 6). It does not control the `.env` observer.
14. **Observer enablement** (section 4c): the guard is implemented and default off. Turning it on is an owner decision, and recovery does not need it. **Not approved.**

Phase 6 and Phase 9 live checks follow from groups 5–7 and need no separate approval once those are granted.
