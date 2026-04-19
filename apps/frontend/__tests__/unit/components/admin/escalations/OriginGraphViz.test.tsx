import { render, screen } from '@testing-library/react';
import { OriginGraphViz } from '@/components/admin/escalations/OriginGraphViz';
import type { OriginGraphSnapshot } from '@/lib/api/escalations';

describe('OriginGraphViz', () => {
  it('renders empty-state message when snapshot is null', () => {
    render(<OriginGraphViz snapshot={null} />);

    expect(screen.getByText(/Nu există graf de origine/)).toBeInTheDocument();
  });

  it('renders empty-state message when nodes array is empty', () => {
    render(<OriginGraphViz snapshot={{ nodes: [], edges: [] }} />);

    expect(screen.getByText(/Nu există graf de origine/)).toBeInTheDocument();
  });

  it('renders nodes grouped into tier columns', () => {
    const snapshot: OriginGraphSnapshot = {
      nodes: [
        { id: 'a', slug: 'reuters', alignment: 'wire_neutral', tier: 1 },
        { id: 'b', slug: 'bbc', alignment: 'western_mainstream', tier: 2 },
        { id: 'c', slug: 'aggregator-x', alignment: 'osint_curated', tier: 3 },
      ],
      edges: [
        { source: 'a', target: 'b' },
        { source: 'b', target: 'c' },
      ],
    };

    const { container } = render(<OriginGraphViz snapshot={snapshot} />);

    // Tier labels always rendered at the top of the SVG.
    expect(screen.getByText(/Tier 1.*Wire/)).toBeInTheDocument();
    expect(screen.getByText(/Tier 2.*Mainstream/)).toBeInTheDocument();
    expect(screen.getByText(/Tier 3.*Agregatori/)).toBeInTheDocument();

    // Node slug labels render under each circle.
    expect(screen.getByText('reuters')).toBeInTheDocument();
    expect(screen.getByText('bbc')).toBeInTheDocument();
    expect(screen.getByText('aggregator-x')).toBeInTheDocument();

    // Edges rendered as <line> elements with arrowhead marker.
    const lines = container.querySelectorAll('line');
    expect(lines.length).toBe(2);
    lines.forEach((line) => {
      expect(line.getAttribute('marker-end')).toBe('url(#origin-graph-arrow)');
    });
  });

  it('truncates long labels to fit the column width', () => {
    const snapshot: OriginGraphSnapshot = {
      nodes: [
        {
          id: 1,
          slug: 'a-very-long-source-slug-that-exceeds-eighteen-characters',
          alignment: 'wire_neutral',
          tier: 1,
        },
      ],
    };

    render(<OriginGraphViz snapshot={snapshot} />);

    // Label is truncated to 18 chars (slice(0, 18)).
    expect(screen.getByText('a-very-long-source')).toBeInTheDocument();
  });

  it('places circles at the expected column x coordinate per tier', () => {
    const snapshot: OriginGraphSnapshot = {
      nodes: [
        { id: '1', slug: 't1', alignment: 'wire_neutral', tier: 1 },
        { id: '2', slug: 't2', alignment: 'western_mainstream', tier: 2 },
        { id: '3', slug: 't3', alignment: 'osint_curated', tier: 3 },
      ],
    };

    const { container } = render(<OriginGraphViz snapshot={snapshot} />);

    // Tier 1 column is at x=70, tier 2 at 250, tier 3 at 430.
    const groups = container.querySelectorAll('g[transform^="translate("]');
    const xCoords = Array.from(groups).map((g) => {
      const match = /translate\((\d+),/.exec(g.getAttribute('transform') ?? '');
      return match ? Number(match[1]) : NaN;
    });

    expect(xCoords).toContain(70);
    expect(xCoords).toContain(250);
    expect(xCoords).toContain(430);
  });

  it('skips edges with unknown node references', () => {
    const snapshot: OriginGraphSnapshot = {
      nodes: [{ id: 'a', slug: 'x', alignment: 'wire_neutral', tier: 1 }],
      edges: [
        { source: 'a', target: 'missing-node' },
        { source: 'also-missing', target: 'a' },
      ],
    };

    const { container } = render(<OriginGraphViz snapshot={snapshot} />);

    // Both edges reference ids that don't exist in nodes → no <line> rendered.
    expect(container.querySelectorAll('line').length).toBe(0);
  });
});
