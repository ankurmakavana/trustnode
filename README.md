# TrustNode

TrustNode is an open-source **Continuous Security & Remediation Platform** designed to secure modern software environments from the ground up.

---

## Why TrustNode?

Modern security models often attempt to enforce governance solely from the top down—auditing deployed infrastructure or reacting to security alerts long after vulnerabilities reach production. However, security posture decays when the individual components composing a system are neglected.

Vulnerabilities, exposed secrets, vulnerable dependencies, insecure container configurations, and misconfigured orchestration manifests do not originate in the cloud; they enter through everyday development workflows, local projects, and version-controlled repositories. Securing systems effectively requires continuous detection, deterministic tracking, and actionable remediation at the resource level before risks compound into organizational exposure.

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

TrustNode starts security at the resource level and builds toward continuous organizational security posture:

```
Individual Resource
       │
       ▼
    Finding
       │
       ▼
Finding Identity (SHA-256 Fingerprint)
       │
       ▼
Lifecycle State (NEW, RECURRING, RESOLVED, REGRESSION)
       │
       ▼
Baseline Comparison (Delta & Drift Tracking)
       │
       ▼
Security Posture & Trend Direction
```

---

## What TrustNode Secures

TrustNode discovers and continuously secures the fundamental resources that compose modern software systems:

- **Source Code**: Static analysis across application code to detect injection vulnerabilities, dangerous code execution, and unsafe path operations.
- **Secrets and Credentials**: High-entropy tokens, private keys, certificates, and API credentials across cloud providers and services.
- **Dependencies**: Third-party package lockfiles evaluated against known vulnerability registries (OSV).
- **Container Definitions**: `Dockerfile` and `docker-compose.yml` configurations audited for privilege escalation, root execution, and unsafe exposures.
- **Kubernetes Manifests**: Workload definitions and orchestration YAML checked for security context misconfigurations and network exposures.
- **Local Projects**: Direct workspace directory auditing with safety bounds and automated packaging.
- **Git Repositories**: Remote source repository cloning and multi-analyzer execution.
- **Supported Infrastructure Targets**: Validated domain endpoints and host boundaries checked for port exposures, TLS expiration, and security headers.

---

## What TrustNode Does

TrustNode executes an automated, deterministic security pipeline across every evaluated resource:

1. **Discover Resource**: Ingests target repositories, local project directories, or network endpoints.
2. **Scan**: Dispatches multi-engine analyzers across SAST, secret detection, lockfile SCA, container definitions, and infrastructure probes.
3. **Detect Weakness**: Identifies vulnerabilities, exposed tokens, misconfigurations, and outdated packages.
4. **Normalize Finding**: Formats findings into a unified structure with standardized severity ratings, remediation guidance, impact analysis, and sanitized evidence.
5. **Assign Deterministic Identity**: Computes unique SHA-256 fingerprints based on rule ID, target identifier, resource path, and normalized code snippets.
6. **Track Lifecycle**: Classifies each finding against prior scans into one of four lifecycle states: `NEW`, `RECURRING`, `RESOLVED`, or `REGRESSION`.
7. **Compare Against Baseline**: Computes scan-to-scan delta metrics, tracking new introductions, resolved issues, and severity migrations.
8. **Measure Posture**: Aggregates historical completed scans to determine posture trajectory (`improving`, `worsening`, `unchanged`).
9. **Generate Report**: Produces structured HTML and downloadable PDF audit reports detailing technical findings, evidence, and remediation steps.

```
[Target Resource]
  ├── Git Repository
  ├── Local Directory
  └── Infrastructure Host / IP
            │
            ▼
[Target Validation & Security Enforcement]
            │
            ▼
[Unified Scanner Engine]
  ├── SAST Analyzer (Pattern matching & unsafe constructs)
  ├── Secret Analyzer (Entropy calculation & credential patterns)
  ├── Dependency SCA (Lockfile parsing & OSV.dev lookup)
  ├── Container Analyzer (Dockerfile & Compose inspection)
  ├── Kubernetes Analyzer (Manifest static inspection)
  └── Infrastructure Probe (Port discovery, TLS validity, Security headers)
            │
            ▼
[Finding Normalization]
            │
            ▼
[Deterministic Finding Identity] (SHA-256 fingerprint)
            │
            ▼
[Finding Lifecycle Engine] (NEW, RECURRING, RESOLVED, REGRESSION)
            │
            ▼
[Baseline Comparison & Posture Intelligence]
            │
            ▼
[Security Audit Reports] (HTML & PDF)
```

---

## Current Security Capabilities

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

## Security Findings & Lifecycle

Every finding identified by TrustNode is assigned a deterministic **Finding Identity** (`FindingIdentity`). Rather than treating each scan run in isolation, TrustNode tracks the continuous lifecycle of every security issue across time:

- **Deterministic Fingerprinting**: Generates SHA-256 fingerprints derived from the analyzer rule ID, target resource, file path, and normalized code context. This enables accurate issue tracking across refactors and file movements.
- **Occurrence History**: Preserves individual scan occurrences (`Finding`) linked back to persistent canonical identities, maintaining complete audit trails.

### Lifecycle States

On every completed scan, TrustNode evaluates findings against prior scans and assigns one of four lifecycle states:

- `NEW`: A vulnerability or issue discovered for the first time on this target resource.
- `RECURRING`: An active finding that was present in preceding scans and remains unmitigated.
- `RESOLVED`: A finding that was previously active but was not detected in the latest scan, indicating remediation.
- `REGRESSION`: A previously resolved finding that has reappeared in a subsequent scan, highlighting security drift.

---

## Security Posture & Regression Intelligence

TrustNode transforms raw scan outputs into continuous posture metrics:

- **Point-to-Point Baseline Comparison**: Compare any target scan against a chosen baseline to measure exact delta metrics, identifying newly introduced vulnerabilities, fixed issues, and severity shifts.
- **Posture Trajectory**: Analyzes historical completed scans across a 10-scan sliding window to calculate posture direction:
  - `improving`: Active findings decrease over consecutive scans.
  - `worsening`: New or regressed findings increase overall risk exposure.
  - `unchanged`: Finding counts and severity distributions remain consistent.
- **Continuous Remediation Auditing**: Enables teams to verify whether security remediations successfully eliminated risks without introducing regressions.

---

## Security Reports

TrustNode produces publication-ready security audit reports containing executive summaries and in-depth technical evidence:

- **Executive Summary**: High-level risk score, scan metadata, target information, and severity distribution breakdown (Critical, High, Medium, Low, Info).
- **Technical Finding Details**: Comprehensive vulnerability descriptions, assigned CWE/CVE references, compliance framework mappings (OWASP, MITRE ATT&CK), and specific remediation steps.
- **Sanitized Evidence**: Source file locations, line numbers, and masked code snippets ensuring credentials and sensitive tokens are never exposed in plaintext.
- **Downloadable Formats**: Interactive HTML viewing and downloadable PDF reports generated via backend rendering (`/api/scans/:id/report/download`).

---

## Quick Start

Get started with the TrustNode CLI:

### 1. Clone & Start Background Engine
```bash
git clone https://github.com/ankurmakavana/trustnode.git
cd trustnode

# Start the local scanning engine in the background
docker compose -f compose.dev.yaml up -d
```

### 2. Discover CLI Commands
```bash
php cli/bin/trustnode list
```
*(On Windows with the batch wrapper: `.\trustnode.cmd help`)*

### 3. Run a Security Scan
```bash
php cli/bin/trustnode scan https://github.com/org/repo.git
```
*Dispatches multi-engine static analysis across SAST, Secrets, Dependencies (SCA), Docker configurations, and Kubernetes manifests, returning the assigned scan ID.*

### 4. Check Scan Status
```bash
php cli/bin/trustnode scan status <scan-id>
```

### 5. Inspect Security Findings
```bash
php cli/bin/trustnode findings
```

### 6. Generate & Download Security Report
```bash
# Request report generation
php cli/bin/trustnode report <scan-id>

# Download the compiled PDF report
php cli/bin/trustnode report download <scan-id> --output="./report.pdf"
```

---

## Using TrustNode

TrustNode provides command-line and script-based interfaces for executing security scans across projects:

### 1. CLI

The primary command-line tool is located at `cli/bin/trustnode` (and `trustnode.cmd` on Windows) for terminal scanning and CI/CD pipelines:

```bash
# Command discovery
php cli/bin/trustnode list

# Start a repository scan
php cli/bin/trustnode scan https://github.com/org/repo.git

# Monitor scan progress
php cli/bin/trustnode scan status <scan-id>

# View finding summaries across scans
php cli/bin/trustnode findings

# Generate and download audit report
php cli/bin/trustnode report <scan-id>
php cli/bin/trustnode report status <scan-id>
php cli/bin/trustnode report download <scan-id> --output="./report.pdf"

# Optional: Authenticate CLI session for remote instances
php cli/bin/trustnode login --token="<your-api-token>"
php cli/bin/trustnode whoami
php cli/bin/trustnode doctor
```

### 2. Local Project Scanning

To scan a local project directory directly from PowerShell without Git remotes:
```powershell
.\local_scan.ps1 -Target "C:\path\to\your\project" -InstallDir "C:\path\to\trustnode"
```
*Validates the path, archives source code respecting safety limits (50k files, 200 MB max), uploads to the scan API, monitors progress, and outputs findings directly to the console.*

### 3. Optional Interactive Interface

TrustNode also provides an optional browser interface for visual posture analytics, baseline comparisons, and target management:
- **Dashboard & Trends**: Visualizes severity distribution and 10-scan historical posture trajectory.
- **Finding Triage**: Filter findings by severity and lifecycle states (`NEW`, `RECURRING`, `RESOLVED`, `REGRESSION`).
- **Reports**: View and download executive HTML and PDF audit summaries.

---

## Scanning Workflows

TrustNode executes static and dynamic analysis organized by target resource:

### Repository Scanning
- **How it works**: Connects to remote Git repositories (public or private via personal access token).
- **Execution**: Clones the repository into an isolated temporary workspace, runs SAST, Secret Detection, Lockfile SCA, Dockerfile/Compose, and Kubernetes analyzers, and removes the workspace upon completion.
- **Initiation**: `php cli/bin/trustnode scan <repository-url>`.

### Local Project Scanning
- **How it works**: Scans a project directory residing on local storage.
- **Execution**: Automatically packages the codebase into an archive (enforcing safety bounds: max 50,000 files, max 200 MB uncompressed, excluding `.git`, `node_modules`, `vendor`), uploads it to `POST /api/scans/local`, and evaluates all static analyzers.
- **Initiation**: Via `.\local_scan.ps1`.

### Infrastructure Scanning
- **How it works**: Audits external network boundaries of domains or public IP addresses.
- **Execution**: Enforces SSRF and DNS rebinding protections, performs active TCP port handshakes across 14 common ports, validates TLS certificate expiration, and checks HTTP security headers.
- **Initiation**: Via target registration and infrastructure scan API dispatch.

---

## Who Is TrustNode For?

- **Developers**: Test repositories and local directories for security issues before committing code or merging pull requests.
- **AppSec & DevSecOps Engineers**: Run fast, repeatable security scans across projects, enforce security baselines, and prevent regressions in CI/CD pipelines.
- **Security Engineers & Analysts**: Triage findings with normalized severity, inspect masked evidence, and export comprehensive HTML/PDF technical audit reports.
- **Small Organizations & Teams**: Self-host a unified security scanner and posture dashboard without complex external enterprise dependencies.
- **Open-Source Contributors**: Extend modular scanner engines, parsers, and compliance mappers.

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

## For Contributors

For contributors setting up the development environment locally:

TrustNode's backend services and CLI are built in PHP/Laravel with a React dashboard interface.

### Development Commands
```bash
# Automated environment setup (dependencies, environment, migrations, asset build)
composer run-script setup

# Start local development servers (API, background queue worker, asset compiler)
composer run-script dev

# Run automated backend test suite
php artisan test

# Build frontend production assets
npm run build
```

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
- `app/Models/`: Models (`Finding.php`, `FindingIdentity.php`, `Scan.php`, `LocalProject.php`, `Asset.php`, `Target.php`).
- `app/Jobs/`: Scan execution jobs (`ScanLocalJob.php`, `ScanRepositoryJob.php`, `ScanInfrastructureJob.php`).
- `routes/api.php`: Authenticated and public API endpoints.
- `tests/`: Automated feature and unit tests.

### 3. Implementation Guardrails
- **Preserve Tenant Isolation**: Always respect `TenantScope` and `TenantContext`. Never bypass tenant constraints in queries or jobs.
- **Do Not Invent Capabilities**: When documenting or planning, categorize unimplemented capabilities strictly as `🚧 PLANNED`.
- **Maintain Test Coverage**: Run `php artisan test` to verify changes before completing tasks. Never modify application logic solely to make tests artificially pass.

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
