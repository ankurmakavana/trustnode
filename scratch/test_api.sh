#!/usr/bin/env bash
set -e

echo "=== TrustNode Repositories API Test ==="

COOKIE_JAR="/tmp/cookies.txt"

# 1. Login with cookie-based session
echo "[1] Logging in..."
LOGIN_RESP=$(curl -s -X POST http://nginx/api/login \
  -c "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@trustnode.local","password":"password"}')

echo "Login response (truncated): $(echo $LOGIN_RESP | head -c 100)"
echo ""

# 2. Test repositories index
echo "[2] GET /api/repositories..."
REPOS=$(curl -s http://nginx/api/repositories \
  -b "$COOKIE_JAR" \
  -H "Accept: application/json")

echo "Response: $(echo $REPOS | head -c 200)"
echo ""

# 3. Test validate-access with public repo
echo "[3] POST /api/repositories/validate-access..."
VALIDATE=$(curl -s -X POST http://nginx/api/repositories/validate-access \
  -b "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"repository_url":"https://github.com/octocat/Spoon-Knife"}')

echo "Validate response: $VALIDATE"
echo ""

echo "=== TEST COMPLETE ==="
