# /// script
# requires-python = ">=3.10"
# dependencies = [
#     "httpx>=0.27.0",
#     "pytest>=8.0.0",
#     "respx>=0.21.0"
# ]
# ///

import os
import pytest
import respx
import httpx
from unittest.mock import patch
import io
import logging

# Set env vars before importing mcp server
os.environ["TRUSTNODE_API_URL"] = "https://trustnode.example.com"
os.environ["TRUSTNODE_API_TOKEN"] = "test-token"

from trustnode_mcp import (
    trustnode_get_scan, 
    trustnode_list_scans,
    trustnode_list_findings,
    trustnode_get_finding,
    trustnode_get_report_status,
    trustnode_get_notifications,
    trustnode_get_current_user,
    logger
)

@pytest.fixture(autouse=True)
def mock_env():
    with patch.dict(os.environ, {"TRUSTNODE_API_URL": "https://trustnode.example.com", "TRUSTNODE_API_TOKEN": "test-token"}):
        yield

@respx.mock
def test_valid_scan_lookup():
    respx.get("https://trustnode.example.com/api/scans/1").mock(return_value=httpx.Response(200, json={"data": {"id": 1, "name": "Test"}}))
    
    result = trustnode_get_scan(scan_id=1)
    assert result["data"]["id"] == 1
    assert "error" not in result

def test_invalid_scan_id():
    # Schema validation test (negative/invalid ID)
    result = trustnode_get_scan(scan_id=-5)
    assert "error" in result
    assert "positive integer" in result["error"]

    result = trustnode_get_scan(scan_id=0)
    assert "error" in result

@respx.mock
def test_unauthorized_request():
    respx.get("https://trustnode.example.com/api/scans/2").mock(return_value=httpx.Response(401, json={"message": "Unauthenticated."}))
    
    result = trustnode_get_scan(scan_id=2)
    assert result["error"] == "Authentication required. Please check your token."
    assert result["status"] == 401

@respx.mock
def test_forbidden_request():
    # Testing cross-tenant or missing ability rejection (403)
    respx.get("https://trustnode.example.com/api/scans/3").mock(return_value=httpx.Response(403, json={"message": "Forbidden."}))
    
    result = trustnode_get_scan(scan_id=3)
    assert result["error"] == "You do not have permission to access this resource."
    assert result["status"] == 403

@respx.mock
def test_rate_limiting():
    respx.get("https://trustnode.example.com/api/scans").mock(return_value=httpx.Response(429, json={"message": "Too Many Requests"}))
    
    result = trustnode_list_scans()
    assert result["error"] == "Rate limit exceeded. Please try again later."
    assert result["status"] == 429

@respx.mock
def test_stack_trace_redaction():
    respx.get("https://trustnode.example.com/api/scans").mock(return_value=httpx.Response(500, text="SQLSTATE[42000]: Syntax error or access violation"))
    
    result = trustnode_list_scans()
    assert result["error"] == "TrustNode service temporarily unavailable."
    assert "SQLSTATE" not in result["error"]

def test_token_not_in_logs(caplog):
    caplog.set_level(logging.INFO)
    
    with respx.mock:
        respx.get("https://trustnode.example.com/api/auth/me").mock(return_value=httpx.Response(200, json={"authenticated": True}))
        trustnode_get_current_user()
    
    log_text = caplog.text
    assert "test-token" not in log_text
    assert "API Request: GET api/auth/me" in log_text

def test_limit_bounding():
    with respx.mock:
        route = respx.get("https://trustnode.example.com/api/scans").mock(return_value=httpx.Response(200, json={"data": []}))
        trustnode_list_scans(limit=5000)
        
        request = route.calls.last.request
        assert "limit=100" in str(request.url) # Bounded to 100

def test_findings_schema_validation():
    with respx.mock:
        route = respx.get("https://trustnode.example.com/api/findings").mock(return_value=httpx.Response(200, json={"data": []}))
        
        # valid
        trustnode_list_findings(scan_id=1, severity="CRITICAL", status="OPEN", limit=10)
        req_url = str(route.calls.last.request.url)
        assert "severity=critical" in req_url
        assert "status=open" in req_url
        
        # invalid severity (ignored or stripped by tool)
        trustnode_list_findings(scan_id=1, severity="EXPLOSIVE")
        req_url = str(route.calls.last.request.url)
        assert "severity=explosive" not in req_url

if __name__ == "__main__":
    pytest.main([__file__])
