/**
 * Auth Module - Barrel Exports
 * Centralized exports for authentication, authorization, and security utilities
 *
 * @see NEXTJS_ALIGNMENT_PLAN.md - Phase 1 Security
 */

// Session management
export {
  encrypt,
  decrypt,
  createSession,
  getSession,
  updateSession,
  deleteSession,
  isSessionValid,
  getAccessToken,
  getRefreshToken,
  hasRole,
  hasAnyRole,
  type SessionPayload,
} from './session';

// Authorization utilities
export {
  authorize,
  isAuthorized,
  hasRole as authorizeHasRole,
  hasAnyRole as authorizeHasAnyRole,
  hasAllRoles,
  requireAuth,
  requireRoles,
  ROLES,
  CommonSchemas,
  type AuthorizeResult,
  type AuthorizeSuccess,
  type AuthorizeFailure,
  type Role,
} from './authorize';

// CSRF protection
export {
  generateCsrfToken,
  getCsrfToken,
  getOrCreateCsrfToken,
  deleteCsrfToken,
  validateCsrfToken,
  validateCsrfFromHeaders,
  withCsrfProtection,
  fetchCsrfToken,
  validateOrigin,
  validateRequest,
  type CsrfValidationResult,
} from './csrf';

// Rate limiting
export {
  checkRateLimit,
  checkRateLimitByIp,
  getClientIdentifier,
  resetRateLimit,
  withRateLimit,
  createRateLimiter,
  getRateLimitHeaders,
  RATE_LIMITS,
  type RateLimitConfig,
  type RateLimitResult,
} from './rate-limit';

// Server actions
export {
  refreshSessionToken,
  updateSessionTokens,
  validateLoginCredentials,
  clearSession,
  checkSessionStatus,
} from './actions';
