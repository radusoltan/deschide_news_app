# Image Management Implementation Plan

## Overview
This document provides a comprehensive plan for implementing the image management system in the article editor. The system allows administrators/editors to attach, manage, and reorder images for articles, including selecting a featured image.

## Backend Architecture (Already Implemented)

### Entities

#### 1. Image Entity
**Location**: `/var/www/deschide_news_app/deschide_backend/src/Entity/Image.php`

**API Endpoints**:
- `GET /api/images/{id}` - Get single image
- `GET /api/images` - Get images collection (paginated, 50 per page)
- `POST /api/images` - Upload new image (multipart/form-data)
- `PUT /api/images/{id}` - Update image metadata (alt, caption, description, imageAuthor)
- `DELETE /api/images/{id}` - Delete image

**Fields**:
- `id` (integer)
- `filename` (string, unique)
- `originalFilename` (string)
- `path` (string) - e.g., "images/abc123.jpg"
- `mimeType` (string)
- `size` (integer, bytes)
- `width` (integer, pixels)
- `height` (integer, pixels)
- `alt` (string, nullable, translatable, max 255)
- `caption` (text, nullable, translatable, max 500)
- `description` (text, nullable, translatable, max 1000)
- `imageAuthor` (string, nullable, max 255)
- `createdAt` (datetime)
- `updatedAt` (datetime)

**Computed Properties**:
- `aspectRatio` (float)
- `formattedSize` (string, e.g., "1.5 MB")

**Upload Constraints**:
- Max size: 10MB
- Allowed types: image/jpeg, image/jpg, image/png, image/gif, image/webp

#### 2. ArticleImage Entity (Pivot Table)
**Location**: `/var/www/deschide_news_app/deschide_backend/src/Entity/ArticleImage.php`

**API Endpoints**:
- `GET /api/article_images/{id}` - Get single article-image relationship
- `GET /api/article_images` - Get collection
- `POST /api/article_images` - Attach image to article
- `PUT /api/article_images/{id}` - Update metadata (position, isFeatured)
- `DELETE /api/article_images/{id}` - Detach image from article

**Fields**:
- `id` (integer)
- `article` (IRI, e.g., "/api/articles/123")
- `image` (IRI, e.g., "/api/images/456")
- `position` (integer, default 0) - For ordering images
- `isFeatured` (boolean, default false) - Only one image should be featured per article
- `createdAt` (datetime)

**Relationships**:
- ManyToOne with Article
- ManyToOne with Image
- Unique constraint on (article_id, image_id)
- Cascade delete

#### 3. Article Entity Update
**Location**: `/var/www/deschide_news_app/deschide_backend/src/Entity/Article.php`

**Relationship**:
```php
#[ORM\OneToMany(targetEntity: ArticleImage::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
#[ORM\OrderBy(['position' => 'ASC'])]
private Collection $articleImages;
```

## Frontend Implementation Plan

### Phase 1: Basic Infrastructure (Session 1)

#### 1.1 Install Required Packages

```bash
cd /var/www/deschide_news_app/deschide_frontend
pnpm add react-dropzone @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
```

**Packages**:
- `react-dropzone` - Drag-and-drop file upload
- `@dnd-kit/*` - Drag-and-drop for reordering images

#### 1.2 Create Type Definitions

**File**: `/lib/types/image.ts`

```typescript
export interface Image {
  id: number;
  filename: string;
  originalFilename: string;
  path: string;
  mimeType: string;
  size: number;
  width: number;
  height: number;
  alt?: string;
  caption?: string;
  description?: string;
  imageAuthor?: string;
  aspectRatio: number;
  formattedSize: string;
  createdAt: string;
  updatedAt: string;
}

export interface ArticleImage {
  id: number;
  article: string; // IRI
  image: Image | string; // Can be full object or IRI
  position: number;
  isFeatured: boolean;
  createdAt: string;
}
```

#### 1.3 Create API Client Functions

**File**: `/lib/api/images.ts`

```typescript
'use server';

import { getAccessToken } from '@/lib/auth/session';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

export async function getImages(page = 1, itemsPerPage = 50) {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated');

  const response = await fetch(
    `${API_BASE_URL}/api/images?page=${page}&itemsPerPage=${itemsPerPage}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/ld+json',
      },
      cache: 'no-store',
    }
  );

  if (!response.ok) {
    throw new Error(`Failed to fetch images: ${response.status}`);
  }

  return response.json();
}

export async function uploadImage(formData: FormData) {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated');

  const response = await fetch(`${API_BASE_URL}/api/images`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/ld+json',
    },
    body: formData, // multipart/form-data
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Upload failed: ${response.status}`);
  }

  return response.json();
}

export async function deleteImage(imageId: number) {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated');

  const response = await fetch(`${API_BASE_URL}/api/images/${imageId}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to delete image: ${response.status}`);
  }

  return true;
}
```

**File**: `/lib/api/article-images.ts`

```typescript
'use server';

import { getAccessToken } from '@/lib/auth/session';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

export async function getArticleImages(articleId: number) {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated');

  const response = await fetch(
    `${API_BASE_URL}/api/article_images?article.id=${articleId}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/ld+json',
      },
      cache: 'no-store',
    }
  );

  if (!response.ok) {
    throw new Error(`Failed to fetch article images: ${response.status}`);
  }

  return response.json();
}

export async function attachImageToArticle(
  articleId: number,
  imageId: number,
  position: number = 0,
  isFeatured: boolean = false
) {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated');

  const response = await fetch(`${API_BASE_URL}/api/article_images`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/ld+json',
      'Accept': 'application/ld+json',
    },
    body: JSON.stringify({
      article: `/api/articles/${articleId}`,
      image: `/api/images/${imageId}`,
      position,
      isFeatured,
    }),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to attach image: ${response.status}`);
  }

  return response.json();
}

export async function detachImageFromArticle(articleImageId: number) {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated');

  const response = await fetch(`${API_BASE_URL}/api/article_images/${articleImageId}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to detach image: ${response.status}`);
  }

  return true;
}

export async function updateArticleImage(
  articleImageId: number,
  position?: number,
  isFeatured?: boolean
) {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated');

  const updates: any = {};
  if (position !== undefined) updates.position = position;
  if (isFeatured !== undefined) updates.isFeatured = isFeatured;

  const response = await fetch(`${API_BASE_URL}/api/article_images/${articleImageId}`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/ld+json',
      'Accept': 'application/ld+json',
    },
    body: JSON.stringify(updates),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `Failed to update article image: ${response.status}`);
  }

  return response.json();
}
```

### Phase 2: Image Upload Component (Session 1)

#### 2.1 Create ImageUploader Component

**File**: `/components/admin/images/ImageUploader.tsx`

```typescript
'use client';

import { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import { HiUpload, HiX } from 'react-icons/hi';

interface ImageUploaderProps {
  onUploadComplete: (imageIds: number[]) => void;
  maxFiles?: number;
}

export default function ImageUploader({ onUploadComplete, maxFiles = 10 }: ImageUploaderProps) {
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState<{ [key: string]: number }>({});
  const [errors, setErrors] = useState<string[]>([]);

  const onDrop = useCallback(async (acceptedFiles: File[]) => {
    setUploading(true);
    setErrors([]);
    const uploadedIds: number[] = [];

    for (const file of acceptedFiles) {
      try {
        // Create FormData
        const formData = new FormData();
        formData.append('file', file);

        // Upload via API route
        const response = await fetch('/api/admin/images/upload', {
          method: 'POST',
          body: formData,
        });

        if (!response.ok) {
          const error = await response.json();
          throw new Error(error.message || 'Upload failed');
        }

        const result = await response.json();
        uploadedIds.push(result.id);

        // Update progress
        setProgress(prev => ({ ...prev, [file.name]: 100 }));
      } catch (error) {
        setErrors(prev => [...prev, `${file.name}: ${error instanceof Error ? error.message : 'Upload failed'}`]);
      }
    }

    setUploading(false);
    if (uploadedIds.length > 0) {
      onUploadComplete(uploadedIds);
    }
  }, [onUploadComplete]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.webp']
    },
    maxFiles,
    maxSize: 10 * 1024 * 1024, // 10MB
    multiple: true,
  });

  return (
    <div>
      <div
        {...getRootProps()}
        className={`border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors ${
          isDragActive
            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
            : 'border-gray-300 dark:border-gray-600 hover:border-blue-400'
        }`}
      >
        <input {...getInputProps()} />
        <HiUpload className="mx-auto h-12 w-12 text-gray-400" />
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
          {isDragActive
            ? 'Drop images here...'
            : 'Drag & drop images here, or click to select'}
        </p>
        <p className="mt-1 text-xs text-gray-500 dark:text-gray-500">
          PNG, JPG, GIF, WEBP up to 10MB (max {maxFiles} files)
        </p>
      </div>

      {uploading && (
        <div className="mt-4">
          <p className="text-sm text-gray-600 dark:text-gray-400">Uploading...</p>
        </div>
      )}

      {errors.length > 0 && (
        <div className="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <div className="flex items-start">
            <HiX className="h-5 w-5 text-red-600 dark:text-red-400 mr-2 flex-shrink-0" />
            <div className="flex-1">
              <h4 className="text-sm font-medium text-red-800 dark:text-red-400">Upload Errors:</h4>
              <ul className="mt-1 text-sm text-red-600 dark:text-red-400 list-disc list-inside">
                {errors.map((error, idx) => (
                  <li key={idx}>{error}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
```

#### 2.2 Create API Route for Upload

**File**: `/app/api/admin/images/upload/route.ts`

```typescript
import { NextRequest, NextResponse } from 'next/server';
import { uploadImage } from '@/lib/api/images';

export async function POST(request: NextRequest) {
  try {
    const formData = await request.formData();
    const file = formData.get('file');

    if (!file || !(file instanceof File)) {
      return NextResponse.json(
        { message: 'No file provided' },
        { status: 400 }
      );
    }

    // Upload to backend
    const result = await uploadImage(formData);

    return NextResponse.json(result);
  } catch (error) {
    console.error('Upload error:', error);
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Upload failed' },
      { status: 500 }
    );
  }
}
```

### Phase 3: Attached Images Section (Session 2)

#### 3.1 Create AttachedImagesSection Component

**File**: `/components/admin/images/AttachedImagesSection.tsx`

```typescript
'use client';

import { useState } from 'react';
import Image from 'next/image';
import { HiTrash, HiStar, HiPhotograph } from 'react-icons/hi';
import ImageUploader from './ImageUploader';

interface AttachedImage {
  id: number;
  articleImageId?: number; // ArticleImage pivot ID
  filename: string;
  path: string;
  width: number;
  height: number;
  alt?: string;
  isFeatured: boolean;
  position: number;
}

interface AttachedImagesSectionProps {
  images: AttachedImage[];
  onImagesChange: (images: AttachedImage[]) => void;
  onRemoveImage: (articleImageId: number) => void;
  onSetFeatured: (imageId: number | null) => void;
  onOpenPicker: () => void;
  maxImages?: number;
}

export default function AttachedImagesSection({
  images,
  onImagesChange,
  onRemoveImage,
  onSetFeatured,
  onOpenPicker,
  maxImages = 100,
}: AttachedImagesSectionProps) {
  const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  const featuredImage = images.find(img => img.isFeatured);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-medium text-gray-900 dark:text-white">
          Article Images ({images.length}/{maxImages})
        </h3>
        <button
          type="button"
          onClick={onOpenPicker}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300"
        >
          <HiPhotograph className="inline-block w-5 h-5 mr-2" />
          Select from Library
        </button>
      </div>

      {/* Featured Image */}
      {featuredImage && (
        <div className="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-lg p-4">
          <div className="flex items-center gap-2 mb-3">
            <HiStar className="w-5 h-5 text-yellow-500" />
            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">Featured Image</h4>
          </div>
          <div className="relative w-full aspect-video bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden">
            <Image
              src={`${API_URL}/${featuredImage.path}`}
              alt={featuredImage.alt || ''}
              fill
              className="object-cover"
            />
          </div>
        </div>
      )}

      {/* Upload Section */}
      <ImageUploader
        onUploadComplete={(imageIds) => {
          // Handle auto-attach after upload
          console.log('Uploaded images:', imageIds);
        }}
        maxFiles={maxImages - images.length}
      />

      {/* Images Grid */}
      {images.length > 0 ? (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {images.map((image, index) => (
            <div
              key={image.id}
              className="relative group bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
            >
              {/* Image */}
              <div className="relative w-full aspect-video bg-gray-200 dark:bg-gray-700">
                <Image
                  src={`${API_URL}/${image.path}`}
                  alt={image.alt || ''}
                  fill
                  className="object-cover"
                />
              </div>

              {/* Actions Overlay */}
              <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                {/* Set as Featured */}
                <button
                  type="button"
                  onClick={() => onSetFeatured(image.isFeatured ? null : image.id)}
                  className={`p-2 rounded-lg ${
                    image.isFeatured
                      ? 'bg-yellow-500 text-white'
                      : 'bg-white text-gray-700 hover:bg-gray-100'
                  }`}
                  title={image.isFeatured ? 'Remove as featured' : 'Set as featured'}
                >
                  <HiStar className="w-5 h-5" />
                </button>

                {/* Remove */}
                {image.articleImageId && (
                  <button
                    type="button"
                    onClick={() => onRemoveImage(image.articleImageId!)}
                    className="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                    title="Remove image"
                  >
                    <HiTrash className="w-5 h-5" />
                  </button>
                )}
              </div>

              {/* Position Badge */}
              <div className="absolute top-2 left-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                #{index + 1}
              </div>

              {/* Featured Badge */}
              {image.isFeatured && (
                <div className="absolute top-2 right-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded flex items-center gap-1">
                  <HiStar className="w-3 h-3" />
                  Featured
                </div>
              )}
            </div>
          ))}
        </div>
      ) : (
        <div className="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
          <HiPhotograph className="mx-auto h-12 w-12 text-gray-400" />
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            No images attached to this article
          </p>
          <p className="mt-1 text-xs text-gray-500 dark:text-gray-500">
            Upload new images or select from library
          </p>
        </div>
      )}
    </div>
  );
}
```

### Phase 4: Image Picker Modal (Session 2)

#### 4.1 Create ImagePickerModal Component

**File**: `/components/admin/images/ImagePickerModal.tsx`

```typescript
'use client';

import { useState, useEffect } from 'react';
import { HiX, HiSearch, HiCheck } from 'react-icons/hi';
import Image from 'next/image';

interface ImagePickerModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSelectImages: (imageIds: number[]) => void;
  currentlyAttached: number[]; // Already attached image IDs
  maxSelect?: number;
  mode?: 'single' | 'multiple';
}

export default function ImagePickerModal({
  isOpen,
  onClose,
  onSelectImages,
  currentlyAttached,
  maxSelect = 100,
  mode = 'multiple',
}: ImagePickerModalProps) {
  const [images, setImages] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<number[]>([]);
  const [page, setPage] = useState(1);

  const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8081';

  useEffect(() => {
    if (isOpen) {
      fetchImages();
    }
  }, [isOpen, page]);

  const fetchImages = async () => {
    setLoading(true);
    try {
      const response = await fetch(`/api/admin/images?page=${page}`);
      const data = await response.json();
      setImages(data.member || []);
    } catch (error) {
      console.error('Failed to fetch images:', error);
    } finally {
      setLoading(false);
    }
  };

  const toggleSelect = (imageId: number) => {
    if (mode === 'single') {
      setSelected([imageId]);
    } else {
      setSelected(prev =>
        prev.includes(imageId)
          ? prev.filter(id => id !== imageId)
          : [...prev, imageId].slice(0, maxSelect)
      );
    }
  };

  const handleConfirm = () => {
    onSelectImages(selected);
    setSelected([]);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/50"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative min-h-screen flex items-center justify-center p-4">
        <div className="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
          {/* Header */}
          <div className="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
              Select Images {selected.length > 0 && `(${selected.length} selected)`}
            </h2>
            <button
              onClick={onClose}
              className="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
            >
              <HiX className="w-6 h-6" />
            </button>
          </div>

          {/* Search */}
          <div className="p-6 border-b border-gray-200 dark:border-gray-700">
            <div className="relative">
              <HiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search images..."
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              />
            </div>
          </div>

          {/* Images Grid */}
          <div className="p-6 overflow-y-auto" style={{ maxHeight: 'calc(90vh - 240px)' }}>
            {loading ? (
              <div className="text-center py-12">
                <p className="text-gray-600 dark:text-gray-400">Loading images...</p>
              </div>
            ) : (
              <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                {images
                  .filter(img => !currentlyAttached.includes(img.id))
                  .map((image) => {
                    const isSelected = selected.includes(image.id);
                    return (
                      <div
                        key={image.id}
                        onClick={() => toggleSelect(image.id)}
                        className={`relative group cursor-pointer rounded-lg overflow-hidden border-2 transition-all ${
                          isSelected
                            ? 'border-blue-500 ring-2 ring-blue-500'
                            : 'border-transparent hover:border-gray-300'
                        }`}
                      >
                        {/* Image */}
                        <div className="relative w-full aspect-square bg-gray-200 dark:bg-gray-700">
                          <Image
                            src={`${API_URL}/${image.path}`}
                            alt={image.alt || ''}
                            fill
                            className="object-cover"
                          />
                        </div>

                        {/* Selection Indicator */}
                        {isSelected && (
                          <div className="absolute inset-0 bg-blue-500/20 flex items-center justify-center">
                            <div className="bg-blue-500 text-white rounded-full p-2">
                              <HiCheck className="w-6 h-6" />
                            </div>
                          </div>
                        )}

                        {/* Info */}
                        <div className="absolute bottom-0 left-0 right-0 bg-black/70 text-white text-xs p-2">
                          <p className="truncate">{image.originalFilename}</p>
                          <p className="text-gray-300">{image.formattedSize}</p>
                        </div>
                      </div>
                    );
                  })}
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="flex items-center justify-between p-6 border-t border-gray-200 dark:border-gray-700">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              {mode === 'single'
                ? 'Select one image'
                : `Select up to ${maxSelect} images`}
            </p>
            <div className="flex gap-3">
              <button
                onClick={onClose}
                className="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirm}
                disabled={selected.length === 0}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Select ({selected.length})
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
```

#### 4.2 Create API Route for Fetching Images

**File**: `/app/api/admin/images/route.ts`

```typescript
import { NextRequest, NextResponse } from 'next/server';
import { getImages } from '@/lib/api/images';

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const page = parseInt(searchParams.get('page') || '1');
    const itemsPerPage = parseInt(searchParams.get('itemsPerPage') || '50');

    const result = await getImages(page, itemsPerPage);

    return NextResponse.json(result);
  } catch (error) {
    console.error('Fetch images error:', error);
    return NextResponse.json(
      { message: error instanceof Error ? error.message : 'Failed to fetch images' },
      { status: 500 }
    );
  }
}
```

### Phase 5: Integration with ArticleForm (Session 3)

#### 5.1 Update ArticleForm Component

**File**: `/app/[locale]/admin/articles/components/ArticleForm.tsx`

Add after the "Category & Publishing" section:

```typescript
// Add to imports
const AttachedImagesSection = dynamic(
  () => import('@/components/admin/images/AttachedImagesSection'),
  { ssr: false }
);
const ImagePickerModal = dynamic(
  () => import('@/components/admin/images/ImagePickerModal'),
  { ssr: false }
);

// Add to state
const [attachedImages, setAttachedImages] = useState<any[]>([]);
const [showImagePicker, setShowImagePicker] = useState(false);

// Add to form JSX (before Form Actions)
{/* Article Images Section */}
<div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
  <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-4">
    Article Images
  </h2>
  <AttachedImagesSection
    images={attachedImages}
    onImagesChange={setAttachedImages}
    onRemoveImage={async (articleImageId) => {
      // Handle remove
      setAttachedImages(prev => prev.filter(img => img.articleImageId !== articleImageId));
    }}
    onSetFeatured={async (imageId) => {
      // Handle set featured
      setAttachedImages(prev =>
        prev.map(img => ({
          ...img,
          isFeatured: imageId === null ? false : img.id === imageId,
        }))
      );
    }}
    onOpenPicker={() => setShowImagePicker(true)}
    maxImages={100}
  />
</div>

{/* Image Picker Modal */}
<ImagePickerModal
  isOpen={showImagePicker}
  onClose={() => setShowImagePicker(false)}
  currentlyAttached={attachedImages.map(img => img.id)}
  onSelectImages={async (imageIds) => {
    // Handle attach images
    console.log('Selected images:', imageIds);
  }}
  maxSelect={100 - attachedImages.length}
  mode="multiple"
/>
```

### Phase 6: Drag-and-Drop Reordering (Session 4)

#### 6.1 Update AttachedImagesSection with DnD

Use `@dnd-kit/sortable` to implement drag-and-drop reordering:

```typescript
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

// Implement sortable image item
function SortableImageItem({ image, ...props }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
  } = useSortable({ id: image.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <div ref={setNodeRef} style={style} {...attributes} {...listeners}>
      {/* Image card JSX */}
    </div>
  );
}

// In main component
const sensors = useSensors(
  useSensor(PointerSensor),
  useSensor(KeyboardSensor, {
    coordinateGetter: sortableKeyboardCoordinates,
  })
);

function handleDragEnd(event) {
  const { active, over } = event;

  if (active.id !== over.id) {
    setImages((items) => {
      const oldIndex = items.findIndex(i => i.id === active.id);
      const newIndex = items.findIndex(i => i.id === over.id);
      return arrayMove(items, oldIndex, newIndex);
    });
  }
}
```

## Implementation Checklist

### Session 1: Foundation
- [ ] Install packages: `react-dropzone`, `@dnd-kit/*`
- [ ] Create type definitions (`/lib/types/image.ts`)
- [ ] Create API client functions (`/lib/api/images.ts`, `/lib/api/article-images.ts`)
- [ ] Create ImageUploader component
- [ ] Create upload API route (`/app/api/admin/images/upload/route.ts`)
- [ ] Test image upload functionality

### Session 2: Image Management
- [ ] Create AttachedImagesSection component (basic version)
- [ ] Create ImagePickerModal component
- [ ] Create images fetch API route (`/app/api/admin/images/route.ts`)
- [ ] Test image selection from library

### Session 3: Integration
- [ ] Update ArticleForm with image section
- [ ] Implement attach/detach functionality
- [ ] Implement featured image selection
- [ ] Test full workflow (upload → attach → set featured)

### Session 4: Advanced Features
- [ ] Implement drag-and-drop reordering with @dnd-kit
- [ ] Add position update API calls
- [ ] Add image metadata editing
- [ ] Add thumbnail generation support
- [ ] Polish UI/UX

## Testing Checklist

- [ ] Upload single image
- [ ] Upload multiple images
- [ ] Upload with size/type validation errors
- [ ] Select images from library
- [ ] Attach images to article
- [ ] Detach images from article
- [ ] Set/unset featured image
- [ ] Reorder images (drag-and-drop)
- [ ] Save article with images
- [ ] Edit article with existing images
- [ ] Delete article with images (cascade)

## Notes

1. **Image Storage**: Images are stored using VichUploaderBundle in `/var/www/deschide_news_app/deschide_backend/public/uploads/images/`

2. **Image URL**: Access via `http://127.0.0.1:8081/uploads/images/{filename}` or use the `path` field from API response

3. **Featured Image**: Only ONE image per article should have `isFeatured: true`

4. **Position**: Images are ordered by `position` field (0-based index)

5. **Cascade Delete**: Deleting an article will automatically delete all ArticleImage pivot records but NOT the Image entities themselves (they can be reused)

6. **Performance**: Consider implementing pagination in ImagePickerModal for large image libraries

7. **Thumbnails**: Backend has Thumbnail entity - consider implementing thumbnail generation for better performance

## Reference Implementation

See `/var/www/news_app/frontend/components/admin/articles/` for complete reference implementation with all features.
