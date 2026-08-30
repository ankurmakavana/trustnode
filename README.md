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
- a comprehensive network vulnerability scanner (only basic port/TLS/HTTP scanning is supported)
- a live CSPM platform
- a container runtime security platform

---

## Current Security Coverage
TrustNode currently provides:

**Code Security**
→ SAST (Regex-based SQLi, Command Injection, Eval, Path Traversal)

**Secret Security**
→ Secret Detection (High-entropy tokens, AWS, GitHub, GitLab, Stripe, Slack, GCP, JWT, Private Keys)

**Dependency Security**
→ Software Composition Analysis (SCA) via lockfile parsing and OSV vulnerability lookup

**Infrastructure Security**
→ Not yet implemented / planned

**Container Security**
→ Static Dockerfile and Docker Compose analysis

**Cloud Security**
→ Not yet implemented / planned

**Network Security**
→ Basic Active Infrastructure Scanning (Ports, TLS certificates, HTTP headers)

**SOC**
→ Finding/reporting foundation, lifecycle tracking, and scan-to-scan baseline delta, but not a full SIEM/SOC platform

**Compliance**
→ Current mapper status: Experimental / Heuristic foundational mapping (OWASP, MITRE, ISO, PCI, NIST, SOC 2)

---

## Status Vocabulary
Use exactly these statuses everywhere in this documentation:
- ✅ IMPLEMENTED
- 🟡 PARTIAL
- 🧪 EXPERIMENTAL
- 🚧 PLANNED
- ❌ NOT IMPLEMENTED

---

## Security Category Scorecard
| Category | Current Status |
|----------|----------------|
| Code Security | 🟡 PARTIAL |
| Secret Security | ✅ IMPLEMENTED |
| Dependency / SCA Security | 🟡 PARTIAL |
| Container Security | 🟡 PARTIAL |
| IaC / Cloud Posture | 🟡 PARTIAL |
| Network Security | 🟡 PARTIAL |
| Cloud / CNAPP | ❌ NOT IMPLEMENTED |
| SOC / Detection | 🟡 PARTIAL |
| Compliance | 🧪 EXPERIMENTAL |
| Reporting | ✅ IMPLEMENTED |
| Scanning Targets | ✅ IMPLEMENTED |
| Platform / Operations | ✅ IMPLEMENTED |

---

## Master Security Capability Matrix

### 1. Code Security
| Capability | Status | Verified Source |
|---|---|---|
| SAST | ✅ IMPLEMENTED | `SastScanner.php` |
| SQL Injection detection | ✅ IMPLEMENTED | `SastScanner.php` |
| Command Injection detection | ✅ IMPLEMENTED | `SastScanner.php` |
| Dangerous eval detection | ✅ IMPLEMENTED | `SastScanner.php` |
| Path Traversal detection | ✅ IMPLEMENTED | `SastScanner.php` |
| Regex-based detection | ✅ IMPLEMENTED | `AbstractRegexScanner.php` |
| Cross-language nature | ✅ IMPLEMENTED | `SastScanner.php` |
| AST-based analysis | 🚧 PLANNED | N/A |
| Framework-aware analysis | 🚧 PLANNED | N/A |
| Language-specific analysis | 🚧 PLANNED | N/A |

### 2. Secret Security
| Capability | Status | Verified Source |
|---|---|---|
| AWS credentials | ✅ IMPLEMENTED | `SecretScanner.php` |
| GitHub tokens | ✅ IMPLEMENTED | `SecretScanner.php` |
| GitLab tokens | ✅ IMPLEMENTED | `SecretScanner.php` |
| Stripe keys | ✅ IMPLEMENTED | `SecretScanner.php` |
| Slack tokens | ✅ IMPLEMENTED | `SecretScanner.php` |
| GCP keys | ✅ IMPLEMENTED | `SecretScanner.php` |
| JWT detection | ✅ IMPLEMENTED | `SecretScanner.php` |
| Private key detection | ✅ IMPLEMENTED | `SecretScanner.php` |
| Generic secrets | ✅ IMPLEMENTED | `SecretScanner.php` |
| Entropy filtering | ✅ IMPLEMENTED | `SecretScanner.php` |
| Placeholder filtering | ✅ IMPLEMENTED | `SecretScanner.php` |
| Secret masking | ✅ IMPLEMENTED | `SecretScanner.php` |
| Evidence truncation | ✅ IMPLEMENTED | `AbstractRegexScanner.php` |

### 3. Dependency / SCA Security
| Capability | Status | Verified Source |
|---|---|---|
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
| IaC Capability | Status | Verified Source |
|---|---|---|
| Kubernetes static analysis | ✅ IMPLEMENTED | `KubernetesScanner.php` |
| Terraform | ❌ NOT IMPLEMENTED | N/A |
| Helm | ❌ NOT IMPLEMENTED | N/A |
| Cloud posture APIs | ❌ NOT IMPLEMENTED | N/A |
| Kubernetes cluster/runtime scanning | ❌ NOT IMPLEMENTED | N/A |

*Note: This scanner performs offline static analysis (no external API calls or Kubernetes cluster connections) and requires no YAML dependencies. It evaluates `privileged`, `hostNetwork`, `hostPID`, `hostPath`, `allowPrivilegeEscalation`, `runAsUser`, and `LoadBalancer` exposure.*

### 6. Network Security
| Capability | Status | Verified Source |
|---|---|---|
| network configuration analysis | ❌ NOT IMPLEMENTED | N/A |
| exposed ports | ✅ IMPLEMENTED | `NativeInfrastructureScanner.php` |
| TLS/security configuration | ✅ IMPLEMENTED | `NativeInfrastructureScanner.php` |
| endpoint/network discovery | ❌ NOT IMPLEMENTED | N/A |
| network vulnerability scanning | ❌ NOT IMPLEMENTED | N/A |
| live network scanning | ✅ IMPLEMENTED | `NativeInfrastructureScanner.php` |
| HTTP security headers | ✅ IMPLEMENTED | `NativeInfrastructureScanner.php` |

*Note: The native infrastructure scanner currently performs active network connectivity to discover open ports (TCP handshake on common ports), validates TLS certificate expiration, and checks for basic missing HTTP security headers on a single target. SSRF and DNS rebinding protections are strictly enforced.*

### 7. Cloud / CNAPP
| Capability | Status | Verified Source |
|---|---|---|
| CSPM | ❌ NOT IMPLEMENTED | N/A |
| CWPP | ❌ NOT IMPLEMENTED | N/A |
| CIEM | ❌ NOT IMPLEMENTED | N/A |
| Container Security | 🟡 PARTIAL | N/A |
| Kubernetes runtime security | ❌ NOT IMPLEMENTED | N/A |
| Runtime workload protection | ❌ NOT IMPLEMENTED | N/A |
| Cloud API posture scanning | ❌ NOT IMPLEMENTED | N/A |
| Cloud asset inventory | ❌ NOT IMPLEMENTED | N/A |

### 8. SOC / Detection
| Capability | Status | Verified Source |
|---|---|---|
| security findings | ✅ IMPLEMENTED | `ScanLocalJob.php` |
| severity classification | ✅ IMPLEMENTED | `ScanLocalJob.php` |
| fingerprints | ✅ IMPLEMENTED | `FingerprintService.php` |
| deduplication | ✅ IMPLEMENTED | `FingerprintService.php` |
| finding persistence | ✅ IMPLEMENTED | `ScanLocalJob.php` |
| finding lifecycle intelligence | ✅ IMPLEMENTED | `FindingLifecycleService.php` |
| security baseline & regression intelligence | ✅ IMPLEMENTED | `ScanBaselineComparisonService.php` |
| SIEM integration | ❌ NOT IMPLEMENTED | N/A |
| SOAR | ❌ NOT IMPLEMENTED | N/A |
| incident response platform | ❌ NOT IMPLEMENTED | N/A |
| ticketing platform | ❌ NOT IMPLEMENTED | N/A |
| runtime detection platform | ❌ NOT IMPLEMENTED | N/A |
| webhook integrations | 🚧 PLANNED | N/A |

### 9. Compliance
| Capability | Status | Verified Source |
|---|---|---|
| Heuristic/control mapping | 🧪 EXPERIMENTAL | `ComplianceMapper.php` |
| OWASP | 🧪 EXPERIMENTAL | `ComplianceSeeder.php` |
| MITRE | 🧪 EXPERIMENTAL | `ComplianceSeeder.php` |
| ISO | 🧪 EXPERIMENTAL | `ComplianceSeeder.php` |
| PCI | 🧪 EXPERIMENTAL | `ComplianceSeeder.php` |
| NIST | 🧪 EXPERIMENTAL | `ComplianceSeeder.php` |
| SOC 2 | 🧪 EXPERIMENTAL | `ComplianceSeeder.php` |
| SOC 2 certification | ❌ NOT IMPLEMENTED | N/A |
| ISO certification | ❌ NOT IMPLEMENTED | N/A |
| PCI certification | ❌ NOT IMPLEMENTED | N/A |
| Regulatory certification | ❌ NOT IMPLEMENTED | N/A |

### 10. Reporting
| Capability | Status | Verified Source |
|---|---|---|
| findings | ✅ IMPLEMENTED | `local_scan.blade.php` |
| HTML reports | ✅ IMPLEMENTED | `local_scan.blade.php` |
| PDF reports | ✅ IMPLEMENTED | `ReportController.php` |
| severity summary | ✅ IMPLEMENTED | `local_scan.blade.php` |
| technical details | ✅ IMPLEMENTED | `local_scan.blade.php` |
| remediation | ✅ IMPLEMENTED | `local_scan.blade.php` |
| business impact | ✅ IMPLEMENTED | `local_scan.blade.php` |
| evidence | ✅ IMPLEMENTED | `local_scan.blade.php` |
| SCA package information | ✅ IMPLEMENTED | `ScaScanner.php` |
| lockfile path | ✅ IMPLEMENTED | `ScaScanner.php` |

### 11. Scanning Targets
| Capability | Status | Verified Source |
|---|---|---|
| local directories | ✅ IMPLEMENTED | `local_scan.ps1` |
| Git repositories | ✅ IMPLEMENTED | `ScanRepositoryJob.php` |
| uploaded archives | ✅ IMPLEMENTED | `ScanLocalJob.php` |
| supported lockfiles | ✅ IMPLEMENTED | `ScaScanner.php` |
| source files | ✅ IMPLEMENTED | `RepositoryScanner.php` |
| live infrastructure hosts (port/TLS/headers) | ✅ IMPLEMENTED | `NativeInfrastructureScanner.php` |
| arbitrary cloud repositories | ❌ NOT IMPLEMENTED | N/A |

### 12. Platform / Operations
| Capability | Status | Verified Source |
|---|---|---|
| CLI | ✅ IMPLEMENTED | `cli/` |
| API | ✅ IMPLEMENTED | `app/Http/Controllers/` |
| queue processing | ✅ IMPLEMENTED | `app/Jobs/` |
| Redis queue / cache | ✅ IMPLEMENTED | `compose.dev.yaml` |
| Docker development environment| ✅ IMPLEMENTED | `docker/` |
| temporary workspace cleanup | ✅ IMPLEMENTED | `local_scan.ps1`, `ScanLocalJob.php` |
| retry handling | ✅ IMPLEMENTED | `OsvApiClient.php` |
| error handling | ✅ IMPLEMENTED | `OsvApiClient.php`, `ScanLocalJob.php` |
| scan lifecycle (progress/status) | ✅ IMPLEMENTED | `ScanLocalJob.php` |

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
- **Evidence Truncation Limit**: 2,000 characters symmetrically (`AbstractRegexScanner.php`)
- **OSV Timeout/Retry Limits**: 10 seconds timeout, 3 retries (`OsvApiClient.php`)
- **Temporary Workspace Cleanup**: Deleted from `%TEMP%` host (`local_scan.ps1`) and `/tmp` container (`ScanLocalJob.php`) immediately post-scan.

---

## Security Roadmap

**Phase 1: Scanner foundation / modular architecture** — completed

**Phase 2: Secret detection expansion** — completed

**Phase 3: SCA** — completed for current supported ecosystems

**Phase 4: Container Security** — completed (Dockerfile and Compose static analysis)

**Phase 5: IaC Security** (🟡 PARTIAL)
- Kubernetes (✅ IMPLEMENTED)
- Terraform (🚧 PLANNED)
- Helm (🚧 PLANNED)

**Phase 6: Advanced Code Security** (🚧 PLANNED)
- AST
- framework-aware rules
- language-specific analysis

**Phase 7: Compliance** (🧪 EXPERIMENTAL)
- Heuristic rule-to-control mapping for OWASP, MITRE, ISO, PCI, NIST, and SOC 2

**Phase 8: SOC / Integrations** (🚧 PLANNED)
- SIEM
- ticketing
- webhooks

**Phase 9: Cloud Security** (🚧 PLANNED)
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
- **Container analysis:** LIVE DOCKER E2E VERIFIED for:
  - Dockerfile
  - docker-compose
- **Kubernetes analysis:** LIVE DOCKER E2E VERIFIED for:
  - parser unit tests
  - false-positive tests
  - multi-document tests
  - structural tests
- **Network infrastructure scanning:** VERIFIED for:
  - SSRF / private IP mitigation (`TargetValidator.php`)
  - TCP port scanning aggregation (`NativeInfrastructureScanner.php`)
  - TLS certificate expiration parsing
  - HTTP security header extraction

---

## Documentation Maintenance Rules

**README.md is the authoritative current security capability inventory.**

Whenever a scanner capability changes:
1. update implementation
2. update tests
3. verify E2E where applicable
4. update README capability matrix
5. update category scorecard
6. update verification status
7. update roadmap/limitations if needed
8. commit code + documentation together where practical

Never allow README to become a marketing document detached from code.
