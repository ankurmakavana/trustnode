import os
import sys
import httpx
from typing import Optional, Any
import logging
from fastmcp import FastMCP

# Setup basic logging to stderr so it doesn't interfere with MCP stdout
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler(sys.stderr)]
)
logger = logging.getLogger("trustnode_mcp")

# FastMCP initialization
mcp = FastMCP("TrustNode MCP")

class TrustNodeAPI:
    def __init__(self):
        self.url = os.environ.get("TRUSTNODE_API_URL")
        self.token = os.environ.get("TRUSTNODE_API_TOKEN")

        if not self.url or not self.token:
            logger.error("Missing TRUSTNODE_API_URL or TRUSTNODE_API_TOKEN environment variables")
            sys.exit(1)

        self.url = self.url.rstrip("/") + "/"
        
        self.client = httpx.Client(
            base_url=self.url,
            headers={
                "Authorization": f"Bearer {self.token}",
                "Accept": "application/json",
            },
            timeout=30.0
        )

    def _handle_error(self, response: httpx.Response) -> str:
        status = response.status_code
        if status == 401:
            return "Authentication required. Please check your token."
        elif status == 403:
            return "You do not have permission to access this resource."
        elif status == 404:
            return "Resource not found."
        elif status == 422:
            return "Invalid input."
        elif status == 429:
            return "Rate limit exceeded. Please try again later."
        elif status >= 500:
            return "TrustNode service temporarily unavailable."
        return f"Unexpected API Error: {status}"

    def request(self, method: str, endpoint: str, params: Optional[dict] = None) -> Any:
        try:
            logger.info(f"API Request: {method} {endpoint}")
            response = self.client.request(method, endpoint, params=params)
            
            if not response.is_success:
                error_msg = self._handle_error(response)
                logger.error(f"API Error ({response.status_code}): {endpoint}")
                return {"error": error_msg, "status": response.status_code}
                
            return response.json()
        except httpx.RequestError as e:
            logger.error(f"Network error during request to {endpoint}")
            return {"error": "TrustNode service temporarily unavailable due to network error."}

api = TrustNodeAPI()

@mcp.tool(description="Get current authenticated user information from TrustNode. (READ ONLY)")
def trustnode_get_current_user() -> dict:
    """Get current authenticated user information from TrustNode."""
    return api.request("GET", "api/auth/me")

@mcp.tool(description="List TrustNode scans with optional filtering. (READ ONLY)")
def trustnode_list_scans(status: Optional[str] = None, type: Optional[str] = None, limit: int = 10) -> dict:
    """List TrustNode scans.
    
    Args:
        status: Optional status to filter by (e.g., 'completed', 'running')
        type: Optional type to filter by (e.g., 'repository', 'database')
        limit: Maximum number of scans to return (max 100)
    """
    limit = min(max(1, limit), 100)
    params = {"limit": limit}
    if status:
        params["status"] = status
    if type:
        params["type"] = type
        
    return api.request("GET", "api/scans", params=params)

@mcp.tool(description="Get details of a specific TrustNode scan. (READ ONLY)")
def trustnode_get_scan(scan_id: int) -> dict:
    """Get detailed information about a specific scan.
    
    Args:
        scan_id: Positive integer ID of the scan
    """
    if scan_id <= 0:
        return {"error": "scan_id must be a positive integer"}
    return api.request("GET", f"api/scans/{scan_id}")

@mcp.tool(description="List findings for a specific scan. (READ ONLY)")
def trustnode_list_findings(scan_id: int, severity: Optional[str] = None, status: Optional[str] = None, limit: int = 50) -> dict:
    """List vulnerabilities/findings for a scan.
    
    Args:
        scan_id: ID of the scan
        severity: Filter by severity (critical, high, medium, low, info)
        status: Filter by status (open, resolved, accepted)
        limit: Max number of findings (max 200)
    """
    if scan_id <= 0:
        return {"error": "scan_id must be a positive integer"}
        
    limit = min(max(1, limit), 200)
    params = {"scan_id": scan_id, "limit": limit}
    
    if severity and severity.lower() in ['critical', 'high', 'medium', 'low', 'info']:
        params["severity"] = severity.lower()
        
    if status and status.lower() in ['open', 'resolved', 'accepted']:
        params["status"] = status.lower()
        
    return api.request("GET", "api/findings", params=params)

@mcp.tool(description="Get details of a specific finding. (READ ONLY)")
def trustnode_get_finding(finding_id: int) -> dict:
    """Get detailed information about a specific finding, including remediation.
    
    Args:
        finding_id: Positive integer ID of the finding
    """
    if finding_id <= 0:
        return {"error": "finding_id must be a positive integer"}
    return api.request("GET", f"api/findings/{finding_id}")

@mcp.tool(description="Get the report generation status for a scan. (READ ONLY)")
def trustnode_get_report_status(scan_id: int) -> dict:
    """Check the status of a report generation for a scan.
    
    Args:
        scan_id: Positive integer ID of the scan
    """
    if scan_id <= 0:
        return {"error": "scan_id must be a positive integer"}
    
    result = api.request("GET", f"api/scans/{scan_id}")
    if "error" in result:
        return result
        
    scan_data = result.get("data", {})
    return {
        "scan_id": scan_id,
        "report_status": scan_data.get("report_status", "unknown")
    }

@mcp.tool(description="Get user notifications. (READ ONLY)")
def trustnode_get_notifications(unread_only: bool = False, limit: int = 20) -> dict:
    """Get the current user's notifications.
    
    Args:
        unread_only: If true, returns only unread notifications
        limit: Max number of notifications (max 50)
    """
    limit = min(max(1, limit), 50)
    params = {"limit": limit}
    
    if unread_only:
        params["unread"] = "true"
        
    return api.request("GET", "api/notifications", params=params)

def main():
    mcp.run()

if __name__ == "__main__":
    main()
