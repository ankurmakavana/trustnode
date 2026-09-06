# TrustNode

TrustNode is an open-source **Continuous Security & Remediation Platform** designed to secure modern software environments from the ground up.

---

## Why TrustNode?

Modern security models often attempt to enforce governance solely from the top down—auditing deployed infrastructure or reacting to security alerts long after vulnerabilities reach production. However, security posture decays when the individual components composing a system are neglected.

Vulnerabilities, exposed secrets, malicious dependencies, insecure container configurations, and misconfigured orchestration manifests do not originate in the cloud; they enter through everyday development workflows, local projects, and version-controlled repositories. Securing systems effectively requires continuous detection, deterministic tracking, and actionable remediation at the resource level before risks compound into organizational exposure.

---

## Security Philosophy

> **"Security starts from the smallest entity/resource."**

TrustNode is built on the principle that organizational security is the cumulative state of every individual resource within the environment.

```
+-----------------------------------------------------------------------------------+
|                           Organizational Security Posture                         |
+-----------------------------------------------------------------------------------+
                                         ▲
                                         │ Cumulative Posture & Baseline Drift
+-----------------------------------------------------------------------------------+
|      Source Code  •  Secrets  •  Dependencies  •  Containers  •  Manifests       |
|                               (Resource Level)                                    |
+-----------------------------------------------------------------------------------+
```

TrustNode focuses on securing the smallest resources that enter or exist within an organization:
- **Source code** files and syntax constructs
- **Authentication credentials**, tokens, and cryptographic keys
- **Third-party software dependencies** and lockfile specifications
- **Container definitions** (`Dockerfile`, `docker-compose.yml`)
- **Kubernetes manifests** and workload definitions
- **Infrastructure targets** and network boundaries

By identifying weaknesses early, establishing deterministic identity for findings, and monitoring lifecycle transitions across completed scans, TrustNode provides the foundation for moving from individual resource security toward comprehensive organizational security.

---

## What We Are Building

TrustNode is evolving into a unified **Continuous Security & Remediation Platform**.

### Current Implementation vs. Long-Term Direction

| Area | Current Implementation | Long-Term Direction (Roadmap) |
|---|---|---|
| **Code Security** | Fast regex-based SAST for high-impact flaws (SQLi, Command Injection, Eval, Path Traversal) | AST-based semantic analysis, taint tracking, and framework-aware rule engines |
| **Secret Detection** | High-entropy token detection, known provider patterns (AWS, GitHub, GitLab, Stripe, Slack, GCP, JWT, Private Keys), placeholder suppression, and credential masking | Custom regex engines, enterprise secret management integration, and automated revocation workflows |
| **SCA / Dependencies** | Lockfile parsing (`composer.lock`, `package-lock.json`, `yarn.lock`, `pnpm-lock.yaml`) with OSV vulnerability lookup and local caching | Multi-language ecosystem expansion (Python, Go, Rust, Java), automated PR dependency patching |
| **Container & IaC** | Static analysis of `Dockerfile`, `docker-compose.yml`, and Kubernetes YAML manifests | Container image registry scanning, runtime container monitoring, Terraform (HCL), and Helm chart parsers |
| **Network Security** | Active TCP port probing, TLS certificate expiry inspection, and HTTP security header evaluation | Continuous asset discovery, banner analysis, and distributed network sensors |
| **Finding Intelligence** | Deterministic `FindingIdentity` generation, 4-state lifecycle tracking (`NEW`, `RECURRING`, `RESOLVED`, `REGRESSION`), and point-to-point baseline regression intelligence | Automated remediation PR generation, cross-target correlation, and ML-assisted triage |
| **Posture & Trends** | Tenant-scoped & target-filtered dashboard, 10-scan historical posture trend, and direction calculation (`improving`, `worsening`, `unchanged`) | Long-range posture analytics, executive reporting, SLA compliance tracking, and security benchmarking |
| **Cloud & CNAPP** | 🚧 Planned (No live cloud provider integrations currently exist) | Cloud security posture management (CSPM), CIEM, and cloud workload protection (CWPP) |

---

## Objective

TrustNode’s objective is to provide a deterministic, transparent security platform that:
1. **Secures Source Resources**: Validates code, configuration, credentials, and dependencies at their source.
2. **Detects Weaknesses Early**: Catches security regressions prior to deployment.
3. **Creates Actionable Findings**: Normalizes findings across diverse scanner engines with remediation guidance, impact analysis, and sanitized evidence.
4. **Tracks Findings Over Time**: Maintains persistent finding identities to track whether issues are newly introduced, recurring, resolved, or regressed.
5. **Measures Security Posture**: Evaluates historical trends to determine whether an asset's security posture is improving, worsening, or remaining stable.
6. **Progressively Expands**: Builds an extensible foundation for broader organizational security without compromising resource-level fidelity.

---

## Who Is TrustNode For?

- **Developers**: Test repositories and local directories for security issues before committing code or merging pull requests.
- **AppSec & DevSecOps Engineers**: Run fast, repeatable security scans across projects, enforce security baselines, and prevent regressions in CI/CD pipelines.
- **Security Engineers & Analysts**: Triage findings with normalized severity, inspect masked evidence, and export comprehensive HTML/PDF technical audit reports.
- **Small Organizations & Teams**: Self-host a unified security scanner and posture dashboard without complex external enterprise dependencies.
- **Open-Source Contributors**: Extend modular scanner engines, parsers, and compliance mappers within a clean, tested Laravel architecture.

---

## Security Capability Inventory

TrustNode maintains an authoritative 12-category security capability inventory:

| # | Security Category | Status | Summary |
|---|---|---|---|
| 1 | **Code Security** | 🟡 PARTIAL | Static pattern-based SAST for SQLi, Command Injection, dangerous eval, and path traversal. |
| 2 | **Secret Security** | ✅ IMPLEMENTED | High-entropy secrets, cloud/API tokens (AWS, GitHub, GitLab, Stripe, Slack, GCP, JWT, Private Keys) with masking and placeholder filtering. |
| 3 | **Dependency / SCA Security** | 🟡 PARTIAL | Lockfile analysis for Packagist (`composer.lock`), npm (`package-lock.json` v1-v3), Yarn Classic (`yarn.lock`), and pnpm (`pnpm-lock.yaml`) backed by OSV.dev vulnerability intelligence. |
| 4 | **Container Security** | 🟡 PARTIAL | Static analysis of `Dockerfile` and `docker-compose.yml` configurations for privilege escalation, root execution, and exposed sockets. |
| 5 | **IaC / Cloud Posture** | 🟡 PARTIAL | Offline static analysis of raw Kubernetes manifests for security context misconfigurations and network exposures. |
| 6 | **Network Security** | 🟡 PARTIAL | Active TCP port discovery on common ports, TLS certificate expiration checks, and HTTP security header evaluation. |
| 7 | **Cloud / CNAPP** | 🚧 PLANNED | Long-term roadmap for cloud provider API posture scanning (CSPM), identity entitlements (CIEM), and workload protection (CWPP). |
| 8 | **SOC / Detection** | 🟡 PARTIAL | Finding normalization, SHA-256 fingerprinting, 4-state lifecycle tracking, point-to-point baseline regression comparison, and posture trend dashboard. |
| 9 | **Compliance** | 🧪 EXPERIMENTAL | Heuristic control mapping for OWASP Top 10, MITRE ATT&CK, ISO 27001, PCI DSS, NIST SP 800-53, and SOC 2. |
| 10 | **Reporting** | ✅ IMPLEMENTED | Generation of styled HTML and downloadable PDF audit reports detailing findings, remediation steps, and technical evidence. |
| 11 | **Scanning Targets** | ✅ IMPLEMENTED | Scan execution across local directories (CLI upload), Git repositories (clone), uploaded ZIP archives, and verified network infrastructure hosts. |
| 12 | **Platform / Operations** | ✅ IMPLEMENTED | Multi-tenant context isolation (`TenantScope`), background queue execution, strict archive extraction boundaries, and SSRF/DNS rebinding protection. |

> **Status Vocabulary:**
> - ✅ **IMPLEMENTED** — Fully functional, tested, and actively available in the codebase.
> - 🟡 **PARTIAL** — Available with specific boundaries or supported subsets.
> - 🧪 **EXPERIMENTAL** — Heuristic or foundational capability under active development.
> - 🚧 **PLANNED** — Roadmap capability designed for future implementation.

---

## What TrustNode Does Today

- **Multi-Engine Static Scanning**: Analyzes source code, credentials, lockfiles, Docker configurations, and Kubernetes YAML in a single unified scan.
- **Active Infrastructure Probing**: Scans target hostnames and IP addresses for open ports, TLS validity, and missing security headers with SSRF protection.
- **Finding Identity & Deduplication**: Generates deterministic SHA-256 fingerprints based on rule ID, target, file path, and normalized code snippets.
- **Lifecycle State Tracking**: Categorizes every finding on completed scans as `NEW`, `RECURRING`, `RESOLVED`, or `REGRESSION`.
- **Point-to-Point Baseline Comparison**: Compares any scan against a chosen baseline to calculate delta metrics, severity migrations, new/resolved findings, and posture state.
- **Posture Trend Visualization**: Displays historical finding counts, lifecycle transitions, and posture direction indicators (`improving`, `worsening`, `unchanged`) across historical scans.
- **Security Audit Reports**: Exports comprehensive HTML and PDF reports containing executive summaries, technical details, remediation guidance, and vulnerability references.

---

## Planned Security Direction

The following capabilities represent the planned roadmap:

- 🚧 **Advanced AST-Based SAST**: Abstract Syntax Tree parsers and semantic data-flow analysis to reduce false positives and detect complex injection chains.
- 🚧 **Terraform & Helm Analysis**: HCL parser integration and Helm template rendering for comprehensive Infrastructure-as-Code auditing.
- 🚧 **Expanded SCA Ecosystems**: Support for Python (`requirements.txt`, `Pipfile.lock`, `poetry.lock`), Go (`go.sum`), Rust (`Cargo.lock`), and Java (`pom.xml`, `build.gradle`).
- 🚧 **Container Registry & Image Scanning**: Static container image layer inspection and package scanning via container engine integration.
- 🚧 **Cloud Posture (CSPM / CIEM)**: Read-only API connectors for AWS, Azure, and GCP to audit cloud infrastructure configurations and identity permissions.
- 🚧 **SIEM & Webhook Integrations**: Webhook notifications and event streaming for Slack, Microsoft Teams, Jira, and SIEM ingestion.
- 🚧 **Remediation Intelligence**: Automated pull request generation for dependency upgrades and vulnerability patching.

---

## How TrustNode Works

```
[Target / Resource]
  ├── Git Repository
  ├── Local Directory / Archive
  └── Infrastructure Host / IP
            │
            ▼
[Target Validation & SSRF Enforcement]
            │
            ▼
[Scan Job Dispatch] (Laravel Queue)
            │
            ▼
[Unified Scanner Engine]
  ├── SastScanner (Pattern matching)
  ├── SecretScanner (Entropy & token detection)
  ├── ScaScanner (Lockfile parsing + OSV.dev lookup)
  ├── ContainerScanner (Dockerfile & Compose analysis)
  ├── KubernetesScanner (Manifest static inspection)
  └── NativeInfrastructureScanner (Ports, TLS, Headers)
            │
            ▼
[Normalized Finding DTO]
            │
            ▼
[Finding Identity & Fingerprinting] (SHA-256 hash)
            │
            ▼
[Finding Lifecycle Engine]
  ├── State Evaluation (NEW, RECURRING, RESOLVED, REGRESSION)
  └── Tenant-Scoped Persistence (Finding & FindingIdentity)
            │
            ▼
[Baseline & Posture Intelligence]
  ├── Baseline Comparison (Scan-to-Scan Delta)
  ├── Posture Trend Aggregation (10-Scan Historical Window)
  └── Executive / Technical Reports (HTML & PDF)
```

---

## Web Application vs. CLI

TrustNode provides two distinct operational interfaces sharing the same underlying security engine:

| Capability | TrustNode Web Application | TrustNode CLI |
|---|---|---|
| **Primary Use Case** | Interactive team dashboard & visual posture management | Terminal scanning, scripts, and CI/CD pipelines |
| **Execution Method** | Self-hosted Docker container stack | Terminal command (`php cli/bin/trustnode`) |
| **User Interface** | Web browser (`http://localhost:8000`) | Command-line standard output |
| **Scan Initiation** | Web form target selection | Command line: `php cli/bin/trustnode scan <target>` |
| **Findings Triage** | Interactive table with filters & detail views | Tabular terminal list: `php cli/bin/trustnode findings` |
| **Reports** | Interactive HTML & downloadable PDF | PDF download: `php cli/bin/trustnode report download` |

---

## Quick Start (Web Application)

Get the self-hosted TrustNode Web Application running with Docker in minutes:

### 1. Clone TrustNode
```bash
git clone https://github.com/ankurmakavana/trustnode.git
cd trustnode
```

### 2. Start TrustNode with Docker
```bash
docker compose -f compose.dev.yaml up -d
```
*Starts the self-hosted stack: Nginx web proxy (port `8000`), PHP-FPM application worker, Vite frontend compiler, MySQL database, and Redis queue/cache containers.*

### 3. Open TrustNode in Your Browser
Navigate to:
```text
http://localhost:8000
```

### 4. Use the Web Application
1. **Register a Target**: Go to **Targets** and add a Git repository URL or network host.
2. **Start a Scan**: Click **Run Scan** to trigger multi-engine static and dynamic analysis.
3. **Monitor Progress**: View real-time background scan execution and queue status.
4. **Triage Findings**: Inspect severity breakdowns, masked code evidence, and remediation guidance under **Findings**.
5. **Track Posture**: Review historical trend charts and baseline deltas on the **Dashboard**.
6. **Export Reports**: Generate and download audit-ready HTML and PDF security reports.

---

## TrustNode CLI

TrustNode provides a dedicated command-line interface located in `cli/bin/trustnode` (with Windows batch wrapper `trustnode.cmd`) for developers, automation scripts, and CI/CD environments.

### 1. Discover CLI Commands
View the complete list of available CLI commands and global options:
```bash
php cli/bin/trustnode list
```
*(On Windows: `.\trustnode.cmd help`)*

### 2. Run a Repository Scan
Start a security scan against a Git repository:
```bash
php cli/bin/trustnode scan https://github.com/org/repo.git
```
*Submits the repository to the scanning orchestrator, executes SAST, Secret Detection, Lockfile SCA, Dockerfile/Compose, and Kubernetes analyzers, and outputs the assigned scan ID.*

### 3. Check Scan Status
Monitor progress and lifecycle state for an active scan:
```bash
php cli/bin/trustnode scan status <scan-id>
```

### 4. Inspect Security Findings
List findings identified across completed scans:
```bash
php cli/bin/trustnode findings
```

### 5. Generate & Download Security Reports
```bash
# Request PDF report generation
php cli/bin/trustnode report <scan-id>

# Check report status
php cli/bin/trustnode report status <scan-id>

# Download the compiled PDF report
php cli/bin/trustnode report download <scan-id> --output="./report.pdf"
```

### 6. CLI Authentication & Diagnostics (Optional)
When interacting with authenticated remote instances:
```bash
# Authenticate CLI session with an API token
php cli/bin/trustnode login --token="<your-api-token>"

# Inspect current authenticated user
php cli/bin/trustnode whoami

# Diagnose API connectivity and token health
php cli/bin/trustnode doctor
```

---

## Scanning Workflows

TrustNode executes static and dynamic analysis across multiple target types:

### 1. Local Directory Scanning via Script
To scan a local codebase on disk without configuring Git remotes, use the included PowerShell scanning script:

```powershell
.\local_scan.ps1 -Target "C:\path\to\your\project" -InstallDir "c:\xampp\htdocs\trustnode"
```

**What the script does:**
1. Validates the local directory and guards against scanning filesystem roots.
2. Packages source files into a temporary archive (enforcing safety ceilings: max 50,000 files, max 200 MB uncompressed, excluding `.git`, `node_modules`, `vendor`).
3. Uploads the archive to `POST /api/scans/local`.
4. Polls `GET /api/scans/{id}` until the background job completes.
5. Displays formatted finding severities, technical details, and direct report download links.

### 2. Git Repository Scanning (Web & CLI)
- **Web UI**: Navigate to `/targets`, add a repository URL (with optional personal access token), and trigger a scan.
- **CLI**: Execute `php cli/bin/trustnode scan <repository-url>`.
- **Engine execution**: Scans source files across all enabled static rules (SAST, Secret Detection, Lockfile SCA, Dockerfile/Compose, and Kubernetes manifests).

### 3. Network Infrastructure Scanning (Web & API)
- **Web UI**: Register a target hostname or public IP under `/targets` and trigger an infrastructure scan.
- **Engine execution**: Validates the host against SSRF/private network blocklists, performs active TCP handshakes across 14 standard ports, checks TLS certificate expiration on ports 443/8443, and audits HTTP security headers.

---

## Security Posture Dashboard & Lifecycle Views

When running the web interface, TrustNode provides dedicated views for tracking security posture:

- **Dashboard / Security Posture Overview** (`/dashboard`): Visualizes total findings, active target counts, severity distributions (Critical, High, Medium, Low), finding lifecycle status counters (`NEW`, `RECURRING`, `RESOLVED`, `REGRESSION`), and the 10-scan historical posture trend chart with direction indicators (`improving`, `worsening`, `unchanged`).
- **Scan Targets & Creation** (`/targets`, `/scans`): Target registration and scan dispatch for Git repositories, uploaded ZIP projects, and network endpoints.
- **Findings Catalog & Triage** (`/findings`): Centralized findings inventory with status filtering, severity categorization, rule references, target mapping, and direct links to generated reports.
- **Finding Detail & Lifecycle History** (`/findings/:id`): Deep technical inspection of normalized findings, including masked source code evidence, Shannon entropy metrics, remediation instructions, and chronological lifecycle transition logs (`NEW` → `RECURRING` → `RESOLVED` → `REGRESSION`).

---

## Security Reports

TrustNode generates structured security audit reports available in two formats:
- **Interactive HTML Report**: Color-coded severity breakdown, filterable findings table, vulnerability descriptions, remediation steps, and masked code evidence.
- **Downloadable PDF Report**: Print-ready executive and technical audit documentation generated via DomPDF (`/api/scans/:id/report/download`).

---

## For AI Coding Agents

If you are an AI coding agent operating inside the TrustNode repository, follow these rules:

### 1. Verify Before Claiming
- **Documentation is not proof of implementation.** Source code, database migrations, and automated tests are the only authoritative source of truth.
- Always inspect the source files before making statements about system capabilities or adding new features.

### 2. Core Repository Map
- `cli/bin/trustnode`: TrustNode CLI executable (`php cli/bin/trustnode list` for command discovery).
- `local_scan.ps1`: Local project packaging and scan dispatch script.
- `app/Services/Scan/Scanners/`: Scanner implementations (`SastScanner.php`, `SecretScanner.php`, `ScaScanner.php`, `ContainerScanner.php`, `KubernetesScanner.php`).
- `app/Services/Scan/Infrastructure/`: Active infrastructure scanning and SSRF validation (`NativeInfrastructureScanner.php`, `TargetValidator.php`).
- `app/Services/Scan/Dependencies/`: Lockfile parsers for Composer, npm, Yarn Classic, and pnpm.
- `app/Services/Finding/`: Finding lifecycle tracking (`FindingLifecycleService.php`) and CRUD operations (`FindingService.php`).
- `app/Services/Scan/ScanBaselineComparisonService.php`: Scan-to-scan baseline comparison and regression calculation.
- `app/Services/Dashboard/DashboardService.php`: Posture trend aggregation and widget calculations.
- `app/Models/`: Eloquent models (`Finding.php`, `FindingIdentity.php`, `Scan.php`, `LocalProject.php`, `Asset.php`, `Target.php`).
- `app/Jobs/`: Scan execution jobs (`ScanLocalJob.php`, `ScanRepositoryJob.php`, `ScanInfrastructureJob.php`).
- `routes/api.php`: Authenticated and public API endpoints.
- `tests/`: Automated feature and unit tests.

### 3. Implementation Guardrails
- **Preserve Tenant Isolation**: Always respect `TenantScope` and `TenantContext`. Never bypass tenant constraints in queries or jobs.
- **Do Not Invent Capabilities**: When documenting or planning, categorize unimplemented capabilities strictly as `🚧 PLANNED`.
- **Maintain Test Coverage**: Run `php artisan test` to verify changes before completing tasks. Never modify application logic solely to make tests artificially pass.

---

## Development & Verification

For developers contributing to TrustNode or running outside Docker:

### Automated Environment Setup
```bash
composer run-script setup
```
*Installs Composer dependencies, creates `.env` from `.env.example`, generates the application key, runs migrations, and builds frontend assets.*

### Development Stack
```bash
composer run-script dev
```
*Concurrently runs `php artisan serve`, queue worker `php artisan queue:listen`, log tailing `php artisan pail`, and Vite dev server `npm run dev`.*

### Backend Test Suite
```bash
php artisan test
```
*Executes the PHPUnit test suite asserting tenant isolation, scanner execution, finding identity deduplication, baseline delta calculations, SSRF defenses, and API endpoints.*

### Frontend Production Build
```bash
npm run build
```
*Executes `vite build` to compile React components and stylesheets into `public/build`.*

---

## Contributing

We welcome contributions from security researchers, AppSec engineers, developers, and open-source practitioners!

### Contribution Process
1. **Fork & Clone**: Fork the repository on GitHub and clone your fork locally.
2. **Create a Feature Branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Inspect Existing Architecture**: Review existing scanners, services, and tests before writing code.
4. **Implement Focused Changes**: Keep pull requests focused on a single capability, scanner enhancement, or bug fix.
5. **Add Automated Tests**: Write corresponding unit or feature tests under `tests/Unit` or `tests/Feature`.
6. **Validate Quality & Run Tests**:
   ```bash
   php artisan test
   npm run build
   ```
7. **Submit a Pull Request**: Provide a clear description of the problem, the technical solution, and test verification output.

### Who Should Contribute?
- **Security Researchers**: Add detection patterns for new secret types or dangerous coding constructs.
- **DevSecOps Engineers**: Enhance container/IaC static analysis rules and CI/CD integration workflows.
- **Backend Developers**: Expand lockfile parsers, improve lifecycle intelligence, and optimize database queries.
- **Frontend Developers**: Refine dashboard visualizations, trend graphs, and report layouts.

---

## Security Policy

Security is the core foundation of TrustNode. If you discover a security vulnerability within the platform:

- **Do NOT open a public GitHub issue.**
- Please review our full [Security Policy](SECURITY.md) and report vulnerabilities directly to our security response team at **security@trustnode.io**.
- We follow coordinated vulnerability disclosure guidelines and acknowledge all validated reports within 48 hours.

---

## Documentation

The primary technical documentation files located at the repository root are:
- [README.md](README.md) — High-level platform overview, security capability inventory, and quick start guide.
- [SECURITY_CAPABILITY_BASELINE.md](SECURITY_CAPABILITY_BASELINE.md) — Detailed technical baseline, scanner specifications, security boundaries, and gap analysis.

---

## License

TrustNode is open-source software licensed under the [Apache License 2.0](LICENSE).
