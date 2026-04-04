'use client';

import { useState, useEffect, useCallback } from 'react';
import type { TopicTreeNode, TopicFlatItem } from '@/lib/types/topic';
import TopicTreeView from '@/components/admin/topics/TopicTreeView';
import TopicFormModal from '@/components/admin/topics/TopicFormModal';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? '';

interface TopicsPageClientProps {
  locale: string;
}

export default function TopicsPageClient({ locale }: TopicsPageClientProps) {
  const [tree, setTree] = useState<TopicTreeNode[]>([]);
  const [flatList, setFlatList] = useState<TopicFlatItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingTopic, setEditingTopic] = useState<TopicTreeNode | null>(null);
  const [parentForNew, setParentForNew] = useState<TopicTreeNode | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<TopicTreeNode | null>(null);
  const [deleteError, setDeleteError] = useState('');
  const [isDeleting, setIsDeleting] = useState(false);

  const fetchTree = useCallback(async () => {
    setIsLoading(true);
    try {
      const [treeRes, flatRes] = await Promise.all([
        fetch(`${API_BASE_URL}/api/topics/tree`, {
          headers: { 'Accept-Language': locale },
          cache: 'no-store',
        }),
        fetch(`${API_BASE_URL}/api/topics/flat?locale=${locale}`, {
          cache: 'no-store',
        }),
      ]);

      if (treeRes.ok) {
        const data = await treeRes.json();
        setTree(data);
      }
      if (flatRes.ok) {
        const data = await flatRes.json();
        setFlatList(data);
      }
    } catch (err) {
      console.error('Failed to fetch topics:', err);
    } finally {
      setIsLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    fetchTree();
  }, [fetchTree]);

  const handleAddRoot = () => {
    setEditingTopic(null);
    setParentForNew(null);
    setIsModalOpen(true);
  };

  const handleAddChild = (parent: TopicTreeNode) => {
    setEditingTopic(null);
    setParentForNew(parent);
    setIsModalOpen(true);
  };

  const handleEdit = (node: TopicTreeNode) => {
    setEditingTopic(node);
    setParentForNew(null);
    setIsModalOpen(true);
  };

  const handleDelete = (node: TopicTreeNode) => {
    setDeleteTarget(node);
    setDeleteError('');
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;

    setIsDeleting(true);
    setDeleteError('');
    try {
      const res = await fetch(`${API_BASE_URL}/api/topics/${deleteTarget.id}`, {
        method: 'DELETE',
        headers: { 'Accept-Language': locale },
      });

      if (!res.ok && res.status !== 204) {
        const err = await res.json().catch(() => ({}));
        throw new Error(
          err['hydra:description'] || err.detail || 'Failed to delete topic'
        );
      }

      setDeleteTarget(null);
      fetchTree();
    } catch (err) {
      setDeleteError(
        err instanceof Error ? err.message : 'Failed to delete topic'
      );
    } finally {
      setIsDeleting(false);
    }
  };

  const handleMove = async (
    nodeId: number,
    parentId: number | null,
    position: number
  ) => {
    try {
      const res = await fetch(`${API_BASE_URL}/api/topics/${nodeId}/move`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept-Language': locale,
        },
        body: JSON.stringify({ parentId, position }),
      });

      if (res.ok) {
        fetchTree();
      }
    } catch (err) {
      console.error('Failed to move topic:', err);
    }
  };

  const handleSaved = () => {
    setIsModalOpen(false);
    setEditingTopic(null);
    setParentForNew(null);
    fetchTree();
  };

  return (
    <>
      {/* Page Header */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-primary dark:text-primary-dark">
            Gestionare Topics
          </h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Clasificare tematica ierarhica pentru articole
          </p>
        </div>
        <button
          onClick={handleAddRoot}
          className="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800"
        >
          + Adauga Topic Root
        </button>
      </div>

      {/* Tree View */}
      <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-md p-4">
        {isLoading ? (
          <div className="py-12 flex items-center justify-center text-secondary dark:text-gray-400">
            <svg
              className="animate-spin h-5 w-5 mr-2"
              viewBox="0 0 24 24"
            >
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
                fill="none"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            Se incarca...
          </div>
        ) : (
          <TopicTreeView
            tree={tree}
            onEdit={handleEdit}
            onDelete={handleDelete}
            onAddChild={handleAddChild}
            onMove={handleMove}
          />
        )}
      </div>

      {/* Form Modal */}
      {isModalOpen && (
        <TopicFormModal
          topic={editingTopic}
          parentTopic={parentForNew}
          flatList={flatList}
          locale={locale}
          onClose={() => {
            setIsModalOpen(false);
            setEditingTopic(null);
            setParentForNew(null);
          }}
          onSaved={handleSaved}
        />
      )}

      {/* Delete Confirmation */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-900/80">
          <div className="bg-surface dark:bg-surface-dark rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <div className="flex items-center gap-3 mb-4">
              <div className="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                <svg
                  className="w-5 h-5 text-red-600 dark:text-red-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                  />
                </svg>
              </div>
              <h3 className="text-lg font-semibold text-primary dark:text-primary-dark">
                Sterge topic
              </h3>
            </div>

            <p className="text-sm text-secondary dark:text-gray-400 mb-2">
              Esti sigur ca vrei sa stergi topic-ul{' '}
              <strong>{deleteTarget.title}</strong>?
            </p>

            {deleteTarget.children && deleteTarget.children.length > 0 && (
              <div className="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-sm text-yellow-700 dark:text-yellow-300">
                Acest topic are {deleteTarget.children.length} subtopic(uri).
                Acestea vor fi sterse impreuna cu parintele.
              </div>
            )}

            {deleteError && (
              <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400">
                {deleteError}
              </div>
            )}

            <div className="mt-6 flex justify-end gap-3">
              <button
                onClick={() => setDeleteTarget(null)}
                disabled={isDeleting}
                className="px-4 py-2 text-sm font-medium text-primary dark:text-primary-dark bg-surface dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
              >
                Anuleaza
              </button>
              <button
                onClick={confirmDelete}
                disabled={isDeleting}
                className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
              >
                {isDeleting ? 'Se sterge...' : 'Sterge'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
