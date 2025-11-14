/**
 * Analytics Types
 *
 * TypeScript definitions for advanced analytics features
 */

// ============================================================================
// Engagement Types
// ============================================================================

export type EngagementType = 'view' | 'read' | 'click' | 'reaction' | 'share';

export interface PostEngagement {
  id: number;
  post_id: number;
  session_id: string;
  user_id?: number;
  engagement_type: EngagementType;
  time_spent?: number;
  scroll_depth?: number;
  clicked_element?: string;
  metadata?: Record<string, any>;
  ip_address?: string;
  user_agent?: string;
  created_at: string;
}

export interface TrackEngagementPayload {
  post_id: number;
  engagement_type: EngagementType;
  time_spent?: number;
  scroll_depth?: number;
  clicked_element?: string;
  metadata?: Record<string, any>;
}

// ============================================================================
// Heatmap Types
// ============================================================================

export interface HeatmapDataPoint {
  post_id: number;
  unique_engagements: number;
  total_engagements: number;
  avg_time_spent: number | null;
  avg_scroll_depth: number | null;
  intensity: number; // 0-100 scale
}

export interface HeatmapData {
  data: HeatmapDataPoint[];
  max_engagements: number;
  total_posts: number;
}

// ============================================================================
// Funnel Types
// ============================================================================

export interface FunnelStage {
  unique_users: number;
  total_events: number;
  conversion_rate: number;
}

export interface FunnelData {
  funnel: {
    view: FunnelStage;
    read: FunnelStage;
    click: FunnelStage;
    reaction: FunnelStage;
    share: FunnelStage;
  };
  total_views: number;
  drop_off_rate: {
    view_to_read: number;
    read_to_click: number;
    click_to_reaction: number;
    reaction_to_share: number;
  };
}

// ============================================================================
// Engagement Summary Types
// ============================================================================

export interface EngagementSummary {
  summary: {
    total_engagements: number;
    total_unique_users: number;
    avg_time_spent: number;
    avg_scroll_depth: number;
    engagement_rate: number;
  };
  funnel: FunnelData;
  top_posts: TopEngagedPost[];
  heatmap_summary: {
    max_engagements: number;
    total_posts: number;
  };
}

export interface TopEngagedPost {
  post_id: number;
  engagement_count: number;
}

// ============================================================================
// A/B Test Types
// ============================================================================

export type AbTestStatus = 'draft' | 'running' | 'paused' | 'completed';

export type VariantType =
  | 'template'
  | 'layout'
  | 'theme'
  | 'content'
  | 'design'
  | 'timing'
  | 'other';

export type TargetMetric =
  | 'views'
  | 'time_spent'
  | 'engagement_rate'
  | 'click_rate'
  | 'share_rate'
  | 'conversion_rate';

export interface VariantConfig {
  key: string;
  name: string;
  description?: string;
  config: Record<string, any>;
}

export interface AbTestVariantMetrics {
  variant: string;
  sample_size: number;
  metric_value: number;
  conversion_rate: number;
  avg_time_spent: number;
  engagement_rate: number;
}

export interface AbTestComparison {
  is_significant: boolean;
  p_value: number;
  confidence_level: string;
  z_score?: number;
  improvement: number;
  message: string;
}

export interface AbTestResults {
  variants: Record<string, AbTestVariantMetrics>;
  comparisons: Record<string, AbTestComparison>;
  winner: string | null;
  confidence_level: string | null;
}

export interface AbTest {
  id: number;
  name: string;
  description?: string;
  hypothesis?: string;
  status: AbTestStatus;
  variant_type: VariantType;
  control_variant: VariantConfig;
  test_variants: Record<string, VariantConfig>;
  traffic_allocation: number;
  target_metric: TargetMetric;
  min_sample_size?: number;
  significance_level?: string;
  start_date?: string;
  end_date?: string;
  results?: AbTestResults;
  winner_variant?: string;
  confidence_level?: string;
  livetext_count: number;
  created_at: string;
  updated_at: string;
  created_by?: {
    id: number;
    name: string;
  };
}

export interface AbTestSummary {
  id: number;
  name: string;
  status: AbTestStatus;
  variant_type: VariantType;
  target_metric: TargetMetric;
  start_date?: string;
  end_date?: string;
  winner_variant?: string;
  confidence_level?: string;
  livetext_count: number;
  results?: AbTestResults;
}

export interface CreateAbTestPayload {
  name: string;
  variant_type: VariantType;
  control_variant: VariantConfig;
  test_variants: Record<string, VariantConfig>;
  target_metric: TargetMetric;
  description?: string;
  hypothesis?: string;
  traffic_allocation?: number;
  min_sample_size?: number;
  significance_level?: string;
}

export interface AssignedVariant {
  variant: string;
  config: VariantConfig;
  test_id: number;
  test_name: string;
}

// ============================================================================
// API Response Types
// ============================================================================

export interface TrackEngagementResponse {
  success: boolean;
  engagement_id?: number;
  error?: string;
}

export interface TopPostsResponse {
  top_posts: TopEngagedPost[];
  limit: number;
}

export interface AbTestListResponse {
  tests: AbTestSummary[];
  total: number;
}

export interface AbTestDetailResponse extends AbTest {
  live_texts?: Array<{
    id: number;
    title: string;
    slug: string;
    status: string;
  }>;
}

export interface CalculateResultsResponse {
  success: boolean;
  results: AbTestResults;
  error?: string;
}

export interface ClearOldDataResponse {
  success: boolean;
  deleted_count: number;
  before_date: string;
}

// ============================================================================
// Chart Data Types
// ============================================================================

export interface HeatmapChartData {
  labels: string[];
  datasets: Array<{
    label: string;
    data: number[];
    backgroundColor: string[];
    borderColor: string[];
  }>;
}

export interface FunnelChartData {
  labels: string[];
  datasets: Array<{
    label: string;
    data: number[];
    backgroundColor: string;
    borderColor: string;
  }>;
}

// ============================================================================
// Filter and Options Types
// ============================================================================

export interface AnalyticsFilters {
  startDate?: Date;
  endDate?: Date;
  engagementType?: EngagementType;
  minEngagements?: number;
}

export interface AbTestFilters {
  status?: AbTestStatus;
  variantType?: VariantType;
  liveTextId?: number;
}
