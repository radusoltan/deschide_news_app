import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Utility function to merge Tailwind CSS classes
 * Combines clsx for conditional classes with tailwind-merge for proper class merging
 *
 * @example
 * cn('bg-red-500', 'bg-blue-500') // => 'bg-blue-500' (later class wins)
 * cn('p-4', { 'mt-2': true, 'mb-2': false }) // => 'p-4 mt-2'
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
