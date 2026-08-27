# TrustNode

[![License](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](LICENSE)
[![CI Status](https://github.com/trustnode-org/trustnode/actions/workflows/ci.yml/badge.svg)](https://github.com/trustnode-org/trustnode/actions)
[![Security Status](https://img.shields.io/badge/Security-Vetted-success.svg)](SECURITY.md)

## PROJECT OVERVIEW

TrustNode is a self-hosted static code and security posture scanning platform. It empowers developers and security teams to automate vulnerability detection for their repositories and local projects securely and privately.

## CURRENT SECURITY CAPABILITIES

Implemented capabilities include:
- **SAST Detection**: Detects raw SQL injection, unsafe command execution, dynamic code evaluation, and potential path traversals via a modular scanning engine.
- **Secret Detection**: Detects hardcoded credentials for AWS, GitHub, GitLab, Stripe, Slack, GCP, Private Keys, and generic high-entropy API tokens.
- **Local Directory Scanning**: Scan local project directories securely by packaging them into a temporary archive (up to 100MB).
- **Remote Repository Scanning**: Pull and scan Git repositories natively.
- **Findings Management & Deduplication**: All findings are fingerprinted and tracked for status reporting.
- **Report Generation**: Export findings to detailed PDF and HTML reports.
- **Compliance Mapping**: Experimental foundational mapping to external security frameworks.

## SCANNER ARCHITECTURE

TrustNode implements a modular, extensible engine pipeline to execute static analysis efficiently without monolith anti-patterns:

```
RepositoryScanner (Orchestrator)
    |
    +-- ScannerInterface
    |
    +-- AbstractRegexScanner (Evidence Truncation & Masking)
            |
            +-- SastScanner (SAST Rules)
            |
            +-- SecretScanner (Entropy & False-Positive Validation)
```

## CLI USAGE

TrustNode provides a global CLI wrapper for streamlined execution.

**Scan a local project:**
```bash
trustnode scan .
trustnode scan C:\path\to\project
```

**Scan a remote repository:**
```bash
trustnode scan https://github.com/example/repo.git
```

**View and manage scans:**
```bash
trustnode scan list
trustnode scan status <SCAN_ID>
trustnode findings --scan=<SCAN_ID>
trustnode repositories
```

**Generate and retrieve reports:**
```bash
trustnode report <SCAN_ID>
trustnode report status <SCAN_ID>
trustnode report download <SCAN_ID>
```

## LOCAL SCAN DOCUMENTATION

The `trustnode scan .` command packages the local directory, adhering to a 100MB maximum archive size limit to ensure reliable HTTP processing. The archive is safely and temporarily extracted within the scanner container, evaluated against all detection rules, and aggressively cleaned up immediately after execution to maintain zero-footprint security.

## SECRET DETECTION

TrustNode implements an advanced `SecretScanner` with protections against common false positives:
- **Provider Rules**: AWS, GitHub, GitLab, Stripe, Slack, GCP, JWT, and Private Keys.
- **Generic Token Validation**: Reduces noise by ignoring common hashes (MD5, SHA), UUIDs, and obvious placeholders (e.g. `example`, `changeme`).
- **Shannon Entropy**: Automatically validates generic tokens against Shannon entropy thresholds (>3.5) to filter out standard strings.
- **Context Preservation**: Hardcoded secrets are masked (`***`) in reports and findings, while safely preserving surrounding line code context up to 2,000 characters to ensure the developer can quickly identify the vulnerability.

## LIMITATIONS / CURRENT SCOPE

TrustNode currently focuses strictly on Static Code and Posture analysis. The following capabilities are explicitly out of scope for the current release:
- No full Software Composition Analysis (SCA) dependency vulnerability scanning yet.
- No Docker container or Dockerfile misconfiguration scanning yet.
- No Infrastructure as Code (IaC) parsing yet.
- No runtime workload protection (CWPP) or active cloud posture monitoring (CSPM / CNAPP).

---

## Community vs. Professional Edition

TrustNode Community Edition is fully functional, self-contained, and open-source. For advanced team features, compliance reporting, and real-time licensing management, consider **TrustNode Professional**.

| Feature | Community Edition (Open Source) | Professional Edition (Proprietary Companion) |
| :--- | :---: | :---: |
| **Self-Hosted Deployment** | :white_check_mark: | :white_check_mark: |
| **Core Vulnerability Scanning** | :white_check_mark: | :white_check_mark: |
| **Basic AI Triage** | :white_check_mark: | :white_check_mark: (Advanced Models) |
| **Custom Scan Scheduling** | :white_check_mark: | :white_check_mark: |
| **Team Management & RBAC** | :x: | :white_check_mark: |
| **Enterprise Integrations (Slack, Jira)** | :x: | :white_check_mark: |
| **Compliance Reports (SOC2, OWASP)** | :x: | :white_check_mark: |
| **Dedicated Enterprise Support** | :x: | :white_check_mark: |
| **Multi-Engine Orchestration** | :x: | :white_check_mark: |

*To manage licenses and upgrade your installation, refer to the private companion `trustnode-license-platform` client module or contact our sales team.*

---

## Getting Started

### Prerequisites
- [Docker](https://docs.docker.com/get-docker/) (24.0.0 or later)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2.0.0 or later)

### Quick Start (Docker Compose)

1. **Clone the repository**:
   ```bash
   git clone https://github.com/trustnode-org/trustnode.git
   cd trustnode
   ```

2. **Initialize Environment Variables**:
   ```bash
   cp .env.example .env
   ```
   *Open `.env` to configure your database credentials, application keys, and AI scanner configurations.*

3. **Start the Platform**:
   ```bash
   docker compose up -d --build
   ```

4. **Initialize Database and Setup Admin Account**:
   ```bash
   docker compose exec app php artisan migrate --seed
   ```

5. **Access the Web Interface**:
   Open `http://localhost:8000` in your web browser.

---

## Contributing

We welcome contributions from the community! Please read our [Contributing Guide](CONTRIBUTING.md) to learn how to propose changes, report issues, and submit code.

---

## Security

If you believe you have found a security vulnerability in TrustNode, please read our [Security Policy](SECURITY.md) for instructions on how to report it responsibly.

---

## License

TrustNode Community Edition is licensed under the [Apache License 2.0](LICENSE).
