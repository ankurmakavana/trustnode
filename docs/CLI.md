# TrustNode CLI

The TrustNode CLI is a lightweight command-line interface for the TrustNode platform. It acts as a thin API client connecting to the TrustNode backend to initiate scans, download reports, and check statuses without requiring the user to navigate the web interface.

## Installation / Development

The CLI is written in PHP and designed as an isolated package.

1. Navigate to the `cli/` directory.
2. Run `composer install` to install dependencies.
3. Make the script executable: `chmod +x bin/trustnode`.
4. Run the CLI: `php bin/trustnode`.

## Configuration & Login

Before using the CLI, you must authenticate. Generate an API token from the TrustNode web application.

```bash
php bin/trustnode login
```

You will be prompted for:
1. **TrustNode URL**: The URL of your TrustNode server (e.g., `https://trustnode.example.com`).
2. **API token**: Your generated API token. This is a hidden prompt and the token will not be displayed.

Credentials are saved locally in `~/.trustnode/config` with restricted 0600 permissions. On Windows, they are stored in the user profile directory.

### Environment Variables
You can bypass local configuration or provide configuration dynamically via environment variables:
- `TRUSTNODE_API_URL`
- `TRUSTNODE_API_TOKEN`

## Logout

To clear your local configuration and securely log out:
```bash
php bin/trustnode logout
```

## Scan Commands

### Start a Scan
```bash
php bin/trustnode scan:start <type> --target <target>
```
Valid `<type>` values are:
- `repository`
- `infrastructure`
- `database`

### Database Scanning & Credentials
When starting a `database` scan, the CLI requires database credentials. The CLI is designed to NEVER leak these credentials to logs, history, or output.

You can provide the database password via:
1. **Interactive Prompt**: The CLI will securely prompt for the password (hidden input).
2. **Environment Variable**: `TRUSTNODE_DB_PASSWORD`
3. **STDIN**: Pipe the password to the command.

Example:
```bash
php bin/trustnode scan:start database --target mysql://db-server --db-user admin --db-name prod
```

## JSON Output

All list and status commands support the `--json` flag to provide machine-readable output. This is useful for CI/CD integrations.
```bash
php bin/trustnode scan:list --json
```

## Report Download

To download a generated report as a PDF:
```bash
php bin/trustnode report:download <scan-id> --output report.pdf
```
If the report is not ready (still generating), the CLI will cleanly exit with an error rather than downloading incomplete data. Use `--force` to overwrite existing files.

## Security Model

The TrustNode CLI operates strictly as a THIN API client:
- **No Token Logging**: The CLI never prints the active token or Authorization headers.
- **No Database Credential Logging**: Database passwords are obfuscated and immediately removed from memory after transmission.
- **Exceptions**: API exceptions and stack traces do not reveal sensitive information.
- **TLS Verification**: Enabled by default for all remote connections.
- **Independent Execution**: The CLI does not invoke local scanners or run arbitrary code. All scanning execution occurs securely within the backend worker queue.
