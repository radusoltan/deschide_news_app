import { BriefingPreviewPanel } from './BriefingPreviewPanel';

export const metadata = {
  title: 'Topic Briefings - Admin',
  description: 'AI-generated topic briefings by cadence',
};

export default function BriefingsPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-primary dark:text-white">
          Topic Briefings
        </h1>
      </div>
      <BriefingPreviewPanel />
    </div>
  );
}
