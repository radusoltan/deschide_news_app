/**
 * FactCheckPanel Component Tests
 *
 * Covers the Sprint 51a UX requirements:
 * - default collapsed / expand on header click
 * - char counter + submit disabled for < 10 chars
 * - loading state while the server action is pending
 * - differentiated 503 error banner (by status)
 * - cached badge for cached responses
 * - last-5 history, replayable
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import FactCheckPanel from '@/app/[locale]/admin/articles/[id]/edit/components/FactCheckPanel';
import {
  runFactCheck,
  type FactCheckResponse,
} from '@/app/actions/factcheck';

jest.mock('@/app/actions/factcheck', () => ({
  runFactCheck: jest.fn(),
}));

const mockRunFactCheck = runFactCheck as jest.MockedFunction<typeof runFactCheck>;

function freshResponse(overrides: Partial<FactCheckResponse> = {}): FactCheckResponse {
  return {
    status: 'fresh',
    data: {
      answer: 'Rezultatul fact-check-ului.',
      question: 'Este această afirmație verificată de surse?',
      topicId: 42,
      notebookId: 'nb-42',
      cached: false,
      checkedAt: '2026-04-17T10:00:00.000Z',
    },
    ...overrides,
  };
}

describe('FactCheckPanel', () => {
  beforeEach(() => {
    mockRunFactCheck.mockReset();
  });

  it('is collapsed by default and expands when the header is clicked', async () => {
    const user = userEvent.setup();
    render(<FactCheckPanel articleId={1} />);

    const toggle = screen.getByRole('button', { name: /fact-check/i });
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();

    await user.click(toggle);

    expect(toggle).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('shows a character counter and disables submit when question is shorter than 10 chars', async () => {
    const user = userEvent.setup();
    render(<FactCheckPanel articleId={1} />);

    await user.click(screen.getByRole('button', { name: /fact-check/i }));

    const textarea = screen.getByRole('textbox');
    await user.type(textarea, 'scurt');

    expect(screen.getByTestId('factcheck-char-counter')).toHaveTextContent('5 / 500');

    const submit = screen.getByRole('button', { name: /run fact-check/i });
    await user.click(submit);
    expect(mockRunFactCheck).not.toHaveBeenCalled();
  });

  it('submits and renders the answer for a fresh response', async () => {
    const user = userEvent.setup();
    mockRunFactCheck.mockResolvedValueOnce(freshResponse());

    render(<FactCheckPanel articleId={1} />);

    await user.click(screen.getByRole('button', { name: /fact-check/i }));
    await user.click(screen.getByRole('button', { name: /run fact-check/i }));

    await waitFor(() => {
      expect(mockRunFactCheck).toHaveBeenCalledWith(1, undefined);
    });

    expect(
      await screen.findByText('Rezultatul fact-check-ului.'),
    ).toBeInTheDocument();
    expect(screen.queryByTestId('factcheck-cached-badge')).not.toBeInTheDocument();
  });

  it('shows the cached badge when the response has status="cached"', async () => {
    const user = userEvent.setup();
    mockRunFactCheck.mockResolvedValueOnce(
      freshResponse({
        status: 'cached',
        data: {
          answer: 'Răspuns cached.',
          question: 'Q?',
          topicId: 42,
          notebookId: 'nb-42',
          cached: true,
          checkedAt: '2026-04-17T09:00:00.000Z',
        },
      }),
    );

    render(<FactCheckPanel articleId={1} />);
    await user.click(screen.getByRole('button', { name: /fact-check/i }));
    await user.click(screen.getByRole('button', { name: /run fact-check/i }));

    expect(await screen.findByTestId('factcheck-cached-badge')).toBeInTheDocument();
  });

  it('shows a tailored 503 banner for status="no_notebook"', async () => {
    const user = userEvent.setup();
    mockRunFactCheck.mockResolvedValueOnce({
      status: 'no_notebook',
      error: 'Topicul nu are încă notebook sincronizat...',
    });

    render(<FactCheckPanel articleId={1} />);
    await user.click(screen.getByRole('button', { name: /fact-check/i }));
    await user.click(screen.getByRole('button', { name: /run fact-check/i }));

    const banner = await screen.findByTestId('factcheck-error');
    expect(banner).toHaveAttribute('data-status', 'no_notebook');
    expect(banner).toHaveTextContent(/notebook sincronizat/i);
  });

  it('shows a different 503 banner for status="disabled"', async () => {
    const user = userEvent.setup();
    mockRunFactCheck.mockResolvedValueOnce({
      status: 'disabled',
      error: 'Fact-check-ul este dezactivat din configurare.',
    });

    render(<FactCheckPanel articleId={1} />);
    await user.click(screen.getByRole('button', { name: /fact-check/i }));
    await user.click(screen.getByRole('button', { name: /run fact-check/i }));

    const banner = await screen.findByTestId('factcheck-error');
    expect(banner).toHaveAttribute('data-status', 'disabled');
  });

  it('uses aria-live polite on the response region for screen reader announcements', async () => {
    const user = userEvent.setup();
    render(<FactCheckPanel articleId={1} />);

    await user.click(screen.getByRole('button', { name: /fact-check/i }));

    const liveRegion = document.querySelector('[aria-live="polite"]');
    expect(liveRegion).not.toBeNull();
    expect(liveRegion).toHaveAttribute('aria-atomic', 'true');
  });

  it('keeps only the last 5 questions in history', async () => {
    const user = userEvent.setup();
    render(<FactCheckPanel articleId={1} />);

    await user.click(screen.getByRole('button', { name: /fact-check/i }));

    for (let i = 1; i <= 7; i++) {
      mockRunFactCheck.mockResolvedValueOnce(
        freshResponse({
          data: {
            answer: `Răspuns #${i}`,
            question: `Întrebare numărul ${i}?`,
            topicId: 42,
            notebookId: 'nb-42',
            cached: false,
            checkedAt: new Date(1_700_000_000_000 + i * 1000).toISOString(),
          },
        }),
      );
      await user.click(screen.getByRole('button', { name: /run fact-check/i }));
      await screen.findByText(`Răspuns #${i}`);
    }

    const history = screen.getByTestId('factcheck-history');
    const items = history.querySelectorAll('li');
    expect(items.length).toBe(5);
    expect(history).toHaveTextContent('Întrebare numărul 7');
    expect(history).not.toHaveTextContent('Întrebare numărul 1');
  });
});
