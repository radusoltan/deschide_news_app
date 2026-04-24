'use client';

import { useMemo } from 'react';
import {
  ALIGNMENT_COLOR,
  type OriginGraphEdge,
  type OriginGraphNode,
  type OriginGraphSnapshot,
} from '@/lib/api/escalations';

interface OriginGraphVizProps {
  snapshot: OriginGraphSnapshot | null | undefined;
  /** Max width in px; defaults to 500. */
  width?: number;
  /** Height in px; defaults to 300. */
  height?: number;
}

const COLUMN_X: Record<1 | 2 | 3, number> = {
  1: 70,
  2: 250,
  3: 430,
};

const NODE_RADIUS = 16;

/**
 * Inline SVG rendering of a ClaimOriginGraph snapshot (Sprint 55 T55.13, audit D15).
 *
 * Zero external deps — fixed column layout keeps the component deterministic and
 * predictable for editors inspecting the cluster shape behind an escalation:
 *   tier 1 (wire/official)       → left column
 *   tier 2 (mainstream investigative) → center column
 *   tier 3 (aggregator/secondary)     → right column
 *
 * Tooltip via native `<title>` element so we don't pull in a popover library.
 * Edges are drawn as simple lines with arrowhead marker.
 *
 * Returns an "No graph snapshot" note when the snapshot is absent or has no
 * nodes — escalations can legitimately land with only an article snapshot
 * when the upstream graph wasn't yet materialised.
 */
export function OriginGraphViz({ snapshot, width = 500, height = 300 }: OriginGraphVizProps) {
  const positioned = useMemo(() => layoutNodes(snapshot?.nodes ?? [], height), [snapshot?.nodes, height]);
  const edges = snapshot?.edges ?? [];

  if (positioned.length === 0) {
    return (
      <div className="text-xs text-secondary dark:text-gray-400 italic p-3 border border-dashed border-gray-300 dark:border-gray-600 rounded">
        Nu există graf de origine pentru această escaladare.
      </div>
    );
  }

  const nodeById = new Map(positioned.map((n) => [n.id, n]));

  return (
    <div className="inline-block" style={{ maxWidth: width }}>
      <svg
        viewBox={`0 0 ${width} ${height}`}
        width="100%"
        height="auto"
        role="img"
        aria-label="Graf de origine a claim-ului"
        className="bg-surface-sunken dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700"
      >
        <defs>
          <marker
            id="origin-graph-arrow"
            viewBox="0 0 10 10"
            refX="10"
            refY="5"
            markerWidth="6"
            markerHeight="6"
            orient="auto-start-reverse"
          >
            <path d="M0,0 L10,5 L0,10 z" fill="#9ca3af" />
          </marker>
        </defs>

        {/* Edges first so nodes overlay them. */}
        <g stroke="#9ca3af" strokeWidth={1.5} opacity={0.7}>
          {edges.map((edge, idx) => {
            const a = nodeById.get(edge.source);
            const b = nodeById.get(edge.target);
            if (!a || !b) return null;

            return (
              <line
                key={`edge-${idx}-${String(edge.source)}-${String(edge.target)}`}
                x1={a.x}
                y1={a.y}
                x2={b.x}
                y2={b.y}
                markerEnd="url(#origin-graph-arrow)"
              />
            );
          })}
        </g>

        {/* Nodes. */}
        <g>
          {positioned.map((node) => {
            const color = ALIGNMENT_COLOR[node.alignment] ?? '#6b7280';
            const labelText = (node.name ?? node.slug ?? String(node.id)).slice(0, 18);
            const tooltip = `${node.name ?? node.slug ?? node.id} · ${node.alignment} · tier ${node.tier}`;

            return (
              <g key={`node-${String(node.id)}`} transform={`translate(${node.x},${node.y})`}>
                <title>{tooltip}</title>
                <circle r={NODE_RADIUS} fill={color} stroke="#1f2937" strokeWidth={1.2} />
                <text
                  x={0}
                  y={NODE_RADIUS + 14}
                  textAnchor="middle"
                  className="fill-primary dark:fill-primary-dark text-[10px] font-medium"
                >
                  {labelText}
                </text>
                <text
                  x={0}
                  y={4}
                  textAnchor="middle"
                  className="fill-white text-[10px] font-semibold"
                >
                  T{node.tier}
                </text>
              </g>
            );
          })}
        </g>

        {/* Column labels. */}
        <g className="fill-secondary dark:fill-gray-400 text-[10px] font-medium uppercase">
          <text x={COLUMN_X[1]} y={16} textAnchor="middle">Tier 1 · Wire</text>
          <text x={COLUMN_X[2]} y={16} textAnchor="middle">Tier 2 · Mainstream</text>
          <text x={COLUMN_X[3]} y={16} textAnchor="middle">Tier 3 · Agregatori</text>
        </g>
      </svg>
    </div>
  );
}

interface PositionedNode extends OriginGraphNode {
  x: number;
  y: number;
}

/**
 * Places nodes in three fixed columns (by tier) and distributes them vertically
 * inside their column. Layout is deterministic — same snapshot yields the same
 * render — which keeps the admin UI visually stable across refetches.
 */
function layoutNodes(nodes: OriginGraphNode[], canvasHeight: number): PositionedNode[] {
  const byTier: Record<1 | 2 | 3, OriginGraphNode[]> = { 1: [], 2: [], 3: [] };
  for (const node of nodes) {
    const t = ([1, 2, 3] as const).includes(node.tier) ? node.tier : 3;
    byTier[t].push(node);
  }

  const verticalPadding = 50;
  const usableHeight = Math.max(canvasHeight - verticalPadding * 2, 1);

  const out: PositionedNode[] = [];
  for (const tier of [1, 2, 3] as const) {
    const column = byTier[tier];
    const count = column.length;
    column.forEach((node, idx) => {
      const y = count === 1
        ? verticalPadding + usableHeight / 2
        : verticalPadding + (usableHeight * idx) / Math.max(count - 1, 1);
      out.push({ ...node, x: COLUMN_X[tier], y });
    });
  }

  return out;
}
