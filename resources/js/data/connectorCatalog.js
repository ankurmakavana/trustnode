export const CONNECTOR_CATALOG = [
    // ── Security Scanners (Active) ──────────────────────────────────────
    { code: 'nmap',       name: 'Nmap',                category: 'scanners',      type: 'scanner',      color:'#4f46e5', comingSoon: false, description: 'Network exploration and security port scanner.', features: ['Port Scanning', 'Host Discovery', 'XML Export', 'OS Detection'] },
    { code: 'greenbone',  name: 'Greenbone / OpenVAS', category: 'scanners',      type: 'scanner',      color:'#16a34a', comingSoon: false, description: 'Open source vulnerability scanner and management.', features: ['Vulnerability Scanning', 'CVE Mapping', 'API Import', 'Scheduled Scans'] },
    { code: 'nessus',     name: 'Nessus',              category: 'scanners',      type: 'scanner',      color:'#0891b2', comingSoon: false, description: 'Enterprise vulnerability scanner from Tenable.', features: ['.nessus Import', 'API Integration', 'Compliance Checks', 'Credentialed Scans'] },
    { code: 'burp',       name: 'Burp Suite',          category: 'scanners',      type: 'scanner',      color:'#ea580c', comingSoon: false, description: 'Web application security testing and scanning.', features: ['REST API', 'XML Import', 'Active Scanning', 'Passive Scanning'] },
    { code: 'acunetix',   name: 'Acunetix',            category: 'scanners',      type: 'scanner',      color:'#dc2626', comingSoon: false, description: 'Web vulnerability scanner with DAST/IAST capabilities.', features: ['API Integration', 'AcuSensor', 'Scheduled Scans', 'Compliance'] },
    { code: 'qualys',     name: 'Qualys',              category: 'scanners',      type: 'scanner',      color:'#7c3aed', comingSoon: false, description: 'Cloud-based vulnerability management platform.', features: ['Cloud Scanner', 'API Import', 'Asset Discovery', 'Policy Compliance'] },
    { code: 'rapid7',     name: 'Rapid7',              category: 'scanners',      type: 'scanner',      color:'#d97706', comingSoon: false, description: 'InsightVM vulnerability risk management.', features: ['API Integration', 'Risk Scoring', 'Remediation Tracking', 'Asset Groups'] },
    // ── Source Code (Coming Soon) ───────────────────────────────────────
    { code: 'github',       name: 'GitHub',          category: 'source_code',   type: 'vcs',           color:'#24292e', comingSoon: true, description: 'Import SAST findings and secret scanning results.', features: ['Secret Scanning', 'Code Scanning', 'Dependabot', 'SARIF Import'] },
    { code: 'gitlab',       name: 'GitLab',          category: 'source_code',   type: 'vcs',           color:'#e24329', comingSoon: true, description: 'Integrate GitLab SAST, DAST and dependency scans.', features: ['SAST', 'DAST', 'Dependency Scanning', 'Container Scanning'] },
    { code: 'azure_devops', name: 'Azure DevOps',    category: 'source_code',   type: 'vcs',           color:'#0078d4', comingSoon: true, description: 'Connect Azure Repos and Pipelines security scans.', features: ['Work Items', 'Pipeline Scans', 'Test Plans', 'Security Extensions'] },
    { code: 'bitbucket',    name: 'Bitbucket',       category: 'source_code',   type: 'vcs',           color:'#0052cc', comingSoon: true, description: 'Bitbucket Pipelines security integration.', features: ['Pipeline Results', 'Security Reports', 'PR Checks', 'Webhooks'] },
    // ── CI/CD (Coming Soon) ─────────────────────────────────────────────
    { code: 'jenkins',         name: 'Jenkins',          category: 'cicd',      type: 'cicd',          color:'#d33833', comingSoon: true, description: 'Import build security scan results from Jenkins.', features: ['Plugin Support', 'Build Reports', 'Webhook Triggers', 'Pipeline Scans'] },
    { code: 'github_actions',  name: 'GitHub Actions',   category: 'cicd',      type: 'cicd',          color:'#2088ff', comingSoon: true, description: 'Parse security workflow results from GitHub Actions.', features: ['SARIF Upload', 'Step Security', 'Action Logs', 'Artifact Import'] },
    { code: 'gitlab_ci',       name: 'GitLab CI/CD',     category: 'cicd',      type: 'cicd',          color:'#e24329', comingSoon: true, description: 'Import GitLab CI pipeline security stage results.', features: ['Security Reports', 'Pipeline Artifacts', 'Rules', 'Scan Results'] },
    { code: 'azure_pipelines', name: 'Azure Pipelines',  category: 'cicd',      type: 'cicd',          color:'#0078d4', comingSoon: true, description: 'Azure Pipelines security extension integration.', features: ['SARIF Results', 'Extension Reports', 'Deployment Logs', 'Test Results'] },
    // ── Cloud (Coming Soon) ─────────────────────────────────────────────
    { code: 'aws',        name: 'AWS',               category: 'cloud',         type: 'cloud',         color:'#ff9900', comingSoon: true, description: 'AWS Security Hub, GuardDuty and Inspector findings.', features: ['Security Hub', 'GuardDuty', 'Inspector', 'CloudTrail'] },
    { code: 'azure',      name: 'Microsoft Azure',   category: 'cloud',         type: 'cloud',         color:'#0078d4', comingSoon: true, description: 'Azure Security Center and Defender for Cloud.', features: ['Security Center', 'Defender', 'Advisor', 'Policy'] },
    { code: 'gcp',        name: 'Google Cloud',      category: 'cloud',         type: 'cloud',         color:'#4285f4', comingSoon: true, description: 'Google Security Command Center findings integration.', features: ['Security Command Center', 'Asset Inventory', 'IAM Analyzer', 'Threat Detection'] },
    { code: 'kubernetes', name: 'Kubernetes',         category: 'cloud',         type: 'cloud',         color:'#326ce5', comingSoon: true, description: 'Kubernetes cluster security posture assessment.', features: ['CIS Benchmarks', 'RBAC Audit', 'Network Policies', 'Image Scanning'] },
    // ── Containers (Coming Soon) ────────────────────────────────────────
    { code: 'docker',     name: 'Docker',             category: 'containers',    type: 'container',     color:'#2496ed', comingSoon: true, description: 'Docker image vulnerability and configuration scanning.', features: ['Image Scanning', 'Layer Analysis', 'Registry Integration', 'Dockerfile Lint'] },
    { code: 'trivy',      name: 'Trivy',              category: 'containers',    type: 'container',     color:'#1904da', comingSoon: true, description: 'Comprehensive container vulnerability scanner by Aqua.', features: ['OS Packages', 'Language Libraries', 'IaC Scanning', 'SBOM'] },
    { code: 'harbor',     name: 'Harbor',             category: 'containers',    type: 'container',     color:'#60b932', comingSoon: true, description: 'Harbor registry with integrated vulnerability scanning.', features: ['Registry API', 'CVE Reports', 'Trivy Integration', 'Notary'] },
    // ── Ticketing (Coming Soon) ─────────────────────────────────────────
    { code: 'jira',        name: 'Jira',             category: 'ticketing',     type: 'ticketing',     color:'#0052cc', comingSoon: true, description: 'Sync findings directly to Jira issue tracker.', features: ['Auto Ticket Creation', 'Status Sync', 'Custom Fields', 'Sprint Mapping'] },
    { code: 'servicenow',  name: 'ServiceNow',        category: 'ticketing',     type: 'ticketing',     color:'#81b5a1', comingSoon: true, description: 'ServiceNow vulnerability response workflow integration.', features: ['Incident Creation', 'CMDB Sync', 'Vulnerability Response', 'SLA Tracking'] },
    { code: 'linear',      name: 'Linear',            category: 'ticketing',     type: 'ticketing',     color:'#5e6ad2', comingSoon: true, description: 'Create Linear issues from security findings.', features: ['Issue Creation', 'Project Mapping', 'Status Sync', 'Priority Mapping'] },
    { code: 'clickup',     name: 'ClickUp',           category: 'ticketing',     type: 'ticketing',     color:'#7b68ee', comingSoon: true, description: 'Push findings to ClickUp tasks automatically.', features: ['Task Creation', 'Space Mapping', 'Custom Fields', 'Status Sync'] },
    // ── Notifications (Coming Soon) ─────────────────────────────────────
    { code: 'slack',      name: 'Slack',              category: 'notifications', type: 'notification',  color:'#4a154b', comingSoon: true, description: 'Real-time security alerts and notifications to Slack.', features: ['Channel Alerts', 'Custom Webhooks', 'Finding Digests', 'Interactive Messages'] },
    { code: 'teams',      name: 'Microsoft Teams',    category: 'notifications', type: 'notification',  color:'#6264a7', comingSoon: true, description: 'Send security alerts to Microsoft Teams channels.', features: ['Adaptive Cards', 'Channel Webhooks', 'Finding Summaries', 'Approval Flows'] },
    { code: 'discord',    name: 'Discord',            category: 'notifications', type: 'notification',  color:'#5865f2', comingSoon: true, description: 'Security alert notifications to Discord servers.', features: ['Webhook Alerts', 'Channel Routing', 'Embeds', 'Role Mentions'] },
    { code: 'email',      name: 'Email / SMTP',       category: 'notifications', type: 'notification',  color:'#ea4335', comingSoon: true, description: 'Automated email alerts for security events.', features: ['SMTP Configuration', 'HTML Templates', 'Digest Reports', 'Alert Routing'] },
];

export const CONNECTOR_CATEGORIES = [
    { id: 'scanners',       label: 'Security Scanners',  },
    { id: 'source_code',    label: 'Source Code',        },
    { id: 'cicd',           label: 'CI/CD Pipelines',    },
    { id: 'cloud',          label: 'Cloud Platforms',    },
    { id: 'containers',     label: 'Containers',         },
    { id: 'ticketing',      label: 'Ticketing',          },
    { id: 'notifications',  label: 'Notifications',      },
];

export function getConnector(code) {
    return CONNECTOR_CATALOG.find(c => c.code === code) ?? null;
}
