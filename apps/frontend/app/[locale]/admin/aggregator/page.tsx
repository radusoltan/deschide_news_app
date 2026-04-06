'use client';

import { useState, useEffect, useCallback } from 'react';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

// Types
interface TrendingTopic {
  topicId: number;
  topicName: string;
  score: number;
  articleCount: number;
  velocity: number;
}

interface AggregatorSourceStat {
  source: string;
  lastRun: string;
  articlesFound: number;
  duplicatesSkipped: number;
  pendingReview: number;
  status: string;
}

interface TopicProposal {
  id: number;
  title: string;
  reviewStatus: string;
  isActive: boolean;
  createdAt: string;
}

// Fetch helpers
async function fetchWithAuth(url: string, options: RequestInit = {}) {
  const token = typeof window !== 'undefined'
    ? document.cookie.split(';').find(c => c.trim().startsWith('auth_token='))?.split('=')[1]
    : null;

  return fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
    credentials: 'include',
  });
}

// Components
function TrendingTopicsChart({ topics }: { topics: TrendingTopic[] }) {
  const maxScore = Math.max(...topics.map(t => t.score), 1);

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
        Trending Topics (7 zile)
      </h2>
      {topics.length === 0 ? (
        <p className="text-gray-500 dark:text-gray-400 text-sm">
          Nu sunt topicuri trending.
        </p>
      ) : (
        <div className="space-y-3">
          {topics.map((topic) => (
            <div key={topic.topicId} className="flex items-center gap-3">
              <div className="w-32 truncate text-sm text-gray-700 dark:text-gray-300">
                {topic.topicName}
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <div className="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                    <div
                      className="bg-blue-600 h-4 rounded-full transition-all duration-500"
                      style={{ width: `${(topic.score / maxScore) * 100}%` }}
                    />
                  </div>
                  <span className="text-xs font-mono text-gray-500 dark:text-gray-400 w-12 text-right">
                    {topic.score.toFixed(2)}
                  </span>
                </div>
              </div>
              <div className="text-xs text-gray-500 dark:text-gray-400 w-20 text-right">
                {topic.articleCount} art. | {topic.velocity.toFixed(1)}/zi
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function AggregatorSourcesStatus({ sources }: { sources: AggregatorSourceStat[] }) {
  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
        Status Surse Agregator
      </h2>
      {sources.length === 0 ? (
        <p className="text-gray-500 dark:text-gray-400 text-sm">
          Nicio statistică disponibilă. Rulați <code>app:aggregator:run</code> pentru a genera date.
        </p>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {sources.map((source) => (
            <div
              key={source.source}
              className="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
            >
              <div className="flex items-center justify-between mb-2">
                <h3 className="font-medium text-gray-900 dark:text-white text-sm">
                  {source.source}
                </h3>
                <span
                  className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                    source.status === 'healthy'
                      ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                      : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                  }`}
                >
                  {source.status}
                </span>
              </div>
              <div className="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                <p>Articole: {source.articlesFound}</p>
                <p>Duplicate: {source.duplicatesSkipped}</p>
                <p>Pending: {source.pendingReview}</p>
                <p>Ultima rulare: {new Date(source.lastRun).toLocaleString('ro-RO')}</p>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function TopicProposalsList({
  proposals,
  onApprove,
  onReject,
}: {
  proposals: TopicProposal[];
  onApprove: (id: number) => void;
  onReject: (id: number) => void;
}) {
  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
        Propuneri Topicuri Noi
      </h2>
      {proposals.length === 0 ? (
        <p className="text-gray-500 dark:text-gray-400 text-sm">
          Nicio propunere de topic în așteptare.
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
              <tr>
                <th className="px-4 py-3">Titlu</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Creat la</th>
                <th className="px-4 py-3">Acțiuni</th>
              </tr>
            </thead>
            <tbody>
              {proposals.map((proposal) => (
                <tr
                  key={proposal.id}
                  className="border-b dark:border-gray-700"
                >
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    {proposal.title}
                  </td>
                  <td className="px-4 py-3">
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                      {proposal.reviewStatus}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500 dark:text-gray-400">
                    {proposal.createdAt
                      ? new Date(proposal.createdAt).toLocaleString('ro-RO')
                      : '-'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <button
                        onClick={() => onApprove(proposal.id)}
                        className="px-3 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 focus:outline-none"
                      >
                        Aprobă
                      </button>
                      <button
                        onClick={() => onReject(proposal.id)}
                        className="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 focus:outline-none"
                      >
                        Respinge
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

// Main Page
export default function AggregatorDashboard() {
  const [trending, setTrending] = useState<TrendingTopic[]>([]);
  const [sources, setSources] = useState<AggregatorSourceStat[]>([]);
  const [proposals, setProposals] = useState<TopicProposal[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [trendingRes, sourcesRes, proposalsRes] = await Promise.allSettled([
        fetchWithAuth(`${API_BASE_URL}/api/topics/trending?days=7&limit=20`),
        fetchWithAuth(`${API_BASE_URL}/api/aggregator/stats`),
        fetchWithAuth(`${API_BASE_URL}/api/topics/proposals`),
      ]);

      if (trendingRes.status === 'fulfilled' && trendingRes.value.ok) {
        setTrending(await trendingRes.value.json());
      }
      if (sourcesRes.status === 'fulfilled' && sourcesRes.value.ok) {
        setSources(await sourcesRes.value.json());
      }
      if (proposalsRes.status === 'fulfilled' && proposalsRes.value.ok) {
        setProposals(await proposalsRes.value.json());
      }
    } catch (err) {
      console.error('Failed to load aggregator data:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleApprove = async (id: number) => {
    try {
      const res = await fetchWithAuth(`${API_BASE_URL}/api/topics/${id}/approve`, {
        method: 'POST',
      });
      if (res.ok) {
        setProposals((prev) => prev.filter((p) => p.id !== id));
      }
    } catch (err) {
      console.error('Failed to approve topic:', err);
    }
  };

  const handleReject = async (id: number) => {
    try {
      const res = await fetchWithAuth(`${API_BASE_URL}/api/topics/${id}/reject`, {
        method: 'POST',
      });
      if (res.ok) {
        setProposals((prev) => prev.filter((p) => p.id !== id));
      }
    } catch (err) {
      console.error('Failed to reject topic:', err);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
          Aggregator Dashboard
        </h1>
        <button
          onClick={loadData}
          className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none"
        >
          Refresh
        </button>
      </div>

      <TrendingTopicsChart topics={trending} />
      <AggregatorSourcesStatus sources={sources} />
      <TopicProposalsList
        proposals={proposals}
        onApprove={handleApprove}
        onReject={handleReject}
      />
    </div>
  );
}
