/**
 * Loading Skeleton Component Tests
 */

import { render, screen } from '@testing-library/react';
import ArticleLoading from '@/app/[locale]/(public)/[categorySlug]/[articleSlug]/loading';

describe('ArticleLoading Component', () => {
  describe('Rendering', () => {
    it('should render loading skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Check for main container
      const main = container.querySelector('main#content');
      expect(main).toBeInTheDocument();
    });

    it('should have animate-pulse classes for skeleton elements', () => {
      const { container } = render(<ArticleLoading />);

      // Check for multiple animate-pulse elements
      const animatedElements = container.querySelectorAll('.animate-pulse');
      expect(animatedElements.length).toBeGreaterThan(0);
    });
  });

  describe('Layout Structure', () => {
    it('should have main content area skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Check for left column (article content)
      const leftColumn = container.querySelector('.lg\\:w-2\\/3');
      expect(leftColumn).toBeInTheDocument();
    });

    it('should have sidebar skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Check for right column (sidebar)
      const rightColumn = container.querySelector('.lg\\:w-1\\/3');
      expect(rightColumn).toBeInTheDocument();
    });
  });

  describe('Article Content Skeleton Elements', () => {
    it('should render title skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Title skeleton should have 2 lines
      const titleLines = container.querySelectorAll('.h-10.bg-gray-300');
      expect(titleLines.length).toBeGreaterThanOrEqual(2);
    });

    it('should render featured image skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Featured image skeleton
      const imageSkeletons = container.querySelectorAll('.h-96.bg-gray-300');
      expect(imageSkeletons.length).toBeGreaterThan(0);
    });

    it('should render lead paragraph skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Lead paragraph skeleton (h-6 elements)
      const leadLines = container.querySelectorAll('.h-6.bg-gray-300');
      expect(leadLines.length).toBeGreaterThanOrEqual(3);
    });

    it('should render content skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Content skeleton (h-4 elements)
      const contentLines = container.querySelectorAll('.h-4.bg-gray-300');
      expect(contentLines.length).toBeGreaterThan(5);
    });

    it('should render author bio skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Author avatar skeleton (rounded-full)
      const avatar = container.querySelector('.rounded-full.bg-gray-300');
      expect(avatar).toBeInTheDocument();
    });
  });

  describe('Sidebar Skeleton Elements', () => {
    it('should render sidebar widget skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Sidebar should have bg-surface element
      const sidebar = container.querySelector('.bg-surface');
      expect(sidebar).toBeInTheDocument();
    });

    it('should render multiple item skeletons in sidebar', () => {
      render(<ArticleLoading />);

      // Should render 3 placeholder items based on map [1, 2, 3]
      // Can't use screen.getByKey, so we'll check the structure exists
      const { container } = render(<ArticleLoading />);
      const sidebarSkeletons = container.querySelectorAll('.flex.space-x-4');
      expect(sidebarSkeletons.length).toBeGreaterThan(0);
    });

    it('should render advertisement skeleton', () => {
      const { container } = render(<ArticleLoading />);

      // Advertisement skeleton (h-64 element)
      const adSkeleton = container.querySelector('.h-64.bg-gray-200');
      expect(adSkeleton).toBeInTheDocument();
    });
  });

  describe('Responsive Layout', () => {
    it('should have responsive classes for mobile/desktop', () => {
      const { container } = render(<ArticleLoading />);

      // Check for mobile-first and lg breakpoint classes
      const responsiveElement = container.querySelector('.w-full.lg\\:w-2\\/3');
      expect(responsiveElement).toBeInTheDocument();
    });

    it('should have correct order classes for mobile/desktop', () => {
      const { container } = render(<ArticleLoading />);

      // Sidebar should be order-first on mobile, order-last on desktop
      const sidebar = container.querySelector('.order-first.lg\\:order-last');
      expect(sidebar).toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should use semantic main element', () => {
      const { container } = render(<ArticleLoading />);

      const main = container.querySelector('main');
      expect(main).toBeInTheDocument();
    });

    it('should have content id for skip links', () => {
      const { container } = render(<ArticleLoading />);

      const main = container.querySelector('main#content');
      expect(main).toBeInTheDocument();
      expect(main).toHaveAttribute('id', 'content');
    });
  });

  describe('Visual Consistency', () => {
    it('should use consistent gray color scheme', () => {
      const { container } = render(<ArticleLoading />);

      // Check for bg-gray-300 (skeleton color)
      const skeletons = container.querySelectorAll('.bg-gray-300');
      expect(skeletons.length).toBeGreaterThan(0);

      // Check for bg-surface-sunken (background color)
      const background = container.querySelector('.bg-surface-sunken');
      expect(background).toBeInTheDocument();
    });

    it('should have rounded corners on skeleton elements', () => {
      const { container } = render(<ArticleLoading />);

      // Check for rounded classes
      const roundedElements = container.querySelectorAll('.rounded, .rounded-full');
      expect(roundedElements.length).toBeGreaterThan(0);
    });
  });

  describe('Container Structure', () => {
    it('should have proper container classes', () => {
      const { container } = render(<ArticleLoading />);

      // Check for xl:container
      const containerElement = container.querySelector('.xl\\:container');
      expect(containerElement).toBeInTheDocument();
    });

    it('should have proper padding classes', () => {
      const { container } = render(<ArticleLoading />);

      // Check for padding classes
      const paddedElement = container.querySelector('.px-3.sm\\:px-4.xl\\:px-2');
      expect(paddedElement).toBeInTheDocument();
    });
  });
});
