'use client';

import { useEffect, useState } from 'react';

interface TopicProposal {
  id: number;
  name: string;
  translations: { ro: string; en: string; ru: string };
  confidence: number;
  articleCount: number;
  reviewStatus: string;
}

export default function TopicProposalsList() {
  const [proposals, setProposals] = useState<TopicProposal[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/api/topics/proposals')
      .then(r => r.ok ? r.json() : [])
      .then(data => { setProposals(Array.isArray(data) ? data : data?.['hydra:member'] ?? []); setLoading(false); })
      .catch(() => setLoading(false));
  }, []);

  const handleAction = async (id: number, action: 'approve' | 'reject') => {
    await fetch(`/api/topics/${id}/${action}`, { method: 'POST' });
    setProposals(prev => prev.filter(p => p.id !== id));
  };

  if (loading) return <div className="animate-pulse h-32 bg-gray-100 dark:bg-gray-800 rounded-lg" />;

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-gray-200 dark:border-gray-700 text-left">
            <th className="py-2 px-3 font-medium">Topic (RO)</th>
            <th className="py-2 px-3 font-medium">EN</th>
            <th className="py-2 px-3 font-medium">RU</th>
            <th className="py-2 px-3 font-medium">Confidence</th>
            <th className="py-2 px-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          {proposals.map(p => (
            <tr key={p.id} className="border-b border-gray-100 dark:border-gray-800">
              <td className="py-2 px-3">{p.translations?.ro ?? p.name}</td>
              <td className="py-2 px-3 text-gray-500">{p.translations?.en ?? '—'}</td>
              <td className="py-2 px-3 text-gray-500">{p.translations?.ru ?? '—'}</td>
              <td className="py-2 px-3">
                <span className={`px-2 py-0.5 rounded text-xs ${p.confidence >= 0.8 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
                  {(p.confidence * 100).toFixed(0)}%
                </span>
              </td>
              <td className="py-2 px-3 flex gap-2">
                <button onClick={() => handleAction(p.id, 'approve')} className="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">Accept</button>
                <button onClick={() => handleAction(p.id, 'reject')} className="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700">Reject</button>
              </td>
            </tr>
          ))}
          {proposals.length === 0 && (
            <tr><td colSpan={5} className="py-8 text-center text-gray-400">No topic proposals pending review.</td></tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
