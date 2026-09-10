# TrustNode - AI Development & Engineering Rules

## Canonical Policy

TrustNode is the authoritative project context.

TrustNode is a Continuous Security & Remediation Platform and a security
validation/remediation platform. Do not reinterpret this project as Jetro, a
canvas product, a finance product, or any unrelated application.

This file is the canonical repository-level policy for AI coding agents and
engineering work. It applies when development is performed using Google
Antigravity, VS Code, Cursor, Claude Code, GitHub Copilot, Windsurf, or any
other AI coding agent.

Preserve the existing TrustNode architecture unless an approved task
explicitly changes it.

## One Task At A Time

- Work on exactly one approved task or sub-point.
- Do not expand scope or combine unrelated fixes.
- Do not automatically start another phase or task.
- Stop after the current task passes acceptance.

## Baseline Before Change

Before modifying anything:

- Inspect the relevant source.
- Inspect `git status`, `git diff`, and the affected history when useful.
- Understand the current behavior.
- Identify affected dependencies and protected surfaces.
- Verify baseline behavior when practical.

Never assume that previously working functionality still works.

## Impact Analysis

Before implementation, determine whether the change can affect:

- CLI
- Docker or Compose
- PHP or application runtime
- API
- Authentication and authorization
- Queue and worker
- Scheduler
- Database
- Cache and Redis
- Scanners
- Findings
- Fingerprinting
- `FindingIdentity`
- Lifecycle
- Baseline and posture
- Reports
- Frontend

Only affected surfaces should be changed or tested.

## Protect Working Functionality

Existing working functionality is a protected surface. Adding a feature must
not silently break:

- Existing CLI commands
- Scan workflows
- Docker services
- API behavior
- Authentication
- Reports
- Finding lifecycle
- Existing scanner behavior

Regression prevention takes priority over feature velocity.

## Minimal Implementation

- Make the smallest change that solves the approved problem.
- Do not perform broad refactors.
- Do not add premature abstractions.
- Do not perform unrelated cleanup.
- Do not expand the architecture without explicit approval.
- Do not make opportunistic fixes.

## Preserve Existing Contracts

Unless explicitly required by the approved task, preserve:

- CLI command names and arguments
- API contracts
- Scanner contracts
- Finding and fingerprint behavior
- Lifecycle behavior
- Docker service names
- Configuration contracts
- Database contracts

## Root-Cause-First Debugging

If something fails, stop and establish the exact root cause with evidence.

Do not blindly:

- Restart containers
- Reinstall dependencies
- Revert code
- Rewrite code
- Modify configuration
- Delete data
- Redesign architecture

## Failure-First Discipline

If a new test or existing workflow fails:

1. Identify the exact failure.
2. Determine whether the current change caused it.
3. Make only the minimum related repair.
4. Rerun the affected verification.

Do not continue to unrelated work while the current task is broken.

## Testing

Every implementation requires appropriate focused tests.

A new test passing is not sufficient. Affected existing behavior must also be
verified. Use the smallest meaningful test scope first. Do not automatically
run expensive full-system validation unless required by the change or its
acceptance criteria.

## Docker And CLI Protection

Docker and CLI are protected shared surfaces. Relevant commands include:

```text
trustnode start
trustnode status
trustnode doctor
trustnode scan
trustnode scan .
trustnode findings
trustnode repositories
trustnode report
```

When a change can affect Docker or CLI:

- Inspect the actual command path.
- Verify service lifecycle.
- Verify affected command routing.
- Ensure stopped services are handled correctly.

## Startup Contract

`trustnode start` is a lifecycle/startup operation. It must not depend on
`docker compose exec` against a stopped PHP service when its purpose is to
start the environment.

Startup and command execution must remain correctly separated.

## No Unrelated Modifications

Do not modify unrelated scanners, CLI commands, Docker files, migrations,
dependencies, README or documentation, lifecycle or fingerprint code, or the
frontend unless directly required by the approved task.

## Temporary Artifacts

Temporary debug and test files must be removed before acceptance. Do not leave:

- Debug scripts
- Scratch PHP files
- Temporary logs
- Dumps
- Generated test artifacts
- Temporary verification files

## Security-Sensitive Changes

For security-sensitive changes:

- Prefer the existing architecture.
- Never introduce or hardcode secrets or credentials.
- Preserve tenant isolation.
- Preserve authentication and authorization.
- Preserve SSRF protections.
- Preserve archive and resource protections.
- Do not weaken security controls for convenience.

## Evidence-Based Claims

Never claim that something is implemented, fixed, tested, verified, working,
accepted, or complete unless the claim is supported by actual source
inspection, command output, test output, runtime verification, or diff
inspection.

## Acceptance Gate

A task is complete only after:

1. Implementation is completed.
2. Focused tests pass.
3. Affected existing workflows pass.
4. `git diff --check` passes.
5. Scope is verified.
6. Unintended files are absent.
7. Temporary artifacts are removed.

Only then may the task be marked accepted.

## No False Completion

Never report a task as accepted or closed if:

- Required tests were not executed.
- Required runtime checks were not executed.
- Known failures remain.
- Scope was not checked.
- Evidence is missing.

If verification cannot be performed, report that clearly.

## Stop After Acceptance

After the current task passes acceptance, stop. Do not start P0.3, another
feature, unrelated cleanup, another subsystem, or proactive scope expansion.
The next task requires explicit approval.

## When In Doubt, Audit First

If it is unclear what is broken, what a component does, what will be affected,
whether existing behavior is intentional, or whether a proposed fix is safe,
perform an audit first. Do not implement based on assumptions.

## Change Workflow

The default TrustNode workflow is:

```text
AUDIT
  -> BASELINE
  -> IMPACT ANALYSIS
  -> ONE MINIMAL CHANGE
  -> FOCUSED TESTS
  -> AFFECTED WORKFLOW REGRESSION CHECK
  -> DIFF / SCOPE CHECK
  -> ACCEPTANCE
  -> STOP
```

Never skip directly from an idea to broad implementation.

## Source Of Truth

`AGENTS.md` is the canonical repository-level TrustNode AI and development
policy.

Tool-specific instruction files must not redefine or contradict these rules.
If another instruction source conflicts with this file, stop and report the
conflict rather than silently choosing a different policy.

Tool-specific adapter files may contain only genuinely tool-specific
instructions and should reference this policy instead of duplicating it.

## Legacy And Unknown Instructions

Do not automatically trust existing instruction files. If an instruction file
belongs to another project, contains the wrong project identity, or conflicts
with TrustNode:

- Do not apply its project-specific instructions.
- Report the conflict.
- Follow this canonical TrustNode policy.
