'use client';

import { useState } from 'react';
import type { TopicTreeNode } from '@/lib/types/topic';

interface TopicTreeViewProps {
  tree: TopicTreeNode[];
  onEdit: (node: TopicTreeNode) => void;
  onDelete: (node: TopicTreeNode) => void;
  onAddChild: (parent: TopicTreeNode) => void;
  onMove: (nodeId: number, parentId: number | null, position: number) => void;
}

export default function TopicTreeView({
  tree,
  onEdit,
  onDelete,
  onAddChild,
  onMove,
}: TopicTreeViewProps) {
  return (
    <div className="space-y-1">
      {tree.map((node) => (
        <TreeNode
          key={node.id}
          node={node}
          onEdit={onEdit}
          onDelete={onDelete}
          onAddChild={onAddChild}
          onMove={onMove}
          depth={0}
        />
      ))}
      {tree.length === 0 && (
        <div className="py-12 text-center text-secondary dark:text-gray-400">
          Niciun topic disponibil. Adauga un topic root pentru a incepe.
        </div>
      )}
    </div>
  );
}

interface TreeNodeProps {
  node: TopicTreeNode;
  onEdit: (node: TopicTreeNode) => void;
  onDelete: (node: TopicTreeNode) => void;
  onAddChild: (parent: TopicTreeNode) => void;
  onMove: (nodeId: number, parentId: number | null, position: number) => void;
  depth: number;
}

function TreeNode({ node, onEdit, onDelete, onAddChild, onMove, depth }: TreeNodeProps) {
  const [expanded, setExpanded] = useState(true);
  const [dragOver, setDragOver] = useState(false);
  const hasChildren = node.children && node.children.length > 0;

  const handleDragStart = (e: React.DragEvent) => {
    e.dataTransfer.setData('text/plain', JSON.stringify({ id: node.id }));
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    setDragOver(true);
  };

  const handleDragLeave = () => {
    setDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    try {
      const data = JSON.parse(e.dataTransfer.getData('text/plain'));
      if (data.id && data.id !== node.id) {
        onMove(data.id, node.id, 0);
      }
    } catch {
      // ignore invalid drag data
    }
  };

  return (
    <div>
      <div
        className={`flex items-center gap-2 px-3 py-2 rounded-lg transition-colors group ${
          dragOver
            ? 'bg-blue-50 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700'
            : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
        } ${!node.isActive ? 'opacity-50' : ''}`}
        style={{ paddingLeft: `${depth * 24 + 12}px` }}
        draggable
        onDragStart={handleDragStart}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
      >
        {/* Expand/Collapse */}
        <button
          type="button"
          onClick={() => setExpanded(!expanded)}
          className={`w-5 h-5 flex items-center justify-center text-secondary dark:text-gray-400 ${
            !hasChildren ? 'invisible' : ''
          }`}
        >
          <svg
            className={`w-4 h-4 transition-transform ${expanded ? 'rotate-90' : ''}`}
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path
              fillRule="evenodd"
              d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
              clipRule="evenodd"
            />
          </svg>
        </button>

        {/* Drag handle */}
        <span className="cursor-grab text-gray-300 dark:text-gray-600 opacity-0 group-hover:opacity-100 transition-opacity">
          <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path d="M7 2a2 2 0 10.001 4.001A2 2 0 007 2zm0 6a2 2 0 10.001 4.001A2 2 0 007 8zm0 6a2 2 0 10.001 4.001A2 2 0 007 14zm6-8a2 2 0 10-.001-4.001A2 2 0 0013 6zm0 2a2 2 0 10.001 4.001A2 2 0 0013 8zm0 6a2 2 0 10.001 4.001A2 2 0 0013 14z" />
          </svg>
        </span>

        {/* Title */}
        <span
          className={`flex-1 ${
            depth === 0
              ? 'font-semibold text-base text-primary dark:text-primary-dark'
              : depth === 1
                ? 'font-normal text-sm text-primary dark:text-primary-dark'
                : 'font-normal text-sm text-secondary dark:text-gray-400'
          }`}
        >
          {node.title}
        </span>

        {/* Inactive badge */}
        {!node.isActive && (
          <span className="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
            Inactiv
          </span>
        )}

        {/* Action buttons */}
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            type="button"
            onClick={() => onAddChild(node)}
            className="p-1 text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 rounded"
            title="Adauga subtopic"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
            </svg>
          </button>
          <button
            type="button"
            onClick={() => onEdit(node)}
            className="p-1 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded"
            title="Editeaza"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </button>
          <button
            type="button"
            onClick={() => onDelete(node)}
            className="p-1 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
            title="Sterge"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>

      {/* Children */}
      {expanded && hasChildren && (
        <div>
          {node.children!.map((child) => (
            <TreeNode
              key={child.id}
              node={child}
              onEdit={onEdit}
              onDelete={onDelete}
              onAddChild={onAddChild}
              onMove={onMove}
              depth={depth + 1}
            />
          ))}
        </div>
      )}
    </div>
  );
}
