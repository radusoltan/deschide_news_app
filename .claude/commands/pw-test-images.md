# Playwright Manual Testing: Image Upload & Management

## Scenario: Test Image Management in Admin Panel

Use Playwright MCP tools to perform exploratory testing of image upload and management.

### Prerequisites
- Must be logged in to admin panel
- If not logged in, run `/pw-test-login` first

### Test Environment
- **Images Gallery**: http://localhost:3005/ro/admin/images
- **Upload Page**: http://localhost:3005/ro/admin/images/upload
- **Edit Image**: http://localhost:3005/ro/admin/images/{id}/edit
- **CDN URL**: http://127.0.0.1:8082

### Test Steps

#### 1. Navigate to Images Gallery
```
Use mcp__playwright__browser_navigate to go to http://localhost:3005/ro/admin/images
```

#### 2. Verify Images Gallery Page
After navigation, use `mcp__playwright__browser_snapshot` to verify:
- [ ] Page title/heading "Images" or "Imagini"
- [ ] Grid/Gallery view of uploaded images
- [ ] Thumbnail previews load correctly
- [ ] Pagination controls
- [ ] "Upload" button
- [ ] Search/filter by filename or date
- [ ] Action buttons per image (View, Edit, Delete)

#### 3. Test Scenarios

**Scenario A: View Images Gallery**
1. Verify thumbnails load from CDN (http://127.0.0.1:8082)
2. Check image metadata displayed (filename, dimensions, size)
3. Test pagination through images
4. Verify image preview/lightbox on click

**Scenario B: Upload New Image**
1. Click "Upload" button or navigate to /images/upload
2. Verify upload form with:
   - File input (drag & drop or click to select)
   - Alt text field
   - Caption field (optional)
   - Supported formats indicator (jpg, png, webp, gif)
   - Max file size indicator
3. Test upload:
   - Use `mcp__playwright__browser_file_upload` with a test image
   - Or verify the file input element exists
4. Verify:
   - Upload progress indicator
   - Success message on completion
   - Thumbnail generation (10 profiles)
   - Redirect to gallery or image detail

**Scenario C: Edit Image Metadata**
1. From gallery, click Edit on an image
2. Verify edit form loads with:
   - Current image preview
   - Alt text (editable)
   - Caption (editable)
   - Filename (read-only or editable)
   - Dimensions (read-only)
   - Upload date (read-only)
   - Thumbnails generated (list of profiles)
3. Modify alt text: "Updated alt text - Playwright test"
4. Click Save
5. Verify success message
6. Verify changes persisted

**Scenario D: Image Crop/Edit (if CropModal exists)**
1. Check if crop functionality is available
2. Test crop tool:
   - Select aspect ratio
   - Adjust crop area
   - Save cropped version

**Scenario E: Delete Image**
1. From gallery, click Delete on an image
2. Verify confirmation dialog:
   - Warning about articles using this image
   - List of affected articles (if any)
3. Confirm deletion
4. Verify image removed from gallery
5. Verify thumbnails also deleted

**Scenario F: Image in Article Context**
1. Navigate to article edit: /ro/admin/articles/{id}/edit
2. Find "Featured Image" or "Images" section
3. Test ImagePickerModal:
   - Open image picker
   - Browse available images
   - Select an image
   - Verify selection saved
4. Test ImageUploadModal:
   - Upload new image directly from article form
   - Verify image attached to article

#### 4. Thumbnail Profiles Verification
1. After upload, verify thumbnails generated:
   - hero_big (1920x1080)
   - hero_small (800x600)
   - article_main (1600x900)
   - card_large (800x600)
   - card_medium (600x400)
   - card_small (400x300)
   - list_item (300x200)
   - mobile_hero (800x600)
   - gallery (1920x600)
2. Check thumbnail URLs at CDN

#### 5. Error Handling Tests
1. Upload invalid file type (.exe, .pdf) → Rejection message
2. Upload oversized file (>10MB) → Size limit message
3. Upload corrupted image → Error handling
4. Upload with network interruption → Retry mechanism

#### 6. Document Results
Create a markdown report with:
- Screenshots of gallery and upload process
- Thumbnail generation verification
- CDN integration status
- Any upload issues encountered
- Performance observations (large file handling)

### Playwright MCP Tools to Use
- `mcp__playwright__browser_navigate` - Navigate between pages
- `mcp__playwright__browser_snapshot` - Capture page state
- `mcp__playwright__browser_file_upload` - Upload test files
- `mcp__playwright__browser_click` - Click buttons
- `mcp__playwright__browser_type` - Fill metadata fields
- `mcp__playwright__browser_take_screenshot` - Visual evidence

### Test Image Path
If you need a test image, create one or use an existing image from:
- `/var/www/deschide_news_app/apps/backend/public/uploads/images/`

---

**START TESTING NOW**: Navigate to the images admin page and begin testing.
