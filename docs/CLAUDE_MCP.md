# TrustNode Claude MCP / Skill Foundation

This document details the architecture, capabilities, and security boundaries of the TrustNode Model Context Protocol (MCP) Server.

## Architecture

The integration implements a secure, read-only bridge between Claude (or any MCP-compatible AI client) and the TrustNode API:

`Claude -> TrustNode MCP Server (Python) -> TrustNode API (Laravel) -> TrustNode Core (Database, Queues, Services)`

The MCP server acts as a thin wrapper around the TrustNode API. **It contains no business logic or authorization logic itself.** All decisions regarding tenant isolation, permissions, and business rules are delegated to the existing robust TrustNode backend.

## Authentication

The MCP Server authenticates to the TrustNode API using Laravel Sanctum Personal Access Tokens (PATs).
- **Required Token Abilities**: The token should be scoped strictly to read-only capabilities: `scans:read`, `findings:read`, `reports:read`.
- **Credential Storage**: The token and server URL are provided to the MCP server securely via environment variables (`TRUSTNODE_API_URL` and `TRUSTNODE_API_TOKEN`). The token is **never** logged or hardcoded.

## Security Restrictions & Read-Only Guarantee

The current iteration of the TrustNode MCP Server is **strictly READ ONLY**.

Claude (and the MCP server) **CANNOT**:
- Start or stop scans.
- Modify vulnerabilities/findings (e.g., mark as resolved, accept risk).
- Create or modify repositories, assets, or infrastructure components.
- Delete any resource.
- Download arbitrary files or raw generated PDF reports to disk.
- Execute commands on the host or database.

If a modifying API endpoint is inadvertently called, the TrustNode backend will enforce standard HTTP 403 Forbidden responses because the MCP token lacks write abilities.

## Tenant Isolation

Claude is treated exactly like any other TrustNode API client.
- When Claude requests scan data (e.g., `trustnode_get_scan(scan_id=100)`), the request passes through the Sanctum guard.
- If the token belongs to User B, and Scan 100 belongs to User A's organization, the TrustNode backend `TenantScope` and Policies will block the request and return a 403/404.
- The MCP server strictly obeys backend authorization and does not attempt its own internal tenant filtering.

## Input Validation and Output Sanitization

- **Strict Schemas**: MCP tools enforce strict schemas using Pydantic. Inputs are sanitized and bounded (e.g., maximum limit of 100 for scans, bounds on integers, allowed enums for status/severity).
- **Error Redaction**: Backend errors are caught and sanitized. Raw stack traces, SQL errors, or internal diagnostic messages are scrubbed. The server maps backend status codes (401, 403, 404, 422, 429, 50x) to safe, user-friendly strings.
- **Log Masking**: API requests are logged for auditability, but authorization headers and payload secrets are never logged.

## Available Tools

The MCP Server currently implements the following machine-readable tools:

- `trustnode_get_current_user`: Returns profile information of the authenticated token owner.
- `trustnode_list_scans`: Retrieves a paginated list of scans. Supports filtering by `status` and `type`, and capping by `limit`.
- `trustnode_get_scan`: Retrieves detailed metadata for a specific `scan_id`.
- `trustnode_list_findings`: Retrieves vulnerabilities/findings for a specific `scan_id`. Supports filtering by `severity` and `status`.
- `trustnode_get_finding`: Retrieves deep details for a specific `finding_id`, including structured remediation advice.
- `trustnode_get_report_status`: Retrieves the current report generation status for a scan.
- `trustnode_get_notifications`: Retrieves notifications bound to the current user (e.g., scan completion alerts).

## Local Development and Configuration

To run the MCP server:

1. Install `uv` (the ultrafast Python package installer and resolver).
2. Set your environment variables:
   ```bash
   export TRUSTNODE_API_URL="http://localhost:8000"  # Or your remote URL
   export TRUSTNODE_API_TOKEN="your-sanctum-token"
   ```
3. Run the MCP server using `uv`:
   ```bash
   cd mcp
   uv run trustnode_mcp.py
   ```

To run tests:
```bash
cd mcp
uv run test_trustnode_mcp.py
```
