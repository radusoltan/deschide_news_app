import { EscalationQueue } from '@/components/admin/escalations/EscalationQueue';

export const metadata = {
  title: 'Escalări editoriale — Admin',
};

/**
 * Admin escalations page (Sprint 55 T55.13).
 *
 * Thin server-component shell. All queue state + Mercure subscription live
 * inside {@see EscalationQueue}. Auth is already enforced by the admin
 * layout (redirects to /login when session absent), so this page can
 * assume ROLE_EDITOR without an explicit gate. Backend endpoints apply
 * the actual RBAC check on every request.
 */
export default function AdminEscalationsPage() {
  return <EscalationQueue />;
}
