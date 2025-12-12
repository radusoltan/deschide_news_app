# Diagnostic Scripts for Automated Testing Agent

These diagnostic scripts are designed to capture debugging information that the automated testing agent cannot access directly (Console, Network tabs).

## Available Scripts

### 1. Console Capture (`console-capture.diag.ts`)
Captures all console messages (errors, warnings, logs) during page navigation.

```bash
npx playwright test __tests__/diagnostics/console-capture.diag.ts --headed
```

**Output:**
- `test-results/diagnostics/console-capture-*.json` - Full console log
- `test-results/diagnostics/console-summary-*.txt` - Human-readable summary

### 2. Network Capture (`network-capture.diag.ts`)
Captures all network requests and API calls with status codes.

```bash
npx playwright test __tests__/diagnostics/network-capture.diag.ts --headed
```

**Output:**
- `test-results/diagnostics/network-capture-*.json` - Full network log
- `test-results/diagnostics/network-summary-*.txt` - Human-readable summary

### 3. Full Diagnostic (`full-diagnostic.diag.ts`)
Comprehensive diagnostic combining console, network, performance, and visual checks.

```bash
npx playwright test __tests__/diagnostics/full-diagnostic.diag.ts --headed
```

**Output:**
- `test-results/diagnostics/full-diagnostic-*.json` - Complete diagnostic data
- `test-results/diagnostics/full-diagnostic-*.md` - Markdown report
- `test-results/diagnostics/diag-*.png` - Screenshots of each page

### 4. API Health Check (`api-health-check.diag.ts`)
Direct API endpoint testing without browser interaction.

```bash
npx playwright test __tests__/diagnostics/api-health-check.diag.ts
```

**Output:**
- `test-results/diagnostics/api-health-*.json` - Full API health data
- `test-results/diagnostics/api-health-*.md` - Markdown report

## Quick Commands

```bash
# Run all diagnostics
pnpm diag:all

# Run specific diagnostic
pnpm diag:console
pnpm diag:network
pnpm diag:full
pnpm diag:api
```

## Usage in Testing Workflow

1. **Before Manual Testing:**
   ```bash
   pnpm diag:api    # Verify API is healthy
   ```

2. **When Test Fails:**
   ```bash
   pnpm diag:full   # Get complete diagnostic
   ```

3. **For Console Issues:**
   ```bash
   pnpm diag:console
   ```

4. **For Network/API Issues:**
   ```bash
   pnpm diag:network
   ```

## Environment Variables

- `BASE_URL` - Frontend URL (default: `http://localhost:3005`)
- `API_URL` - Backend API URL (default: `http://127.0.0.1:8081`)

Example:
```bash
BASE_URL=http://localhost:3005 API_URL=http://127.0.0.1:8081 pnpm diag:full
```

## Report Location

All reports are saved to: `test-results/diagnostics/`

## Integration with Testing Agent

The automated testing agent should run these diagnostics:

1. **At session start:** Run `api-health-check` to verify backend
2. **On any test failure:** Run `full-diagnostic` for the failing page
3. **Attach reports** to the test failure documentation

## HTTP Status Code Reference

| Code | Meaning | Action |
|------|---------|--------|
| 200 | OK | No action needed |
| 201 | Created | Resource created successfully |
| 204 | No Content | Success (no body) |
| 400 | Bad Request | Check request payload |
| 401 | Unauthorized | Check authentication |
| 403 | Forbidden | Check permissions |
| 404 | Not Found | Check resource exists |
| 422 | Unprocessable | Check validation errors |
| 500 | Server Error | Check backend logs |
| 502 | Bad Gateway | Check backend is running |
| 503 | Unavailable | Backend overloaded |
