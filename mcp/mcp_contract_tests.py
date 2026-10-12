"""MCP contract tests, runnable with the standard library and the project's own dependencies.

These use unittest and httpx's MockTransport, so they run in the existing .venv without installing pytest
or respx. Each test records the requests the MCP sends and checks them against the API contract: the
parameter names the server reads, the endpoints it exposes, and the fields it returns.
"""

import asyncio
import json
import os
import subprocess
import sys
import unittest
from pathlib import Path

os.environ.setdefault("TRUSTNODE_API_URL", "https://trustnode.example.com")
os.environ.setdefault("TRUSTNODE_API_TOKEN", "contract-test-token")

import httpx  # noqa: E402

import trustnode_mcp as server  # noqa: E402

EXPECTED_TOOLS = {
    "trustnode_get_current_user",
    "trustnode_list_scans",
    "trustnode_get_scan",
    "trustnode_list_findings",
    "trustnode_get_finding",
    "trustnode_get_report_status",
    "trustnode_get_notifications",
}


class ContractCase(unittest.TestCase):
    def setUp(self):
        self.requests = []
        self.responder = lambda request: httpx.Response(200, json={"data": []})
        self._original_client = server.api.client

        def handler(request):
            self.requests.append(request)
            return self.responder(request)

        server.api.client = httpx.Client(
            base_url=server.api.url,
            headers={"Authorization": "Bearer contract-test-token", "Accept": "application/json"},
            transport=httpx.MockTransport(handler),
        )

    def tearDown(self):
        server.api.client.close()
        server.api.client = self._original_client

    def respond_with(self, status, body=None):
        self.responder = lambda request: httpx.Response(status, json=body if body is not None else {})

    def last_query(self):
        return dict(self.requests[-1].url.params)


class ToolDiscoveryTests(unittest.TestCase):
    def test_all_expected_tools_are_registered(self):
        tools = asyncio.run(server.mcp.list_tools())
        names = {tool.name for tool in tools}

        self.assertEqual(EXPECTED_TOOLS, names)

    def test_every_tool_is_marked_read_only_in_its_description(self):
        for tool in asyncio.run(server.mcp.list_tools()):
            self.assertIn("READ ONLY", tool.description or "", tool.name)


class ReadOnlyAndAuthorizationTests(ContractCase):
    def test_every_tool_issues_only_get_requests(self):
        self.respond_with(200, {"data": [], "status": "completed"})

        server.trustnode_get_current_user()
        server.trustnode_list_scans()
        server.trustnode_get_scan(3)
        server.trustnode_list_findings(3)
        server.trustnode_get_finding(4)
        server.trustnode_get_report_status(3)
        server.trustnode_get_notifications()

        self.assertGreaterEqual(len(self.requests), 7)
        self.assertEqual({"GET"}, {r.method for r in self.requests})

    def test_requests_carry_the_bearer_token(self):
        self.respond_with(200, {"data": []})

        server.trustnode_list_scans()

        self.assertEqual("Bearer contract-test-token", self.requests[-1].headers["Authorization"])

    def test_unauthorized_response_is_mapped_without_echoing_the_token(self):
        self.respond_with(401, {"message": "Unauthenticated."})

        result = server.trustnode_get_scan(3)

        self.assertEqual(401, result["status"])
        self.assertIn("Authentication required", result["error"])
        self.assertNotIn("contract-test-token", json.dumps(result))

    def test_forbidden_response_is_mapped(self):
        self.respond_with(403, {"message": "This action is unauthorized."})

        result = server.trustnode_list_findings(3)

        self.assertEqual(403, result["status"])
        self.assertIn("do not have permission", result["error"])

    def test_missing_scan_is_reported_as_not_found(self):
        self.respond_with(404, {"message": "Not found."})

        result = server.trustnode_get_scan(99)

        self.assertEqual("Resource not found.", result["error"])

    def test_network_failure_is_returned_as_an_error_not_an_exception(self):
        def fail(request):
            raise httpx.ConnectError("refused", request=request)

        self.responder = fail

        result = server.trustnode_get_scan(3)

        self.assertIn("error", result)
        self.assertIn("network error", result["error"])


class InputValidationTests(ContractCase):
    def test_non_positive_scan_id_is_rejected_without_a_request(self):
        result = server.trustnode_get_scan(0)

        self.assertEqual({"error": "scan_id must be a positive integer"}, result)
        self.assertEqual([], self.requests)

    def test_non_positive_finding_id_is_rejected_without_a_request(self):
        result = server.trustnode_get_finding(-1)

        self.assertEqual({"error": "finding_id must be a positive integer"}, result)
        self.assertEqual([], self.requests)

    def test_list_limits_are_bounded(self):
        self.respond_with(200, {"data": []})

        server.trustnode_list_scans(limit=10_000)
        self.assertEqual("100", self.last_query()["per_page"])

        server.trustnode_list_findings(3, limit=-5)
        self.assertEqual("1", self.last_query()["per_page"])

    def test_severity_and_status_are_normalised_and_unknown_values_dropped(self):
        self.respond_with(200, {"data": []})

        server.trustnode_list_findings(3, severity="HIGH", status="bogus")

        query = self.last_query()
        self.assertEqual("high", query["severity"])
        self.assertNotIn("status", query)


class ApiContractTests(ContractCase):
    """Parameter names and response fields the MCP relies on, matched to the server controllers."""

    def test_scan_list_uses_per_page_and_passes_filters(self):
        self.respond_with(200, {"data": []})

        server.trustnode_list_scans(status="completed", type="repository", limit=7)

        self.assertEqual("/api/scans", self.requests[-1].url.path)
        self.assertEqual({"per_page": "7", "status": "completed", "type": "repository"}, self.last_query())

    def test_findings_are_scoped_to_the_scan(self):
        self.respond_with(200, {"data": []})

        server.trustnode_list_findings(42, limit=5)

        self.assertEqual("/api/findings", self.requests[-1].url.path)
        self.assertEqual({"scan_id": "42", "per_page": "5"}, self.last_query())

    def test_report_status_reads_the_report_endpoint(self):
        self.respond_with(200, {"id": 9, "scan_id": 42, "status": "completed"})

        result = server.trustnode_get_report_status(42)

        self.assertEqual("/api/scans/42/report/status", self.requests[-1].url.path)
        self.assertEqual({"scan_id": 42, "report_status": "completed"}, result)

    def test_report_status_is_none_when_no_report_exists(self):
        self.respond_with(404, {"message": "No report found."})

        result = server.trustnode_get_report_status(42)

        self.assertEqual({"scan_id": 42, "report_status": "none"}, result)

    def test_notifications_unread_is_applied_client_side_and_limit_is_enforced(self):
        self.respond_with(200, {"data": [
            {"id": "a", "read_at": None},
            {"id": "b", "read_at": "2026-10-12T10:00:00Z"},
            {"id": "c", "read_at": None},
            {"id": "d", "read_at": None},
        ]})

        result = server.trustnode_get_notifications(unread_only=True, limit=2)

        self.assertEqual("/api/notifications", self.requests[-1].url.path)
        self.assertEqual([], list(self.last_query()))
        self.assertEqual(["a", "c"], [n["id"] for n in result["data"]])


class ConfigurationTests(unittest.TestCase):
    def test_server_refuses_to_start_without_its_configuration(self):
        here = Path(__file__).resolve().parent
        env = {k: v for k, v in os.environ.items() if not k.startswith("TRUSTNODE_API_")}

        completed = subprocess.run(
            [sys.executable, "-c", "import trustnode_mcp"],
            cwd=here,
            env=env,
            capture_output=True,
            text=True,
            timeout=60,
        )

        self.assertEqual(1, completed.returncode)
        self.assertIn("Missing TRUSTNODE_API_URL or TRUSTNODE_API_TOKEN", completed.stderr)


if __name__ == "__main__":
    unittest.main()
