import type { Metadata } from 'next';
import Navbar from './components/Navbar';
import Sidebar from './components/Sidebar';
import FlowbiteInit from './components/FlowbiteInit';
import { getSession } from '@/lib/auth/session';
import './admin.css';

export const metadata: Metadata = {
  title: 'Admin Dashboard - Deschide News',
  description: 'Admin dashboard for Deschide News platform',
};

export default async function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const session = await getSession();
  const username = session?.user?.username ?? 'Admin';

  return (
    <div className="antialiased bg-surface-sunken dark:bg-surface-dark">
      <FlowbiteInit />
      <Navbar username={username} />
      <Sidebar />

      <main className="p-4 md:ml-64 h-auto pt-20 relative z-0">
        {children}
      </main>
    </div>
  );
}
