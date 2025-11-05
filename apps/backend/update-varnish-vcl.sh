#!/bin/bash

# Update Varnish VCL to force caching for public API endpoints
# This overrides Symfony's no-cache headers in development mode

cat > /tmp/default.vcl << 'EOF'
# Varnish Configuration for Deschide News Backend
# VCL version 4.1
# Optimized for API Platform JSON-LD responses

vcl 4.1;

import std;

# Backend configuration - Symfony dev server
backend default {
    .host = "127.0.0.1";
    .port = "8081";
    .connect_timeout = 5s;
    .first_byte_timeout = 60s;
    .between_bytes_timeout = 10s;
}

# ACL for cache purging (allowed IPs)
acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
}

# ============================================================================
# vcl_recv - Client request handling
# ============================================================================

sub vcl_recv {
    # Allow cache purging from allowed IPs
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        return (purge);
    }

    # Allow BAN requests (bulk purge)
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        ban("req.url ~ " + req.url);
        return (synth(200, "Ban added"));
    }

    # Only cache GET and HEAD requests
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Don't cache authentication endpoints
    if (req.url ~ "^/api/(login|token|refresh)") {
        return (pass);
    }

    # Don't cache admin/write operations
    if (req.url ~ "^/api/admin") {
        return (pass);
    }

    # Don't cache if Authorization header is present (authenticated requests)
    if (req.http.Authorization) {
        return (pass);
    }

    # Remove cookies for API requests (they're not needed for caching)
    if (req.url ~ "^/api/") {
        unset req.http.Cookie;
    }

    # Normalize Accept-Language header for better cache efficiency
    if (req.http.Accept-Language) {
        if (req.http.Accept-Language ~ "ro") {
            set req.http.X-Language = "ro";
        } elsif (req.http.Accept-Language ~ "en") {
            set req.http.X-Language = "en";
        } elsif (req.http.Accept-Language ~ "ru") {
            set req.http.X-Language = "ru";
        } else {
            set req.http.X-Language = "ro";
        }
    } else {
        set req.http.X-Language = "ro";
    }

    # Handle compression
    if (req.http.Accept-Encoding) {
        if (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            unset req.http.Accept-Encoding;
        }
    }

    return (hash);
}

# ============================================================================
# vcl_hash - Cache key generation
# ============================================================================

sub vcl_hash {
    hash_data(req.url);

    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    if (req.http.X-Language) {
        hash_data(req.http.X-Language);
    }

    if (req.http.Accept) {
        hash_data(req.http.Accept);
    }

    return (lookup);
}

# ============================================================================
# vcl_backend_response - Response from backend (Symfony)
# ============================================================================

sub vcl_backend_response {
    # FORCE CACHING for public API endpoints (override Symfony's no-cache in dev mode)
    if (bereq.url ~ "^/api/" &&
        bereq.url !~ "^/api/(login|token|refresh|admin)" &&
        !bereq.http.Authorization) {

        # Override backend's Cache-Control headers
        unset beresp.http.Set-Cookie;

        # Force caching
        set beresp.http.Cache-Control = "public, max-age=3600, s-maxage=7200";
        set beresp.ttl = 2h;
        set beresp.grace = 6h;
        set beresp.uncacheable = false;

        # Enable gzip compression
        if (beresp.http.content-type ~ "text|json|javascript|xml") {
            set beresp.do_gzip = true;
        }

        return (deliver);
    }

    # For other endpoints, respect backend's caching directives
    if (beresp.http.Cache-Control ~ "private|no-cache|no-store") {
        set beresp.uncacheable = true;
        return (deliver);
    }

    # Cache successful responses
    if (beresp.status == 200 || beresp.status == 203 || beresp.status == 204 ||
        beresp.status == 206 || beresp.status == 300 || beresp.status == 301 ||
        beresp.status == 404 || beresp.status == 405 || beresp.status == 410 ||
        beresp.status == 414) {

        if (beresp.http.Cache-Control ~ "s-maxage=([0-9]+)") {
            # Use s-maxage from backend
        } else {
            set beresp.ttl = 30m;
        }

        set beresp.grace = 6h;

        if (beresp.http.content-type ~ "text|json|javascript|xml") {
            set beresp.do_gzip = true;
        }
    } else {
        set beresp.ttl = 0s;
        set beresp.uncacheable = true;
    }

    return (deliver);
}

# ============================================================================
# vcl_deliver - Add custom headers to response
# ============================================================================

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }

    set resp.http.X-Cache-Age = obj.age;

    # Remove internal headers (security)
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.X-Powered-By;

    return (deliver);
}

# ============================================================================
# vcl_purge - Handle cache purge responses
# ============================================================================

sub vcl_purge {
    return (synth(200, "Purged"));
}

# ============================================================================
# vcl_backend_error - Handle backend errors
# ============================================================================

sub vcl_backend_error {
    set beresp.http.Content-Type = "application/json; charset=utf-8";
    set beresp.status = 503;

    synthetic({"{"error": "Service temporarily unavailable", "code": 503}"});

    return (deliver);
}
EOF

echo "VCL file updated"
