'use client';

import { useRef, useState, useMemo, useCallback, useEffect } from 'react';
import Cropper, { ReactCropperElement } from 'react-cropper';
import '@/app/styles/cropper.css';
import type { ThumbnailProfile, CropCoordinates, ImageWithThumbnails } from '@/lib/types/image';
import { FiCheckCircle, FiX, FiAlertCircle } from 'react-icons/fi';

interface CropModalProps {
  image: ImageWithThumbnails;
  profiles: ThumbnailProfile[];
  onCropComplete: (profile: string, format: string, cropData: CropCoordinates) => void | Promise<void>;
  onClose: () => void;
}

interface ToastMessage {
  type: 'success' | 'error';
  message: string;
}

export function CropModal({ image, profiles, onCropComplete, onClose }: CropModalProps) {
  const cropperRef = useRef<ReactCropperElement>(null);
  const [selectedProfile, setSelectedProfile] = useState<ThumbnailProfile>(profiles[0]);
  const [selectedFormat, setSelectedFormat] = useState<'jpg' | 'webp'>('jpg');
  const [isProcessing, setIsProcessing] = useState(false);
  const [toast, setToast] = useState<ToastMessage | null>(null);

  // Calculate aspect ratio from profile dimensions
  const profileAspectRatio = useMemo(() => {
    return selectedProfile.width / selectedProfile.height;
  }, [selectedProfile]);

  // Update aspect ratio and crop coordinates when profile changes
  useEffect(() => {
    const cropper = cropperRef.current?.cropper;
    if (!cropper) return;

    // Set aspect ratio for the new profile
    cropper.setAspectRatio(profileAspectRatio);

    // Find existing thumbnail for this profile
    const existingThumbnail = image.thumbnails?.find(
      (t) => {
        // Check if profile is an object or IRI string
        const profileName = typeof t.profile === 'string'
          ? t.profile.split('/').pop() // Extract name from IRI
          : t.profile.name;
        return profileName === selectedProfile.name;
      }
    );

    // If thumbnail has custom crop data, apply those coordinates
    if (existingThumbnail && existingThumbnail.cropData) {
      const crop = existingThumbnail.cropData;

      // Set crop box data (absolute pixel coordinates)
      cropper.setData({
        x: crop.x,
        y: crop.y,
        width: crop.width,
        height: crop.height,
        rotate: 0,
        scaleX: 1,
        scaleY: 1,
      });
    }
  }, [selectedProfile, selectedFormat, image.thumbnails, profileAspectRatio]);

  const handleCrop = useCallback(async () => {
    const cropper = cropperRef.current?.cropper;
    if (!cropper) return;

    try {
      setIsProcessing(true);
      setToast(null); // Clear previous toast

      // Get crop data in absolute pixel coordinates (relative to natural image size)
      const cropData = cropper.getData(true); // true = rounded values

      // Send to backend (absolute pixel coordinates)
      const coordinates: CropCoordinates = {
        x: Math.round(cropData.x),
        y: Math.round(cropData.y),
        width: Math.round(cropData.width),
        height: Math.round(cropData.height),
      };

      await onCropComplete(selectedProfile.name, selectedFormat, coordinates);

      // Show success toast
      setToast({
        type: 'success',
        message: `Thumbnail generated successfully! Profile: ${selectedProfile.displayName || selectedProfile.name}, Format: ${selectedFormat.toUpperCase()}`,
      });

      // Auto-hide toast after 5 seconds
      setTimeout(() => setToast(null), 5000);
    } catch (error) {
      console.error('Crop failed:', error);

      // Show error toast
      setToast({
        type: 'error',
        message: error instanceof Error ? error.message : 'Failed to apply crop. Please try again.',
      });

      // Auto-hide error toast after 7 seconds
      setTimeout(() => setToast(null), 7000);
    } finally {
      setIsProcessing(false);
    }
  }, [selectedProfile, selectedFormat, onCropComplete]);

  const handleReset = useCallback(() => {
    const cropper = cropperRef.current?.cropper;
    if (cropper) {
      // Reset crop box to center position
      cropper.reset();

      // Re-fit image to fill container
      const containerData = cropper.getContainerData();
      const imageData = cropper.getImageData();
      const scaleX = containerData.width / imageData.naturalWidth;
      const scaleY = containerData.height / imageData.naturalHeight;
      const scale = Math.max(scaleX, scaleY);

      cropper.zoomTo(scale);

      // Re-center image
      const canvasData = cropper.getCanvasData();
      cropper.setCanvasData({
        left: (containerData.width - canvasData.width) / 2,
        top: (containerData.height - canvasData.height) / 2,
      });
    }
  }, []);

  // Proxy image through Next.js to avoid CORS issues with canvas-based cropper
  const imagePath = image.path || (image.filename ? `images/${image.filename}` : '');
  const imageUrl = imagePath ? `/api/images/proxy?path=${encodeURIComponent(imagePath)}` : '';

  return (
    <div className="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-gray-900 w-full h-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col rounded-lg">
        {/* Header */}
        <div className="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
          <h2 className="text-xl font-bold dark:text-white">Crop Thumbnail</h2>
          <button
            onClick={onClose}
            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-2xl leading-none"
            disabled={isProcessing}
          >
            ✕
          </button>
        </div>

        {/* Content wrapper - scrollable */}
        <div className="flex-1 p-4 overflow-y-auto min-h-0">
          {/* Profile Selection */}
          <div className="mb-4">
            <label className="block text-sm font-medium mb-2 dark:text-white">Thumbnail Profile</label>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
              {profiles.map((profile) => (
                <button
                  key={profile.id}
                  onClick={() => setSelectedProfile(profile)}
                  disabled={isProcessing}
                  className={`p-3 border rounded-lg transition-all text-left ${
                    selectedProfile.id === profile.id
                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-900 dark:border-blue-400'
                      : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
                  } ${isProcessing ? 'opacity-50 cursor-not-allowed' : ''}`}
                >
                  <div className="font-medium text-sm dark:text-white">{profile.displayName}</div>
                  <div className="text-xs text-gray-600 dark:text-gray-300 font-mono">
                    {profile.name}
                  </div>
                  <div className="text-xs text-gray-500 dark:text-gray-400">
                    {profile.dimensionsLabel}
                  </div>
                  {profile.description && (
                    <div className="text-xs text-gray-400 dark:text-gray-500 mt-1">{profile.description}</div>
                  )}
                </button>
              ))}
            </div>
          </div>

          {/* Format Selection */}
          <div className="mb-4">
            <label className="block text-sm font-medium mb-2 dark:text-white">Format</label>
            <div className="flex gap-2">
              {(['jpg', 'webp'] as const).map((format) => (
                <button
                  key={format}
                  onClick={() => setSelectedFormat(format)}
                  disabled={isProcessing}
                  className={`px-6 py-3 rounded-lg border transition-all ${
                    selectedFormat === format
                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-900 dark:border-blue-400 text-blue-700 dark:text-blue-200'
                      : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 dark:text-gray-300'
                  } ${isProcessing ? 'opacity-50 cursor-not-allowed' : ''}`}
                >
                  {format.toUpperCase()}
                </button>
              ))}
            </div>
          </div>

          {/* Cropper */}
          <div className="space-y-3 mb-4">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-medium dark:text-white">
                {selectedProfile.displayName} - {selectedProfile.dimensionsLabel}
              </h3>
              <div className="text-sm text-gray-600 dark:text-gray-400">
                Original: {image.width} × {image.height} px
                {image.formattedSize && ` • ${image.formattedSize}`}
              </div>
            </div>

            <div
              className="border-2 border-blue-500 dark:border-blue-400 rounded-lg overflow-hidden shadow-2xl bg-gray-900"
              style={{
                height: '50vh',
                minHeight: '350px',
                maxHeight: '500px',
              }}
            >
              <Cropper
                ref={cropperRef}
                src={imageUrl}
                style={{ height: '100%', width: '100%' }}
                // Fixed image fills container, movable crop box
                aspectRatio={profileAspectRatio}
                dragMode="crop" // move crop box, not image
                cropBoxMovable={true} // crop box can move
                cropBoxResizable={true} // crop box can resize
                zoomable={false} // disable zoom (image is fixed)
                zoomOnWheel={false} // disable zoom on wheel
                scalable={false} // disable scaling
                autoCrop={true} // auto-enable crop
                autoCropArea={0.5} // crop box starts at 50% size
                center={true} // crop box centered initially
                viewMode={1} // restrict crop box to canvas area
                guides={true} // show crop guides
                background={false} // no background pattern needed
                responsive={true} // responsive on resize
                checkOrientation={true} // correct EXIF orientation
                minCropBoxWidth={100} // minimum crop box size
                minCropBoxHeight={100}
                modal={true} // show modal overlay (darkens area outside crop)
                highlight={true} // highlight crop area
                toggleDragModeOnDblclick={false} // prevent accidental mode changes
                ready={() => {
                  // Fit image to fill entire container
                  const cropper = cropperRef.current?.cropper;
                  if (cropper) {
                    const containerData = cropper.getContainerData();
                    const imageData = cropper.getImageData();

                    // Calculate scale to fill container (cover behavior)
                    const scaleX = containerData.width / imageData.naturalWidth;
                    const scaleY = containerData.height / imageData.naturalHeight;
                    const scale = Math.max(scaleX, scaleY);

                    // Apply scale to fill container
                    cropper.zoomTo(scale);

                    // Center the image
                    const canvasData = cropper.getCanvasData();
                    cropper.setCanvasData({
                      left: (containerData.width - canvasData.width) / 2,
                      top: (containerData.height - canvasData.height) / 2,
                    });
                  }
                }}
              />
            </div>

            {/* Crop Controls */}
            <div className="flex justify-center gap-3">
              <button
                onClick={handleReset}
                disabled={isProcessing}
                className="px-6 py-3 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 dark:text-white font-medium"
              >
                Reset Crop Position
              </button>
            </div>
            <div className="text-center text-sm text-gray-600 dark:text-gray-400 mt-3">
              Drag the crop box to select area • Resize from corners/edges
            </div>
          </div>
        </div>

        {/* Actions - Fixed footer */}
        <div className="border-t border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
          <div className="flex justify-end gap-3">
            <button
              onClick={onClose}
              disabled={isProcessing}
              className="px-8 py-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 dark:text-white font-medium transition-colors"
            >
              Close
            </button>
            <button
              onClick={handleCrop}
              disabled={isProcessing}
              className="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2 font-medium shadow-lg transition-all"
            >
              {isProcessing && (
                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
              )}
              {isProcessing ? 'Processing...' : 'Apply Crop & Generate Thumbnail'}
            </button>
          </div>
        </div>

        {/* Toast Notification */}
        {toast && (
          <div className="fixed bottom-4 right-4 z-[60] animate-in slide-in-from-bottom-5 duration-300">
            <div
              className={`flex items-center gap-3 p-4 rounded-lg shadow-lg min-w-[320px] max-w-md ${
                toast.type === 'success'
                  ? 'bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700'
                  : 'bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700'
              }`}
            >
              {toast.type === 'success' ? (
                <FiCheckCircle className="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" />
              ) : (
                <FiAlertCircle className="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0" />
              )}
              <p
                className={`text-sm font-medium flex-1 ${
                  toast.type === 'success'
                    ? 'text-green-800 dark:text-green-200'
                    : 'text-red-800 dark:text-red-200'
                }`}
              >
                {toast.message}
              </p>
              <button
                onClick={() => setToast(null)}
                className={`flex-shrink-0 ${
                  toast.type === 'success'
                    ? 'text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200'
                    : 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200'
                }`}
              >
                <FiX className="w-5 h-5" />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
