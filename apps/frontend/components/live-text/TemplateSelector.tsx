'use client';

import { useState, useEffect } from 'react';
import type { LiveTextTemplate } from '@/lib/types/livetext';

interface TemplateSelectorProps {
  selectedTemplateId?: number | null;
  onSelectTemplate: (template: LiveTextTemplate | null) => void;
  locale: string;
}

const TEMPLATE_ICONS: Record<string, string> = {
  breaking_news: '🚨',
  sport: '⚽',
  conference: '🎤',
  election: '🗳️',
};

const TEMPLATE_LABELS = {
  ro: {
    breaking_news: 'Știri de Ultimă Oră',
    sport: 'Eveniment Sportiv',
    conference: 'Conferință',
    election: 'Alegeri',
    noTemplate: 'Fără șablon',
    selectTemplate: 'Selectează un șablon',
    loading: 'Se încarcă șabloanele...',
  },
  en: {
    breaking_news: 'Breaking News',
    sport: 'Sport Event',
    conference: 'Conference',
    election: 'Election',
    noTemplate: 'No template',
    selectTemplate: 'Select a template',
    loading: 'Loading templates...',
  },
  ru: {
    breaking_news: 'Срочные новости',
    sport: 'Спортивное событие',
    conference: 'Конференция',
    election: 'Выборы',
    noTemplate: 'Без шаблона',
    selectTemplate: 'Выберите шаблон',
    loading: 'Загрузка шаблонов...',
  },
};

export function TemplateSelector({
  selectedTemplateId,
  onSelectTemplate,
  locale,
}: TemplateSelectorProps) {
  const [templates, setTemplates] = useState<LiveTextTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const t = TEMPLATE_LABELS[locale as keyof typeof TEMPLATE_LABELS] || TEMPLATE_LABELS.ro;

  useEffect(() => {
    const fetchTemplates = async () => {
      try {
        const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? '';
        const response = await fetch(`${apiUrl}/api/live_text_templates`);

        if (!response.ok) {
          throw new Error('Failed to fetch templates');
        }

        const data = await response.json();
        setTemplates(data.member || []);
      } catch (err) {
        console.error('Error fetching templates:', err);
        setError(err instanceof Error ? err.message : 'Unknown error');
      } finally {
        setLoading(false);
      }
    };

    fetchTemplates();
  }, []);

  const handleSelectTemplate = (template: LiveTextTemplate | null) => {
    onSelectTemplate(template);
  };

  if (loading) {
    return (
      <div className="text-center py-8 text-gray-600 dark:text-gray-400">
        {t.loading}
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-8 text-red-600 dark:text-red-400">
        Error: {error}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
        {t.selectTemplate}
      </h3>

      {/* Grid of template cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* No Template Option */}
        <button
          type="button"
          onClick={() => handleSelectTemplate(null)}
          className={`p-4 rounded-lg border-2 transition-all text-left ${
            selectedTemplateId === null
              ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
              : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 bg-white dark:bg-gray-800'
          }`}
        >
          <div className="flex items-center gap-3 mb-2">
            <span className="text-3xl">⚪</span>
            <h4 className="font-semibold text-gray-900 dark:text-white">
              {t.noTemplate}
            </h4>
          </div>
          <p className="text-sm text-gray-600 dark:text-gray-400">
            {locale === 'ro'
              ? 'Utilizează setările implicite'
              : locale === 'en'
              ? 'Use default settings'
              : 'Использовать настройки по умолчанию'}
          </p>
        </button>

        {/* Template Cards */}
        {templates.map((template) => (
          <button
            key={template.id}
            type="button"
            onClick={() => handleSelectTemplate(template)}
            className={`p-4 rounded-lg border-2 transition-all text-left ${
              selectedTemplateId === template.id
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 bg-white dark:bg-gray-800'
            }`}
          >
            {/* Template Header */}
            <div className="flex items-center gap-3 mb-2">
              <span className="text-3xl">{TEMPLATE_ICONS[template.type]}</span>
              <h4 className="font-semibold text-gray-900 dark:text-white">
                {template.name}
              </h4>
            </div>

            {/* Template Description */}
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-3">
              {template.description}
            </p>

            {/* Color Preview */}
            <div className="flex items-center gap-1">
              {Object.entries(template.config.colors).slice(0, 3).map(([key, color]) => (
                <div
                  key={key}
                  className="w-6 h-6 rounded-full border border-gray-300 dark:border-gray-600"
                  style={{ backgroundColor: color }}
                  title={key}
                />
              ))}
            </div>

            {/* Features Summary */}
            <div className="mt-3 flex flex-wrap gap-1">
              {template.config.features.enableReactions && (
                <span className="px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                  ❤️ Reactions
                </span>
              )}
              {template.config.features.enableTimeline && (
                <span className="px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                  📅 Timeline
                </span>
              )}
              {template.config.features.autoRefresh && (
                <span className="px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                  🔄 Auto-refresh
                </span>
              )}
            </div>
          </button>
        ))}
      </div>
    </div>
  );
}
