# Admin Area Comprehensive Test Scenario

## Scope
This scenario covers the Backend Administration Panel accessible via the Frontend application (`/[locale]/admin`).

**URL**: `http://localhost:3005/ro/admin`
**Credentials**: `admin` / `password`

## 1. Authentication & Access

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| AD-01 | **Login Success** | 1. Go to `/admin`.<br>2. Enter correct credentials. | Redirects to Admin Dashboard. Session is active. |
| AD-02 | **Login Failure** | Enter invalid password. | Error message "Invalid credentials". Remains on login page. |
| AD-03 | **Logout** | Click User Profile -> Logout. | Redirects to Login page. Cannot access `/admin` without logging in again. |
| AD-04 | **Role Access (Editor)**| Login as `editor`. Try to access "Users" or "System Settings" (if restricted). | Menu item should be hidden or access denied. |

## 2. Article Management

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| AD-05 | **Create Article** | 1. Click "Articles" -> "New".<br>2. Fill Title, Lead, Content.<br>3. Select Category.<br>4. Click "Save Draft". | Article is saved. Redirects to Edit page/List. Status is "Draft". |
| AD-06 | **Publish Article** | 1. Open Draft article.<br>2. Change status to "Published".<br>3. Set "Published At" to now.<br>4. Save. | Article status is "Published". Article appears on Frontend Homepage. |
| AD-07 | **Media Upload** | 1. In Article Editor, click "Add Image".<br>2. Upload new image.<br>3. Select it. | Image is attached to article. Thumbnail appears in editor. |
| AD-08 | **Translation** | 1. Open Article.<br>2. Switch Language tab (e.g., EN).<br>3. Enter translated Title/Content.<br>4. Save. | Translation is saved. Can switch back to RO tab and see original content. |

## 3. Live Text Management

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| AD-09 | **Create Live Text** | 1. Go to "Live Texts".<br>2. Create New.<br>3. Fill Title, Start Time.<br>4. Save. | Event is created. Status "Draft". |
| AD-10 | **Go Live** | Change status to "LIVE". | Status updates. Frontend shows "Live" badge. |
| AD-11 | **Post Update** | 1. Open Live Text "Posts" management.<br>2. Write update.<br>3. Click "Publish". | Post appears in the list. (Verify on Frontend FE-15). |
| AD-12 | **Key Point** | Create post, check "Key Point" (or "Important"). | Post is highlighted in the list. |

## 4. General Management

| ID | Test Case | Steps | Expected Result |
|----|-----------|-------|-----------------|
| AD-13 | **Category Management** | Create a new Category "Tech Test". | appears in the list. (May require cache clear to see on frontend). |
| AD-14 | **Image Library** | Go to "Media/Images". Upload an image directly. | Image appears in the gallery grid. |
| AD-15 | **Dashboard Stats** | Check Dashboard. | Counters (Total Articles, Views) match reality/database. |

## 5. Troubleshooting & Debugging

If any of the above tests fail, particularly the administration actions:

| Check | Action | What to look for |
|-------|--------|------------------|
| **Browser Console** | Open DevTools (F12) -> Console. | Errors when saving forms (422 validation, 500 server error). |
| **Network Tab** | Open DevTools (F12) -> Network. | Check the response of `POST` requests. Review the "Preview" tab for detailed error messages from API Platform. |
| **Authentication** | Check Application -> Cookies | Ensure JWT token is present. If 401 Unauthorized, check if token expired. |
| **Backend Logs** | terminal: `symfony server:log` | Permissions errors (Access Denied), Database constraint violations. |
