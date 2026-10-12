# TrustNode — Canonical Implementation Plan

**Status:** Approved plan; implementation must follow the phase gates below  
**Purpose:** Single source of truth for continuing TrustNode work with Claude  
**Rule:** The current repository and actual runtime are authoritative. Earlier audits are snapshots, not proof of current state.

---

## 1. Product direction

TrustNode helps developers find and understand security risks early, starting with the smallest code/resource entities.

The immediate goal is a reliable developer workflow—not a broad rewrite:

1. Install/start the supported local developer edition.
2. Complete secure first-run setup.
3. Explicitly authorize the codebase(s) TrustNode may inspect.
4. Run a real scan against only approved scope.
5. See truthful progress, Agent status, findings and safe evidence.
6. Get remediation guidance and a copyable AI fix prompt.
7. Export a valid, safely redacted report.

### Non-negotiable safety promises

- Scanning approved source code is read-only.
- Never automatically edit, delete, install into, commit, or push the developer's code.
- Never scan paths outside the explicitly authorized scope.
- Never expose raw secrets in findings, logs, notifications, AI prompts, or reports.
- Never fake success or substitute mocked data for real functionality.
- Never claim a feature works solely because a route, class, page, or test exists.
- Preserve existing work and architecture unless concrete evidence justifies a narrow change.

## 2. Product and repository boundaries

Keep the repository/product boundaries intentional; do not merge everything into one repository as a shortcut.

- **Public `trustnode` repository:** Developer Free CLI/scanner core and the reusable scanning foundation.
- **Private `trustnode-platform` repository:** Paid web/admin experience, organizations, policies, RBAC, licensing, reports, LLM-assisted features and enterprise controls.
- **Private `trustnode-agent` repository:** Agent runtime/distribution where that boundary is already part of the accepted product direction.
- **Self-hosted delivery:** Preserve the self-hosted deployment model.
- **No VS Code extension in this plan.** Do not introduce it unless the product owner changes scope.

The current checkout may contain legacy platform UI, Agent code, tenant models or other components. Do not delete, move or split them during this audit. First inventory the real state, identify repository ownership and dependencies, and propose a safe boundary plan separately if code currently crosses these boundaries.

**Reuse before rebuilding:** Existing scanners/VAPT, findings, evidence, reports, CLI and Docker foundation should be reused. The immediate CLI launch path should prioritize local/public/private repository scanning, risk validation, and re-validation. Do not reimplement existing scanner functionality without evidence of a defect.

## 3. Current evidence and audit limitations

Earlier audit snapshots reported:

- Laravel/PHP backend and React/Vite frontend exist.
- Existing scanner implementations include secret detection, SAST, dependency scanning and infrastructure/container-related components.
- Setup/login, Agent telemetry, scan, findings, report, notification and settings pages/routes exist.
- Several end-to-end paths remain unverified; previous test runs reported failures.
- A previous local snapshot reported a mismatch between `compose.dev.yaml` and `compose.yaml`, with installer scripts still referring to `compose.dev.yaml`.
- A previous snapshot reported database/cache/queue configuration mismatches and a writable repository mount for the Agent.
- A prior audit reported setup-lock changes, but the inspected remote `main` controller did not contain that lock.

These observations may refer to different commits or working trees. **Do not assume they are all true of the current local checkout.** Resolve them with the Phase 0 snapshot before changing code.

No live local runtime was available to this planning document. Do not claim containers, database, installer, Agent, or browser workflows are currently working until actually verified.

## 4. Execution rules for Claude

### Always preserve the user's work

Before any implementation:
- Inspect `git status --short --branch`, staged and unstaged diffs, untracked files, current branch and relevant file history.
- Identify generated/local files and avoid overwriting them.
- Never run `git reset`, `git clean`, forced checkout, stash, rebase, or any command that discards or relocates user work.
- Never commit or push.
- Do not edit `.env`, reveal its values, reset databases, delete volumes, run destructive migrations, install dependencies, or start/stop/restart services without explaining the need and obtaining explicit authorization.
- Do not remove debug/temp artifacts blindly; inspect ownership, tracking and usage first.
- Do not delete `TenantScope`, `organization_id`, default Team, migrations, backfills or related compatibility/security structures merely to simplify single-developer behavior.
- Do not weaken authorization or broaden `withoutGlobalScopes()` to make tests pass.
- Do not introduce mock success or simulated Agent activity.

### Work in small, auditable batches

For every batch, report:
1. Files changed and why.
2. Concrete behavior corrected.
3. Exact commands/tests run and actual results.
4. What remains unverified.
5. Confirmation that unrelated user work was preserved.

Run focused tests for changed code. Avoid repeatedly running the entire suite after every small edit. Run the full suite once at an appropriate acceptance gate if the environment allows it. Do not call a failure “pre-existing” without a committed-baseline comparison.

Pause for destructive/irreversible actions, database reset/migration risks, dependency installation, service restarts, or a genuine product decision. Ordinary reversible changes within the approved scope do not need repeated approval.

---

## 5. Phased implementation plan

### Phase 0 — Read-only repository and runtime audit

**Current task: do this first. No code changes.**

Collect evidence from the actual local checkout and runtime. Do not fix anything during this phase.

#### 0.1 Repository integrity
- Current branch and `git status --short --branch`.
- Staged/unstaged diff summaries and relevant full diffs.
- Tracked, untracked, deleted and renamed Compose/install files.
- Confirm whether `compose.dev.yaml`, `compose.yaml`, production Compose files and installer references agree.
- Identify the actual commit/branch and whether the latest reported P0 changes exist in this checkout.
- Preserve all user changes; do not normalize the tree.

#### 0.2 Runtime inventory — read-only
- List containers, status, published ports and names.
- Report whether the Agent, worker, scheduler, web/API and database are actually running.
- Inspect existing logs only when safe and avoid printing credentials or sensitive data.
- Do not start/stop/restart services, execute migrations, change environment files or touch persistent volumes.
- If Docker is unavailable, say so and continue the repository audit without pretending runtime verification occurred.

#### 0.3 Security/configuration checks
- Confirm whether unauthenticated `/debug-db` exists in the current working tree and registered route list.
- Inspect local port bindings, including web and Vite development ports.
- Inspect initial-owner setup for atomic/concurrency-safe behavior.
- Compare database name, cache, session and queue configuration across `.env` (never output values), Compose and application config.
- Inspect whether source paths are mounted read-only for the Agent and whether runtime data is separately writable.
- Inspect canonical-path validation and directory-boundary enforcement.
- Inspect CORS and public debug/diagnostic endpoints without exposing secrets.

#### 0.4 Feature/test inventory
For each feature, record:
- `IMPLEMENTED AND VERIFIED`, `IMPLEMENTED BUT UNVERIFIED`, `PARTIAL`, `BROKEN`, `MISSING`, or `BLOCKED`.
- Actual file/class/route evidence.
- Exact test evidence and test environment.
- Known failure/root cause only when supported by evidence.

Cover setup/login, CLI, project authorization, scans, scanners, Agent lifecycle/telemetry, queue, findings/lifecycle, redaction, notifications, remediation prompt, reports/PDF, installer and repository boundaries.

#### Phase 0 deliverable
Create a concise evidence-based report in the project documentation area or provide it in the Claude response. It must include:
- Current repo/branch and dirty-tree summary.
- Current runtime facts or explicit inability to verify.
- Security findings ranked P0/P1/P2.
- Feature status matrix.
- Missing functionality and evidence.
- Conflicts between the checkout and older audits.
- Exactly **one recommended next task**, with reason and acceptance criteria.

**Stop after reporting Phase 0. Do not edit files in this phase.**

### Phase 1 — P0 local security hardening

Begin only after Phase 0 findings are available and the owner authorizes implementation.

- Remove or protect any confirmed unauthenticated debug/database endpoint.
- Bind local web/UI ports to `127.0.0.1` where local-only behavior is intended; preserve internal Docker networking.
- Review development-server ports separately; do not assume the UI port is the only exposure.
- Make initial setup one-time and concurrency-safe across actual supported cache/database configurations.
- Ensure setup failures do not leak exception details or secrets.
- Add focused regression tests for the confirmed defects.
- Do not claim runtime/network protection based only on static tests.

**Acceptance:** Focused tests pass; relevant route/Compose diffs are reviewed; setup cannot create multiple initial owners under the supported configuration; no unrelated changes.

### Phase 2 — One dependable developer scan workflow

Trace one real scan through the full path:

`explicit project authorization → scan request → queued task/Agent → scanner execution → persisted scan/findings → UI/CLI result`

- Reuse the existing CLI and scanner core.
- Fix actual root causes for broken route binding, missing context, enum conversion, task processing or finding persistence.
- Validate the target path canonically before reading files.
- Enforce a directory boundary (for example, a path prefix must end at a separator or be checked with a safe path-relative operation).
- Restrict scanning to approved scope; do not follow symlinks outside it without an explicit, safe policy.
- Keep source code read-only.
- Report meaningful progress, completion, partial results and errors.
- Never bypass authorization to make a workflow pass.

**Acceptance:** One approved project completes a real scan; findings persist and are visible in the appropriate CLI/platform surface; an unapproved/out-of-scope path is rejected.

### Phase 3 — Finding correctness and server-side secret safety

- Trace finding creation, identity/deduplication, severity, lifecycle, status counts and baseline behavior.
- Fix persistence/root-cause defects without hiding failures or weakening scopes.
- Redact sensitive evidence server-side before storage or before any outward use, according to the data model and required forensic policy.
- Apply consistent redaction to API responses, notifications, logs, reports/PDF and AI fix prompts.
- Never send a raw secret to an LLM.
- Use synthetic test secrets only; never include actual credentials in test output.

**Acceptance:** Synthetic secrets do not appear unredacted in any user-facing/exported channel; lifecycle and counts match persisted records; focused tests pass.

### Phase 4 — Agent reliability and honest observability

- Verify the Agent process, heartbeat, task queue, active task, last successful scan and failure state against real runtime data.
- Distinguish `stopped`, `starting`, `healthy/idle`, `running`, `stale`, `failed` and `unknown` only where each state has evidence.
- Show useful failure reasons and next steps without leaking sensitive details.
- Make retries bounded and observable.
- Add lifecycle controls only if compatible with the actual process manager and safely authorized.
- Avoid duplicate schedulers, duplicate Agent instances or false “healthy” defaults.

**Acceptance:** UI/CLI telemetry matches the real process and database state; stopped/stale/failing states are not shown as healthy; recovery behavior is tested.

### Phase 5 — Remediation guidance and AI prompt

- Keep remediation advisory; never apply patches automatically.
- Include finding ID, rule, severity, sanitized evidence, file/line context where safe, and expected validation.
- Tell the receiving coding assistant to inspect surrounding code, propose a minimal patch, add tests and explain risk.
- Exclude secrets, tokens, `.env` contents and unrelated source material.
- Validate prompt redaction on the server, not only in React.

**Acceptance:** Copyable prompt is useful and reproducible; synthetic secret tests prove that raw credentials are not included; no automatic code mutation occurs.

### Phase 6 — Reports and PDF acceptance

- Generate a report from a real scan rather than a fixture/mock.
- Verify authorization at the download endpoint.
- Use a safe filename and appropriate response headers.
- Open the generated PDF and verify its content, pagination, severity totals and evidence redaction.
- Ensure report generation/download failure is shown honestly.
- Inspect stored/generated HTML and report artifacts before changing or deleting them.

**Acceptance:** A real report downloads and opens, reflects the selected scan, contains no raw secrets, and cannot be downloaded by an unauthorized user.

### Phase 7 — Installation and configuration consistency

- Choose and document one supported install path for the current product/repository.
- Make installer references match the actual Compose file names.
- Reconcile database name and service configuration; reconcile cache/session/queue drivers.
- Ensure the Agent has read-only source mounts and separate writable runtime storage.
- Validate path authorization across Linux/Windows path conventions as supported.
- Review CORS and debug endpoints.
- Preserve user `.env` and existing database volumes; no reset to “fix” configuration.
- Do not install dependencies or recreate services without authorization.

**Acceptance:** Documentation and installer agree; configuration is internally consistent; the Agent cannot write to approved source code; fresh-install verification is performed only with explicit authorization and safe data handling.

### Phase 8 — Product boundary and release readiness

- Map files/features to public `trustnode`, private `trustnode-platform` and private `trustnode-agent`.
- Identify shared interfaces/contracts and avoid copying secrets or proprietary platform features into the public core.
- Do not move/delete files in this phase without a separate migration plan, impact analysis and approval.
- Define a reproducible CLI-first release checklist: install, authorize target, scan, validate risk, re-scan/re-validate, export safe results.
- Keep the existing scanners/VAPT, findings, evidence, reports, CLI and Docker foundation.

**Acceptance:** Repository ownership is documented; public/private boundaries are explicit; release workflow is reproducible; no accidental data or feature migration.

### Phase 9 — Final acceptance

- Run focused tests for all changed areas.
- Run the full test suite once if the environment supports it.
- Perform one controlled end-to-end run of the supported workflow.
- Separate actual new regressions from baseline failures only when baseline evidence exists.
- Record any blocked tests and why.
- Review `git diff`, staged/unstaged status and untracked files to confirm unrelated user work remains intact.
- Do not commit or push.

**Definition of done:** The supported developer workflow is real, secure, repeatable, observable and read-only against source code; failures are visible; evidence is redacted; documentation matches implementation.

---

## 6. Priority guide

Use this ordering; do not work on several unrelated phases at once.

1. Phase 0 read-only evidence.
2. Phase 1 confirmed P0 security issues.
3. Phase 2 end-to-end scan workflow.
4. Phase 3 finding integrity and secret safety.
5. Phase 4 Agent reliability.
6. Phase 5 remediation prompt safety.
7. Phase 6 real PDF acceptance.
8. Phase 7 install/config consistency.
9. Phase 8 public/private boundary mapping.
10. Phase 9 final acceptance.

If Phase 0 discovers a more severe active exposure or data-loss risk, explain it and recommend the smallest safe action before proceeding.

## 7. Copy-paste master prompt for Claude

You are continuing TrustNode development. Read `TRUSTNODE_IMPLEMENTATION_PLAN.md` at the project root first. Treat it as the approved product and execution plan, but treat the actual repository and runtime as the authority for current state.

**START WITH PHASE 0 ONLY.**

Perform a read-only repository and runtime audit. Do not modify files. Do not install dependencies. Do not change `.env`. Do not reset/migrate databases or delete volumes. Do not start, stop or restart services. Do not commit, push, stash, reset, clean or checkout away from user work. Do not print secrets.

Inspect the current branch and Git status, staged/unstaged diffs, untracked/deleted files, Compose/installer consistency, setup/security routes, runtime containers/ports, database/cache/queue configuration (without exposing `.env` values), Agent mounts, path validation, existing tests and actual feature evidence. If runtime access is unavailable, state that limitation rather than inferring success.

Do not repeat old audits indiscriminately. Reconcile prior reports with the current checkout. Every claim must have file/line, command output or test evidence. Use these feature labels: IMPLEMENTED AND VERIFIED, IMPLEMENTED BUT UNVERIFIED, PARTIAL, BROKEN, MISSING, BLOCKED.

Deliver:
1. Repository/branch/dirty-tree snapshot.
2. Runtime status or explicit runtime-verification limitation.
3. P0/P1/P2 security findings with evidence.
4. Feature status matrix.
5. Missing functionality and known test failures, without guessing root causes.
6. Conflicts between prior audit snapshots and current source.
7. Exactly one recommended next task, including rationale, files likely involved, risk and acceptance criteria.

**Stop after delivering Phase 0. Do not implement fixes until the owner reviews the report and authorizes the next task.** Preserve all existing work.

For later authorized phases, implement small focused batches, reuse existing functionality, run only relevant tests, report exact results, and continue only within the approved phase. Never weaken authorization, fake success, auto-edit source code, or claim unverified functionality works.

---

## 8. How to use this document

1. Place `TRUSTNODE_IMPLEMENTATION_PLAN.md` in the root of the correct TrustNode working repository.
2. Open that repository in Claude Code or the chosen Claude coding environment.
3. Ask Claude to read the root plan and execute the Phase 0 master prompt.
4. Review the read-only audit report.
5. Authorize one recommended task at a time.
6. Keep the plan updated only when a product decision changes; record implementation progress in a separate progress/audit document so the canonical plan remains stable.
