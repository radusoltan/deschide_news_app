/**
 * Type definitions for the editorial escalation queue admin API
 * (Sprint 55 T55.12 backend contract).
 *
 * Mirrors the `AdminEscalationController` response envelope and the
 * `EditorialEscalationLog` row shape returned by `serializeRow()`.
 */

/**
 * Backing value of the {@see EscalationCategory} PHP enum (DB short code).
 * Keep this in sync with {@see App\Enum\Editorial\EscalationCategory}.
 */
export type EscalationCategoryValue =
  | 'categ_1'
  | 'categ_2'
  | 'categ_3'
  | 'categ_4'
  | 'categ_5'
  | 'categ_6'
  | 'categ_7'
  | 'family_a'
  | 'family_b'
  | 'family_c'
  | 'family_d';

/**
 * PHP constant name of the {@see EscalationCategory} enum (verbose label).
 * Preferred as the stable UI key — does not change if short codes are ever
 * rewritten.
 */
export type EscalationCategoryName =
  | 'CATEGORY_1_NUCLEAR_WAR'
  | 'CATEGORY_2_HEAD_OF_STATE_DEATH'
  | 'CATEGORY_3_NBC_ATTACK'
  | 'CATEGORY_4_COUP'
  | 'CATEGORY_5_MASS_CASUALTIES'
  | 'CATEGORY_6_CRIMINAL_ACCUSATION'
  | 'CATEGORY_7_PRE_CEC_ELECTORAL'
  | 'FAMILY_A_CHURCH'
  | 'FAMILY_B_EU_NATO_RUSSIA'
  | 'FAMILY_C_TRANSNISTRIA_GAGAUZIA'
  | 'FAMILY_D_CEC_PARTY_LEADERS';

export type EscalationStatus = 'pending' | 'approved' | 'rejected' | 'expired';

export type EscalationDecision = 'approved' | 'rejected' | 'expired' | null;

/**
 * One node of the claim-origin graph — a verified source that contributed
 * to the clustered claim. Tiers align with ADR-020 D3:
 *   1 = wire/official
 *   2 = mainstream investigative
 *   3 = aggregator / secondary mentions
 */
export interface OriginGraphNode {
  id: number | string;
  slug: string;
  name?: string;
  alignment: string;
  tier: 1 | 2 | 3;
}

export interface OriginGraphEdge {
  source: number | string;
  target: number | string;
}

export interface OriginGraphSnapshot {
  nodes?: OriginGraphNode[];
  edges?: OriginGraphEdge[];
  [key: string]: unknown;
}

export interface ArticleSnapshot {
  title?: string;
  lead?: string;
  content?: string;
  summary?: string;
  verdict_type?: string;
  verdict_rationale?: string;
  primary_signal_id?: number;
  supporting_signal_ids?: number[];
  topic_id?: number;
  [key: string]: unknown;
}

export interface Escalation {
  id: number;
  category: EscalationCategoryValue;
  category_name: EscalationCategoryName;
  article_snapshot: ArticleSnapshot;
  origin_graph_snapshot: OriginGraphSnapshot;
  decision: EscalationDecision;
  decided_by: number | null;
  decided_at: string | null;
  created_at: string;
  expires_at: string | null;
}

/**
 * Standard response envelope shared with ArticleFactCheckController (Sprint 51a).
 */
export interface EscalationEnvelope<T = unknown> {
  success: boolean;
  status: string;
  error?: string;
  violations?: Array<{ field: string; message: string }>;
  data?: T;
}

export interface EscalationListPayload {
  page: number;
  limit: number;
  items: Escalation[];
}

export interface EscalationStatsPayload {
  pending_total: number;
  pending_by_category: Partial<Record<EscalationCategoryName, number>>;
}

export interface EscalationApprovePayload {
  id: number;
  decision: 'approved';
  decided_at: string | null;
  publish_dispatch: { status: string; reason?: string; message_id?: number } | null;
}

export interface EscalationRejectPayload {
  id: number;
  decision: 'rejected';
  decided_at: string | null;
}

export interface EscalationExtendPayload {
  id: number;
  expires_at: string;
  added_seconds: number;
}

/**
 * Human-readable Romanian labels for each EscalationCategory. Keyed by the
 * verbose enum name so the UI can switch on a stable key regardless of any
 * future short-code rewrite.
 */
export const ESCALATION_CATEGORY_LABEL: Record<EscalationCategoryName, string> = {
  CATEGORY_1_NUCLEAR_WAR: 'Război nuclear',
  CATEGORY_2_HEAD_OF_STATE_DEATH: 'Deces șef de stat',
  CATEGORY_3_NBC_ATTACK: 'Atac NBC',
  CATEGORY_4_COUP: 'Lovitură de stat',
  CATEGORY_5_MASS_CASUALTIES: 'Victime în masă',
  CATEGORY_6_CRIMINAL_ACCUSATION: 'Acuzație penală',
  CATEGORY_7_PRE_CEC_ELECTORAL: 'Rezultate pre-CEC',
  FAMILY_A_CHURCH: 'Biserică / Patriarhat',
  FAMILY_B_EU_NATO_RUSSIA: 'UE / NATO / Rusia',
  FAMILY_C_TRANSNISTRIA_GAGAUZIA: 'Transnistria / Găgăuzia',
  FAMILY_D_CEC_PARTY_LEADERS: 'CEC / lideri partide',
};

/**
 * Alignment → hex color map used by OriginGraphViz. One-liner map aligned with
 * backend `EditorialAlignment` enum values.
 */
export const ALIGNMENT_COLOR: Record<string, string> = {
  wire_neutral: '#6b7280',
  western_mainstream: '#2563eb',
  eu_official: '#0891b2',
  kremlin_aligned: '#dc2626',
  independent_ru: '#f97316',
  ukrainian_state: '#eab308',
  ukrainian_independent: '#fbbf24',
  md_government: '#059669',
  md_independent_pro_eu: '#10b981',
  md_independent_pro_ru: '#f87171',
  md_investigative: '#7c3aed',
  ro_mainstream: '#b45309',
  osint_curated: '#4b5563',
};
