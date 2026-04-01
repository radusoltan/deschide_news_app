/**
 * TemplateSelector Component Tests
 */

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TemplateSelector } from '@/components/live-text/TemplateSelector';

const mockTemplates = [
  {
    id: 1,
    type: 'breaking_news',
    name: 'Breaking News',
    description: 'For urgent breaking news',
    config: {
      colors: { primary: '#ff0000', secondary: 'var(--color-text-primary)', background: 'var(--color-surface)' },
      features: {
        enableReactions: true,
        enableTimeline: false,
        autoRefresh: true,
      },
    },
  },
  {
    id: 2,
    type: 'sport',
    name: 'Sport Event',
    description: 'For live sport events',
    config: {
      colors: { primary: 'var(--color-text-primary)0ff', secondary: 'var(--color-surface)', background: '#f0f0f0' },
      features: {
        enableReactions: false,
        enableTimeline: true,
        autoRefresh: true,
      },
    },
  },
];

describe('TemplateSelector', () => {
  const mockFetch = jest.fn();

  beforeEach(() => {
    global.fetch = mockFetch;
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ member: mockTemplates }),
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('shows loading state initially', () => {
    mockFetch.mockReturnValue(new Promise(() => {}));
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="ro"
        selectedTemplateId={null}
      />
    );
    expect(screen.getByText('Se încarcă șabloanele...')).toBeInTheDocument();
  });

  it('shows loading state in English', () => {
    mockFetch.mockReturnValue(new Promise(() => {}));
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    expect(screen.getByText('Loading templates...')).toBeInTheDocument();
  });

  it('shows loading state in Russian', () => {
    mockFetch.mockReturnValue(new Promise(() => {}));
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="ru"
        selectedTemplateId={null}
      />
    );
    expect(screen.getByText('Загрузка шаблонов...')).toBeInTheDocument();
  });

  it('renders templates after loading', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText('Breaking News')).toBeInTheDocument();
      expect(screen.getByText('Sport Event')).toBeInTheDocument();
    });
  });

  it('renders "No template" option', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText('No template')).toBeInTheDocument();
    });
  });

  it('renders "No template" in Romanian', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="ro"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText('Fără șablon')).toBeInTheDocument();
    });
  });

  it('calls onSelectTemplate with null when "No template" is clicked', async () => {
    const onSelect = jest.fn();
    render(
      <TemplateSelector
        onSelectTemplate={onSelect}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText('No template')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('No template').closest('button')!);
    expect(onSelect).toHaveBeenCalledWith(null);
  });

  it('calls onSelectTemplate with template when card is clicked', async () => {
    const onSelect = jest.fn();
    render(
      <TemplateSelector
        onSelectTemplate={onSelect}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText('Breaking News')).toBeInTheDocument();
    });
    fireEvent.click(screen.getByText('Breaking News').closest('button')!);
    expect(onSelect).toHaveBeenCalledWith(mockTemplates[0]);
  });

  it('shows error state when fetch fails', async () => {
    mockFetch.mockRejectedValue(new Error('Network error'));
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText(/Error:/)).toBeInTheDocument();
    });
  });

  it('shows error when response is not ok', async () => {
    mockFetch.mockResolvedValue({ ok: false });
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText(/Error:/)).toBeInTheDocument();
    });
  });

  it('shows reactions feature badge', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText(/Reactions/)).toBeInTheDocument();
    });
  });

  it('shows timeline feature badge', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText(/Timeline/)).toBeInTheDocument();
    });
  });

  it('shows auto-refresh feature badge', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getAllByText(/Auto-refresh/).length).toBeGreaterThan(0);
    });
  });

  it('highlights selected template', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={1}
      />
    );
    await waitFor(() => {
      const btn = screen.getByText('Breaking News').closest('button');
      expect(btn?.className).toContain('border-blue-500');
    });
  });

  it('shows select template heading in English', async () => {
    render(
      <TemplateSelector
        onSelectTemplate={jest.fn()}
        locale="en"
        selectedTemplateId={null}
      />
    );
    await waitFor(() => {
      expect(screen.getByText('Select a template')).toBeInTheDocument();
    });
  });
});
