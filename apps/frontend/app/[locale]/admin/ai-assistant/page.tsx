import { getAccessToken } from '@/lib/dal';
import AiAssistantClient from './AiAssistantClient';

export const metadata = {
  title: 'AI Assistant - Deschide News Admin',
  description: 'AI Newsroom Assistant for journalists',
};

export default async function AiAssistantPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const token = await getAccessToken();

  return <AiAssistantClient locale={locale} token={token ?? ''} />;
}
