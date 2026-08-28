# TrustNode

TrustNode is a self-hosted application security scanning platform for source repositories and local project directories. It empowers developers to automate static vulnerability detection natively using Docker and a local CLI.

## Table of Contents
- [What TrustNode Does](#what-trustnode-does)
- [Current Capability Matrix](#current-capability-matrix)
- [Architecture](#architecture)
- [Scanner Architecture](#scanner-architecture)
- [Supported Security Checks](#supported-security-checks)
- [Secret Masking](#secret-masking)
- [Scan Types](#scan-types)
- [CLI Reference](#cli-reference)
- [Installation / Development Setup](#installation--development-setup)
- [Running TrustNode](#running-trustnode)
- [Scan Lifecycle](#scan-lifecycle)
- [Findings](#findings)
- [Reports](#reports)
- [Safety and Resource Limits](#safety-and-resource-limits)
- [Compliance](#compliance)
- [What TrustNode Does NOT Yet Do](#what-trustnode-does-not-yet-do)
- [Roadmap](#roadmap)
- [Project Structure](#project-structure)
- [Troubleshooting](#troubleshooting)
- [Development Status](#development-status)

## What TrustNode Does
TrustNode currently implements the following capabilities:
- **Repository Scanning:** Pulls and scans remote Git repositories natively.
- **Local Directory Scanning:** Scans local project files by securely transferring them to the scanning container.
- **SAST (Static Application Security Testing):** Detects injection, traversal, and unsafe execution vulnerabilities.
- **Secret Detection:** Flags high-entropy exposed tokens and well-known provider keys securely.
- **Finding Normalization & Deduplication:** Aggregates findings and generates fingerprints to deduplicate identical findings over multiple scans.
- **Scan Status Tracking:** Asynchronously processes jobs with queue workers and tracks states.
- **Report Generation:** Generates downloadable PDF reports.
- **CLI Interaction:** Provides a native `trustnode` CLI to manage scans locally.

## Current Capability Matrix
| Capability | Status | Current Scope |
|---|---|---|
| Repository Scan | Implemented | Scans remote Git repository URLs. |
| Local Directory Scan | Implemented | Scans local host directory via packaged ZIP upload. |
| SAST | Implemented | Regex-based detection for SQLi, Command Injection, Eval, Path Traversal. |
| Secret Detection | Implemented | Detects AWS, GitHub, GitLab, Stripe, Slack, GCP, JWT, Private Keys, and generic high-entropy API tokens. |
| Finding Deduplication | Implemented | Findings are fingerprinted to track recurring vulnerabilities. |
| Reports | Implemented | Generates PDF status reports of scan findings. |
| Compliance Mapping | Partial | Experimental, heuristic foundational mapping to external security frameworks. |
| SCA | Implemented | composer.lock / Packagist scanning, package-lock.json / npm |
| Container Security | Not Implemented | N/A |
| IaC Security | Not Implemented | N/A |
| CNAPP/CSPM | Not Implemented | N/A |
| Runtime/CWPP | Not Implemented | N/A |
| SOC/SIEM Integration | Not Implemented | N/A |

## Architecture
```text
CLI / API
   |
   v
Scan Creation
   |
   +----------------------+
   |                      |
Repository Scan      Local Directory Scan
   |                      |
Clone Repository     Package + Upload Archive
   |                      |
   +----------+-----------+
              |
              v
        Background Job
              |
              v
      RepositoryScanner
              |
              +---------------------+
              |                     |
              v                     v
         SastScanner          SecretScanner
              |                     |
              +----------+----------+
                         |
                         v
                Normalized Findings
                         |
                         v
              Fingerprint / Deduplication
                         |
                         v
                    Findings DB
                         |
                         v
                     Reports
```

## Scanner Architecture
The scanning engine has been refactored into a modular, orchestrator-driven architecture.

- **`RepositoryScanner`**: The master orchestrator. It traverses the filesystem (respecting the 5MB file-size limit and ignoring vendor/binary paths), reads file contents into memory, and feeds them sequentially to all active scanners.
- **`ScannerInterface`**: The core contract defining `scan(string $content, array $lines, string $relativePath, string $repositoryUrl)`.
- **`AbstractRegexScanner`**: A specialized abstract base for pattern-based detection providing line-by-line validation, evidence context-window truncation, and base compliance mapping.
- **`SastScanner`**: Implements regex logic for SQLi, Eval, Exec, and Path Traversal patterns.
- **`SecretScanner`**: Implements entropy-validated generic secret detection and provider-specific key validation.
- **`ScaScanner`**: Implements Software Composition Analysis. Parses lockfiles and queries the OSV API using `OsvApiClient` to detect vulnerable dependencies. Supports:
  - `composer.lock` via `ComposerLockParser`
  - `package-lock.json` (v1, v2, v3) via `NpmLockParser`

### Privacy & Telemetry
TrustNode is self-hosted and privacy-focused. For SCA, TrustNode sends **ONLY** the `ecosystem`, `package name`, and `installed version` to the OSV database. It **DOES NOT** send source code, repository structures, environment variables, or secrets externally.

### SCA Limitations & Caching
Currently, SCA only supports `composer.lock` (Packagist) and `package-lock.json` (npm). Yarn, pnpm, Python, Go, Rust, Maven, Gradle, etc., are **not yet supported**. Unreachable OSV API errors gracefully downgrade the scan (SCA skipped) rather than crashing the SAST/Secret detection. OSV results are cached (vulnerable=7 days, clean=24 hours). This is dependency vulnerability scanning, not runtime protection.

Future scanner engines can seamlessly plug into this orchestrator architecture by implementing the `ScannerInterface`.

## Supported Security Checks

### SAST Rules
| Rule ID | Title | Category | Severity | Description |
|---|---|---|---|---|
| SEC-SAST-SQLI | Potential SQL Injection | SAST | High | A raw SQL query concatenates/interpolates variables directly. This makes the application vulnerable to SQL Injection. |
| SEC-SAST-CMD | Unsafe Command Execution | SAST | Critical | The application executes system shell commands using interpolated variables. This can lead to Remote Command Execution (RCE). |
| SEC-SAST-EVAL | Unsafe Dynamic Code Execution (eval) | SAST | Critical | The eval() function executes arbitrary strings as code. If user input is passed here, it allows arbitrary code execution. |
| SEC-SAST-PATH | Potential Path Traversal | SAST | Medium | Unvalidated user input is concatenated into a file system read function, potentially allowing path traversal to read arbitrary system files. |

### Secret Detection Rules
| Rule ID | Provider | Severity | Description | False-Positive Protection |
|---|---|---|---|---|
| SEC-SECRET-AWS | AWS | Critical | AWS Access Key ID. | Strict prefix formatting. |
| SEC-SECRET-GITHUB | GitHub | Critical | GitHub PAT/OAuth token. | Strict modern `ghp_` etc. prefix and length limits. |
| SEC-SECRET-GITLAB | GitLab | Critical | GitLab PAT. | Strict `glpat-` prefix. |
| SEC-SECRET-STRIPE | Stripe | Critical | Stripe live secret keys. | Excludes test tokens; requires `sk_live_`/`rk_live_`. |
| SEC-SECRET-SLACK | Slack | High | Slack API token. | Requires `xox[baprs]-` prefix. |
| SEC-SECRET-GCP | Google Cloud | High | GCP API key. | Requires `AIza` prefix and strict boundary. |
| SEC-SECRET-PRIVATE-KEY | Cryptography | Critical | Standard PEM Private Key. | Validates strict PEM header structure. |
| SEC-SECRET-JWT | Auth | High | JSON Web Token (JWT). | Requires `eyJ` base64-header and dot boundaries. |
| SEC-SECRET-GENERIC | Generic | High | Exposed high-entropy API token. | Uses Shannon Entropy > 3.5, and string filters. |

**Generic Secret Filters:**
To prevent noisy detections, `SEC-SECRET-GENERIC` automatically filters out the following before flagging:
- Placeholder values (`example`, `placeholder`, `changeme`, `test`, `null`, `YOUR_API_KEY`, `localhost`).
- Known hash structures (MD5, SHA1, SHA256).
- UUIDs.

## Secret Masking
Exposed secrets pose a risk in reporting tools. TrustNode masks secret values during processing before they are ever persisted to the database.

- The secret value (`ghp_1234567890abcdef1234567890abcdef1234`) will be aggressively masked to `ghp_********************************1234` in the database.
- The context *around* the matched line will be safely preserved up to a 2000-character boundary so developers can still understand where the leak occurred without exposing the actual token itself.

## Scan Types

### Repository Scan
**Command:**
```bash
trustnode scan https://github.com/example/repo.git
```
**Flow:**
- Dispatches `ScanRepositoryJob` which securely clones the remote Git repository.
- Scans files using the modular scanner engines in the background.
- Emits scan status and stores fingerprinted findings.

### Local Directory Scan
**Command:**
```bash
trustnode scan C:\path\to\project
trustnode scan .
```
**Flow:**
1. The Windows host PowerShell CLI validates the directory.
2. The CLI iterates files and excludes known noise directories (`vendor`, `node_modules`, `.git`, etc.).
3. A temporary `.zip` archive is created locally.
4. An archive size restriction is enforced locally (max 50,000 files, max 200MB source).
5. The archive is uploaded securely to the Docker API via POST (adhering to a 100MB compressed Nginx/PHP HTTP max upload limit).
6. A local scan background job is queued in Docker.
7. The worker extracts the workspace, scans it, and cleans up temporary archives both locally and inside the container immediately to ensure zero footprint.

## CLI Reference
The `trustnode` global CLI provides native management of the TrustNode instance.

| Command | Description | Example |
|---|---|---|
| `trustnode scan` | Start a new remote or local scan. | `trustnode scan .` |
| `trustnode scan list` | List all historical scans. | `trustnode scan list` |
| `trustnode scan status` | Check the lifecycle status of a specific scan ID. | `trustnode scan status 102` |
| `trustnode findings` | Retrieve real findings for a scan ID. | `trustnode findings --scan=102` |
| `trustnode findings:list` | List all findings across scans. | `trustnode findings:list` |
| `trustnode report` | Request a new PDF report to generate. | `trustnode report 102` |
| `trustnode report status` | Check status of the PDF report generation. | `trustnode report status 102` |
| `trustnode report download`| Download the generated PDF report. | `trustnode report download 102` |
| `trustnode repositories` | List tracked repositories. | `trustnode repositories` |
| `trustnode login` | Login using an API token. | `trustnode login` |
| `trustnode logout` | Logout and revoke API token. | `trustnode logout` |
| `trustnode update` | Update TrustNode instance. | `trustnode update` |
| `trustnode status` | View system and service health. | `trustnode status` |
| `trustnode whoami` | Get authenticated user info. | `trustnode whoami` |
| `trustnode doctor` | Diagnose authentication and connectivity. | `trustnode doctor` |
| `trustnode repair` | Repair local CLI authentication tokens. | `trustnode repair` |
| `trustnode activate` | Activate Professional License. | `trustnode activate` |

## Installation / Development Setup

### Prerequisites
- Docker & Docker Compose
- Windows PowerShell (if running natively on Windows)

### Setup
TrustNode uses a streamlined installer script:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/trustnode-org/trustnode.git
   cd trustnode
   ```
2. **Execute Installation:**
   ```powershell
   ./install.ps1 -Mode install
   ```
   *The installer requires a TrustNode License Key, checks for Administrator permissions and Docker prerequisites, sets up environment variables, provisions containers, configures the database, links the local CLI wrapper, and creates the admin account.*

## Running TrustNode

- **Start Services:**
  ```bash
  docker compose up -d
  ```
- **Verify Containers:**
  ```bash
  docker compose ps
  ```
- **Access Application:** Open `http://localhost:8000`
- **Use CLI:** Run `trustnode` globally from any PowerShell terminal.

## Scan Lifecycle
A scan transits through the following statuses processed asynchronously by the Redis-backed queue worker:
1. `created` - The scan record was initialized.
2. `queued` - The scan job was dispatched to the worker.
3. `running` - The worker is currently executing the `RepositoryScanner`.
4. `completed` - Scan successfully finished analyzing files.
5. `failed` - The scan aborted due to an internal error or invalid repository.

## Findings
Generated findings persist to the database mapping to `App\Models\Finding`. Each finding contains:
- The identified **Rule ID**, **Title**, and **Severity**.
- **Evidence** (with secrets masked and context safely preserved/truncated).
- **Technical Details** (line numbers).
- **URL/Path references**.
- A unique deterministic **Fingerprint** hash generated per-vulnerability. The `FingerprintService` deduplicates duplicate findings across repetitive scans so issues are tracked over time.

## Reports
TrustNode generates detailed asynchronous reports.
1. Run `trustnode report <SCAN_ID>` to dispatch the generation job.
2. Check `trustnode report status <SCAN_ID>` for `queued`, `generating`, or `completed`.
3. When complete, run `trustnode report download <SCAN_ID>` to download the PDF to your local filesystem.

## Safety and Resource Limits
TrustNode imposes strict limits to guarantee stability and prevent resource exhaustion:
- **5MB Scan Limit**: Source files exceeding 5MB are automatically skipped by the `RepositoryScanner` to prevent Docker container memory/RAM OOM faults.
- **Upload Archiving Limit**: The CLI enforces a strict 50,000 file limit and 200MB uncompressed limit on local host projects.
- **HTTP Max Size**: Nginx and PHP limit local uploads strictly to a 100MB compressed limit.
- **Evidence Truncation**: Extremely long, minified single-line source code matches are proactively truncated symmetrically to 2000-character windows to prevent SQL Database `TEXT` overflow exceptions.
- **Automated Cleanup**: Temporary zips inside `%TEMP%` on the host, and `/tmp` inside the container are strictly deleted immediately post-scan for hygiene.

## Compliance
**Disclaimer:** TrustNode features a `ComplianceMapper` with experimental, partial, and basic heuristic mapping (e.g., mapping Web findings to generic OWASP controls, and Network findings to CIS controls). TrustNode does **not** currently claim certified or deterministic OWASP/NIST/SOC2 compliance coverage natively out of the box.

## What TrustNode Does NOT Yet Do
The following capabilities are **explicitly not implemented**:
- Docker/container misconfiguration scanning
- Terraform scanning
- Kubernetes scanning
- Helm scanning
- Live AWS/Azure/GCP posture scanning
- Runtime workload protection
- SIEM integration
- SOC automation

## Roadmap
- **Phase 4** — Container Security
- **Phase 5** — IaC Security
- **Phase 6** — Deterministic Compliance Mapping
- **Future** — Cloud integrations / posture scanning
- **Future** — SOC/SIEM integrations

## Project Structure
- `app/` - Laravel backend source.
  - `app/Services/Scan/` - Scan engine core orchestrators.
  - `app/Services/Scan/Scanners/` - Modular engine rule logic (`SastScanner`, `SecretScanner`, `ScaScanner`).
  - `app/Http/Controllers/` - REST API endpoints.
  - `app/Jobs/` - Asynchronous background jobs (`ScanLocalJob`, `ScanRepositoryJob`, etc).
  - `app/Models/` - Database Eloquent models.
- `cli/` - Global PHP-based CLI binary application for Windows interaction.
- `docker/` - Nginx, Node, and PHP configurations and Dockerfiles.
- `database/` - Migrations and Seeders.
- `resources/` - React frontend and Blade views.

## Troubleshooting
- **413 Request Entity Too Large**: Ensure you are scanning directories smaller than the strict 100MB upload limit. Use `--exclude` to ignore large files in the `trustnode scan` CLI.
- **Docker containers not running**: Verify Docker Desktop is active. Check `docker compose logs php` or `docker compose logs nginx` for issues.
- **Scan remains queued**: Ensure the queue worker container (`trustnode-worker-1`) is running. Check `docker compose logs worker`.
- **Scan failed**: Check the scan configuration or network accessibility of the Git URL.
- **Local directory path invalid**: Ensure you pass absolute paths or relative paths in existing directories (avoid root directory scanning like `C:\`).

## Development Status
TrustNode is currently under active development.
**Current focus:**
- Modular scanner foundation
- SAST
- Secret detection
- SCA (Software Composition Analysis)

**Next planned capability:**
- Container Security
