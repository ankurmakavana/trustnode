# TrustNode MCP

TrustNode MCP provides an integration layer bridging any MCP-compatible AI agent (like Claude Desktop) with the TrustNode API.

It implements a set of READ-ONLY tools for safely querying scans, findings, and reports.

## Installation

This project is managed using `uv`. 

1. Install `uv` (https://docs.astral.sh/uv/)
2. Clone this repository and navigate to this directory:
   ```bash
   cd mcp
   ```
3. Sync dependencies:
   ```bash
   uv sync
   ```

## Configuration

Copy `.env.example` to `.env` and fill in your details:
```bash
cp .env.example .env
```

Your `TRUSTNODE_API_TOKEN` must be a Sanctum Personal Access Token with the following abilities:
- `scans:read`
- `findings:read`
- `reports:read`

Do NOT use a token with write privileges.

## Running the Server

To start the MCP server over standard input/output (stdio), which is what Claude Desktop expects:

```bash
uv run trustnode-mcp
```

## Adding to Claude Desktop

Edit your `claude_desktop_config.json` (typically found in `%APPDATA%\Claude\claude_desktop_config.json` on Windows or `~/Library/Application Support/Claude/claude_desktop_config.json` on macOS) to include:

```json
{
  "mcpServers": {
    "trustnode": {
      "command": "uv",
      "args": [
        "run",
        "trustnode-mcp"
      ],
      "env": {
        "TRUSTNODE_API_URL": "http://localhost:8000",
        "TRUSTNODE_API_TOKEN": "your-read-only-token"
      },
      "cwd": "/path/to/trustnode/mcp"
    }
  }
}
```
