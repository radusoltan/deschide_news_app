/**
 * Avatar Component
 * Display user avatars with fallback to initials
 */

import React from 'react';
import Image from 'next/image';
import Link from 'next/link';

interface AvatarProps {
  src?: string;
  alt?: string;
  name: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  href?: string;
  className?: string;
}

const sizeStyles = {
  sm: 'w-8 h-8 text-xs',
  md: 'w-12 h-12 text-sm',
  lg: 'w-16 h-16 text-base',
  xl: 'w-20 h-20 text-xl',
};

/**
 * Get initials from name
 */
function getInitials(name: string): string {
  const words = name.trim().split(/\s+/);
  if (words.length === 1) {
    return words[0].substring(0, 2).toUpperCase();
  }
  return (words[0][0] + words[words.length - 1][0]).toUpperCase();
}

function AvatarContent({ src, alt, name, size = 'md', className = '' }: AvatarProps) {
  const [imageError, setImageError] = React.useState(false);

  if (src && !imageError) {
    return (
      <div
        className={`${sizeStyles[size]} rounded-full overflow-hidden bg-gray-200 border border-gray-300 ${className}`}
      >
        <Image
          src={src}
          alt={alt || name}
          width={size === 'sm' ? 32 : size === 'md' ? 48 : size === 'lg' ? 64 : 80}
          height={size === 'sm' ? 32 : size === 'md' ? 48 : size === 'lg' ? 64 : 80}
          className="w-full h-full object-cover"
          onError={() => setImageError(true)}
        />
      </div>
    );
  }

  return (
    <div
      className={`${sizeStyles[size]} rounded-full border border-gray-300 flex items-center justify-center bg-gray-200 font-bold text-gray-600 ${className}`}
    >
      {getInitials(name)}
    </div>
  );
}

export default function Avatar(props: AvatarProps) {
  if (props.href) {
    return (
      <Link href={props.href}>
        <AvatarContent {...props} />
      </Link>
    );
  }

  return <AvatarContent {...props} />;
}
