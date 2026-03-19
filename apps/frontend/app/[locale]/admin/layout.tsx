import type { Metadata } from 'next';
import Navbar from './components/Navbar';
import Sidebar from './components/Sidebar';
import FlowbiteInit from './components/FlowbiteInit';
import './admin.css';

export const metadata: Metadata = {
  title: 'Admin Dashboard - Deschide News',
  description: 'Admin dashboard for Deschide News platform',
};

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="antialiased bg-gray-50 dark:bg-gray-900">
      <FlowbiteInit />
      <Navbar />
      <Sidebar />

      <main className="p-4 md:ml-64 h-auto pt-20 relative z-0">
        {children}
      </main>
    </div>
  );
}
