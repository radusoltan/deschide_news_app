/**
 * TopicCountBadge / ArticlesTableClient — Sprint 51c (T51c.6)
 *
 * Covers:
 *  - Grey "No topics" for 0
 *  - Blue count for 1–2
 *  - Indigo "3+" for 3 or more
 */

import { render, screen } from '@testing-library/react';

import { TopicCountBadge } from '@/app/[locale]/admin/articles/components/TopicCountBadge';

describe('TopicCountBadge', () => {
  it('renders the empty state for zero topics', () => {
    render(<TopicCountBadge count={0} />);

    const badge = screen.getByTestId('topic-count-badge-empty');
    expect(badge).toHaveTextContent('No topics');
    expect(badge).toHaveClass('bg-gray-100');
  });

  it('renders the blue chip with the exact count for 1 topic', () => {
    render(<TopicCountBadge count={1} />);

    const badge = screen.getByTestId('topic-count-badge-few');
    expect(badge).toHaveTextContent('1');
    expect(badge).toHaveClass('bg-blue-100');
  });

  it('renders the blue chip with the exact count for 2 topics', () => {
    render(<TopicCountBadge count={2} />);

    const badge = screen.getByTestId('topic-count-badge-few');
    expect(badge).toHaveTextContent('2');
    expect(badge).toHaveClass('bg-blue-100');
  });

  it('renders the indigo 3+ chip for 3 topics', () => {
    render(<TopicCountBadge count={3} />);

    const badge = screen.getByTestId('topic-count-badge-many');
    expect(badge).toHaveTextContent('3+');
    expect(badge).toHaveClass('bg-indigo-100');
  });

  it('renders the indigo 3+ chip for larger counts', () => {
    render(<TopicCountBadge count={12} />);

    const badge = screen.getByTestId('topic-count-badge-many');
    expect(badge).toHaveTextContent('3+');
  });
});
