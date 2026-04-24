<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * Claim-origin graph for a stabilized signal cluster (Sprint 54 T54.8, ADR-020 D3).
 *
 * Nodes are either registered {@see \App\Entity\Editorial\VerifiedSource} rows
 * (keyed by VerifiedSource.id as int) or virtual "external" placeholders for
 * domains that appear in a signal's source_links_out but have no matching
 * VerifiedSource (keyed as `external:{domain}`).
 *
 * Edges are directed "A cites B" relations derived from:
 *   - `SourceSignal.source_links_out` domain match against `Source.domain_pattern`
 *   - `SourceSignal.source_attribution` name fuzzy-match against `Source.name`
 *
 * `independentChains` = number of connected components (union-find over
 * undirected edge view) that contain at least one non-external node. An
 * echo chamber (TASS → RIA → Kommersant → TASS) collapses to a single
 * component and yields independentChains=1, NOT 3. This is the mechanical
 * enforcement of the ADR-020 D3 publication matrix.
 *
 * `alignmentClusters` = distinct EditorialAlignment values across the
 * non-external nodes, sorted. "1 chain + 1 alignment" ≠ "1 chain + diverse
 * alignments" even when the chain count is equal.
 *
 * Serializable via {@see toArray()} for persistence onto
 * `source_signals.claim_graph_snapshot` (T54.3) at verdict time.
 */
final readonly class ClaimOriginGraph
{
    /**
     * @param list<array{id: int|string, slug: string, alignment: string, tier: int|null}> $nodes
     * @param list<array{from: int|string, to: int|string, type: 'domain'|'name'}> $edges
     * @param list<string> $alignmentClusters
     * @param array<string, int> $tierDistribution  tier label (e.g. "1", "2", "external") -> count
     */
    public function __construct(
        public string $topicHash,
        public string $claimHash,
        public array $nodes,
        public array $edges,
        public array $alignmentClusters,
        public int $independentChains,
        public array $tierDistribution,
    ) {}

    /**
     * Count nodes that belong to a registered VerifiedSource (integer id),
     * excluding virtual `external:*` placeholders.
     */
    public function registeredNodeCount(): int
    {
        $count = 0;
        foreach ($this->nodes as $node) {
            if (\is_int($node['id'])) {
                ++$count;
            }
        }

        return $count;
    }

    public function hasAlignment(string $alignment): bool
    {
        return \in_array($alignment, $this->alignmentClusters, true);
    }

    /**
     * Number of nodes at the given tier (1/2/…). 0 when the tier has no
     * representatives in the graph. Accepts integer tier — internal string
     * key mapping is an implementation detail.
     */
    public function getTierCount(int $tier): int
    {
        $key = (string) $tier;

        return \array_key_exists($key, $this->tierDistribution)
            ? $this->tierDistribution[$key]
            : 0;
    }

    public function hasAlignmentDiversity(): bool
    {
        return \count($this->alignmentClusters) >= 2;
    }

    /**
     * Serialize the graph as a structured array suitable for storage into
     * `source_signals.claim_graph_snapshot`.
     *
     * @return array{topic_hash: string, claim_hash: string, nodes: list<array{id: int|string, slug: string, alignment: string, tier: int|null}>, edges: list<array{from: int|string, to: int|string, type: 'domain'|'name'}>, alignment_clusters: list<string>, independent_chains: int, tier_distribution: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'topic_hash' => $this->topicHash,
            'claim_hash' => $this->claimHash,
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'alignment_clusters' => $this->alignmentClusters,
            'independent_chains' => $this->independentChains,
            'tier_distribution' => $this->tierDistribution,
        ];
    }

    /**
     * Reconstruct a graph from its {@see toArray()} form — used by handlers
     * that receive the serialized payload through the messenger bus.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array{id: int|string, slug: string, alignment: string, tier: int|null}> $nodes */
        $nodes = \is_array($data['nodes'] ?? null) ? $data['nodes'] : [];
        /** @var list<array{from: int|string, to: int|string, type: 'domain'|'name'}> $edges */
        $edges = \is_array($data['edges'] ?? null) ? $data['edges'] : [];
        /** @var list<string> $clusters */
        $clusters = \is_array($data['alignment_clusters'] ?? null) ? $data['alignment_clusters'] : [];
        /** @var array<string, int> $tiers */
        $tiers = \is_array($data['tier_distribution'] ?? null) ? $data['tier_distribution'] : [];

        return new self(
            topicHash: (string) ($data['topic_hash'] ?? ''),
            claimHash: (string) ($data['claim_hash'] ?? ''),
            nodes: $nodes,
            edges: $edges,
            alignmentClusters: $clusters,
            independentChains: (int) ($data['independent_chains'] ?? 0),
            tierDistribution: $tiers,
        );
    }
}
