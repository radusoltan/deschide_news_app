<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IRREVERSIBLE: This migration drops editorial pipeline tables and
 * columns. The down() method recreates the schema but cannot restore
 * data. Treat as one-way migration.
 *
 * Refs: T60.X-NUKE-EDITORIAL
 * Audit: var/audit/T60.X-editorial-pipeline-state-2026-05-08.md
 * Plan: var/audit/T60.X-nuke-editorial-discovery-2026-05-08.md
 *
 * Schema changes:
 *  - 15 tables dropped (press_releases, press_release_topics, source_signals,
 *    verified_sources, sources, source_claim_history, editorial_escalation_log,
 *    topic_briefings, aggregator_runs, curation_suggestions, relevance_keywords,
 *    generated_content, ai_conversations, ai_messages, ai_prompt_templates).
 *  - 10 columns dropped from articles (source_email, internal_summary,
 *    ingested_at, ai_generated, ai_confidence_score, ai_source_count,
 *    article_type, revision_history, revision_count, original_source_signal_id).
 *  - 3 columns dropped from topics (notebook_lm_id, notebook_last_synced_at,
 *    notebook_source_count).
 *
 * Data changes:
 *  - app_settings purged: every row except agent.emergency_halt and
 *    agent.journalistic_translator.{model_tier,enabled,timeout_seconds}.
 */
final class Version20260508063850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T60.X-NUKE-EDITORIAL: drop 15 editorial tables, 13 columns, purge AppSettings.';
    }

    public function up(Schema $schema): void
    {
        // T60.X: drop FK on articles.original_source_signal_id FIRST so the
        // subsequent DROP TABLE source_signals doesn't trip the dependency
        // check (Postgres errors with SQLSTATE[2BP01] otherwise).
        $this->addSql('ALTER TABLE articles DROP CONSTRAINT fk_bfdd3168b5ff931f');

        // FK drops for editorial-table-only constraints
        $this->addSql('ALTER TABLE ai_conversations DROP CONSTRAINT fk_f36727d7a76ed395');
        $this->addSql('ALTER TABLE ai_messages DROP CONSTRAINT fk_c4e498f69ac0396');
        $this->addSql('ALTER TABLE editorial_escalation_log DROP CONSTRAINT fk_f99c2536515f5bc8');
        $this->addSql('ALTER TABLE press_release_topics DROP CONSTRAINT fk_5a623e6578750292');
        $this->addSql('ALTER TABLE press_release_topics DROP CONSTRAINT fk_5a623e651f55203d');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT fk_9b6ca7232ffd4fd3');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT fk_9b6ca723953c1c61');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT fk_9b6ca7237294869c');
        $this->addSql('ALTER TABLE source_claim_history DROP CONSTRAINT fk_732d4e17dd61a775');
        $this->addSql('ALTER TABLE source_signals DROP CONSTRAINT fk_9f727d65dd61a775');
        $this->addSql('ALTER TABLE topic_briefings DROP CONSTRAINT fk_24ce29ae1f55203d');
        $this->addSql('ALTER TABLE verified_sources DROP CONSTRAINT fk_8a12e8b1953c1c61');
        $this->addSql('DROP TABLE aggregator_runs');
        $this->addSql('DROP TABLE ai_conversations');
        $this->addSql('DROP TABLE ai_messages');
        $this->addSql('DROP TABLE ai_prompt_templates');
        $this->addSql('DROP TABLE curation_suggestions');
        $this->addSql('DROP TABLE editorial_escalation_log');
        $this->addSql('DROP TABLE generated_content');
        $this->addSql('DROP TABLE press_release_topics');
        $this->addSql('DROP TABLE press_releases');
        $this->addSql('DROP TABLE relevance_keywords');
        $this->addSql('DROP TABLE source_claim_history');
        $this->addSql('DROP TABLE source_signals');
        $this->addSql('DROP TABLE sources');
        $this->addSql('DROP TABLE topic_briefings');
        $this->addSql('DROP TABLE verified_sources');
        $this->addSql('DROP INDEX uniq_bfdd31687e9aa74b');
        $this->addSql('DROP INDEX idx_article_type_updated');
        $this->addSql('DROP INDEX idx_article_type');
        $this->addSql('DROP INDEX idx_bfdd3168b5ff931f');
        $this->addSql('DROP INDEX idx_article_ingested_at');
        $this->addSql('ALTER TABLE articles DROP source_email');
        $this->addSql('ALTER TABLE articles DROP internal_summary');
        $this->addSql('ALTER TABLE articles DROP ingested_at');
        $this->addSql('ALTER TABLE articles DROP ai_generated');
        $this->addSql('ALTER TABLE articles DROP ai_confidence_score');
        $this->addSql('ALTER TABLE articles DROP ai_source_count');
        $this->addSql('ALTER TABLE articles DROP article_type');
        $this->addSql('ALTER TABLE articles DROP revision_history');
        $this->addSql('ALTER TABLE articles DROP revision_count');
        $this->addSql('ALTER TABLE articles DROP original_source_signal_id');
        $this->addSql('ALTER TABLE topics DROP notebook_lm_id');
        $this->addSql('ALTER TABLE topics DROP notebook_last_synced_at');
        $this->addSql('ALTER TABLE topics DROP notebook_source_count');

        // T60.X-NUKE-EDITORIAL: purge editorial AppSettings rows.
        // Single DELETE covers both the editorial.emergency_halt -> agent.emergency_halt
        // rename outcome (drops the old key; the new key was already seeded by
        // AppSettingsFixture during B6) and the broader purge of editorial.*,
        // briefing.*, article_generation.*, notebooklm.*, agent.* tier configs.
        $this->addSql("DELETE FROM app_settings WHERE key NOT IN ('agent.emergency_halt', 'agent.journalistic_translator.model_tier', 'agent.journalistic_translator.enabled', 'agent.journalistic_translator.timeout_seconds')");
    }

    public function down(Schema $schema): void
    {
        // T60.X-NUKE-EDITORIAL: down() recreates the schema (table CREATEs +
        // column re-adds + FK reattachments) but cannot restore the data that
        // existed before up(). Treat this migration as one-way; rollback is
        // operationally a `git revert` + restore from a pre-migration backup.
        // Auto-generated diff continues below.
        $this->addSql('CREATE TABLE aggregator_runs (id UUID NOT NULL, source VARCHAR(100) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(20) NOT NULL, articles_found INT NOT NULL, duplicates_skipped INT NOT NULL, errors_count INT NOT NULL, error_details JSON DEFAULT NULL, triggered_by VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_agg_run_source ON aggregator_runs (source)');
        $this->addSql('CREATE INDEX idx_agg_run_started ON aggregator_runs (started_at)');
        $this->addSql('CREATE INDEX idx_agg_run_status ON aggregator_runs (status)');
        $this->addSql('CREATE TABLE ai_conversations (id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, agent_type VARCHAR(50) DEFAULT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, message_count INT DEFAULT 0 NOT NULL, total_tokens_used INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ai_conv_status ON ai_conversations (status)');
        $this->addSql('CREATE INDEX idx_ai_conv_created ON ai_conversations (created_at)');
        $this->addSql('CREATE INDEX idx_ai_conv_user ON ai_conversations (user_id)');
        $this->addSql('CREATE TABLE ai_messages (id UUID NOT NULL, role VARCHAR(20) NOT NULL, content TEXT NOT NULL, model VARCHAR(50) DEFAULT NULL, agent_type VARCHAR(50) DEFAULT NULL, tokens_used INT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, conversation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ai_msg_created ON ai_messages (created_at)');
        $this->addSql('CREATE INDEX idx_ai_msg_conversation ON ai_messages (conversation_id)');
        $this->addSql('CREATE INDEX idx_ai_msg_role ON ai_messages (role)');
        $this->addSql('CREATE TABLE ai_prompt_templates (id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, category VARCHAR(50) NOT NULL, agent_type VARCHAR(50) NOT NULL, prompt_template TEXT NOT NULL, required_fields JSON NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ai_tpl_category ON ai_prompt_templates (category)');
        $this->addSql('CREATE INDEX idx_ai_tpl_agent_type ON ai_prompt_templates (agent_type)');
        $this->addSql('CREATE INDEX idx_ai_tpl_active ON ai_prompt_templates (is_active)');
        $this->addSql('CREATE TABLE curation_suggestions (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, cluster_ids JSON NOT NULL, target_cluster_id INT DEFAULT NULL, reason TEXT NOT NULL, suggested_topic VARCHAR(100) DEFAULT NULL, confidence DOUBLE PRECISION NOT NULL, suggested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, resolved_by VARCHAR(100) DEFAULT NULL, cluster_headlines JSON DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_curation_type ON curation_suggestions (type)');
        $this->addSql('CREATE INDEX idx_curation_status ON curation_suggestions (status)');
        $this->addSql('CREATE TABLE editorial_escalation_log (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, article_snapshot JSON NOT NULL, category_code VARCHAR(20) NOT NULL, origin_graph_snapshot JSON NOT NULL, decision VARCHAR(20) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, decided_by_user_id INT DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_editorial_escalation_log_created_at ON editorial_escalation_log (created_at)');
        $this->addSql('CREATE INDEX idx_editorial_escalation_log_decision ON editorial_escalation_log (decision)');
        $this->addSql('CREATE INDEX idx_editorial_escalation_log_category ON editorial_escalation_log (category_code)');
        $this->addSql('CREATE INDEX idx_esc_log_expires_pending ON editorial_escalation_log (expires_at) WHERE (decision IS NULL)');
        $this->addSql('CREATE INDEX idx_editorial_escalation_log_decided_by ON editorial_escalation_log (decided_by_user_id)');
        $this->addSql('CREATE TABLE generated_content (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, type VARCHAR(30) NOT NULL, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, metadata JSON DEFAULT NULL, generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(5) DEFAULT \'ro\' NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_gc_type_date ON generated_content (type, generated_at)');
        $this->addSql('CREATE INDEX idx_gc_locale ON generated_content (locale)');
        $this->addSql('CREATE TABLE press_release_topics (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, confidence DOUBLE PRECISION NOT NULL, detected_by VARCHAR(20) NOT NULL, detected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, press_release_id INT NOT NULL, topic_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_prt_press_release ON press_release_topics (press_release_id)');
        $this->addSql('CREATE INDEX idx_prt_topic_created ON press_release_topics (topic_id, detected_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_prt_pr_topic ON press_release_topics (press_release_id, topic_id)');
        $this->addSql('CREATE INDEX idx_5a623e651f55203d ON press_release_topics (topic_id)');
        $this->addSql('CREATE TABLE press_releases (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, title VARCHAR(255) NOT NULL, lead TEXT DEFAULT NULL, content TEXT NOT NULL, source_email_id VARCHAR(64) DEFAULT NULL, sender_address VARCHAR(255) DEFAULT NULL, sender_name VARCHAR(255) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, category_slug VARCHAR(50) NOT NULL, email_subject VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, content_length INT NOT NULL, processed_by_id INT DEFAULT NULL, article_id INT DEFAULT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, attachment_path VARCHAR(500) DEFAULT NULL, attachment_mime_type VARCHAR(100) DEFAULT NULL, attachment_size INT DEFAULT NULL, content_hash VARCHAR(64) DEFAULT NULL, source_type VARCHAR(255) DEFAULT \'email\' NOT NULL, original_language VARCHAR(5) DEFAULT \'ro\', source_name VARCHAR(100) DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, suggested_topics JSON DEFAULT NULL, relevance_score DOUBLE PRECISION DEFAULT NULL, original_title VARCHAR(255) DEFAULT NULL, original_content TEXT DEFAULT NULL, source_image_url VARCHAR(2048) DEFAULT NULL, source_publisher_domain VARCHAR(255) DEFAULT NULL, detected_language VARCHAR(5) DEFAULT NULL, source_id INT DEFAULT NULL, ai_confidence_score DOUBLE PRECISION DEFAULT NULL, ai_source_count INT DEFAULT NULL, enriched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_9b6ca723eb12f71b ON press_releases (source_email_id)');
        $this->addSql('CREATE INDEX idx_9b6ca7232ffd4fd3 ON press_releases (processed_by_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_9b6ca7237294869c ON press_releases (article_id)');
        $this->addSql('CREATE INDEX idx_press_release_status ON press_releases (status)');
        $this->addSql('CREATE INDEX idx_press_release_received ON press_releases (received_at)');
        $this->addSql('CREATE INDEX idx_press_release_content_hash ON press_releases (content_hash)');
        $this->addSql('CREATE INDEX idx_press_release_source_type ON press_releases (source_type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_content_hash_source_type ON press_releases (content_hash, source_type)');
        $this->addSql('CREATE INDEX idx_9b6ca723953c1c61 ON press_releases (source_id)');
        $this->addSql('CREATE INDEX idx_pr_status_created ON press_releases (status, created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_pr_source_url ON press_releases (source_url)');
        $this->addSql('CREATE TABLE relevance_keywords (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, keyword VARCHAR(100) NOT NULL, tier SMALLINT NOT NULL, language VARCHAR(5) NOT NULL, is_active BOOLEAN NOT NULL, added_by VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_rk_language ON relevance_keywords (language)');
        $this->addSql('CREATE INDEX idx_rk_tier ON relevance_keywords (tier)');
        $this->addSql('CREATE INDEX idx_rk_is_active ON relevance_keywords (is_active)');
        $this->addSql('CREATE TABLE source_claim_history (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, claim_text TEXT NOT NULL, outcome VARCHAR(20) NOT NULL, observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, notes TEXT DEFAULT NULL, verified_source_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_source_claim_history_outcome ON source_claim_history (outcome)');
        $this->addSql('CREATE INDEX idx_source_claim_history_source ON source_claim_history (verified_source_id)');
        $this->addSql('CREATE INDEX idx_source_claim_history_observed_at ON source_claim_history (observed_at)');
        $this->addSql('CREATE TABLE source_signals (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, captured_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source_url TEXT NOT NULL, canonical_url TEXT DEFAULT NULL, title TEXT NOT NULL, raw_summary TEXT DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_attribution TEXT DEFAULT NULL, source_links_out JSON DEFAULT NULL, raw_content_hash VARCHAR(64) NOT NULL, raw_payload JSON DEFAULT NULL, verified_source_id INT NOT NULL, claim_graph_snapshot JSON DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_source_signals_captured_at ON source_signals (captured_at)');
        $this->addSql('CREATE INDEX idx_source_signals_source_captured ON source_signals (verified_source_id, captured_at)');
        $this->addSql('CREATE INDEX idx_9f727d65dd61a775 ON source_signals (verified_source_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_source_signal_source_hash ON source_signals (verified_source_id, raw_content_hash)');
        $this->addSql('CREATE INDEX idx_source_signals_published_at ON source_signals (published_at)');
        $this->addSql('CREATE TABLE sources (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, name VARCHAR(255) NOT NULL, rss_url VARCHAR(2048) DEFAULT NULL, credibility_weight DOUBLE PRECISION DEFAULT \'0.5\' NOT NULL, country VARCHAR(2) DEFAULT NULL, source_category VARCHAR(30) DEFAULT NULL, fetch_frequency_minutes INT DEFAULT 60 NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, type VARCHAR(20) DEFAULT \'rss\' NOT NULL, domain_pattern VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_source_country ON sources (country)');
        $this->addSql('CREATE INDEX idx_source_category ON sources (source_category)');
        $this->addSql('CREATE UNIQUE INDEX uniq_d25d65f25e237e06 ON sources (name)');
        $this->addSql('CREATE INDEX idx_source_is_active ON sources (is_active)');
        $this->addSql('CREATE TABLE topic_briefings (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, cadence VARCHAR(10) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, title VARCHAR(255) DEFAULT NULL, summary_short TEXT DEFAULT NULL, summary_long TEXT DEFAULT NULL, key_facts JSON DEFAULT NULL, why_it_matters TEXT DEFAULT NULL, gemini_draft_raw TEXT DEFAULT NULL, claude_polished BOOLEAN DEFAULT false NOT NULL, pr_count INT DEFAULT 0 NOT NULL, period_from TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, period_to TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, topic_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_24ce29ae1f55203d ON topic_briefings (topic_id)');
        $this->addSql('CREATE INDEX idx_tb_topic_cadence ON topic_briefings (topic_id, cadence)');
        $this->addSql('CREATE INDEX idx_tb_cadence_status ON topic_briefings (cadence, status)');
        $this->addSql('CREATE INDEX idx_tb_created ON topic_briefings (created_at)');
        $this->addSql('CREATE INDEX idx_tb_period ON topic_briefings (period_from, period_to)');
        $this->addSql('CREATE TABLE verified_sources (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, slug VARCHAR(100) NOT NULL, tier SMALLINT NOT NULL, editorial_alignment VARCHAR(40) NOT NULL, trust_score_baseline NUMERIC(3, 2) NOT NULL, trust_score_rolling NUMERIC(3, 2) DEFAULT NULL, enabled BOOLEAN DEFAULT true NOT NULL, editorial_notes TEXT DEFAULT NULL, language VARCHAR(2) DEFAULT NULL, url VARCHAR(2048) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_8a12e8b1953c1c61 ON verified_sources (source_id)');
        $this->addSql('CREATE INDEX idx_verified_sources_alignment ON verified_sources (editorial_alignment)');
        $this->addSql('CREATE INDEX idx_verified_sources_enabled ON verified_sources (enabled)');
        $this->addSql('CREATE UNIQUE INDEX uniq_8a12e8b1989d9b62 ON verified_sources (slug)');
        $this->addSql('CREATE INDEX idx_verified_sources_tier ON verified_sources (tier)');
        $this->addSql('ALTER TABLE ai_conversations ADD CONSTRAINT fk_f36727d7a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_messages ADD CONSTRAINT fk_c4e498f69ac0396 FOREIGN KEY (conversation_id) REFERENCES ai_conversations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE editorial_escalation_log ADD CONSTRAINT fk_f99c2536515f5bc8 FOREIGN KEY (decided_by_user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE press_release_topics ADD CONSTRAINT fk_5a623e6578750292 FOREIGN KEY (press_release_id) REFERENCES press_releases (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE press_release_topics ADD CONSTRAINT fk_5a623e651f55203d FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT fk_9b6ca7232ffd4fd3 FOREIGN KEY (processed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT fk_9b6ca723953c1c61 FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT fk_9b6ca7237294869c FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE source_claim_history ADD CONSTRAINT fk_732d4e17dd61a775 FOREIGN KEY (verified_source_id) REFERENCES verified_sources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE source_signals ADD CONSTRAINT fk_9f727d65dd61a775 FOREIGN KEY (verified_source_id) REFERENCES verified_sources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic_briefings ADD CONSTRAINT fk_24ce29ae1f55203d FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE verified_sources ADD CONSTRAINT fk_8a12e8b1953c1c61 FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE articles ADD source_email VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD internal_summary TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD ingested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD ai_generated BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE articles ADD ai_confidence_score DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD ai_source_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD article_type VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD revision_history JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD revision_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE articles ADD original_source_signal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT fk_bfdd3168b5ff931f FOREIGN KEY (original_source_signal_id) REFERENCES source_signals (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_bfdd31687e9aa74b ON articles (source_email)');
        $this->addSql('CREATE INDEX idx_article_type_updated ON articles (article_type, updated_at)');
        $this->addSql('CREATE INDEX idx_article_type ON articles (article_type)');
        $this->addSql('CREATE INDEX idx_bfdd3168b5ff931f ON articles (original_source_signal_id)');
        $this->addSql('CREATE INDEX idx_article_ingested_at ON articles (ingested_at)');
        $this->addSql('ALTER TABLE topics ADD notebook_lm_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE topics ADD notebook_last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE topics ADD notebook_source_count INT DEFAULT 0 NOT NULL');
    }
}
