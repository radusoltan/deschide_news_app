/**
 * AuthorAvatar — Reusable circular author avatar
 *
 * Displays the author's photo if available, otherwise falls back to
 * colored initials. The background color is deterministic per author name
 * so the same author always gets the same color.
 */

import Image from 'next/image';
import { cn } from '@/lib/utils/cn';

interface AuthorAvatarProps {
  name: string;
  avatarUrl?: string | null;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

const sizeMap = {
  sm: { container: 'w-8 h-8', text: 'text-[10px]', px: 32 },
  md: { container: 'w-12 h-12', text: 'text-xs', px: 48 },
  lg: { container: 'w-16 h-16', text: 'text-sm', px: 64 },
} as const;

/** Deterministic color from name — same author always gets same color */
function getColorIndex(name: string): number {
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return Math.abs(hash) % 6;
}

const bgColors = [
  'bg-blue-600',
  'bg-emerald-600',
  'bg-amber-600',
  'bg-rose-600',
  'bg-violet-600',
  'bg-teal-600',
];

export function AuthorAvatar({ name, avatarUrl, size = 'md', className }: AuthorAvatarProps) {
  const s = sizeMap[size];
  const initials = name
    .split(' ')
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
  const colorIdx = getColorIndex(name);

  return (
    <div
      className={cn(
        'rounded-full overflow-hidden flex-shrink-0 border-2 border-[var(--color-border)] dark:border-[var(--color-border-dark)]',
        s.container,
        className,
      )}
    >
      {avatarUrl ? (
        <Image
          src={avatarUrl}
          alt={name}
          width={s.px}
          height={s.px}
          className="object-cover w-full h-full"
        />
      ) : (
        <div
          className={cn(
            'w-full h-full flex items-center justify-center text-white font-bold font-sans',
            s.text,
            bgColors[colorIdx],
          )}
        >
          {initials}
        </div>
      )}
    </div>
  );
}
