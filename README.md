# TrustNode Security Capability Inventory

TrustNode is a self-hosted static security scanning platform focused on Source Code Security, Secret Detection, Software Composition Analysis (SCA), Local and Repository Scanning, Security Findings, and Security Reporting.

## What TrustNode Is Today
TrustNode is currently a self-hosted static security scanning platform focused on:
1. Source Code Security (SAST)
2. Secret Detection
3. Software Composition Analysis (SCA)
4. Local and Repository Scanning
5. Security Findings (deduplication, fingerprinting)
6. Security Reporting (HTML/PDF)

It is NOT yet:
- a runtime protection platform
- a full CNAPP
- a SIEM
- a network vulnerability scanner
- a live CSPM platform
- a container runtime security platform

---

## Current Security Coverage
TrustNode currently provides:

**Code Security**
â†’ SAST (Regex-based SQLi, Command Injection, Eval, Path Traversal)

**Secret Security**
â†’ Secret Detection (High-entropy tokens, AWS, GitHub, GitLab, Stripe, Slack, GCP, JWT, Private Keys)

**Dependency Security**
â†’ Software Composition Analysis (SCA) via lockfile parsing and OSV vulnerability lookup

**Infrastructure Security**
â†’ Not yet implemented / planned

**Container Security**
â†’ Static Dockerfile and Docker Compose analysis

**Cloud Security**
â†’ Not yet implemented / planned

**Network Security**
â†’ Not yet implemented / planned

**SOC**
â†’ Finding/reporting foundation, but not a full SIEM/SOC platform

**Compliance**
â†’ Current mapper status: Experimental / Heuristic foundational mapping

---

## Status Vocabulary
Use exactly these statuses everywhere in this documentation:
- âœ… IMPLEMENTED
- ðŸŸ¡ PARTIAL
- ðŸ§ª EXPERIMENTAL
- ðŸš§ PLANNED
- âŒ NOT IMPLEMENTED

---

## Security Category Scorecard
| Category | Current Status |
|----------|----------------|
| Code Security | ðŸŸ¡ PARTIAL |
| Secret Security | âœ… IMPLEMENTED |
| Dependency / SCA Security | âœ… IMPLEMENTED |
| Container Security | âŒ IMPLEMENTED |
| IaC / Cloud Posture | âŒ NOT IMPLEMENTED |
| Network Security | âŒ NOT IMPLEMENTED |
| Cloud / CNAPP | âŒ NOT IMPLEMENTED |
| SOC / Detection | ðŸŸ¡ PARTIAL |
| Compliance | ðŸ§ª EXPERIMENTAL |
| Reporting | âœ… IMPLEMENTED |
| Scanning Targets | âœ… IMPLEMENTED |
| Platform / Operations | âœ… IMPLEMENTED |

---

## Master Security Capability Matrix

### 1. Code Security
| Capability | Status | Verified Source |
|---|---|---|
| SAST | âœ… IMPLEMENTED | `SastScanner.php` |
| SQL Injection detection | âœ… IMPLEMENTED | `SastScanner.php` |
| Command Injection detection | âœ… IMPLEMENTED | `SastScanner.php` |
| Dangerous eval detection | âœ… IMPLEMENTED | `SastScanner.php` |
| Path Traversal detection | âœ… IMPLEMENTED | `SastScanner.php` |
| Regex-based detection | âœ… IMPLEMENTED | `AbstractRegexScanner.php` |
| Cross-language nature | âœ… IMPLEMENTED | `SastScanner.php` |
| AST-based analysis | ðŸš§ PLANNED | N/A |
| Framework-aware analysis | ðŸš§ PLANNED | N/A |
| Language-specific analysis | ðŸš§ PLANNED | N/A |

### 2. Secret Security
| Capability | Status | Verified Source |
|---|---|---|
| AWS credentials | âœ… IMPLEMENTED | `SecretScanner.php` |
| GitHub tokens | âœ… IMPLEMENTED | `SecretScanner.php` |
| GitLab tokens | âœ… IMPLEMENTED | `SecretScanner.php` |
| Stripe keys | âœ… IMPLEMENTED | `SecretScanner.php` |
| Slack tokens | âœ… IMPLEMENTED | `SecretScanner.php` |
| GCP keys | âœ… IMPLEMENTED | `SecretScanner.php` |
| JWT detection | âœ… IMPLEMENTED | `SecretScanner.php` |
| Private key detection | âœ… IMPLEMENTED | `SecretScanner.php` |
| Generic secrets | âœ… IMPLEMENTED | `SecretScanner.php` |
| Entropy filtering | âœ… IMPLEMENTED | `SecretScanner.php` |
| Placeholder filtering | âœ… IMPLEMENTED | `SecretScanner.php` |
| Secret masking | âœ… IMPLEMENTED | `SecretScanner.php` |
| Evidence truncation | âœ… IMPLEMENTED | `AbstractRegexScanner.php` |

### 3. Dependency / SCA Security
| Capability | Status | Verified Source |
|---|---|---|
| composer.lock | âœ… IMPLEMENTED | `ComposerLockParser.php` |
| package-lock.json | âœ… IMPLEMENTED | `NpmLockParser.php` |
| lockfileVersion 1 | âœ… IMPLEMENTED | `NpmLockParser.php` |
| lockfileVersion 2 | âœ… IMPLEMENTED | `NpmLockParser.php` |
| lockfileVersion 3 | âœ… IMPLEMENTED | `NpmLockParser.php` |
| Yarn Classic v1 | âœ… IMPLEMENTED | `YarnLockParser.php` |
| Yarn Berry v2+ | âŒ NOT IMPLEMENTED | N/A |
| pnpm-lock.yaml (Verified v5/v6/v9 formats) | âœ… IMPLEMENTED | `PnpmLockParser.php` |
| Python | âŒ NOT IMPLEMENTED | N/A |
| Go | âŒ NOT IMPLEMENTED | N/A |
| Rust | âŒ NOT IMPLEMENTED | N/A |
| composer.lock | ✅ IMPLEMENTED | `ComposerLockParser.php` |
| package-lock.json | ✅ IMPLEMENTED | `NpmLockParser.php` |
| lockfileVersion 1 | ✅ IMPLEMENTED | `NpmLockParser.php` |
| lockfileVersion 2 | ✅ IMPLEMENTED | `NpmLockParser.php` |
| lockfileVersion 3 | ✅ IMPLEMENTED | `NpmLockParser.php` |
| Yarn Classic v1 | ✅ IMPLEMENTED | `YarnLockParser.php` |
| Yarn Berry v2+ | ❌ NOT IMPLEMENTED | N/A |
| pnpm-lock.yaml (Verified v5/v6/v9 formats) | ✅ IMPLEMENTED | `PnpmLockParser.php` |
| Python | ❌ NOT IMPLEMENTED | N/A |
| Go | ❌ NOT IMPLEMENTED | N/A |
| Rust | ❌ NOT IMPLEMENTED | N/A |
| Maven | ❌ NOT IMPLEMENTED | N/A |
| Gradle | ❌ NOT IMPLEMENTED | N/A |

### 4. Container Security
| Capability | Status | Verified Source |
|---|---|---|
| Dockerfile scanning | ✅ IMPLEMENTED | `ContainerScanner.php` |
| docker-compose scanning | ✅ IMPLEMENTED | `ContainerScanner.php` |
| image vulnerability scanning | ❌ NOT IMPLEMENTED | N/A |
| image configuration scanning | ❌ NOT IMPLEMENTED | N/A |
| container runtime scanning | ❌ NOT IMPLEMENTED | N/A |
| registry scanning | ❌ NOT IMPLEMENTED | N/A |

### 5. IaC / Cloud Posture
| Capability | Status | Verified Source |
|---|---|---|
| Terraform | âŒ NOT IMPLEMENTED | N/A |
| Kubernetes | âŒ NOT IMPLEMENTED | N/A |
| Helm | âŒ NOT IMPLEMENTED | N/A |
| CloudFormation | âŒ NOT IMPLEMENTED | N/A |
| AWS posture | âŒ NOT IMPLEMENTED | N/A |
| GCP posture | âŒ NOT IMPLEMENTED | N/A |
| Azure posture | âŒ NOT IMPLEMENTED | N/A |
| CSPM | âŒ NOT IMPLEMENTED | N/A |

### 6. Network Security
| Capability | Status | Verified Source |
|---|---|---|
| network configuration analysis | âŒ NOT IMPLEMENTED | N/A |
| exposed ports | âŒ NOT IMPLEMENTED | N/A |
| TLS/security configuration | âŒ NOT IMPLEMENTED | N/A |
| endpoint/network discovery | âŒ NOT IMPLEMENTED | N/A |
| network vulnerability scanning | âŒ NOT IMPLEMENTED | N/A |
| live network scanning | âŒ NOT IMPLEMENTED | N/A |

### 7. Cloud / CNAPP
| Capability | Status | Verified Source |
|---|---|---|
| CSPM | âŒ NOT IMPLEMENTED | N/A |
| CWPP | âŒ NOT IMPLEMENTED | N/A |
| CIEM | âŒ NOT IMPLEMENTED | N/A |
| Container Security | âŒ IMPLEMENTED | N/A |
| Kubernetes runtime security | âŒ NOT IMPLEMENTED | N/A |
| Runtime workload protection | âŒ NOT IMPLEMENTED | N/A |
| Cloud API posture scanning | âŒ NOT IMPLEMENTED | N/A |
| Cloud asset inventory | âŒ NOT IMPLEMENTED | N/A |

### 8. SOC / Detection
| Capability | Status | Verified Source |
|---|---|---|
| security findings | âœ… IMPLEMENTED | `ScanLocalJob.php` |
| severity classification | âœ… IMPLEMENTED | `ScanLocalJob.php` |
| fingerprints | âœ… IMPLEMENTED | `FingerprintService.php` |
| deduplication | âœ… IMPLEMENTED | `FingerprintService.php` |
| finding persistence | âœ… IMPLEMENTED | `ScanLocalJob.php` |
| SIEM integration | âŒ NOT IMPLEMENTED | N/A |
| SOAR | âŒ NOT IMPLEMENTED | N/A |
| incident response platform | âŒ NOT IMPLEMENTED | N/A |
| ticketing platform | âŒ NOT IMPLEMENTED | N/A |
| runtime detection platform | âŒ NOT IMPLEMENTED | N/A |
| webhook integrations | ðŸš§ PLANNED | N/A |

### 9. Compliance
| Capability | Status | Verified Source |
|---|---|---|
| Heuristic/control mapping | ðŸ§ª EXPERIMENTAL | `ComplianceMapper.php` |
| OWASP | ðŸ§ª EXPERIMENTAL | `ComplianceMapper.php` |
| CIS | ðŸ§ª EXPERIMENTAL | `ComplianceMapper.php` |
| SOC 2 certification | âŒ NOT IMPLEMENTED | N/A |
| ISO certification | âŒ NOT IMPLEMENTED | N/A |
| PCI certification | âŒ NOT IMPLEMENTED | N/A |
| Regulatory certification | âŒ NOT IMPLEMENTED | N/A |

### 10. Reporting
| Capability | Status | Verified Source |
|---|---|---|
| findings | âœ… IMPLEMENTED | `local_scan.blade.php` |
| HTML reports | âœ… IMPLEMENTED | `local_scan.blade.php` |
| PDF reports | âœ… IMPLEMENTED | `ReportController.php` |
| severity summary | âœ… IMPLEMENTED | `local_scan.blade.php` |
| technical details | âœ… IMPLEMENTED | `local_scan.blade.php` |
| remediation | âœ… IMPLEMENTED | `local_scan.blade.php` |
| business impact | âœ… IMPLEMENTED | `local_scan.blade.php` |
| evidence | âœ… IMPLEMENTED | `local_scan.blade.php` |
| SCA package information | âœ… IMPLEMENTED | `ScaScanner.php` |
| lockfile path | âœ… IMPLEMENTED | `ScaScanner.php` |

### 11. Scanning Targets
| Capability | Status | Verified Source |
|---|---|---|
| local directories | âœ… IMPLEMENTED | `local_scan.ps1` |
| Git repositories | âœ… IMPLEMENTED | `ScanRepositoryJob.php` |
| uploaded archives | âœ… IMPLEMENTED | `ScanLocalJob.php` |
| supported lockfiles | âœ… IMPLEMENTED | `ScaScanner.php` |
| source files | âœ… IMPLEMENTED | `RepositoryScanner.php` |
| arbitrary cloud repositories | âŒ NOT IMPLEMENTED | N/A |
| live infrastructure scanning | âŒ NOT IMPLEMENTED | N/A |

### 12. Platform / Operations
| Capability | Status | Verified Source |
|---|---|---|
| CLI | âœ… IMPLEMENTED | `cli/` |
| API | âœ… IMPLEMENTED | `app/Http/Controllers/` |
| queue processing | âœ… IMPLEMENTED | `app/Jobs/` |
| Redis queue / cache | âœ… IMPLEMENTED | `compose.dev.yaml` |
| Docker development environment| âœ… IMPLEMENTED | `docker/` |
| temporary workspace cleanup | âœ… IMPLEMENTED | `local_scan.ps1`, `ScanLocalJob.php` |
| retry handling | âœ… IMPLEMENTED | `OsvApiClient.php` |
| error handling | âœ… IMPLEMENTED | `OsvApiClient.php`, `ScanLocalJob.php` |
| scan lifecycle (progress/status) | âœ… IMPLEMENTED | `ScanLocalJob.php` |

---

## Scanner Architecture
The scanning engine uses a modular, orchestrator-driven architecture:

```text
Local Directory / Git Repository
            |
            v
      Scan Trigger
            |
            v
     Queue / Scan Job
            |
            v
    RepositoryScanner
            |
     +------+-------+----------------+
     |              |                |
     v              v                v
SastScanner   SecretScanner     ScaScanner
                                  |
                  +---------------+---------------+
                  |       |       |       |
                  v       v       v       v
              Composer   NPM    Yarn    pnpm
                Parser   Parser  Parser  Parser
                                  |
                                  v
                            OsvApiClient
                                  |
                                  v
                         NormalizedFinding
                                  |
                                  v
                     Fingerprint / Persistence
                                  |
                                  v
                              Reports
```

---

## External Network Dependencies
TrustNode makes the following external network requests during operation:

| Service | Purpose | Required? | Data Sent | Failure Behavior |
|---|---|---|---|---|
| OSV API (`api.osv.dev`) | Dependency vulnerability intelligence | No | ecosystem, package name, installed version | Warning logged, SCA findings skipped, broader scan continues seamlessly |

**External Network Dependency != Network Security.**
TrustNode using OSV does NOT mean TrustNode implements Network Security. Outbound dependency lookups do not constitute network posture scanning.

**What TrustNode DOES NOT Send:**
- source code
- secrets
- environment variables
- repository contents
- local file contents

**OSV Integration Behavior:**
- **Batch Size:** Up to 500 dependencies per request.
- **Timeout & Retries:** 10-second timeout with up to 3 retries (1000ms backoff).
- **Caching:** OSV API results are cached (Vulnerability results: 7 days. Clean results: 24 hours).
- **Cache Backend:** Redis (Verified via `compose.dev.yaml`).

---

## Resource and Safety Limits
TrustNode enforces exact limits traceable to the source code to guarantee stability:
- **Archive Upload Limit**: 100 MB max compressed (`local_scan.ps1`)
- **File Count Limit**: 50,000 files (`local_scan.ps1`)
- **Uncompressed Source Limit**: 200 MB (`local_scan.ps1`)
- **Per-file Source Read Limit**: 5 MB (`RepositoryScanner.php`)
- **Evidence Truncation Limit**: 2000 characters symmetrically (`AbstractRegexScanner.php`)
- **OSV Timeout/Retry Limits**: 10 seconds timeout, 3 retries (`OsvApiClient.php`)
- **Temporary Workspace Cleanup**: Deleted from `%TEMP%` host (`local_scan.ps1`) and `/tmp` container (`ScanLocalJob.php`) immediately post-scan.

---

## Security Roadmap

**Phase 1: Scanner foundation / modular architecture** â€” completed

**Phase 2: Secret detection expansion** â€” completed

**Phase 3: SCA** â€” completed for current supported ecosystems

**Phase 4: Container Security** â€” completed (Dockerfile and Compose static analysis)

**Phase 5: IaC Security** (ðŸš§ PLANNED)
- Terraform
- Kubernetes
- Helm

**Phase 6: Advanced Code Security** (ðŸš§ PLANNED)
- AST
- framework-aware rules
- language-specific analysis

**Phase 7: Compliance** (ðŸš§ PLANNED)
- deterministic rule-to-control mapping

**Phase 8: SOC / Integrations** (ðŸš§ PLANNED)
- SIEM
- ticketing
- webhooks

**Phase 9: Cloud Security** (ðŸš§ PLANNED)
- CSPM
- cloud APIs
- runtime capabilities

---

## Verification Status
The current implementation has been explicitly verified via unit testing and live Docker E2E regression pipelines:

- **Local directory scanning:** LIVE DOCKER E2E VERIFIED (via CLI zip upload)
- **Repository scanning:** LIVE DOCKER E2E VERIFIED (via Git clone)
- **Report generation:** LIVE DOCKER E2E VERIFIED (HTML and PDF rendering)
- **OSV success:** LIVE DOCKER E2E VERIFIED
- **OSV failure resilience:** LIVE DOCKER E2E VERIFIED (OSV downtime simulated gracefully)
- **OSV cache behavior:** LIVE DOCKER E2E VERIFIED (Repeated OSV lookups explicitly bypass HTTP)
- **Fingerprint/deduplication:** LIVE DOCKER E2E VERIFIED
- **SAST regression:** LIVE DOCKER E2E VERIFIED
- **Secret scanner regression:** LIVE DOCKER E2E VERIFIED
- **SCA parsing & lookup:** LIVE DOCKER E2E VERIFIED for:
  - composer.lock (Composer SCA)
  - package-lock.json v1/v2/v3
  - Yarn Classic v1
  - pnpm verified formats (v5, v6, v9)

---

## Documentation Maintenance Rules
Every new security capability MUST update `README.md` in the same change.

At minimum update:
- category scorecard
- capability matrix
- architecture
- supported targets
- external network dependencies
- verification status
- limitations
- roadmap
- CLI/API documentation when applicable

This README is the authoritative capability inventory and must never lag behind production capabilities. Do not document planned features as implemented.
