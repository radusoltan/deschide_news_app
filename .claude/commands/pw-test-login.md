# Playwright Manual Testing: Admin Login

## Scenario: Test Admin Panel Authentication

Use Playwright MCP tools to perform exploratory testing of the login functionality.

### Test Environment
- **URL**: http://localhost:3005/ro/login
- **Backend API**: http://127.0.0.1:8081

### Test Steps

#### 1. Navigate to Login Page
```
Use mcp__playwright__browser_navigate to go to http://localhost:3005/ro/login
```

#### 2. Verify Login Page Elements
After navigation, use `mcp__playwright__browser_snapshot` to capture the page state and verify:
- [ ] Logo "Deschide News" is visible
- [ ] "Sign in to your account" heading is displayed
- [ ] Username input field exists (id="username", placeholder="admin")
- [ ] Password input field exists (id="password", type="password")
- [ ] "Remember me" checkbox exists
- [ ] "Forgot password?" link exists
- [ ] "Sign in" button exists
- [ ] "Sign up" link exists

#### 3. Test Scenarios

**Scenario A: Empty Form Submission**
1. Click "Sign in" button without entering credentials
2. Verify validation messages appear
3. Document any error messages

**Scenario B: Invalid Credentials**
1. Enter username: `invalid_user`
2. Enter password: `wrong_password`
3. Click "Sign in"
4. Verify error message: "Invalid credentials" or similar
5. Take screenshot of error state

**Scenario C: Valid Login (if test credentials available)**
1. Enter valid username
2. Enter valid password
3. Click "Sign in"
4. Verify redirect to `/ro/admin` dashboard
5. Verify admin sidebar/navbar appears

#### 4. Document Results
Create a markdown report with:
- Screenshots of each state
- Any issues found
- Accessibility observations
- UI/UX recommendations

### Playwright MCP Tools to Use
- `mcp__playwright__browser_navigate` - Navigate to URLs
- `mcp__playwright__browser_snapshot` - Capture page accessibility tree
- `mcp__playwright__browser_type` - Enter text in fields
- `mcp__playwright__browser_click` - Click buttons/links
- `mcp__playwright__browser_take_screenshot` - Capture visual evidence
- `mcp__playwright__browser_console_messages` - Check for JS errors

### Expected Test Credentials
Ask user for test credentials or check if there are default admin credentials in the backend fixtures.

---

**START TESTING NOW**: Navigate to the login page and begin the exploratory testing process.
