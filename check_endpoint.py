import urllib.request
import urllib.error

url = 'https://trustnode.in/api/v1/releases/core/latest'
req = urllib.request.Request(url)

try:
    response = urllib.request.urlopen(req)
    print(f"Status: {response.status}")
    print(response.read().decode('utf-8'))
except urllib.error.HTTPError as e:
    print(f"Status: {e.code}")
    print(f"Headers: {e.headers}")
    print(f"Body: {e.read().decode('utf-8', errors='ignore')}")
except Exception as e:
    print(f"Error: {e}")
