/**
 * Server-side API client functions for Image operations
 * These functions run on the server and use authentication
 */

import 'server-only';
import { getAccessToken } from '@/lib/dal';
import type {
  Image,
  ImageListResponse,
  ImageWithThumbnails,
  ThumbnailProfile,
  ThumbnailProfileListResponse,
  Thumbnail,
  CropCoordinates
} from '@/lib/types/image';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

/**
 * Get a fresh access token using the canonical DAL implementation.
 * This delegates to dal.ts which handles refresh and session cookie updates.
 */
async function getFreshAccessToken(): Promise<string | null> {
  return getAccessToken();
}

/**
 * Fetch paginated list of images
 */
export async function getImages(params?: {
  page?: number;
  itemsPerPage?: number;
  search?: string;
}): Promise<ImageListResponse> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const queryParams = new URLSearchParams();
  if (params?.page) queryParams.append('page', params.page.toString());
  if (params?.itemsPerPage) queryParams.append('itemsPerPage', params.itemsPerPage.toString());
  if (params?.search) queryParams.append('originalFilename', params.search);

  const url = `${API_BASE_URL}/api/images?${queryParams.toString()}`;

  const response = await fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch images: ${response.statusText}`);
  }

  return response.json();
}

/**
 * Fetch a single image by ID
 */
export async function getImage(id: number): Promise<Image> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/images/${id}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch image: ${response.statusText}`);
  }

  return response.json();
}

/**
 * Upload a new image
 * Note: This should be called from an API route, not directly from components
 */
export async function uploadImage(formData: FormData): Promise<Image> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/images`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      // Do NOT set Content-Type - let browser set it with boundary for multipart/form-data
    },
    body: formData,
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'Failed to upload image');
  }

  return response.json();
}

/**
 * Update image metadata (alt, caption, description)
 */
export async function updateImage(id: number, data: {
  alt?: string;
  caption?: string;
  description?: string;
}): Promise<Image> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/images/${id}`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/ld+json',
    },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'Failed to update image');
  }

  return response.json();
}

/**
 * Delete an image
 */
export async function deleteImage(id: number): Promise<void> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/images/${id}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    // Try to get error message from response body
    let errorMessage = 'Failed to delete image';
    try {
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        const error = await response.json();
        errorMessage = error.message || error.detail || error['hydra:description'] || errorMessage;
      } else {
        errorMessage = response.statusText || errorMessage;
      }
    } catch {
      // If parsing fails, use default message
    }
    throw new Error(errorMessage);
  }
}

/**
 * Fetch image with thumbnails (expanded)
 */
export async function getImageWithThumbnails(id: number): Promise<ImageWithThumbnails> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/images/${id}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch image: ${response.statusText}`);
  }

  return response.json();
}

/**
 * Fetch all thumbnail profiles
 */
export async function getThumbnailProfiles(): Promise<ThumbnailProfile[]> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/thumbnail_profiles`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/ld+json',
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch thumbnail profiles: ${response.statusText}`);
  }

  const data = await response.json();

  // Handle both Hydra and plain array responses
  if (Array.isArray(data)) {
    return data;
  }

  if (data['hydra:member'] && Array.isArray(data['hydra:member'])) {
    return data['hydra:member'];
  }

  if (data.member && Array.isArray(data.member)) {
    return data.member;
  }

  console.error('Unexpected thumbnail profiles response format:', data);
  throw new Error('Invalid thumbnail profiles response format');
}

/**
 * Apply custom crop to generate thumbnail
 */
export async function applyCustomCrop(
  imageId: number,
  profile: string,
  format: 'jpg' | 'webp',
  cropData: CropCoordinates
): Promise<Thumbnail> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/images/${imageId}/thumbnails/crop`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/ld+json',
    },
    body: JSON.stringify({
      profile,
      format,
      cropData,
    }),
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.error('[Images API] Crop failed:', {
      status: response.status,
      statusText: response.statusText,
      body: errorText
    });
    let errorMessage = 'Failed to apply crop';
    try {
      const errorJson = JSON.parse(errorText);
      errorMessage = errorJson.message || errorJson['hydra:description'] || errorJson.detail || errorMessage;
    } catch {
      errorMessage = errorText || response.statusText || errorMessage;
    }
    throw new Error(errorMessage);
  }

  return response.json();
}

/**
 * Reset crop to default (regenerate thumbnail without custom crop)
 */
export async function resetCrop(
  imageId: number,
  profile: string,
  format: 'jpg' | 'webp'
): Promise<Thumbnail> {
  const token = await getFreshAccessToken();
  if (!token) {
    throw new Error('Authentication required');
  }

  const response = await fetch(`${API_BASE_URL}/api/images/${imageId}/thumbnails/reset-crop`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/ld+json',
    },
    body: JSON.stringify({
      profile,
      format,
    }),
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'Failed to reset crop');
  }

  return response.json();
}

/**
 * Get public URL for image (from CDN)
 */
export function getImageUrl(image: Pick<Image, 'filename'>): string {
  const CDN_BASE = process.env.NEXT_PUBLIC_CDN_URL ?? '';
  return `${CDN_BASE}/uploads/images/${image.filename}`;
}

/**
 * Get public URL for thumbnail (from CDN)
 */
export function getThumbnailUrl(thumbnail: Pick<Thumbnail, 'path'>): string {
  const CDN_BASE = process.env.NEXT_PUBLIC_CDN_URL ?? '';
  return `${CDN_BASE}/uploads/${thumbnail.path}`;
}

// Alias for consistency
export { getImage as getImageById };
