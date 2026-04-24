<?php

declare(strict_types=1);

namespace App\Service\Editorial\Verification;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Repository\Editorial\VerifiedSourceRepository;
use App\Repository\SourceRepository;
use Psr\Log\LoggerInterface;

/**
 * Builds a {@see ClaimOriginGraph} for a set of signals confirmed to belong
 * to the same cluster by {@see SignalAggregator} (Sprint 54 T54.8).
 *
 * The builder is deliberately separate from the aggregator so the graph
 * algorithm can be exercised in unit tests without ES / LLM dependencies.
 * All I/O is scoped to two repositories: {@see SourceRepository} for domain
 * lookups, {@see VerifiedSourceRepository} for the Source → VerifiedSource
 * bridge.
 *
 * Edge derivation rules:
 *   - `source_links_out` URL → registered VerifiedSource if the URL's
 *     domain matches a Source.domain_pattern (case-insensitive suffix match).
 *   - `source_links_out` URL with no registered match → virtual external
 *     node `external:{domain}`. External nodes still participate in the
 *     connectivity analysis so two signals citing the same unregistered
 *     third party get correctly collapsed to one chain.
 *   - `source_attribution` text → registered VerifiedSource if any
 *     Source.name is a case-insensitive substring of the attribution text.
 *     A single name match is enough; we don't attempt to disambiguate when
 *     multiple names overlap.
 *
 * Independent-chain counting uses union-find on the undirected edge view.
 * Virtual external nodes participate in unions but only "registered"
 * nodes (integer id) count as chain roots — so a 3-signal chain that all
 * cite Reuters (external) collapses to 1 chain whose root is one of the
 * three registered nodes.
 */
class ClaimOriginGraphBuilder
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly VerifiedSourceRepository $verifiedSourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<SourceSignal> $signals
     */
    public function build(string $topicHash, array $signals): ClaimOriginGraph
    {
        /** @var array<int|string, array{id: int|string, slug: string, alignment: string, tier: int|null}> $nodes keyed by id/slug */
        $nodes = [];
        /** @var list<array{from: int|string, to: int|string, type: 'domain'|'name'}> $edges */
        $edges = [];

        foreach ($signals as $signal) {
            $vs = $signal->getVerifiedSource();
            $fromId = $this->nodeIdForVerifiedSource($vs);
            $nodes[$fromId] ??= $this->makeRegisteredNode($vs);
        }

        foreach ($signals as $signal) {
            $vs = $signal->getVerifiedSource();
            $fromId = $this->nodeIdForVerifiedSource($vs);

            foreach ($signal->getSourceLinksOut() ?? [] as $url) {
                $edge = $this->resolveLinkEdge($fromId, $url, $nodes);
                if ($edge !== null) {
                    $edges[] = $edge;
                }
            }

            $attribution = $signal->getSourceAttribution();
            if ($attribution !== null && trim($attribution) !== '') {
                $edge = $this->resolveAttributionEdge($fromId, $attribution);
                if ($edge !== null) {
                    $edges[] = $edge;
                }
            }
        }

        $alignmentClusters = $this->buildAlignmentClusters($nodes);
        $tierDistribution = $this->buildTierDistribution($nodes);
        $independentChains = $this->countIndependentChains(array_keys($nodes), $edges);
        $claimHash = $this->computeClaimHash($nodes);

        $orderedNodes = array_values($nodes);

        $this->logger->debug('ClaimOriginGraphBuilder: built graph', [
            'topic_hash' => $topicHash,
            'nodes' => \count($orderedNodes),
            'edges' => \count($edges),
            'independent_chains' => $independentChains,
            'alignment_clusters' => $alignmentClusters,
        ]);

        return new ClaimOriginGraph(
            topicHash: $topicHash,
            claimHash: $claimHash,
            nodes: $orderedNodes,
            edges: $edges,
            alignmentClusters: $alignmentClusters,
            independentChains: $independentChains,
            tierDistribution: $tierDistribution,
        );
    }

    private function nodeIdForVerifiedSource(VerifiedSource $vs): int
    {
        $id = $vs->getId();
        if ($id === null) {
            throw new \RuntimeException(sprintf(
                'VerifiedSource "%s" has no id — cannot build claim-origin graph.',
                $vs->getSlug(),
            ));
        }

        return $id;
    }

    /**
     * @return array{id: int, slug: string, alignment: string, tier: int|null}
     */
    private function makeRegisteredNode(VerifiedSource $vs): array
    {
        return [
            'id' => (int) $vs->getId(),
            'slug' => $vs->getSlug(),
            'alignment' => $vs->getEditorialAlignment()->value,
            'tier' => $vs->getTier(),
        ];
    }

    /**
     * @param array<int|string, array{id: int|string, slug: string, alignment: string, tier: int|null}> $nodes
     *
     * @return array{from: int|string, to: int|string, type: 'domain'|'name'}|null
     */
    private function resolveLinkEdge(int|string $fromId, string $url, array &$nodes): ?array
    {
        $domain = $this->extractDomain($url);
        if ($domain === null) {
            return null;
        }

        $source = $this->findRegisteredSourceForDomain($domain);
        if ($source !== null) {
            $vs = $this->verifiedSourceRepository->findOneBy(['source' => $source, 'enabled' => true]);
            if ($vs !== null) {
                $toId = $this->nodeIdForVerifiedSource($vs);
                $nodes[$toId] ??= $this->makeRegisteredNode($vs);
                if ($toId === $fromId) {
                    return null;
                }

                return ['from' => $fromId, 'to' => $toId, 'type' => 'domain'];
            }
        }

        // Unregistered → virtual external node.
        $virtualId = 'external:' . $domain;
        $nodes[$virtualId] ??= [
            'id' => $virtualId,
            'slug' => $virtualId,
            'alignment' => 'external',
            'tier' => null,
        ];

        if ($virtualId === $fromId) {
            return null;
        }

        return ['from' => $fromId, 'to' => $virtualId, 'type' => 'domain'];
    }

    /**
     * @return array{from: int|string, to: int|string, type: 'domain'|'name'}|null
     */
    private function resolveAttributionEdge(int|string $fromId, string $attribution): ?array
    {
        $needle = mb_strtolower(trim($attribution));

        // Scan all VerifiedSources once — in S54 we have ~18 rows total,
        // so a full-scan substring match is trivially cheap. Sprint 55+ may
        // switch to an in-memory index if the registry grows past 100.
        foreach ($this->verifiedSourceRepository->findBy(['enabled' => true]) as $vs) {
            $source = $vs->getSource();
            if ($source === null) {
                continue;
            }
            $name = mb_strtolower($source->getName());
            if ($name !== '' && str_contains($needle, $name)) {
                $toId = $this->nodeIdForVerifiedSource($vs);
                if ($toId === $fromId) {
                    continue;
                }

                return ['from' => $fromId, 'to' => $toId, 'type' => 'name'];
            }
        }

        return null;
    }

    private function findRegisteredSourceForDomain(string $domain): ?Source
    {
        // Case-insensitive suffix match so "www.tass.com" → "tass.com"
        // (Source.domain_pattern is stored bare).
        $lower = mb_strtolower($domain);
        foreach ($this->sourceRepository->findAll() as $source) {
            $pattern = $source->getDomainPattern();
            if ($pattern === null || $pattern === '') {
                continue;
            }
            $patternLower = mb_strtolower($pattern);
            if ($lower === $patternLower || str_ends_with($lower, '.' . $patternLower)) {
                return $source;
            }
        }

        return null;
    }

    private function extractDomain(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            return null;
        }

        return mb_strtolower($host);
    }

    /**
     * @param array<int|string, array{id: int|string, slug: string, alignment: string, tier: int|null}> $nodes
     *
     * @return list<string>
     */
    private function buildAlignmentClusters(array $nodes): array
    {
        $alignments = [];
        foreach ($nodes as $node) {
            if (!\is_int($node['id'])) {
                continue; // skip virtual external nodes
            }
            $alignments[$node['alignment']] = true;
        }

        $values = array_keys($alignments);
        sort($values);

        return $values;
    }

    /**
     * @param array<int|string, array{id: int|string, slug: string, alignment: string, tier: int|null}> $nodes
     *
     * @return array<string, int>
     */
    private function buildTierDistribution(array $nodes): array
    {
        $dist = [];
        foreach ($nodes as $node) {
            $label = $node['tier'] === null ? 'external' : (string) $node['tier'];
            $dist[$label] = ($dist[$label] ?? 0) + 1;
        }
        ksort($dist);

        return $dist;
    }

    /**
     * Union-find on the undirected view of the edge graph.
     *
     * @param list<int|string> $nodeIds
     * @param list<array{from: int|string, to: int|string, type: 'domain'|'name'}> $edges
     */
    private function countIndependentChains(array $nodeIds, array $edges): int
    {
        $parent = [];
        foreach ($nodeIds as $id) {
            $parent[(string) $id] = (string) $id;
        }

        $find = function (string $x) use (&$parent, &$find): string {
            if ($parent[$x] === $x) {
                return $x;
            }

            return $parent[$x] = $find($parent[$x]);
        };

        foreach ($edges as $edge) {
            $pa = $find((string) $edge['from']);
            $pb = $find((string) $edge['to']);
            if ($pa !== $pb) {
                $parent[$pa] = $pb;
            }
        }

        $roots = [];
        foreach ($nodeIds as $id) {
            // Only count registered (integer id) nodes as chain roots.
            if (!\is_int($id)) {
                continue;
            }
            $root = $find((string) $id);
            $roots[$root] = true;
        }

        return \count($roots);
    }

    /**
     * @param array<int|string, array{id: int|string, slug: string, alignment: string, tier: int|null}> $nodes
     */
    private function computeClaimHash(array $nodes): string
    {
        $slugs = [];
        foreach ($nodes as $node) {
            $slugs[] = $node['slug'];
        }
        sort($slugs);

        return substr(md5(implode('|', $slugs)), 0, 16);
    }
}
