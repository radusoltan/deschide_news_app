# Security & Authentication - Arhitectură

Sistemul de securitate pentru aplicația Deschide News cu **Symfony backend** (API) și **Next.js frontend** (separate deployment).

## Arhitectură Generală

```
┌─────────────────┐         HTTPS/REST API        ┌─────────────────┐
│                 │◄──────────────────────────────►│                 │
│   Next.js       │   JWT Token in Headers        │   Symfony       │
│   Frontend      │   Refresh Token in Cookie     │   Backend API   │
│                 │                                │                 │
│  (Port 3000)    │                                │  (Port 8000)    │
└─────────────────┘                                └─────────────────┘
     │                                                      │
     │ Store tokens in:                                    │ Manage:
     │ - Memory (access token)                             │ - User entities
     │ - HttpOnly Cookie (refresh token)                   │ - JWT generation
     │                                                      │ - Token validation
```

## Componente Backend (Symfony)

### Pachete Necesare

```bash
composer require lexik/jwt-authentication-bundle
composer require gesdinet/jwt-refresh-token-bundle
```

### 1. User Entity

```php
<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity('email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Clear sensitive data if needed
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    // Getters and setters...
}
```

### 2. Database Schema

```sql
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL UNIQUE,
    roles JSON NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
);

-- Refresh tokens (managed by JWTRefreshTokenBundle)
CREATE TABLE refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    refresh_token VARCHAR(128) NOT NULL UNIQUE,
    username VARCHAR(180) NOT NULL,
    valid DATETIME NOT NULL,
    INDEX idx_refresh_token (refresh_token)
);
```

### 3. Configurație Symfony

**config/packages/security.yaml**
```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        login:
            pattern: ^/api/login
            stateless: true
            json_login:
                check_path: /api/login
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure

        refresh:
            pattern: ^/api/token/refresh
            stateless: true

        api:
            pattern: ^/api
            stateless: true
            jwt: ~

        main:
            lazy: true

    access_control:
        - { path: ^/api/login, roles: PUBLIC_ACCESS }
        - { path: ^/api/token/refresh, roles: PUBLIC_ACCESS }
        - { path: ^/api/public, roles: PUBLIC_ACCESS }
        - { path: ^/api/admin, roles: ROLE_ADMIN }
        - { path: ^/api, roles: ROLE_USER }

    role_hierarchy:
        ROLE_ADMIN: ROLE_USER
        ROLE_SUPER_ADMIN: ROLE_ADMIN
```

**config/packages/lexik_jwt_authentication.yaml**
```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600  # 1 hour
    user_identity_field: email
```

**config/packages/gesdinet_jwt_refresh_token.yaml**
```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken
    ttl: 2592000  # 30 days
    ttl_update: true
    user_provider: security.user.provider.concrete.app_user_provider
    user_identity_field: email
    single_use: false
    token_parameter_name: refresh_token
```

**config/packages/nelmio_cors.yaml**
```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization', 'X-Requested-With']
        expose_headers: ['Link', 'X-Total-Count']
        max_age: 3600
        allow_credentials: true
    paths:
        '^/api/':
            allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
            allow_credentials: true
```

**.env**
```bash
# JWT Configuration
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase_here

# CORS
CORS_ALLOW_ORIGIN=http://localhost:3000
```

### 4. Generate JWT Keys

```bash
# Generate private key
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

# Generate public key
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# Set permissions
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem
```

### 5. Routing Configuration

**config/routes.yaml**
```yaml
# Login endpoint - handled automatically by LexikJWTAuthenticationBundle
# Configuration in security.yaml (json_login firewall)
api_login_check:
    path: /api/login
    methods: ['POST']
    # No controller needed - Lexik handles this automatically

# Token refresh endpoint - handled automatically by JWTRefreshTokenBundle
api_refresh_token:
    path: /api/token/refresh
    methods: ['POST']
    # No controller needed - bundle handles this automatically
```

**IMPORTANT**:
- `/api/login` este gestionat automat de `LexikJWTAuthenticationBundle` prin `json_login` din `security.yaml`
- `/api/token/refresh` este gestionat automat de `JWTRefreshTokenBundle`
- **NU** creați controller-e pentru aceste endpoint-uri - bundle-urile le gestionează complet

### 6. User Controller (Minimal)

**src/Controller/Api/UserController.php**
```php
<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class UserController extends AbstractController
{
    /**
     * Get current authenticated user profile
     * GET /api/me
     * Headers: Authorization: Bearer {token}
     */
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(
                ['message' => 'Missing credentials'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
        ]);
    }
}
```

### 7. Admin User Management Controller

**src/Controller/Api/Admin/UserManagementController.php**
```php
<?php

namespace App\Controller\Api\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {}

    /**
     * List all users
     * GET /api/admin/users
     */
    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $offset = ($page - 1) * $limit;

        $qb = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('u.createdAt', 'DESC');

        $users = $qb->getQuery()->getResult();
        $total = $this->em->getRepository(User::class)->count([]);

        return new JsonResponse([
            'users' => array_map(fn($user) => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()->format('c'),
            ], $users),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Create new user (admin only)
     * POST /api/admin/users
     * Body: {"email": "...", "password": "...", "firstName": "...", "lastName": "...", "roles": [...]}
     */
    #[Route('', name: 'api_admin_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation
        if (!isset($data['email'], $data['password'], $data['firstName'], $data['lastName'])) {
            return new JsonResponse(
                ['message' => 'Missing required fields'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Check if user exists
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            return new JsonResponse(
                ['message' => 'User with this email already exists'],
                Response::HTTP_CONFLICT
            );
        }

        // Create user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $data['password'])
        );

        // Set roles (default to ROLE_USER)
        $roles = $data['roles'] ?? ['ROLE_USER'];
        $user->setRoles($roles);

        // Validate
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(
                ['errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Get user details
     * GET /api/admin/users/{id}
     */
    #[Route('/{id}', name: 'api_admin_users_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(
                ['message' => 'User not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()->format('c'),
        ]);
    }

    /**
     * Update user
     * PUT /api/admin/users/{id}
     */
    #[Route('/{id}', name: 'api_admin_users_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(
                ['message' => 'User not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = json_decode($request->getContent(), true);

        // Update fields
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }
        if (isset($data['isActive'])) {
            $user->setIsActive($data['isActive']);
        }
        if (isset($data['password'])) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $data['password'])
            );
        }

        // Validate
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(
                ['errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->em->flush();

        return new JsonResponse([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
            ]
        ]);
    }

    /**
     * Delete user
     * DELETE /api/admin/users/{id}
     */
    #[Route('/{id}', name: 'api_admin_users_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(
                ['message' => 'User not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Prevent deleting yourself
        if ($user->getId() === $this->getUser()->getId()) {
            return new JsonResponse(
                ['message' => 'Cannot delete your own account'],
                Response::HTTP_FORBIDDEN
            );
        }

        $this->em->remove($user);
        $this->em->flush();

        return new JsonResponse([
            'message' => 'User deleted successfully'
        ]);
    }
}
```

### 6. Event Listeners

**src/EventListener/JWTCreatedListener.php**
```php
<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTCreatedListener
{
    public function __construct(private RequestStack $requestStack) {}

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();

        // Add custom claims
        $payload['id'] = $user->getId();
        $payload['email'] = $user->getEmail();
        $payload['firstName'] = $user->getFirstName();
        $payload['lastName'] = $user->getLastName();
        $payload['roles'] = $user->getRoles();

        // Add request info
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $payload['ip'] = $request->getClientIp();
        }

        $event->setData($payload);
    }
}
```

**config/services.yaml**
```yaml
services:
    App\EventListener\JWTCreatedListener:
        tags:
            - { name: kernel.event_listener, event: lexik_jwt_authentication.on_jwt_created, method: onJWTCreated }
```

## Frontend (Next.js)

### 1. Environment Variables

**.env.local**
```bash
NEXT_PUBLIC_API_URL=http://localhost:8000
```

### 2. API Client with Axios

**lib/api/client.ts**
```typescript
import axios, { AxiosInstance, AxiosError } from 'axios';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

class ApiClient {
  private client: AxiosInstance;
  private accessToken: string | null = null;

  constructor() {
    this.client = axios.create({
      baseURL: API_URL,
      headers: {
        'Content-Type': 'application/json',
      },
      withCredentials: true, // Important for cookies (refresh token)
    });

    // Request interceptor - add access token
    this.client.interceptors.request.use(
      (config) => {
        if (this.accessToken) {
          config.headers.Authorization = `Bearer ${this.accessToken}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor - handle token refresh
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        const originalRequest = error.config as any;

        // If 401 and not already retried, try to refresh token
        if (error.response?.status === 401 && !originalRequest._retry) {
          originalRequest._retry = true;

          try {
            const newToken = await this.refreshToken();
            this.setAccessToken(newToken);
            originalRequest.headers.Authorization = `Bearer ${newToken}`;
            return this.client(originalRequest);
          } catch (refreshError) {
            // Refresh failed, redirect to login
            this.clearTokens();
            if (typeof window !== 'undefined') {
              window.location.href = '/login';
            }
            return Promise.reject(refreshError);
          }
        }

        return Promise.reject(error);
      }
    );
  }

  setAccessToken(token: string) {
    this.accessToken = token;
    // Store in memory only (not localStorage for security)
    if (typeof window !== 'undefined') {
      // Optional: store in sessionStorage for page refresh
      sessionStorage.setItem('access_token', token);
    }
  }

  getAccessToken(): string | null {
    if (!this.accessToken && typeof window !== 'undefined') {
      this.accessToken = sessionStorage.getItem('access_token');
    }
    return this.accessToken;
  }

  clearTokens() {
    this.accessToken = null;
    if (typeof window !== 'undefined') {
      sessionStorage.removeItem('access_token');
    }
  }

  async refreshToken(): Promise<string> {
    // Refresh token is sent automatically via HttpOnly cookie
    const response = await axios.post(
      `${API_URL}/api/token/refresh`,
      {},
      { withCredentials: true }
    );
    return response.data.token;
  }

  async login(email: string, password: string) {
    const response = await this.client.post('/api/login', {
      username: email,
      password,
    });

    const { token, refresh_token } = response.data;
    this.setAccessToken(token);

    return response.data;
  }

  async logout() {
    // JWT is stateless - just clear tokens client-side
    this.clearTokens();
  }

  async getCurrentUser() {
    const response = await this.client.get('/api/me');
    return response.data;
  }

  // Public API methods (no auth required)
  async getArticles(params?: any) {
    const response = await this.client.get('/api/public/articles', { params });
    return response.data;
  }

  async getArticle(slug: string, locale: string) {
    const response = await this.client.get(`/api/public/articles/${slug}`, {
      params: { locale },
    });
    return response.data;
  }

  // Admin API methods (auth required)
  async createArticle(data: any) {
    const response = await this.client.post('/api/admin/articles', data);
    return response.data;
  }

  async updateArticle(id: number, data: any) {
    const response = await this.client.put(`/api/admin/articles/${id}`, data);
    return response.data;
  }

  async deleteArticle(id: number) {
    const response = await this.client.delete(`/api/admin/articles/${id}`);
    return response.data;
  }

  // Admin User Management API methods
  async getUsers(params?: any) {
    const response = await this.client.get('/api/admin/users', { params });
    return response.data;
  }

  async createUser(data: any) {
    const response = await this.client.post('/api/admin/users', data);
    return response.data;
  }

  async updateUser(id: number, data: any) {
    const response = await this.client.put(`/api/admin/users/${id}`, data);
    return response.data;
  }

  async deleteUser(id: number) {
    const response = await this.client.delete(`/api/admin/users/${id}`);
    return response.data;
  }
}

export const apiClient = new ApiClient();
```

### 3. Auth Context (React)

**contexts/AuthContext.tsx**
```typescript
'use client';

import React, { createContext, useContext, useState, useEffect } from 'react';
import { apiClient } from '@/lib/api/client';

interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  fullName: string;
  roles: string[];
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  isAuthenticated: boolean;
  hasRole: (role: string) => boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check if user is logged in on mount
    const token = apiClient.getAccessToken();
    if (token) {
      fetchCurrentUser();
    } else {
      setLoading(false);
    }
  }, []);

  const fetchCurrentUser = async () => {
    try {
      const userData = await apiClient.getCurrentUser();
      setUser(userData);
    } catch (error) {
      console.error('Failed to fetch user', error);
      apiClient.clearTokens();
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  const login = async (email: string, password: string) => {
    try {
      await apiClient.login(email, password);
      await fetchCurrentUser();
    } catch (error) {
      console.error('Login failed', error);
      throw error;
    }
  };

  const logout = () => {
    apiClient.logout();
    setUser(null);
  };

  const hasRole = (role: string): boolean => {
    return user?.roles?.includes(role) || false;
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        login,
        logout,
        isAuthenticated: !!user,
        hasRole,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
}
```

### 4. Protected Route HOC

**components/auth/ProtectedRoute.tsx**
```typescript
'use client';

import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredRole?: string;
  redirectTo?: string;
}

export function ProtectedRoute({
  children,
  requiredRole,
  redirectTo = '/login',
}: ProtectedRouteProps) {
  const { isAuthenticated, hasRole, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading) {
      if (!isAuthenticated) {
        router.push(redirectTo);
      } else if (requiredRole && !hasRole(requiredRole)) {
        router.push('/unauthorized');
      }
    }
  }, [isAuthenticated, hasRole, loading, requiredRole, redirectTo, router]);

  if (loading) {
    return <div>Loading...</div>;
  }

  if (!isAuthenticated) {
    return null;
  }

  if (requiredRole && !hasRole(requiredRole)) {
    return null;
  }

  return <>{children}</>;
}
```

### 5. Login Page

**app/login/page.tsx**
```typescript
'use client';

import { useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await login(email, password);
      router.push('/admin/dashboard');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="max-w-md w-full space-y-8">
        <h2 className="text-3xl font-bold text-center">Login</h2>
        {error && (
          <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {error}
          </div>
        )}
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label htmlFor="email" className="block text-sm font-medium">
              Email
            </label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2"
            />
          </div>
          <div>
            <label htmlFor="password" className="block text-sm font-medium">
              Password
            </label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? 'Loading...' : 'Login'}
          </button>
        </form>
      </div>
    </div>
  );
}
```

### 6. Root Layout with AuthProvider

**app/layout.tsx**
```typescript
import { AuthProvider } from '@/contexts/AuthContext';
import './globals.css';

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body>
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
```

### 7. Protected Admin Page Example

**app/admin/dashboard/page.tsx**
```typescript
import { ProtectedRoute } from '@/components/auth/ProtectedRoute';

export default function AdminDashboard() {
  return (
    <ProtectedRoute requiredRole="ROLE_ADMIN">
      <div>
        <h1>Admin Dashboard</h1>
        <p>Only accessible to admins</p>
      </div>
    </ProtectedRoute>
  );
}
```

## Flow Diagram

### Authentication Flow

```
1. Login Request
   Next.js → POST /api/login → Symfony
   Body: {username, password}

2. Symfony validates credentials
   ↓
3. Symfony generates tokens:
   - Access Token (JWT, short-lived: 1h)
   - Refresh Token (long-lived: 30d)

4. Symfony sends response:
   - Access Token in response body
   - Refresh Token in HttpOnly Cookie

5. Next.js stores:
   - Access Token → Memory/SessionStorage
   - Refresh Token → Automatic (browser handles cookie)

6. Authenticated Requests
   Next.js → GET /api/admin/articles
   Headers: Authorization: Bearer {access_token}
   Cookies: refresh_token (automatic)

7. Token Expired (401)
   Next.js → POST /api/token/refresh
   Cookies: refresh_token (automatic)
   ↓
   Symfony validates refresh token
   ↓
   New Access Token returned
   ↓
   Retry original request with new token
```

## Security Best Practices

### Backend (Symfony)

1. **HTTPS Only in Production**
   - Force HTTPS for all API endpoints
   - Set secure flag on refresh token cookie

2. **CORS Configuration**
   - Whitelist only frontend domain
   - Enable credentials for cookies
   - Limit allowed methods

3. **Rate Limiting**
   ```bash
   composer require symfony/rate-limiter
   ```
   - Limit login attempts
   - Limit API requests per user

4. **Token Security**
   - Short TTL for access tokens (1 hour)
   - Longer TTL for refresh tokens (30 days)
   - Rotate refresh tokens on use
   - Store hashed refresh tokens in DB

5. **Password Security**
   - Use bcrypt/argon2 hashing
   - Enforce strong password policy
   - Implement password reset flow

### Frontend (Next.js)

1. **Token Storage**
   - Access Token: Memory or SessionStorage (NOT localStorage)
   - Refresh Token: HttpOnly Cookie (secure, sameSite)

2. **XSS Prevention**
   - Never store tokens in localStorage
   - Sanitize all user inputs
   - Use Content Security Policy

3. **CSRF Protection**
   - Use SameSite cookie attribute
   - CORS properly configured

4. **Request Security**
   - Always use HTTPS in production
   - Validate API responses
   - Handle errors gracefully

## API Endpoints

### Public (No Auth)

```
GET  /api/public/articles           - Lista articole publicate
GET  /api/public/articles/{slug}    - Detalii articol
GET  /api/public/categories         - Lista categorii
GET  /api/public/authors            - Lista autori
```

### Auth Endpoints

```
POST /api/login                     - Login (handled by Lexik, returns JWT + refresh token)
POST /api/token/refresh             - Refresh access token (handled by JWTRefreshTokenBundle)
GET  /api/me                        - Get current authenticated user
```

### Admin - Article Management (ROLE_ADMIN)

```
GET    /api/admin/articles          - Lista articole (toate)
POST   /api/admin/articles          - Creare articol
GET    /api/admin/articles/{id}     - Detalii articol
PUT    /api/admin/articles/{id}     - Update articol
DELETE /api/admin/articles/{id}     - Ștergere articol
```

### Admin - User Management (ROLE_ADMIN)

```
GET    /api/admin/users             - Lista utilizatori (paginat)
POST   /api/admin/users             - Creare utilizator nou
GET    /api/admin/users/{id}        - Detalii utilizator
PUT    /api/admin/users/{id}        - Update utilizator
DELETE /api/admin/users/{id}        - Ștergere utilizator
```

**Note**: Nu există endpoint de register public. Utilizatorii noi sunt creați doar de administratori prin `/api/admin/users`.

## Testing

### Backend Tests

```php
// tests/Controller/AuthControllerTest.php
public function testLogin(): void
{
    $client = static::createClient();
    $client->request('POST', '/api/login', [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode([
        'username' => 'test@example.com',
        'password' => 'password123',
    ]));

    $this->assertResponseIsSuccessful();
    $data = json_decode($client->getResponse()->getContent(), true);
    $this->assertArrayHasKey('token', $data);
    $this->assertArrayHasKey('refresh_token', $data);
}
```

### Frontend Tests

```typescript
// __tests__/auth.test.ts
describe('Authentication', () => {
  it('should login successfully', async () => {
    const { result } = renderHook(() => useAuth(), {
      wrapper: AuthProvider,
    });

    await act(async () => {
      await result.current.login('test@example.com', 'password123');
    });

    expect(result.current.isAuthenticated).toBe(true);
    expect(result.current.user).toBeDefined();
  });
});
```

## Deployment Considerations

### Production Environment

**.env.prod (Symfony)**
```bash
JWT_PASSPHRASE=strong_production_passphrase
CORS_ALLOW_ORIGIN=https://deschide-news.com
```

**.env.production (Next.js)**
```bash
NEXT_PUBLIC_API_URL=https://api.deschide-news.com
```

### Nginx Configuration

```nginx
# Backend (Symfony)
server {
    listen 443 ssl;
    server_name api.deschide-news.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}

# Frontend (Next.js)
server {
    listen 443 ssl;
    server_name deschide-news.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://localhost:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## Troubleshooting

### Common Issues

1. **CORS Errors**
   - Check CORS_ALLOW_ORIGIN matches frontend URL exactly
   - Ensure withCredentials: true in axios config
   - Verify allow_credentials: true in nelmio_cors config

2. **Token Refresh Loop**
   - Check refresh token cookie is being sent
   - Verify refresh token TTL hasn't expired
   - Check for multiple concurrent refresh requests

3. **401 Unauthorized**
   - Verify token format: `Bearer {token}`
   - Check token hasn't expired
   - Ensure user still exists and is active

4. **Cookie Not Set**
   - Check sameSite attribute (use 'lax' in development)
   - Verify secure flag (false in development, true in production)
   - Ensure domain matches between frontend and backend
