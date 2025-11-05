/**
 * Breadcrumb Component Tests
 */

import { render, screen } from '@testing-library/react';
import Breadcrumb, { buildArticleBreadcrumbs } from '@/components/navigation/Breadcrumb';
import { mockArticle } from '@/__tests__/__mocks__/articles';

describe('Breadcrumb Component', () => {
  describe('Rendering', () => {
    it('should render breadcrumb items', () => {
      const items = [
        { label: 'Home', href: '/ro' },
        { label: 'Politics', href: '/ro/politics' },
        { label: 'Article Title' },
      ];

      render(<Breadcrumb items={items} locale="ro" />);

      expect(screen.getByText('Home')).toBeInTheDocument();
      expect(screen.getByText('Politics')).toBeInTheDocument();
      expect(screen.getByText('Article Title')).toBeInTheDocument();
    });

    it('should return null when items array is empty', () => {
      const { container } = render(<Breadcrumb items={[]} locale="ro" />);

      expect(container.firstChild).toBeNull();
    });

    it('should return null when items is undefined', () => {
      const { container } = render(<Breadcrumb items={undefined as any} locale="ro" />);

      expect(container.firstChild).toBeNull();
    });

    it('should apply custom className', () => {
      const items = [{ label: 'Home', href: '/ro' }];
      const { container } = render(
        <Breadcrumb items={items} locale="ro" className="custom-breadcrumb-class" />
      );

      const nav = container.querySelector('nav');
      expect(nav).toHaveClass('custom-breadcrumb-class');
    });
  });

  describe('Links and Navigation', () => {
    it('should render links for items with href', () => {
      const items = [
        { label: 'Home', href: '/ro' },
        { label: 'Politics', href: '/ro/politics' },
        { label: 'Article Title' },
      ];

      const { container } = render(<Breadcrumb items={items} locale="ro" />);

      const links = container.querySelectorAll('a');
      expect(links.length).toBe(2);
      expect(links[0]).toHaveAttribute('href', '/ro');
      expect(links[1]).toHaveAttribute('href', '/ro/politics');
    });

    it('should render last item as plain text', () => {
      const items = [
        { label: 'Home', href: '/ro' },
        { label: 'Article Title' },
      ];

      render(<Breadcrumb items={items} locale="ro" />);

      const lastItem = screen.getByText('Article Title');
      expect(lastItem.tagName).toBe('SPAN');
    });

    it('should not render link for item without href', () => {
      const items = [
        { label: 'Home' },
        { label: 'Article Title' },
      ];

      const { container } = render(<Breadcrumb items={items} locale="ro" />);

      const links = container.querySelectorAll('a');
      expect(links.length).toBe(0);
    });
  });

  describe('Styling', () => {
    it('should apply special styling to last item', () => {
      const items = [
        { label: 'Home', href: '/ro' },
        { label: 'Article Title' },
      ];

      render(<Breadcrumb items={items} locale="ro" />);

      const lastItem = screen.getByText('Article Title');
      expect(lastItem).toHaveClass('text-gray-900');
      expect(lastItem).toHaveClass('font-medium');
    });

    it('should render separators between items', () => {
      const items = [
        { label: 'Home', href: '/ro' },
        { label: 'Politics', href: '/ro/politics' },
        { label: 'Article Title' },
      ];

      const { container } = render(<Breadcrumb items={items} locale="ro" />);

      // Should have 2 separators (between 3 items)
      const separators = container.querySelectorAll('svg');
      expect(separators.length).toBe(2);
    });

    it('should not render separator after last item', () => {
      const items = [
        { label: 'Home', href: '/ro' },
        { label: 'Article Title' },
      ];

      const { container } = render(<Breadcrumb items={items} locale="ro" />);

      const lastLi = container.querySelectorAll('li')[1];
      const separator = lastLi.querySelector('svg');
      expect(separator).not.toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA label', () => {
      const items = [{ label: 'Home', href: '/ro' }];

      render(<Breadcrumb items={items} locale="ro" />);

      const nav = screen.getByRole('navigation', { name: /breadcrumb/i });
      expect(nav).toBeInTheDocument();
    });

    it('should use semantic nav and list elements', () => {
      const items = [
        { label: 'Home', href: '/ro' },
        { label: 'Article Title' },
      ];

      const { container } = render(<Breadcrumb items={items} locale="ro" />);

      const nav = container.querySelector('nav');
      const ol = container.querySelector('ol');
      const listItems = container.querySelectorAll('li');

      expect(nav).toBeInTheDocument();
      expect(ol).toBeInTheDocument();
      expect(listItems.length).toBe(2);
    });
  });

  describe('Single Item', () => {
    it('should render single item without separator', () => {
      const items = [{ label: 'Home', href: '/ro' }];

      const { container } = render(<Breadcrumb items={items} locale="ro" />);

      const separators = container.querySelectorAll('svg');
      expect(separators.length).toBe(0);
    });

    it('should render single item without link if no href', () => {
      const items = [{ label: 'Home' }];

      const { container } = render(<Breadcrumb items={items} locale="ro" />);

      const link = container.querySelector('a');
      expect(link).not.toBeInTheDocument();

      expect(screen.getByText('Home')).toBeInTheDocument();
    });
  });
});

describe('buildArticleBreadcrumbs Helper', () => {
  describe('Romanian Locale', () => {
    it('should build breadcrumbs for Romanian locale', () => {
      const breadcrumbs = buildArticleBreadcrumbs(mockArticle, 'ro');

      expect(breadcrumbs.length).toBe(3);
      expect(breadcrumbs[0]).toEqual({ label: 'Acasă', href: '/ro' });
      expect(breadcrumbs[1]).toEqual({
        label: 'Politics',
        href: '/ro/politics',
      });
      expect(breadcrumbs[2]).toEqual({ label: 'Test Article Title' });
    });
  });

  describe('English Locale', () => {
    it('should build breadcrumbs for English locale', () => {
      const breadcrumbs = buildArticleBreadcrumbs(mockArticle, 'en');

      expect(breadcrumbs.length).toBe(3);
      expect(breadcrumbs[0]).toEqual({ label: 'Home', href: '/en' });
      expect(breadcrumbs[1]).toEqual({
        label: 'Politics',
        href: '/en/politics',
      });
      expect(breadcrumbs[2]).toEqual({ label: 'Test Article Title' });
    });
  });

  describe('Russian Locale', () => {
    it('should build breadcrumbs for Russian locale', () => {
      const breadcrumbs = buildArticleBreadcrumbs(mockArticle, 'ru');

      expect(breadcrumbs.length).toBe(3);
      expect(breadcrumbs[0]).toEqual({ label: 'Главная', href: '/ru' });
      expect(breadcrumbs[1]).toEqual({
        label: 'Politics',
        href: '/ru/politics',
      });
      expect(breadcrumbs[2]).toEqual({ label: 'Test Article Title' });
    });
  });

  describe('Article Without Category', () => {
    it('should build breadcrumbs without category', () => {
      const articleWithoutCategory = {
        ...mockArticle,
        category: undefined,
      };

      const breadcrumbs = buildArticleBreadcrumbs(articleWithoutCategory, 'ro');

      expect(breadcrumbs.length).toBe(2);
      expect(breadcrumbs[0]).toEqual({ label: 'Acasă', href: '/ro' });
      expect(breadcrumbs[1]).toEqual({ label: 'Test Article Title' });
    });
  });

  describe('Article with Null Category', () => {
    it('should handle null category gracefully', () => {
      const articleWithNullCategory = {
        ...mockArticle,
        category: null,
      };

      const breadcrumbs = buildArticleBreadcrumbs(articleWithNullCategory, 'ro');

      expect(breadcrumbs.length).toBe(2);
      expect(breadcrumbs[0]).toEqual({ label: 'Acasă', href: '/ro' });
      expect(breadcrumbs[1]).toEqual({ label: 'Test Article Title' });
    });
  });

  describe('Integration with Breadcrumb Component', () => {
    it('should work correctly with Breadcrumb component', () => {
      const breadcrumbs = buildArticleBreadcrumbs(mockArticle, 'ro');

      render(<Breadcrumb items={breadcrumbs} locale="ro" />);

      expect(screen.getByText('Acasă')).toBeInTheDocument();
      expect(screen.getByText('Politics')).toBeInTheDocument();
      expect(screen.getByText('Test Article Title')).toBeInTheDocument();
    });
  });
});
