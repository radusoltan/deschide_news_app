import { NextResponse } from 'next/server';
import { getThumbnailProfiles } from '@/lib/api/images';

/**
 * GET /api/thumbnail-profiles
 * Fetch all thumbnail profiles
 */
export async function GET() {
  try {
    const profiles = await getThumbnailProfiles();

    // Validate profiles data
    if (!profiles || !Array.isArray(profiles)) {
      console.error('Invalid profiles data:', profiles);
      return NextResponse.json(
        { error: 'Invalid thumbnail profiles data received from backend' },
        { status: 500 }
      );
    }

    // Convert to plain JSON-serializable objects
    const serializedProfiles = profiles.map(profile => ({
      '@id': profile['@id'],
      '@type': profile['@type'],
      id: profile.id,
      name: profile.name,
      displayName: profile.displayName,
      description: profile.description,
      width: profile.width,
      height: profile.height,
      aspectRatio: profile.aspectRatio,
      calculatedAspectRatio: profile.calculatedAspectRatio,
      dimensionsLabel: profile.dimensionsLabel,
      mode: profile.mode,
      quality: profile.quality,
      category: profile.category,
      isActive: profile.isActive,
      createdAt: typeof profile.createdAt === 'string' ? profile.createdAt : profile.createdAt?.toString(),
      updatedAt: typeof profile.updatedAt === 'string' ? profile.updatedAt : profile.updatedAt?.toString(),
      locale: profile.locale,
      translatableLocale: profile.translatableLocale,
    }));

    return NextResponse.json(serializedProfiles);
  } catch (error) {
    console.error('Failed to fetch thumbnail profiles:', error);
    return NextResponse.json(
      { error: error instanceof Error ? error.message : 'Failed to fetch thumbnail profiles' },
      { status: 500 }
    );
  }
}
