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
}
