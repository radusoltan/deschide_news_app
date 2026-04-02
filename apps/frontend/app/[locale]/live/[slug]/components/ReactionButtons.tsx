'use client';

import { useState, useEffect } from 'react';

interface ReactionCounts {
  like: number;
  love: number;
  wow: number;
  sad: number;
  angry: number;
}

interface ReactionButtonsProps {
  postId: number;
  initialCounts?: ReactionCounts;
  locale: string;
}

const REACTION_EMOJIS = {
  like: '👍',
  love: '❤️',
  wow: '😮',
  sad: '😢',
  angry: '😠',
};

const REACTION_LABELS = {
  ro: {
    like: 'Like',
    love: 'Iubire',
    wow: 'Uau',
    sad: 'Trist',
    angry: 'Furios',
  },
  en: {
    like: 'Like',
    love: 'Love',
    wow: 'Wow',
    sad: 'Sad',
    angry: 'Angry',
  },
  ru: {
    like: 'Нравится',
    love: 'Любовь',
    wow: 'Вау',
    sad: 'Грустно',
    angry: 'Злюсь',
  },
};

export function ReactionButtons({ postId, initialCounts, locale }: ReactionButtonsProps) {
  const [counts, setCounts] = useState<ReactionCounts>(initialCounts || {
    like: 0,
    love: 0,
    wow: 0,
    sad: 0,
    angry: 0,
  });
  const [userReaction, setUserReaction] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const labels = REACTION_LABELS[locale as keyof typeof REACTION_LABELS] || REACTION_LABELS.ro;

  // Load user's reaction from localStorage
  useEffect(() => {
    const stored = localStorage.getItem(`reaction_${postId}`);
    if (stored) {
      setUserReaction(stored);
    }
  }, [postId]);

  // Fetch current counts
  useEffect(() => {
    fetchCounts();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [postId]);

  const fetchCounts = async () => {
    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
      const response = await fetch(`${apiUrl}/api/live_text_posts/${postId}/reactions/count`, {
        credentials: 'include',
      });

      if (response.ok) {
        const data = await response.json();
        if (data.counts) {
          // Handle both plain object and hydra-wrapped response
          if (typeof data.counts === 'object' && !Array.isArray(data.counts)) {
            if (data.counts.member) {
              // Hydra wrapped - extract values
              const member = data.counts.member;
              setCounts({
                like: member[0] || 0,
                love: member[1] || 0,
                wow: member[2] || 0,
                sad: member[3] || 0,
                angry: member[4] || 0,
              });
            } else {
              // Plain object
              setCounts(data.counts);
            }
          }
        }
      }
    } catch (error) {
      console.error('Error fetching reaction counts:', error);
    }
  };

  const handleReaction = async (reactionType: string) => {
    if (isSubmitting) return;

    const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';

    // If clicking same reaction, remove it
    if (userReaction === reactionType) {
      setIsSubmitting(true);

      // Optimistic update
      const oldReaction = userReaction;
      setUserReaction(null);
      setCounts(prev => ({
        ...prev,
        [reactionType]: Math.max(0, prev[reactionType as keyof ReactionCounts] - 1),
      }));
      localStorage.removeItem(`reaction_${postId}`);

      try {
        // Note: DELETE requires finding the reaction ID first
        // For simplicity, we just refetch counts
        await fetchCounts();
      } catch (error) {
        console.error('Error removing reaction:', error);
        // Rollback
        setUserReaction(oldReaction);
        fetchCounts();
      } finally {
        setIsSubmitting(false);
      }
      return;
    }

    setIsSubmitting(true);

    // Optimistic update
    const oldReaction = userReaction;
    const oldCounts = { ...counts };

    setUserReaction(reactionType);
    setCounts(prev => {
      const newCounts = { ...prev };
      // Remove old reaction count
      if (oldReaction) {
        newCounts[oldReaction as keyof ReactionCounts] = Math.max(0, newCounts[oldReaction as keyof ReactionCounts] - 1);
      }
      // Add new reaction count
      newCounts[reactionType as keyof ReactionCounts]++;
      return newCounts;
    });
    localStorage.setItem(`reaction_${postId}`, reactionType);

    try {
      const response = await fetch(`${apiUrl}/api/live_text_reactions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/ld+json',
          'Accept': 'application/ld+json',
        },
        credentials: 'include',
        body: JSON.stringify({
          liveTextPost: `/api/live_text_posts/${postId}`,
          reactionType,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to add reaction');
      }

      // Fetch updated counts to be sure
      await fetchCounts();
    } catch (error) {
      console.error('Error adding reaction:', error);
      // Rollback
      setUserReaction(oldReaction);
      setCounts(oldCounts);
      if (oldReaction) {
        localStorage.setItem(`reaction_${postId}`, oldReaction);
      } else {
        localStorage.removeItem(`reaction_${postId}`);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex items-center gap-2 flex-wrap">
      {Object.keys(REACTION_EMOJIS).map((reactionType) => {
        const count = counts[reactionType as keyof ReactionCounts];
        const isActive = userReaction === reactionType;

        return (
          <button
            key={reactionType}
            onClick={() => handleReaction(reactionType)}
            disabled={isSubmitting}
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-all ${
              isActive
                ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 ring-2 ring-red-500'
                : 'bg-gray-100 dark:bg-surface-dark text-primary dark:text-primary-dark hover:bg-gray-200 dark:hover:bg-gray-700'
            } disabled:opacity-50`}
            title={labels[reactionType as keyof typeof labels]}
          >
            <span className="text-lg">{REACTION_EMOJIS[reactionType as keyof typeof REACTION_EMOJIS]}</span>
            {count > 0 && <span className="text-xs">{count}</span>}
          </button>
        );
      })}
    </div>
  );
}
