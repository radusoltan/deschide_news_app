/**
 * Error Boundary Component
 * Catches JavaScript errors in component tree and displays fallback UI
 */

'use client';

import { Component, type ReactNode } from 'react';
import * as Sentry from '@sentry/nextjs';
import ArticleError from './article-error';
import type { Locale } from '@/lib/types';

interface ErrorBoundaryProps {
  children: ReactNode;
  fallback?: ReactNode;
  locale: Locale;
  onError?: (error: Error, errorInfo: React.ErrorInfo) => void;
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

export default class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
    };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    // Update state so the next render will show the fallback UI
    return {
      hasError: true,
      error,
    };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    // Log error to console in development
    if (process.env.NODE_ENV === 'development') {
      console.error('Error Boundary caught an error:', error, errorInfo);
    }

    // Call optional error handler
    if (this.props.onError) {
      this.props.onError(error, errorInfo);
    }

    // Report to Sentry in production
    Sentry.captureException(error, {
      contexts: {
        react: { componentStack: errorInfo.componentStack },
      },
    });
  }

  handleReset = () => {
    this.setState({
      hasError: false,
      error: null,
    });
  };

  render() {
    if (this.state.hasError) {
      // Custom fallback UI if provided
      if (this.props.fallback) {
        return this.props.fallback;
      }

      // Default fallback UI
      return (
        <ArticleError
          error={this.state.error || undefined}
          locale={this.props.locale}
          onRetry={this.handleReset}
        />
      );
    }

    return this.props.children;
  }
}
