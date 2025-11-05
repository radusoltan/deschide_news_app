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
    # WARNING: Only do this for public API endpoints!
    if (req.url ~ "^/api/") {
        unset req.http.Cookie;
    }

    # Normalize Accept-Language header for better cache efficiency
    # Store full header but simplify for caching
    if (req.http.Accept-Language) {
        if (req.http.Accept-Language ~ "ro") {
            set req.http.X-Language = "ro";
        } elsif (req.http.Accept-Language ~ "en") {
            set req.http.X-Language = "en";
        } elsif (req.http.Accept-Language ~ "ru") {
            set req.http.X-Language = "ru";
        } else {
            set req.http.X-Language = "ro";  # Default
        }
    } else {
        set req.http.X-Language = "ro";
    }

    # Normalize query parameters order for better cache hit rate
    # Varnish will hash the full URL including query params
    if (req.url ~ "\?") {
        # Sort query parameters (requires vmod-querystring - optional)
        # For now, we rely on clients sending consistent parameter order
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

    # Default: try to serve from cache
    return (hash);
}

# ============================================================================
# vcl_hash - Cache key generation
# ============================================================================

sub vcl_hash {
    # Include URL in hash
    hash_data(req.url);

    # Include host in hash
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    # Include normalized language in hash (for multilanguage content)
    if (req.http.X-Language) {
        hash_data(req.http.X-Language);
    }

    # Include Accept header for content negotiation (JSON-LD vs JSON)
    if (req.http.Accept) {
        hash_data(req.http.Accept);
    }

    return (lookup);
}

# ============================================================================
# vcl_backend_response - Response from backend (Symfony)
# ============================================================================

sub vcl_backend_response {
    # Set cache duration based on backend Cache-Control headers
    # Symfony is already sending: Cache-Control: max-age=1800, public, s-maxage=3600

    if (beresp.http.Cache-Control ~ "private") {
        # Don't cache private responses
        set beresp.uncacheable = true;
        return (deliver);
    }

    if (beresp.http.Cache-Control ~ "no-cache|no-store") {
        # Respect no-cache directives
        set beresp.uncacheable = true;
        return (deliver);
    }

    # Cache successful responses
    if (beresp.status == 200 || beresp.status == 203 || beresp.status == 204 ||
        beresp.status == 206 || beresp.status == 300 || beresp.status == 301 ||
        beresp.status == 404 || beresp.status == 405 || beresp.status == 410 ||
        beresp.status == 414) {

        # Extract s-maxage from Cache-Control header if present
        if (beresp.http.Cache-Control ~ "s-maxage=([0-9]+)") {
            # Varnish will automatically use s-maxage value
            # Default from Symfony: s-maxage=3600 (1 hour)
        } else {
            # Fallback TTL if no s-maxage is set
            set beresp.ttl = 30m;
        }

        # Grace period: serve stale content while fetching new content
        # This prevents cache stampede
        set beresp.grace = 6h;

        # Enable ESI processing if needed
        # set beresp.do_esi = true;

        # Enable gzip compression for text responses
        if (beresp.http.content-type ~ "text|json|javascript|xml") {
            set beresp.do_gzip = true;
        }
    } else {
        # Don't cache error responses (500, 502, 503)
        set beresp.ttl = 0s;
        set beresp.uncacheable = true;
    }

    # Add custom header to indicate cache status
    set beresp.http.X-Varnish-TTL = beresp.ttl;

    return (deliver);
}

# ============================================================================
# vcl_deliver - Deliver response to client
# ============================================================================

sub vcl_deliver {
    # Add debug headers (disable in production)
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }

    # Add cache age
    set resp.http.X-Cache-Age = resp.http.Age;

    # Remove internal headers (optional, for security)
    # unset resp.http.X-Varnish;
    # unset resp.http.Via;
    # unset resp.http.X-Cache;
    # unset resp.http.X-Cache-Hits;

    return (deliver);
}

# ============================================================================
# vcl_backend_error - Handle backend errors
# ============================================================================

sub vcl_backend_error {
    # Grace handling is automatic in Varnish 7.x
    # If we have stale content within grace period, Varnish serves it automatically

    # Custom error page for backend errors
    set beresp.http.Content-Type = "application/json";
    synthetic({"{"error": "Backend unavailable", "status": 503}"});
    return (deliver);
}

# ============================================================================
# vcl_purge - Handle cache purging
# ============================================================================

sub vcl_purge {
    return (synth(200, "Purged"));
}
