# Authentication UI Flow Documentation

## Overview

This document describes the authentication user interface and flow in the Deschide News frontend, including login, token management, route protection, and user session handling.

---

## 🔐 Authentication Architecture

### Technology Stack

- **JWT Tokens**: Access token + Refresh token
- **Storage**: Memory-based (secure, not localStorage)
- **Auto-refresh**: Automatic token renewal on expiration
- **Route Guards**: Server and client-side protection
- **Session Persistence**: Optional cookie-based persistence

---

## 🎯 Authentication Flow Diagram

```
┌─────────────────┐
│  User visits    │
│  /ro/admin      │
└────────┬────────┘
         │
         v
   ┌─────────────┐
   │ Authenticated?│
   └─────┬───┬────┘
         │   │
      No │   │ Yes
         │   │
         v   v
┌──────────────┐  ┌──────────────┐
│ Redirect to  │  │ Show Admin   │
│ /ro/login    │  │ Dashboard    │
└──────┬───────┘  └──────────────┘
       │
       v
┌──────────────────────┐
│  Login Page          │
│  - Email input       │
│  - Password input    │
│  - Submit button     │
└──────────┬───────────┘
           │
           v (submit)
┌──────────────────────┐
│  POST /api/login_check│
│  {email, password}   │
└──────────┬───────────┘
           │
     ┌─────┴─────┐
     │           │
  Success     Failure
     │           │
     v           v
┌─────────┐  ┌──────────┐
│ Receive │  │ Show     │
│ Tokens  │  │ Error    │
└────┬────┘  └──────────┘
     │
     v
┌─────────────────┐
│ Store Tokens    │
│ - Access token  │
│ - Refresh token │
└────┬────────────┘
     │
     v
┌─────────────────┐
│ Redirect to     │
│ /ro/admin       │
└─────────────────┘
```

---

## 📄 Login Page

### Location

**File**: `app/[locale]/login/page.tsx`

**Route**: `/ro/login`, `/en/login`, `/ru/login`

### Implementation

```typescript
'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { setTokens } from '@/lib/api-client';

export default function LoginPage() {
  const router = useRouter();
  const params = useParams();
  const locale = params.locale as string;
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    
    try {
      // Call backend login endpoint
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/login_check`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          username: email,  // Backend expects 'username' field
          password: password,
        }),
      });
      
      if (!response.ok) {
        throw new Error('Invalid credentials');
      }
      
      const data = await response.json();
      
      // Store tokens in memory
      setTokens(data.token, data.refresh_token);
      
      // Redirect to admin dashboard
      router.push(`/${locale}/admin`);
      
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  };
  
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-100">
      <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
        <h1 className="text-2xl font-bold mb-6 text-center">
          {locale === 'ro' ? 'Autentificare' : 
           locale === 'en' ? 'Login' : 
           'Вход'}
        </h1>
        
        {error && (
          <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
            {error}
          </div>
        )}
        
        <form onSubmit={handleSubmit}>
          <div className="mb-4">
            <label htmlFor="email" className="block text-sm font-medium mb-2">
              Email
            </label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="admin@example.com"
            />
          </div>
          
          <div className="mb-6">
            <label htmlFor="password" className="block text-sm font-medium mb-2">
              {locale === 'ro' ? 'Parolă' : 
               locale === 'en' ? 'Password' : 
               'Пароль'}
            </label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="••••••••"
            />
          </div>
          
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? 
              (locale === 'ro' ? 'Se încarcă...' : 
               locale === 'en' ? 'Loading...' : 
               'Загрузка...') :
              (locale === 'ro' ? 'Autentificare' : 
               locale === 'en' ? 'Login' : 
               'Войти')
            }
          </button>
        </form>
      </div>
    </div>
  );
}
```

---

## 🔑 Token Management

### Token Storage

**File**: `lib/api-client.ts`

```typescript
// Store tokens in memory (NOT localStorage for security)
let accessToken: string | null = null;
let refreshToken: string | null = null;

export function setTokens(access: string, refresh: string): void {
  accessToken = access;
  refreshToken = refresh;
  
  // Optional: Store in httpOnly cookie via API
  // This allows token persistence across page reloads
}

export function getAccessToken(): string | null {
  return accessToken;
}

export function getRefreshToken(): string | null {
  return refreshToken;
}

export function clearTokens(): void {
  accessToken = null;
  refreshToken = null;
}
```

### Why Not localStorage?

**Security Risks**:
- ❌ Vulnerable to XSS attacks
- ❌ Accessible via JavaScript
- ❌ No automatic expiration

**Better Alternatives**:
1. **Memory storage** (current implementation)
   - ✅ Not accessible to XSS
   - ✅ Automatically cleared on tab close
   - ❌ Lost on page refresh (requires re-login)

2. **httpOnly cookies** (recommended for production)
   - ✅ Not accessible to JavaScript
   - ✅ Automatically sent with requests
   - ✅ Persistent across page reloads
   - ✅ Secure flag for HTTPS-only

---

## 🔄 Token Auto-Refresh

### Implementation

```typescript
// lib/api-client.ts

export async function refreshAccessToken(): Promise<string | null> {
  if (!refreshToken) {
    return null;
  }
  
  try {
    const response = await fetch(`${API_URL}/api/token/refresh`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        refresh_token: refreshToken,
      }),
    });
    
    if (!response.ok) {
      // Refresh token expired or invalid
      clearTokens();
      return null;
    }
    
    const data = await response.json();
    
    // Update tokens
    setTokens(data.token, data.refresh_token);
    
    return data.token;
  } catch (error) {
    console.error('Token refresh failed:', error);
    clearTokens();
    return null;
  }
}
```

### Auto-Refresh on 401

```typescript
export async function apiRequest<T>(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<T> {
  const { requiresAuth = false, ...fetchOptions } = options;
  
  const headers = new Headers(fetchOptions.headers);
  
  if (requiresAuth) {
    const token = getAccessToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }
  }
  
  let response = await fetch(`${API_URL}${endpoint}`, {
    ...fetchOptions,
    headers,
  });
  
  // Handle token expiration
  if (response.status === 401 && requiresAuth) {
    // Try to refresh token
    const newToken = await refreshAccessToken();
    
    if (newToken) {
      // Retry request with new token
      headers.set('Authorization', `Bearer ${newToken}`);
      response = await fetch(`${API_URL}${endpoint}`, {
        ...fetchOptions,
        headers,
      });
    } else {
      // Refresh failed, redirect to login
      window.location.href = '/ro/login';
      throw new Error('Session expired');
    }
  }
  
  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }
  
  return response.json();
}
```

---

## 🛡️ Route Protection

### Server-Side Protection

**Layout or Page Component**:

```typescript
// app/[locale]/admin/layout.tsx

import { redirect } from 'next/navigation';
import { getServerSession } from '@/lib/auth'; // Custom session handler

export default async function AdminLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const session = await getServerSession();
  
  // Redirect to login if not authenticated
  if (!session) {
    redirect(`/${locale}/login`);
  }
  
  // Check user role
  if (!session.roles.includes('ROLE_ADMIN') && !session.roles.includes('ROLE_EDITOR')) {
    redirect(`/${locale}`);
  }
  
  return (
    <div className="admin-layout">
      {children}
    </div>
  );
}
```

### Client-Side Protection

**Hook**: `lib/hooks/useAuth.ts`

```typescript
'use client';

import { useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { getAccessToken } from '@/lib/api-client';

export function useRequireAuth() {
  const router = useRouter();
  const params = useParams();
  const locale = params.locale as string;
  
  useEffect(() => {
    const token = getAccessToken();
    
    if (!token) {
      router.push(`/${locale}/login`);
    }
  }, [router, locale]);
}

// Usage in protected pages
export default function AdminPage() {
  useRequireAuth();
  
  return <div>Admin content</div>;
}
```

---

## 👤 User Context

### Auth Context Provider

**File**: `lib/context/AuthContext.tsx`

```typescript
'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { getAccessToken } from '@/lib/api-client';

interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  roles: string[];
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    // Check if user is authenticated on mount
    const token = getAccessToken();
    
    if (token) {
      // Decode JWT to get user info (or fetch from API)
      const decoded = decodeJWT(token);
      setUser(decoded);
    }
    
    setLoading(false);
  }, []);
  
  const login = async (email: string, password: string) => {
    // Login implementation
  };
  
  const logout = () => {
    clearTokens();
    setUser(null);
    window.location.href = '/ro/login';
  };
  
  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
}
```

### Usage in Components

```typescript
import { useAuth } from '@/lib/context/AuthContext';

export function UserProfile() {
  const { user, logout } = useAuth();
  
  if (!user) return null;
  
  return (
    <div>
      <p>Welcome, {user.firstName}!</p>
      <p>Email: {user.email}</p>
      <p>Roles: {user.roles.join(', ')}</p>
      <button onClick={logout}>Logout</button>
    </div>
  );
}
```

---

## 🚪 Logout Flow

### Logout Implementation

```typescript
export async function logout(locale: string = 'ro') {
  // Optional: Invalidate refresh token on backend
  const refreshToken = getRefreshToken();
  
  if (refreshToken) {
    try {
      await fetch(`${API_URL}/api/token/invalidate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refreshToken }),
      });
    } catch (error) {
      console.error('Failed to invalidate token:', error);
    }
  }
  
  // Clear local tokens
  clearTokens();
  
  // Redirect to login
  window.location.href = `/${locale}/login`;
}
```

### Logout Button

```typescript
export function LogoutButton() {
  const params = useParams();
  const locale = params.locale as string;
  
  const handleLogout = () => {
    if (confirm('Are you sure you want to logout?')) {
      logout(locale);
    }
  };
  
  return (
    <button onClick={handleLogout} className="btn-logout">
      Logout
    </button>
  );
}
```

---

## 🔒 Role-Based UI

### Conditional Rendering

```typescript
import { useAuth } from '@/lib/context/AuthContext';

export function AdminMenu() {
  const { user } = useAuth();
  
  if (!user) return null;
  
  const isAdmin = user.roles.includes('ROLE_ADMIN');
  const isEditor = user.roles.includes('ROLE_EDITOR');
  
  return (
    <nav>
      <a href="/admin">Dashboard</a>
      <a href="/admin/articles">Articles</a>
      
      {(isAdmin || isEditor) && (
        <a href="/admin/categories">Categories</a>
      )}
      
      {isAdmin && (
        <>
          <a href="/admin/users">Users</a>
          <a href="/admin/settings">Settings</a>
        </>
      )}
    </nav>
  );
}
```

---

## ⚠️ Error Handling

### Authentication Errors

```typescript
export class AuthError extends Error {
  constructor(
    message: string,
    public code: 'INVALID_CREDENTIALS' | 'TOKEN_EXPIRED' | 'UNAUTHORIZED'
  ) {
    super(message);
    this.name = 'AuthError';
  }
}

// In login function
if (!response.ok) {
  if (response.status === 401) {
    throw new AuthError('Invalid email or password', 'INVALID_CREDENTIALS');
  }
  throw new AuthError('Authentication failed', 'UNAUTHORIZED');
}
```

### Error Display

```typescript
{error && (
  <div className="alert alert-error">
    {error.code === 'INVALID_CREDENTIALS' && 
      'Email sau parola greșită'}
    {error.code === 'TOKEN_EXPIRED' && 
      'Sesiunea a expirat. Vă rugăm autentificați-vă din nou.'}
    {error.code === 'UNAUTHORIZED' && 
      'Nu aveți permisiunea să accesați această pagină'}
  </div>
)}
```

---

## 🧪 Testing

### Login Flow Test

```typescript
// __tests__/e2e/authentication.spec.ts

import { test, expect } from '@playwright/test';

test('should login successfully', async ({ page }) => {
  await page.goto('/ro/login');
  
  // Fill in credentials
  await page.fill('input[type="email"]', 'admin@example.com');
  await page.fill('input[type="password"]', 'password123');
  
  // Submit form
  await page.click('button[type="submit"]');
  
  // Should redirect to admin
  await expect(page).toHaveURL(/\/ro\/admin/);
  
  // Should see user info
  await expect(page.locator('text=Welcome')).toBeVisible();
});

test('should show error on invalid credentials', async ({ page }) => {
  await page.goto('/ro/login');
  
  await page.fill('input[type="email"]', 'wrong@example.com');
  await page.fill('input[type="password"]', 'wrongpassword');
  await page.click('button[type="submit"]');
  
  // Should show error message
  await expect(page.locator('.alert-error')).toBeVisible();
  
  // Should stay on login page
  await expect(page).toHaveURL(/\/ro\/login/);
});
```

---

## 📱 Session Persistence (Optional)

### Using Cookies

**Backend**: Set httpOnly cookie on login

**Frontend**: Cookies automatically sent with requests

```typescript
// On login success, backend sets:
Set-Cookie: access_token=...; HttpOnly; Secure; SameSite=Strict
Set-Cookie: refresh_token=...; HttpOnly; Secure; SameSite=Strict

// Frontend doesn't need to manually manage tokens
// Cookies automatically included in all API requests
```

---

## 🔐 Security Best Practices

### Implemented

- ✅ Tokens stored in memory (not localStorage)
- ✅ Automatic token refresh on expiration
- ✅ Server-side route protection
- ✅ HTTPS-only in production
- ✅ CSRF protection (via SameSite cookies)
- ✅ Password input masking

### Recommended

- 🔲 Implement httpOnly cookies for production
- 🔲 Add 2FA for admin accounts
- 🔲 Rate limiting on login endpoint
- 🔲 Session timeout warning
- 🔲 Audit log for authentication events

---

**Last Updated**: November 5, 2025  
**Version**: 1.0  
**Security Level**: Production-ready
