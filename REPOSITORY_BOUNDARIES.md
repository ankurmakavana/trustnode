# TrustNode — Repository ownership and boundaries

Status: **documentation only.** No file is moved, split or deleted by this document. Plan Phase 8 requires
moves to be planned and approved separately. This page records where each area belongs today, what
crosses the boundary, and what the public core must never contain.

Authority: `TRUSTNODE_IMPLEMENTATION_PLAN.md` section 2 (product and repository boundaries).

## Three repositories

| Repository | Visibility | Purpose |
|---|---|---|
| `trustnode` | Public | Developer Free CLI, scanning core, and the reusable scanning foundation |
| `trustnode-platform` | Private | Paid web and admin experience, organizations, policies, RBAC, licensing, reports, LLM-assisted features, enterprise controls |
| `trustnode-agent` | Private | Agent runtime and distribution, where that boundary is accepted |

No VS Code extension is in scope.

## Current checkout: where each area belongs

The checkout is still a single repository. Every area below is in it today. The "target" column is where
the area belongs once the boundary is applied.

| Area | Current location (verified) | Target repository | Notes |
|---|---|---|---|
| Developer CLI | `cli/` (24 command classes, `bin/trustnode`) | `trustnode` | Project commands call only the project endpoints. |
| MCP server | `mcp/` (Python, read-only tools) | `trustnode` | Read-only by design; needs its token scope enforced server-side (see progress log). |
| Scanners (SAST, SCA, secrets, container, Kubernetes) | `app/Services/Scan/Scanners/`, `Scan/Infrastructure/`, `Scan/Database/` | `trustnode` | Reusable core. Must not depend on platform code. |
| Finding model, identity, lifecycle | `app/Models/Finding*`, `app/Services/Finding/`, `app/Services/Import/FingerprintService.php` | `trustnode` | Core data model. |
| Secret redaction | `app/Services/SecretRedactor.php` | `trustnode` | Shared by core and platform. |
| Codebase project registration and scan mount boundary | `app/Models/CodebaseProject.php`, `app/Http/Controllers/Api/CodebaseProjectController.php`, `app/Enums/CodebaseProject/` | `trustnode` | Developer workflow. |
| Agent runtime, queue, worker, heartbeat | `app/Services/Agent*`, `app/Console/Commands/AgentRunCommand.php`, `app/Handlers/Agent*`, `app/Capabilities/Agent*` | `trustnode-agent` (per plan) | Platform code must not import agent internals except through the contract. |
| Reports and PDF | `app/Jobs/GenerateScanReport.php`, `resources/views/reports/` | `trustnode` for generation; platform for branded or enterprise templates | |
| Organizations, teams, invitations, permissions | `app/Models/Organization.php`, `Team.php`, `app/Models/Scopes/TenantScope.php`, invitation controllers, `PermissionsPage.jsx` | `trustnode-platform` | Multi-tenancy is platform scope. The single-developer install keeps one default organization (see progress log). |
| Licensing | `app/Facades/LicenseGate`, `Services/License/`, `/api/license/activate` | `trustnode-platform` | |
| LLM-assisted features | `app/Services/DeepSeekHarnessAdapter.php`, `DeepSeekHarnessExecutionResult.php` | `trustnode-platform` | Must never send a raw secret to an LLM (plan Phase 3). |
| AI fix prompt | `app/Services/Remediation/AiFixPromptBuilder.php`, `FindingController::aiPrompt` | `trustnode` | Advisory and redacted. Part of the developer workflow. |
| Web UI | `resources/js/` (33 pages) | Split: developer pages to `trustnode`, admin and enterprise pages to `trustnode-platform` | No split is planned yet. |
| Integrations (credentials, connectors) | `app/Services/Integration/`, `IntegrationController` | `trustnode-platform` | Credentials are encrypted and per organization. |
| Mail | `app/Services/Mail/`, `SettingController` | Installation-wide settings: `trustnode`. Organization-scoped delivery: `trustnode-platform` | See settings design in the progress log. |
| Docker, Compose, installers | `compose.dev.yaml`, `compose.prod.yaml`, `install.*`, `docker/` | `trustnode` | Self-hosted delivery must be preserved. |

## Shared contracts (the only allowed crossing points)

These are the interfaces the three repositories may share. Anything else crosses only through them.

1. **HTTP API** under `/api` (documented by the contract tests in `tests/Feature/CliApiContractTest.php` and
   `tests/Feature/McpApiContractTest.php`). The CLI and MCP depend on the API, not on server internals.
2. **Agent task contract:** the queue payloads (`agent.capability.execute`, `agent.report_finding`,
   `agent.observe_env`) and the capability registry interface (`AgentCapabilityRegistryInterface`).
3. **Finding record:** the fields in `FindingResource` and the redaction rules in `SecretRedactor`.
4. **Scan mount:** the mount path and the read-only rule. The agent may read only what a registered project
   allows.

## Public core must not contain

- Organization management UI or invitation flows.
- Licensing logic or license keys.
- LLM provider keys or prompts that carry platform data.
- Enterprise policy and RBAC enforcement beyond the single-developer model.
- Any real credential, token or `.env` value. The current tree has committed development values in
  `compose.dev.yaml` and placeholder values in `mcp/.env.example`. Both must be reviewed before the public split.

## Current gaps against this boundary (not fixed here)

- The checkout still contains platform code (organizations, licensing, LLM adapter) in the same
  repository as the core. A split requires an approved migration plan (plan Phase 8).
- `cli/vendor/` (2,125 files) is committed in the repository. Vendored dependencies do not belong in a
  public core.
- Artifacts at the repository root that do not belong to any repository: `console.json` (empty),
  `public/test_report.pdf` (a generated report in the web root), `trustnode.png` (4.2 MB image),
  `TRUSTNODE_IMPLEMENTATION_PLAN.md`. These were committed with the remote push and need a decision.

## Release readiness (CLI-first, plan Phase 8)

The reproducible release checklist follows the developer workflow:

1. Install from `install.ps1` / `install.sh` against `compose.dev.yaml`.
2. Complete first-run setup.
3. Register an authorized project: `trustnode project:add --name ... --path ...`.
4. Scan the approved project: `trustnode project:scan <id>`.
5. Review findings: `trustnode project:list`, `trustnode findings`.
6. Obtain remediation guidance: the AI fix prompt for the finding (`GET /api/findings/{id}/ai-prompt`).
7. Re-scan and confirm the finding is gone.
8. Export a redacted report: `trustnode report`.

Status of each step is recorded in `TRUSTNODE_PROGRESS.md`. Steps that need a live run are marked unverified
until one is performed under approval.
