/**
 * ArticleError Component Tests
 */

import { render, screen, fireEvent } from '@testing-library/react';
import ArticleError from '@/components/errors/article-error';

describe('ArticleError Component', () => {
  describe('Rendering', () => {
    it('should render error component with default message', () => {
      render(<ArticleError locale="ro" />);

      expect(screen.getByText('Eroare la încărcarea articolului')).toBeInTheDocument();
    });

    it('should render error message from Error object', () => {
      const error = new Error('Network timeout');
      render(<ArticleError error={error} locale="en" />);

      expect(screen.getByText('Error Loading Article')).toBeInTheDocument();
    });

    it('should render error message from string', () => {
      render(<ArticleError error="Custom error message" locale="en" />);

      expect(screen.getByText('Error Loading Article')).toBeInTheDocument();
    });
  });

  describe('Translations', () => {
    it('should display Romanian translations', () => {
      render(<ArticleError locale="ro" />);

      expect(screen.getByText('Eroare la încărcarea articolului')).toBeInTheDocument();
      expect(screen.getByText('Ne cerem scuze, dar nu am putut încărca articolul solicitat.')).toBeInTheDocument();
      expect(screen.getByText('Înapoi la prima pagină')).toBeInTheDocument();
    });

    it('should display English translations', () => {
      render(<ArticleError locale="en" />);

      expect(screen.getByText('Error Loading Article')).toBeInTheDocument();
      expect(screen.getByText('We apologize, but we could not load the requested article.')).toBeInTheDocument();
      expect(screen.getByText('Back to Home')).toBeInTheDocument();
    });

    it('should display Russian translations', () => {
      render(<ArticleError locale="ru" />);

      expect(screen.getByText('Ошибка загрузки статьи')).toBeInTheDocument();
      expect(screen.getByText('Извините, мы не смогли загрузить запрошенную статью.')).toBeInTheDocument();
      expect(screen.getByText('Вернуться на главную')).toBeInTheDocument();
    });
  });

  describe('Retry Functionality', () => {
    it('should render retry button when onRetry is provided', () => {
      const onRetry = jest.fn();
      render(<ArticleError locale="en" onRetry={onRetry} />);

      const retryButton = screen.getByText('Try Again');
      expect(retryButton).toBeInTheDocument();
    });

    it('should not render retry button when onRetry is not provided', () => {
      render(<ArticleError locale="en" />);

      const retryButton = screen.queryByText('Try Again');
      expect(retryButton).not.toBeInTheDocument();
    });

    it('should call onRetry when retry button is clicked', () => {
      const onRetry = jest.fn();
      render(<ArticleError locale="en" onRetry={onRetry} />);

      const retryButton = screen.getByText('Try Again');
      fireEvent.click(retryButton);

      expect(onRetry).toHaveBeenCalledTimes(1);
    });

    it('should call onRetry multiple times if clicked multiple times', () => {
      const onRetry = jest.fn();
      render(<ArticleError locale="en" onRetry={onRetry} />);

      const retryButton = screen.getByText('Try Again');
      fireEvent.click(retryButton);
      fireEvent.click(retryButton);
      fireEvent.click(retryButton);

      expect(onRetry).toHaveBeenCalledTimes(3);
    });
  });

  describe('Home Link', () => {
    it('should render home link with correct Romanian URL', () => {
      render(<ArticleError locale="ro" />);

      const homeLink = screen.getByText('Înapoi la prima pagină');
      expect(homeLink).toHaveAttribute('href', '/ro');
    });

    it('should render home link with correct English URL', () => {
      render(<ArticleError locale="en" />);

      const homeLink = screen.getByText('Back to Home');
      expect(homeLink).toHaveAttribute('href', '/en');
    });

    it('should render home link with correct Russian URL', () => {
      render(<ArticleError locale="ru" />);

      const homeLink = screen.getByText('Вернуться на главную');
      expect(homeLink).toHaveAttribute('href', '/ru');
    });
  });

  describe('Error Icon', () => {
    it('should render error icon', () => {
      const { container } = render(<ArticleError locale="en" />);

      const icon = container.querySelector('svg');
      expect(icon).toBeInTheDocument();
      expect(icon).toHaveClass('text-red-600');
    });

    it('should have proper icon container styling', () => {
      const { container } = render(<ArticleError locale="en" />);

      const iconContainer = container.querySelector('.bg-red-100.rounded-full');
      expect(iconContainer).toBeInTheDocument();
    });
  });

  describe('Layout and Styling', () => {
    it('should have proper container styling', () => {
      const { container } = render(<ArticleError locale="en" />);

      const mainContainer = container.querySelector('.min-h-\\[60vh\\]');
      expect(mainContainer).toBeInTheDocument();
    });

    it('should center content', () => {
      const { container } = render(<ArticleError locale="en" />);

      const centerContainer = container.querySelector('.flex.items-center.justify-center');
      expect(centerContainer).toBeInTheDocument();
    });

    it('should have max-width constraint', () => {
      const { container } = render(<ArticleError locale="en" />);

      const contentContainer = container.querySelector('.max-w-md');
      expect(contentContainer).toBeInTheDocument();
    });

    it('should be text-centered', () => {
      const { container } = render(<ArticleError locale="en" />);

      const textCenterContainer = container.querySelector('.text-center');
      expect(textCenterContainer).toBeInTheDocument();
    });
  });

  describe('Button Styling', () => {
    it('should style retry button correctly', () => {
      const onRetry = jest.fn();
      render(<ArticleError locale="en" onRetry={onRetry} />);

      const retryButton = screen.getByText('Try Again');
      expect(retryButton).toHaveClass('bg-red-600');
      expect(retryButton).toHaveClass('text-white');
    });

    it('should style home link correctly', () => {
      render(<ArticleError locale="en" />);

      const homeLink = screen.getByText('Back to Home');
      expect(homeLink).toHaveClass('bg-gray-200');
      expect(homeLink).toHaveClass('text-primary');
    });
  });

  describe('Error Message Display', () => {
    it('should extract message from Error object', () => {
      // Mock NODE_ENV to development to show error details
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      const error = new Error('Custom error message');
      render(<ArticleError error={error} locale="en" />);

      expect(screen.getByText('Custom error message')).toBeInTheDocument();

      // Restore original NODE_ENV
      process.env.NODE_ENV = originalEnv;
    });

    it('should handle string error messages', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      render(<ArticleError error="String error" locale="en" />);

      expect(screen.getByText('String error')).toBeInTheDocument();

      process.env.NODE_ENV = originalEnv;
    });

    it('should use default message when no error provided', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      render(<ArticleError locale="en" />);

      expect(screen.getByText('Failed to load article')).toBeInTheDocument();

      process.env.NODE_ENV = originalEnv;
    });
  });

  describe('Responsive Design', () => {
    it('should have responsive button layout', () => {
      const onRetry = jest.fn();
      const { container } = render(<ArticleError locale="en" onRetry={onRetry} />);

      const buttonContainer = container.querySelector('.flex.flex-col.sm\\:flex-row');
      expect(buttonContainer).toBeInTheDocument();
    });

    it('should have responsive padding', () => {
      const { container } = render(<ArticleError locale="en" />);

      const mainContainer = container.querySelector('.px-4');
      expect(mainContainer).toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should have semantic heading', () => {
      render(<ArticleError locale="en" />);

      const heading = screen.getByRole('heading', { level: 1 });
      expect(heading).toBeInTheDocument();
      expect(heading).toHaveTextContent('Error Loading Article');
    });

    it('should have accessible button when retry is available', () => {
      const onRetry = jest.fn();
      render(<ArticleError locale="en" onRetry={onRetry} />);

      const retryButton = screen.getByRole('button', { name: /try again/i });
      expect(retryButton).toBeInTheDocument();
    });

    it('should have accessible link', () => {
      render(<ArticleError locale="en" />);

      const homeLink = screen.getByRole('link', { name: /back to home/i });
      expect(homeLink).toBeInTheDocument();
    });
  });

  describe('Edge Cases', () => {
    it('should handle Error object with empty message', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      const error = new Error('');
      render(<ArticleError error={error} locale="en" />);

      // Should still render the component
      expect(screen.getByText('Error Loading Article')).toBeInTheDocument();

      process.env.NODE_ENV = originalEnv;
    });

    it('should handle very long error messages', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      const longMessage = 'A'.repeat(500);
      const error = new Error(longMessage);
      const { container } = render(<ArticleError error={error} locale="en" />);

      // Check that error details container has break-all class for long text
      const errorDetails = container.querySelector('.break-all');
      expect(errorDetails).toBeInTheDocument();

      process.env.NODE_ENV = originalEnv;
    });
  });
});
