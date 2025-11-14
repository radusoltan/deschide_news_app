# Authentication & Authorization Flow

## Overview

Deschide News backend uses **JWT (JSON Web Token)** based authentication with:
- **Lexik JWT Authentication Bundle** for token generation
- **Gesdinet JWT Refresh Token Bundle** for token refresh
- **Symfony Security** for authorization and access control

## Architecture Components

### 1. Authentication Packages

```json
{
  "lexik/jwt-authentication-bundle": "^3.2",
  "gesdinet/jwt-refresh-token-bundle": "^2.0"
}
```

### 2. JWT Key Pair

Located in `config/jwt/`:
- `private.pem` - Private key for signing tokens (NOT in git)
- `public.pem` - Public key for verifying tokens (NOT in git)

**Generation:**
```bash
symfony console lexik:jwt:generate-keypair
```

## Authentication Flow

### Step 1: User Login

**Request:**
```http
POST /api/login_check
Content-Type: application/json

{
  "username": "admin@example.com",
  "password": "securePassword123"
}
```

**Success Response (200):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200e3b6f8c7a9d2..."
}
```

**Error Response (401):**
```json
{
  "code": 401,
  "message": "Invalid credentials."
}
```

### Step 2: Using Access Token

All subsequent requests include the JWT token:

**Request:**
```http
GET /api/articles
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Accept-Language: ro
```

**Response:**
- **200 OK** - Request successful
- **401 Unauthorized** - Token expired or invalid
- **403 Forbidden** - Insufficient permissions

### Step 3: Token Refresh

When access token expires (default: 3600 seconds = 1 hour):

**Request:**
```http
POST /api/token/refresh
Content-Type: application/json

{
  "refresh_token": "def50200e3b6f8c7a9d2..."
}
```

**Success Response (200):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200e3b6f8c7a9d2..."
}
```

### Step 4: Logout (Optional)

Refresh token invalidation:

**Request:**
```http
POST /api/token/invalidate
Content-Type: application/json

{
  "refresh_token": "def50200e3b6f8c7a9d2..."
}
```

## Authorization (Roles)

### User Roles Hierarchy

```
ROLE_SUPER_ADMIN
  └── ROLE_ADMIN
       └── ROLE_EDITOR
            └── ROLE_USER
```

### Role Definitions

| Role | Description | Permissions |
|------|-------------|-------------|
| `ROLE_USER` | Basic authenticated user | Read access to protected resources |
| `ROLE_EDITOR` | Content editor | Create, edit, publish articles |
| `ROLE_ADMIN` | Administrator | Full CRUD on all resources |
| `ROLE_SUPER_ADMIN` | Super administrator | User management, system config |

### Access Control

Defined in `config/packages/security.yaml`:

```yaml
access_control:
  - { path: ^/api/login_check, roles: PUBLIC_ACCESS }
  - { path: ^/api/token/refresh, roles: PUBLIC_ACCESS }
  - { path: ^/api/docs, roles: PUBLIC_ACCESS }
  - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

## JWT Token Structure

### Token Claims

```json
{
  "iat": 1699200000,
  "exp": 1699203600,
  "roles": ["ROLE_ADMIN", "ROLE_USER"],
  "username": "admin@example.com",
  "email": "admin@example.com",
  "userId": 1
}
```

**Claims:**
- `iat` - Issued at timestamp
- `exp` - Expiration timestamp
- `roles` - User roles array
- `username` - User identifier
- `email` - User email
- `userId` - Database user ID

## Security Configuration

### Password Hashing

Uses **Sodium** algorithm (PHP 8.4 default):

```yaml
# config/packages/security.yaml
security:
  password_hashers:
    App\Entity\User:
      algorithm: auto
```

### CORS Configuration

Frontend allowed origins:

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
  defaults:
    origin_regex: true
    allow_origin: ['^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$']
    allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
    allow_headers: ['Content-Type', 'Authorization', 'Accept-Language']
    expose_headers: ['Link']
    max_age: 3600
```

## Entity: User

### Database Schema

```sql
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  email VARCHAR(180) UNIQUE NOT NULL,
  roles JSONB NOT NULL,
  password VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL
);
```

### Entity Class

```php
// src/Entity/User.php
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Email already exists')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    // Implements UserInterface methods
    public function getRoles(): array { /* ... */ }
    public function eraseCredentials(): void { /* ... */ }
    public function getUserIdentifier(): string { return $this->email; }
}
```

## Refresh Tokens

### Database Schema

```sql
CREATE TABLE refresh_tokens (
  id SERIAL PRIMARY KEY,
  refresh_token VARCHAR(128) UNIQUE NOT NULL,
  username VARCHAR(255) NOT NULL,
  valid TIMESTAMP NOT NULL
);
```

### Token Lifetime

- **Access Token**: 1 hour (3600 seconds)
- **Refresh Token**: 7 days (604800 seconds)

**Configuration:**
```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
  secret_key: '%kernel.project_dir%/config/jwt/private.pem'
  public_key: '%kernel.project_dir%/config/jwt/public.pem'
  pass_phrase: '%env(JWT_PASSPHRASE)%'
  token_ttl: 3600

# config/packages/gesdinet_jwt_refresh_token.yaml
gesdinet_jwt_refresh_token:
  ttl: 604800
  ttl_update: true
```

## Frontend Integration

### Storage Strategy

**Recommended:** Store tokens in **httpOnly cookies** (handled by backend)
**Alternative:** Store in memory or sessionStorage (NEVER localStorage for security)

### Auto-Refresh Logic

```typescript
// Frontend pseudocode
async function makeAuthenticatedRequest(url: string) {
  let token = getAccessToken();
  
  const response = await fetch(url, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  
  if (response.status === 401) {
    // Token expired, refresh it
    token = await refreshAccessToken();
    
    // Retry request with new token
    return fetch(url, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
  }
  
  return response;
}
```

## Security Best Practices

### ✅ Implemented

- [x] JWT keys stored outside public directory
- [x] JWT keys NOT committed to git
- [x] HTTPS-only in production (SSL/TLS)
- [x] Password hashing with Sodium
- [x] CORS properly configured
- [x] Token expiration enforced
- [x] Refresh token rotation
- [x] Rate limiting on login endpoint (planned)

### 🔒 Recommendations

1. **Enable HTTPS in production** - JWT tokens should never be transmitted over HTTP
2. **Implement rate limiting** on `/api/login_check` to prevent brute-force attacks
3. **Monitor refresh tokens** - Log all token refresh attempts
4. **Revoke tokens on suspicious activity** - Implement token blacklist if needed
5. **Use short-lived access tokens** - Current 1 hour is reasonable
6. **Implement 2FA** for admin users (future enhancement)

## Testing Authentication

### cURL Examples

**Login:**
```bash
curl -X POST http://127.0.0.1:8081/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin@example.com","password":"password"}'
```

**Access Protected Resource:**
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

curl http://127.0.0.1:8081/api/articles \
  -H "Authorization: Bearer $TOKEN"
```

**Refresh Token:**
```bash
REFRESH_TOKEN="def50200e3b6f8c7a9d2..."

curl -X POST http://127.0.0.1:8081/api/token/refresh \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}"
```

## Troubleshooting

### "Invalid JWT Token"

**Causes:**
- Token expired
- Token signature invalid
- JWT keys missing or mismatched

**Solution:**
```bash
# Regenerate JWT keys
symfony console lexik:jwt:generate-keypair

# Check JWT configuration
cat .env.local | grep JWT
```

### "Invalid credentials"

**Causes:**
- Wrong email/password
- User account inactive
- Database connection issue

**Solution:**
```bash
# Check user exists
symfony console doctrine:query:sql "SELECT * FROM users WHERE email='admin@example.com'"

# Reset password (if needed)
symfony console app:user:change-password admin@example.com
```

### "CORS error in browser"

**Solution:**
Check `nelmio_cors.yaml` allows frontend origin:
```yaml
allow_origin: ['^https?://localhost:3005$']
```

---

**Last Updated**: November 2025
**Security Audit**: Recommended annually
