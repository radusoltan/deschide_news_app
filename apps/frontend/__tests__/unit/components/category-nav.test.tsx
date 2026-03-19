/**
 * CategoryNav Component Tests
 */

import { render, screen, fireEvent } from '@testing-library/react';
import CategoryNav from '@/components/navigation/CategoryNav';
import { mockCategories } from '@/__tests__/__mocks__/categories';

describe('CategoryNav Component', () => {
  describe('Rendering', () => {
    it('should render all categories in desktop view', () => {
      render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      // Check that all category titles are present
      expect(screen.getByText('Politics')).toBeInTheDocument();
      expect(screen.getByText('Economy')).toBeInTheDocument();
      expect(screen.getByText('Technology')).toBeInTheDocument();
      expect(screen.getByText('Society')).toBeInTheDocument();
    });

    it('should return null when categories array is empty', () => {
      const { container } = render(
        <CategoryNav categories={[]} locale="ro" />
      );

      expect(container.firstChild).toBeNull();
    });

    it('should return null when categories is undefined', () => {
      const { container } = render(
        <CategoryNav categories={undefined as any} locale="ro" />
      );

      expect(container.firstChild).toBeNull();
    });

    it('should apply custom className', () => {
      const { container } = render(
        <CategoryNav
          categories={mockCategories}
          locale="ro"
          className="custom-nav-class"
        />
      );

      const nav = container.querySelector('nav');
      expect(nav).toHaveClass('custom-nav-class');
    });
  });

  describe('Desktop Menu', () => {
    it('should build correct category URLs for Romanian locale', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const links = container.querySelectorAll('.hidden.md\\:block a');
      expect(links[0]).toHaveAttribute('href', '/politics');
      expect(links[1]).toHaveAttribute('href', '/economy');
      expect(links[2]).toHaveAttribute('href', '/technology');
      expect(links[3]).toHaveAttribute('href', '/society');
    });

    it('should build correct category URLs for English locale', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="en" />
      );

      const links = container.querySelectorAll('.hidden.md\\:block a');
      expect(links[0]).toHaveAttribute('href', '/en/politics');
    });

    it('should highlight active category in desktop view', () => {
      const { container } = render(
        <CategoryNav
          categories={mockCategories}
          locale="ro"
          currentCategorySlug="politics"
        />
      );

      const activeLink = container.querySelector('.hidden.md\\:block a.bg-brand-tomato');
      expect(activeLink).toBeInTheDocument();
      expect(activeLink).toHaveTextContent('Politics');
    });

    it('should not highlight any category when none is active', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const activeLink = container.querySelector('.hidden.md\\:block a.bg-brand-tomato');
      expect(activeLink).not.toBeInTheDocument();
    });
  });

  describe('Mobile Menu', () => {
    it('should show dropdown toggle button', () => {
      render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const button = screen.getByRole('button', { name: /toggle category menu/i });
      expect(button).toBeInTheDocument();
    });

    it('should show "Categorii" text when no category is active', () => {
      render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      expect(screen.getByText('Categorii')).toBeInTheDocument();
    });

    it('should show active category title in toggle button', () => {
      render(
        <CategoryNav
          categories={mockCategories}
          locale="ro"
          currentCategorySlug="politics"
        />
      );

      const button = screen.getByRole('button', { name: /toggle category menu/i });
      expect(button).toHaveTextContent('Politics');
    });

    it('should toggle dropdown when button is clicked', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const button = screen.getByRole('button', { name: /toggle category menu/i });

      // Initially closed
      expect(button).toHaveAttribute('aria-expanded', 'false');
      expect(container.querySelector('.md\\:hidden .mt-2')).not.toBeInTheDocument();

      // Click to open
      fireEvent.click(button);
      expect(button).toHaveAttribute('aria-expanded', 'true');
      expect(container.querySelector('.md\\:hidden .mt-2')).toBeInTheDocument();

      // Click to close
      fireEvent.click(button);
      expect(button).toHaveAttribute('aria-expanded', 'false');
      expect(container.querySelector('.md\\:hidden .mt-2')).not.toBeInTheDocument();
    });

    it('should close dropdown when a category is clicked', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const button = screen.getByRole('button', { name: /toggle category menu/i });

      // Open dropdown
      fireEvent.click(button);
      expect(container.querySelector('.md\\:hidden .mt-2')).toBeInTheDocument();

      // Click on a category link
      const categoryLinks = container.querySelectorAll('.md\\:hidden .mt-2 a');
      fireEvent.click(categoryLinks[0]);

      // Dropdown should close
      expect(container.querySelector('.md\\:hidden .mt-2')).not.toBeInTheDocument();
    });

    it('should highlight active category in mobile dropdown', () => {
      const { container } = render(
        <CategoryNav
          categories={mockCategories}
          locale="ro"
          currentCategorySlug="economy"
        />
      );

      const button = screen.getByRole('button', { name: /toggle category menu/i });
      fireEvent.click(button);

      const activeLink = container.querySelector('.md\\:hidden .bg-brand-tomato-50.text-brand-tomato-500');
      expect(activeLink).toBeInTheDocument();
      expect(activeLink).toHaveTextContent('Economy');
    });

    it('should rotate chevron icon when dropdown is open', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const button = screen.getByRole('button', { name: /toggle category menu/i });
      const icon = button.querySelector('svg');

      // Initially not rotated
      expect(icon).not.toHaveClass('rotate-180');

      // Open dropdown
      fireEvent.click(button);
      expect(icon).toHaveClass('rotate-180');

      // Close dropdown
      fireEvent.click(button);
      expect(icon).not.toHaveClass('rotate-180');
    });
  });

  describe('Accessibility', () => {
    it('should have proper ARIA labels', () => {
      render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const nav = screen.getByRole('navigation', { name: /category navigation/i });
      expect(nav).toBeInTheDocument();

      const button = screen.getByRole('button', { name: /toggle category menu/i });
      expect(button).toBeInTheDocument();
    });

    it('should have proper aria-expanded attribute', () => {
      render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const button = screen.getByRole('button', { name: /toggle category menu/i });

      // Initially false
      expect(button).toHaveAttribute('aria-expanded', 'false');

      // After click, true
      fireEvent.click(button);
      expect(button).toHaveAttribute('aria-expanded', 'true');
    });

    it('should use semantic nav element', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const nav = container.querySelector('nav');
      expect(nav).toBeInTheDocument();
    });

    it('should use semantic list elements for desktop menu', () => {
      const { container } = render(
        <CategoryNav categories={mockCategories} locale="ro" />
      );

      const ul = container.querySelector('.hidden.md\\:block ul');
      expect(ul).toBeInTheDocument();

      const listItems = container.querySelectorAll('.hidden.md\\:block li');
      expect(listItems.length).toBe(mockCategories.length);
    });
  });

  describe('URL Generation', () => {
    it('should generate correct URLs for different locales', () => {
      const locales = ['ro', 'en', 'ru'] as const;

      locales.forEach((locale) => {
        const { container } = render(
          <CategoryNav categories={mockCategories} locale={locale} />
        );

        const firstLink = container.querySelector('.hidden.md\\:block a');
        const expectedHref = locale === 'ro' ? '/politics' : `/${locale}/politics`;
        expect(firstLink).toHaveAttribute('href', expectedHref);
      });
    });
  });
});
