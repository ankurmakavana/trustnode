import asyncio
from mcp import ClientSession, StdioServerParameters
from mcp.client.stdio import stdio_client
import sys

async def main():
    import os
    env = os.environ.copy()
    env["TRUSTNODE_API_URL"] = "http://localhost:8000"
    env["TRUSTNODE_API_TOKEN"] = "dummy-test-token"
    
    server_params = StdioServerParameters(
        command="uv",
        args=["run", "trustnode-mcp"],
        env=env
    )

    print("Starting client...")
    try:
        async with stdio_client(server_params) as (read, write):
            async with ClientSession(read, write) as session:
                await session.initialize()
                
                print("\n=== Initialized ===")
                
                # List tools
                response = await session.list_tools()
                print("\n=== Discovered Tools ===")
                for tool in response.tools:
                    print(f"- {tool.name}: {tool.description}")
                    
                # Call a tool
                print("\n=== Invoking trustnode_list_scans ===")
                try:
                    result = await session.call_tool("trustnode_list_scans", {"limit": 5})
                    print("Result:")
                    print(result)
                except Exception as e:
                    print(f"Tool execution error: {e}")
                
                print("\n=== Success ===")
    except Exception as e:
        print(f"Connection failed: {e}")
        sys.exit(1)

if __name__ == "__main__":
    asyncio.run(main())
