#!/bin/bash

echo "🔒 Deschide News Backend - Security Fix Script"
echo "=============================================="
echo ""

# Check if running as correct user
if [ "$EUID" -eq 0 ]; then
    echo "⚠️  WARNING: Running as root. Consider running as web server user."
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# FIX 1: JWT Key Permissions (CRITICAL)
echo "1. Fixing JWT key permissions (CRITICAL)..."
if [ -d "config/jwt" ]; then
    chmod 750 config/jwt/
    if [ -f "config/jwt/private.pem" ]; then
        chmod 600 config/jwt/private.pem
        echo "   ✅ Private key secured (600)"
    else
        echo "   ⚠️  Private key not found"
    fi
    if [ -f "config/jwt/public.pem" ]; then
        chmod 644 config/jwt/public.pem
        echo "   ✅ Public key secured (644)"
    else
        echo "   ⚠️  Public key not found"
    fi
    echo "   ✅ JWT directory secured (750)"
else
    echo "   ❌ config/jwt directory not found!"
fi
echo ""

# FIX 2: Upload Directory Permissions
echo "2. Fixing upload directory permissions..."
if [ -d "public/uploads" ]; then
    chmod 755 public/uploads/ 2>/dev/null || true
    chmod 755 public/uploads/images/ 2>/dev/null || true
    chmod 755 public/uploads/images/originals 2>/dev/null || true
    chmod 755 public/uploads/images/thumbnails 2>/dev/null || true
    chmod 755 public/uploads/thumbnails 2>/dev/null || true
    echo "   ✅ Upload directories secured (755)"
else
    echo "   ⚠️  public/uploads directory not found"
fi
echo ""

# FIX 3: Verify Permissions
echo "3. Verifying permissions..."
echo ""
echo "   JWT Keys:"
ls -la config/jwt/ 2>/dev/null | grep -E "^\." || echo "   JWT directory not accessible"
echo ""
echo "   Upload Directories:"
find public/uploads/ -maxdepth 2 -type d -exec ls -ld {} \; 2>/dev/null | head -5 || echo "   Upload directory not accessible"
echo ""

# FIX 4: Check if Symfony CLI is available
if ! command -v symfony &> /dev/null; then
    echo "⚠️  Symfony CLI not found. Skipping JWT key regeneration."
    echo "   Install from: https://symfony.com/download"
    echo ""
else
    # FIX 4: Regenerate JWT Keys (OPTIONAL)
    echo "4. JWT Key Regeneration (OPTIONAL)"
    echo "   ⚠️  WARNING: This will invalidate ALL existing JWT tokens!"
    echo "   ⚠️  All users will need to re-authenticate."
    read -p "   Regenerate JWT keys? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        symfony console lexik:jwt:generate-keypair --overwrite
        # Re-apply secure permissions
        chmod 600 config/jwt/private.pem
        chmod 644 config/jwt/public.pem
        echo "   ✅ JWT keys regenerated and secured"
    else
        echo "   ⏭️  Skipped"
    fi
fi
echo ""

# Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎉 Security fixes applied!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ FIXED:"
echo "   - JWT key permissions secured"
echo "   - Upload directory permissions secured"
echo ""
echo "⚠️  STILL TODO (Manual Steps Required):"
echo ""
echo "   1. Implement Rate Limiting:"
echo "      composer require symfony/rate-limiter"
echo "      See: SPRINT_3_SECURITY_AUDIT_REPORT.md (MEDIUM-2)"
echo ""
echo "   2. Replace unserialize() in PerformanceService:"
echo "      Replace with json_encode/json_decode"
echo "      File: src/Service/PerformanceService.php:54"
echo "      See: SPRINT_3_SECURITY_AUDIT_REPORT.md (MEDIUM-3)"
echo ""
echo "   3. Add Production Debug Check:"
echo "      Add check in public/index.php"
echo "      See: SPRINT_3_SECURITY_AUDIT_REPORT.md (MEDIUM-4)"
echo ""
echo "   4. Restrict /metrics Endpoint:"
echo "      Add IP whitelist in security.yaml"
echo "      See: SPRINT_3_SECURITY_AUDIT_REPORT.md (MEDIUM-5)"
echo ""
echo "   5. Update Dependencies:"
echo "      composer update"
echo "      See: SPRINT_3_SECURITY_AUDIT_REPORT.md (LOW-2)"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📄 Full Report: SPRINT_3_SECURITY_AUDIT_REPORT.md"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
