/**
 * Topic count chip for the admin articles table. Three stops:
 *  - 0 topics → grey "No topics" (signals work-to-do)
 *  - 1–2 topics → blue count
 *  - 3 or more → indigo "3+"
 */
export function TopicCountBadge({ count }: { count: number }) {
  if (count === 0) {
    return (
      <span
        data-testid="topic-count-badge-empty"
        className="px-2 py-0.5 inline-flex items-center text-xs font-medium rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
        title="No topics assigned"
      >
        No topics
      </span>
    );
  }

  if (count >= 3) {
    return (
      <span
        data-testid="topic-count-badge-many"
        className="px-2 py-0.5 inline-flex items-center text-xs font-medium rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
        title={`${count} topics assigned`}
      >
        3+
      </span>
    );
  }

  return (
    <span
      data-testid="topic-count-badge-few"
      className="px-2 py-0.5 inline-flex items-center text-xs font-medium rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
      title={`${count} topic${count > 1 ? 's' : ''} assigned`}
    >
      {count}
    </span>
  );
}
