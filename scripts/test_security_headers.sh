#!/usr/bin/env bash
# Test Security Headers
# Usage: ./scripts/test_security_headers.sh [URL]
# Example: ./scripts/test_security_headers.sh https://venues.involo.ch/

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default URL or accept from command line
URL="${1:-https://venues.involo.ch/}"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Security Headers Test${NC}"
echo -e "${BLUE}======================================${NC}"
echo -e "Testing: ${YELLOW}${URL}${NC}\n"

# Function to check for a header
check_header() {
    local header_name="$1"
    local expected_value="$2"
    local required="${3:-yes}"
    
    echo -n "Checking ${header_name}... "
    
    response=$(curl -s -I "${URL}" 2>/dev/null | grep -i "^${header_name}:" | cut -d' ' -f2- | tr -d '\r\n' || echo "")
    
    if [ -z "$response" ]; then
        if [ "$required" = "yes" ]; then
            echo -e "${RED}✗ MISSING${NC}"
            return 1
        else
            echo -e "${YELLOW}⚠ Not set (optional)${NC}"
            return 0
        fi
    else
        if [ -n "$expected_value" ] && [[ ! "$response" =~ $expected_value ]]; then
            echo -e "${YELLOW}⚠ Found but unexpected value${NC}"
            echo "  Got: ${response}"
            echo "  Expected: ${expected_value}"
            return 0
        else
            echo -e "${GREEN}✓ ${response}${NC}"
            return 0
        fi
    fi
}

# Test required security headers
echo -e "${BLUE}Required Security Headers:${NC}"
check_header "X-Frame-Options" "SAMEORIGIN"
check_header "X-Content-Type-Options" "nosniff"
check_header "X-XSS-Protection" "1"
check_header "Referrer-Policy" "strict-origin"
check_header "Content-Security-Policy" "default-src"
echo ""

# Test optional/conditional headers
echo -e "${BLUE}Optional/Conditional Headers:${NC}"
check_header "Strict-Transport-Security" "max-age" "no"
check_header "Permissions-Policy" "" "no"
echo ""

# Test cache headers
echo -e "${BLUE}Cache Control:${NC}"
check_header "Cache-Control" "" "no"
echo ""

# Overall assessment
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Full Response Headers:${NC}"
echo -e "${BLUE}======================================${NC}"
curl -s -I "${URL}" 2>/dev/null || echo -e "${RED}Failed to fetch headers${NC}"
echo ""

# Security grade estimate
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Security Assessment${NC}"
echo -e "${BLUE}======================================${NC}"
echo -e "For detailed analysis, visit:"
echo -e "${GREEN}https://securityheaders.com/?q=${URL}${NC}"
echo -e "${GREEN}https://observatory.mozilla.org/analyze/${URL}${NC}"
echo ""

# Check if HTTPS is being used
if [[ ! "$URL" =~ ^https:// ]]; then
    echo -e "${YELLOW}⚠ WARNING: Testing HTTP URL. HSTS will not be enabled.${NC}"
    echo -e "${YELLOW}⚠ Switch to HTTPS for full security header protection.${NC}"
fi
