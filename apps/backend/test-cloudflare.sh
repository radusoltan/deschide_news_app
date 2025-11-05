#!/bin/bash

# Cloudflare CDN Testing Script
# Tests CDN functionality, SSL, caching, and performance

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="${1:-api.deschide.md}"
TEST_ENDPOINT="/api/articles?itemsPerPage=5"
FULL_URL="https://${DOMAIN}${TEST_ENDPOINT}"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Cloudflare CDN - Testing Suite                      ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo ""
echo "Testing domain: ${DOMAIN}"
echo "Test URL: ${FULL_URL}"
echo ""

# ============================================================================
# Test 1: DNS Resolution (Cloudflare IPs)
# ============================================================================

echo -e "${YELLOW}[Test 1]${NC} Checking DNS resolution..."

DNS_OUTPUT=$(dig +short ${DOMAIN} @8.8.8.8 | head -2)
if echo "$DNS_OUTPUT" | grep -qE "^104\.|^172\.64\.|^162\.159\.|^2606:4700"; then
    echo -e "${GREEN}✓${NC} Domain resolves to Cloudflare IP(s)"
    echo "$DNS_OUTPUT" | while read ip; do echo "  → $ip"; done
else
    echo -e "${RED}✗${NC} Domain does NOT resolve to Cloudflare IPs"
    echo "  Current IPs:"
    echo "$DNS_OUTPUT" | while read ip; do echo "  → $ip"; done
    echo ""
    echo -e "${YELLOW}⚠${NC} Make sure you've:"
    echo "  1. Added domain to Cloudflare"
    echo "  2. Updated nameservers"
    echo "  3. Set DNS records to Proxied (orange cloud)"
fi

echo ""

# ============================================================================
# Test 2: SSL/TLS Certificate
# ============================================================================

echo -e "${YELLOW}[Test 2]${NC} Checking SSL/TLS certificate..."

SSL_INFO=$(echo | openssl s_client -connect ${DOMAIN}:443 -servername ${DOMAIN} 2>/dev/null | openssl x509 -noout -issuer -subject 2>/dev/null)

if echo "$SSL_INFO" | grep -qi "cloudflare"; then
    echo -e "${GREEN}✓${NC} SSL certificate issued by Cloudflare"
    echo "$SSL_INFO" | grep -i "issuer"
else
    echo -e "${YELLOW}⚠${NC} SSL certificate NOT from Cloudflare"
    echo "$SSL_INFO"
fi

# Check TLS version
TLS_VERSION=$(echo | openssl s_client -connect ${DOMAIN}:443 2>/dev/null | grep "Protocol" | awk '{print $3}')
if [[ "$TLS_VERSION" == "TLSv1.3" ]] || [[ "$TLS_VERSION" == "TLSv1.2" ]]; then
    echo -e "${GREEN}✓${NC} TLS version: $TLS_VERSION"
else
    echo -e "${YELLOW}⚠${NC} TLS version: $TLS_VERSION (consider upgrading)"
fi

echo ""

# ============================================================================
# Test 3: Cloudflare Headers
# ============================================================================

echo -e "${YELLOW}[Test 3]${NC} Checking Cloudflare headers..."

HEADERS=$(curl -sI "$FULL_URL" 2>&1)

# cf-ray (Cloudflare request ID)
if echo "$HEADERS" | grep -qi "cf-ray"; then
    CF_RAY=$(echo "$HEADERS" | grep -i "cf-ray" | awk '{print $2}' | tr -d '\r')
    echo -e "${GREEN}✓${NC} cf-ray: $CF_RAY (traffic going through Cloudflare)"
else
    echo -e "${RED}✗${NC} cf-ray header missing (NOT using Cloudflare)"
fi

# cf-cache-status
if echo "$HEADERS" | grep -qi "cf-cache-status"; then
    CF_CACHE=$(echo "$HEADERS" | grep -i "cf-cache-status" | awk '{print $2}' | tr -d '\r')
    if [[ "$CF_CACHE" == "HIT" ]]; then
        echo -e "${GREEN}✓${NC} cf-cache-status: HIT (cached at edge!)"
    elif [[ "$CF_CACHE" == "MISS" ]]; then
        echo -e "${YELLOW}⚠${NC} cf-cache-status: MISS (first request)"
    elif [[ "$CF_CACHE" == "DYNAMIC" ]]; then
        echo -e "${YELLOW}⚠${NC} cf-cache-status: DYNAMIC (not cacheable - check Page Rules)"
    elif [[ "$CF_CACHE" == "BYPASS" ]]; then
        echo -e "${YELLOW}⚠${NC} cf-cache-status: BYPASS (cache disabled for this URL)"
    else
        echo -e "${YELLOW}⚠${NC} cf-cache-status: $CF_CACHE"
    fi
else
    echo -e "${YELLOW}⚠${NC} cf-cache-status header missing"
fi

# Server header
if echo "$HEADERS" | grep -qi "server.*cloudflare"; then
    echo -e "${GREEN}✓${NC} Server: cloudflare"
else
    SERVER=$(echo "$HEADERS" | grep -i "^server:" | awk '{print $2}' | tr -d '\r')
    echo -e "${YELLOW}⚠${NC} Server: $SERVER (not cloudflare)"
fi

echo ""

# ============================================================================
# Test 4: HTTP/2 and HTTP/3 Support
# ============================================================================

echo -e "${YELLOW}[Test 4]${NC} Checking HTTP protocol versions..."

# HTTP/2
HTTP2_CHECK=$(curl -sI --http2 "$FULL_URL" 2>&1 | grep -i "HTTP/2")
if [ -n "$HTTP2_CHECK" ]; then
    echo -e "${GREEN}✓${NC} HTTP/2 supported"
else
    echo -e "${YELLOW}⚠${NC} HTTP/2 not detected"
fi

# HTTP/3 (QUIC)
HTTP3_CHECK=$(echo "$HEADERS" | grep -i "alt-svc.*h3")
if [ -n "$HTTP3_CHECK" ]; then
    echo -e "${GREEN}✓${NC} HTTP/3 (QUIC) advertised"
else
    echo -e "${YELLOW}⚠${NC} HTTP/3 not advertised (enable in Cloudflare dashboard)"
fi

echo ""

# ============================================================================
# Test 5: Cache Behavior (MISS → HIT)
# ============================================================================

echo -e "${YELLOW}[Test 5]${NC} Testing cache behavior..."

# First request (likely MISS or DYNAMIC)
echo "  Making first request..."
FIRST_START=$(date +%s%N)
FIRST_HEADERS=$(curl -sI "$FULL_URL" 2>&1)
FIRST_END=$(date +%s%N)
FIRST_TIME=$(echo "scale=2; ($FIRST_END - $FIRST_START) / 1000000" | bc)

FIRST_CACHE=$(echo "$FIRST_HEADERS" | grep -i "cf-cache-status" | awk '{print $2}' | tr -d '\r')
echo -e "  First request: ${FIRST_CACHE} (${FIRST_TIME}ms)"

# Wait a moment
sleep 2

# Second request (should be HIT if cacheable)
echo "  Making second request..."
SECOND_START=$(date +%s%N)
SECOND_HEADERS=$(curl -sI "$FULL_URL" 2>&1)
SECOND_END=$(date +%s%N)
SECOND_TIME=$(echo "scale=2; ($SECOND_END - $SECOND_START) / 1000000" | bc)

SECOND_CACHE=$(echo "$SECOND_HEADERS" | grep -i "cf-cache-status" | awk '{print $2}' | tr -d '\r')
echo -e "  Second request: ${SECOND_CACHE} (${SECOND_TIME}ms)"

if [[ "$SECOND_CACHE" == "HIT" ]]; then
    SPEEDUP=$(echo "scale=1; $FIRST_TIME / $SECOND_TIME" | bc)
    IMPROVEMENT=$(echo "scale=1; (($FIRST_TIME - $SECOND_TIME) / $FIRST_TIME) * 100" | bc)
    echo -e "${GREEN}  ⚡ ${SPEEDUP}x faster with Cloudflare cache (${IMPROVEMENT}% improvement)${NC}"
elif [[ "$SECOND_CACHE" == "DYNAMIC" ]] || [[ "$SECOND_CACHE" == "BYPASS" ]]; then
    echo -e "${YELLOW}  ⚠ Cache not enabled for this endpoint${NC}"
    echo "  Check Cloudflare Page Rules to enable 'Cache Everything'"
else
    echo -e "${YELLOW}  ⚠ Second request still ${SECOND_CACHE}${NC}"
fi

echo ""

# ============================================================================
# Test 6: Security Headers
# ============================================================================

echo -e "${YELLOW}[Test 6]${NC} Checking security headers..."

# Strict-Transport-Security (HSTS)
if echo "$HEADERS" | grep -qi "strict-transport-security"; then
    HSTS=$(echo "$HEADERS" | grep -i "strict-transport-security" | cut -d: -f2- | xargs)
    echo -e "${GREEN}✓${NC} HSTS enabled: $HSTS"
else
    echo -e "${YELLOW}⚠${NC} HSTS not enabled (consider enabling in Cloudflare)"
fi

# X-Content-Type-Options
if echo "$HEADERS" | grep -qi "x-content-type-options.*nosniff"; then
    echo -e "${GREEN}✓${NC} X-Content-Type-Options: nosniff"
else
    echo -e "${YELLOW}⚠${NC} X-Content-Type-Options header missing"
fi

# X-Frame-Options
if echo "$HEADERS" | grep -qi "x-frame-options"; then
    XFO=$(echo "$HEADERS" | grep -i "x-frame-options" | awk '{print $2}' | tr -d '\r')
    echo -e "${GREEN}✓${NC} X-Frame-Options: $XFO"
else
    echo -e "${YELLOW}⚠${NC} X-Frame-Options header missing"
fi

echo ""

# ============================================================================
# Test 7: Compression
# ============================================================================

echo -e "${YELLOW}[Test 7]${NC} Checking compression..."

ENCODING=$(echo "$HEADERS" | grep -i "content-encoding" | awk '{print $2}' | tr -d '\r')
if [[ "$ENCODING" == "br" ]]; then
    echo -e "${GREEN}✓${NC} Brotli compression enabled (best)"
elif [[ "$ENCODING" == "gzip" ]]; then
    echo -e "${GREEN}✓${NC} Gzip compression enabled"
elif [ -n "$ENCODING" ]; then
    echo -e "${YELLOW}⚠${NC} Compression: $ENCODING"
else
    echo -e "${YELLOW}⚠${NC} No compression detected"
fi

echo ""

# ============================================================================
# Test 8: Global Performance (optional, requires external tool)
# ============================================================================

echo -e "${YELLOW}[Test 8]${NC} Global performance check..."

echo "  To test global performance from multiple locations:"
echo "  → Visit: https://www.dotcom-tools.com/website-speed-test.aspx"
echo "  → Enter URL: $FULL_URL"
echo "  → Test from: USA, Europe, Asia"
echo ""
echo "  Expected results:"
echo "  - Moldova (local): 5-20ms"
echo "  - Europe: 20-40ms"
echo "  - USA: 30-60ms"
echo "  - Asia: 40-80ms"

echo ""

# ============================================================================
# Test 9: Cloudflare API (cache purge test)
# ============================================================================

echo -e "${YELLOW}[Test 9]${NC} Cloudflare API integration..."

if [ -f ".env.local" ] && grep -q "CLOUDFLARE_API_TOKEN" .env.local; then
    if grep -q "CLOUDFLARE_API_TOKEN=your_cloudflare" .env.local; then
        echo -e "${YELLOW}⚠${NC} Cloudflare API token not configured in .env.local"
        echo "  Configure CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID to enable API"
    else
        echo -e "${GREEN}✓${NC} Cloudflare API credentials configured"
        echo "  Test cache purge:"
        echo "  → symfony console app:cloudflare:purge-url $FULL_URL"
    fi
else
    echo -e "${YELLOW}⚠${NC} .env.local not found or missing Cloudflare config"
fi

echo ""

# ============================================================================
# Summary
# ============================================================================

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    Test Summary                            ║${NC}"
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo ""

# Count successes
TOTAL_TESTS=9
PASSED=0

[[ "$DNS_OUTPUT" =~ 104\.|172\.64\.|162\.159\.|2606:4700 ]] && ((PASSED++))
echo "$SSL_INFO" | grep -qi "cloudflare" && ((PASSED++))
echo "$HEADERS" | grep -qi "cf-ray" && ((PASSED++))
[[ "$SECOND_CACHE" == "HIT" ]] && ((PASSED++))
[[ -n "$HTTP2_CHECK" ]] && ((PASSED++))
echo "$HEADERS" | grep -qi "strict-transport-security" && ((PASSED++))
[[ -n "$ENCODING" ]] && ((PASSED++))

echo -e "Tests passed: ${GREEN}${PASSED}/${TOTAL_TESTS}${NC}"
echo ""

if [ $PASSED -ge 7 ]; then
    echo -e "${GREEN}✓ Cloudflare CDN is working correctly!${NC}"
elif [ $PASSED -ge 4 ]; then
    echo -e "${YELLOW}⚠ Cloudflare CDN is partially configured${NC}"
    echo "  Review failed tests and Cloudflare dashboard settings"
else
    echo -e "${RED}✗ Cloudflare CDN requires configuration${NC}"
    echo "  Follow CLOUDFLARE_SETUP.md for complete setup"
fi

echo ""
echo "Next steps:"
echo "  1. Review Cloudflare Dashboard for analytics"
echo "  2. Configure Page Rules for API caching"
echo "  3. Enable security features (WAF, Rate Limiting)"
echo "  4. Test cache purging with CloudflareCacheService"
echo ""
