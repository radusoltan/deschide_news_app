import { Heart } from 'lucide-react';
import type { PostEngagementData } from '@/lib/types/livetext';

interface PostEngagementTableProps {
  data: PostEngagementData[];
}

export function PostEngagementTable({ data }: PostEngagementTableProps) {
  if (!data || data.length === 0) {
    return (
      <div className="text-center text-secondary dark:text-gray-400 py-8">
        No engagement data available
      </div>
    );
  }

  const formatDate = (dateString: string) => {
    try {
      const date = new Date(dateString);
      return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch {
      return dateString;
    }
  };

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-gray-200 dark:border-gray-700">
            <th className="text-left py-3 px-2 font-medium text-gray-600 dark:text-gray-400">
              Post
            </th>
            <th className="text-center py-3 px-2 font-medium text-gray-600 dark:text-gray-400">
              Reactions
            </th>
            <th className="text-right py-3 px-2 font-medium text-gray-600 dark:text-gray-400">
              Published
            </th>
          </tr>
        </thead>
        <tbody>
          {data.map((post) => (
            <tr
              key={post.postId}
              className="border-b border-gray-100 dark:border-gray-800 hover:bg-surface-sunken dark:hover:bg-gray-700/50 transition-colors"
            >
              <td className="py-3 px-2">
                <div className="text-primary dark:text-gray-100 line-clamp-2">
                  {post.content}
                </div>
                <div className="text-xs text-secondary dark:text-secondary mt-1">
                  Post #{post.postId}
                </div>
              </td>
              <td className="py-3 px-2 text-center">
                <div className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-pink-100 dark:bg-pink-900/20 text-pink-700 dark:text-pink-400">
                  <Heart className="h-3 w-3" />
                  <span className="font-medium">{post.reactionsCount}</span>
                </div>
              </td>
              <td className="py-3 px-2 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">
                {formatDate(post.publishedAt)}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
