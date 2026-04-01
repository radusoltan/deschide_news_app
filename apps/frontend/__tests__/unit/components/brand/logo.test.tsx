/**
 * Logo Component Tests
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import Logo, { LogoWithSpacing, LogoIcon, LogoWithTagline } from '@/components/brand/Logo';

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, className, 'aria-label': ariaLabel, ...props }: any) => (
    <a href={href} className={className} aria-label={ariaLabel} {...props}>
      {children}
    </a>
  ),
}));

jest.mock('@/lib/utils/cn', () => ({
  cn: (...classes: any[]) => classes.filter(Boolean).join(' '),
}));

describe('Logo', () => {
  it('renders DESCHIDE text', () => {
    render(<Logo />);
    expect(screen.getByText('DESCHIDE')).toBeInTheDocument();
  });

  it('renders SVG icon', () => {
    const { container } = render(<Logo />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('renders without link by default', () => {
    const { container } = render(<Logo />);
    expect(container.querySelector('a')).not.toBeInTheDocument();
  });

  it('wraps in link when href provided', () => {
    render(<Logo href="/" />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/');
    expect(link).toHaveAttribute('aria-label', 'Deschide - Home');
  });

  it('applies blue variant by default', () => {
    const { container } = render(<Logo />);
    const wrapper = container.firstElementChild;
    expect(wrapper?.className).toContain('text-brand-oxford');
  });

  it('applies red variant', () => {
    const { container } = render(<Logo variant="red" />);
    const wrapper = container.firstElementChild;
    expect(wrapper?.className).toContain('text-brand-tomato');
  });

  it('applies white variant', () => {
    const { container } = render(<Logo variant="white" />);
    const wrapper = container.firstElementChild;
    expect(wrapper?.className).toContain('text-white');
  });

  it('applies sm size', () => {
    const { container } = render(<Logo size="sm" />);
    const textSpan = container.querySelector('span');
    expect(textSpan?.className).toContain('text-xl');
  });

  it('applies md size by default', () => {
    const { container } = render(<Logo />);
    const textSpan = container.querySelector('span');
    expect(textSpan?.className).toContain('text-4xl');
  });

  it('applies lg size', () => {
    const { container } = render(<Logo size="lg" />);
    const textSpan = container.querySelector('span');
    expect(textSpan?.className).toContain('text-6xl');
  });

  it('applies xl size', () => {
    const { container } = render(<Logo size="xl" />);
    const textSpan = container.querySelector('span');
    expect(textSpan?.className).toContain('text-7xl');
  });

  it('applies custom className', () => {
    const { container } = render(<Logo className="my-logo-class" />);
    const wrapper = container.firstElementChild;
    expect(wrapper?.className).toContain('my-logo-class');
  });

  it('applies spacing when withSpacing is true', () => {
    const { container } = render(<Logo withSpacing />);
    const wrapper = container.firstElementChild;
    expect(wrapper?.className).toContain('p-2');
  });
});

describe('LogoWithSpacing', () => {
  it('renders without crashing', () => {
    const { container } = render(<LogoWithSpacing />);
    expect(container.firstElementChild).toBeInTheDocument();
  });

  it('renders DESCHIDE text', () => {
    render(<LogoWithSpacing />);
    expect(screen.getByText('DESCHIDE')).toBeInTheDocument();
  });

  it('wraps in an extra div', () => {
    const { container } = render(<LogoWithSpacing />);
    const outerDiv = container.firstElementChild;
    expect(outerDiv?.className).toContain('inline-block');
  });
});

describe('LogoIcon', () => {
  it('renders the "D" letter', () => {
    render(<LogoIcon />);
    expect(screen.getByText('D')).toBeInTheDocument();
  });

  it('applies blue variant by default', () => {
    const { container } = render(<LogoIcon />);
    expect(container.firstElementChild?.className).toContain('bg-brand-oxford');
  });

  it('applies red variant', () => {
    const { container } = render(<LogoIcon variant="red" />);
    expect(container.firstElementChild?.className).toContain('bg-brand-tomato');
  });

  it('applies white variant', () => {
    const { container } = render(<LogoIcon variant="white" />);
    expect(container.firstElementChild?.className).toContain('bg-surface');
  });

  it('applies sm size', () => {
    const { container } = render(<LogoIcon size="sm" />);
    expect(container.firstElementChild?.className).toContain('w-6');
  });

  it('applies md size by default', () => {
    const { container } = render(<LogoIcon />);
    expect(container.firstElementChild?.className).toContain('w-10');
  });

  it('applies lg size', () => {
    const { container } = render(<LogoIcon size="lg" />);
    expect(container.firstElementChild?.className).toContain('w-16');
  });

  it('applies custom className', () => {
    const { container } = render(<LogoIcon className="icon-custom" />);
    expect(container.firstElementChild?.className).toContain('icon-custom');
  });
});

describe('LogoWithTagline', () => {
  it('renders DESCHIDE and default tagline', () => {
    render(<LogoWithTagline />);
    expect(screen.getByText('DESCHIDE')).toBeInTheDocument();
    expect(screen.getByText('Știri din Moldova')).toBeInTheDocument();
  });

  it('renders custom tagline', () => {
    render(<LogoWithTagline tagline="News from Moldova" />);
    expect(screen.getByText('News from Moldova')).toBeInTheDocument();
  });

  it('does not render tagline when empty string provided', () => {
    render(<LogoWithTagline tagline="" />);
    // Only DESCHIDE text, no tagline paragraph
    expect(screen.queryByText('Știri din Moldova')).not.toBeInTheDocument();
  });

  it('renders with href', () => {
    render(<LogoWithTagline href="/" />);
    expect(screen.getByRole('link')).toBeInTheDocument();
  });
});
