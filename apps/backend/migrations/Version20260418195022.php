<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 55 T55.2 — editorial-pipeline article classification columns.
 *
 * Adds:
 *  - article_type VARCHAR(30)           — {@see \App\Enum\ArticleType}
 *  - revision_history JSON               — append-only revision trail (cap 100 entries)
 *  - revision_count INT NOT NULL default 0
 *  - original_source_signal_id INT NULL FK source_signals(id) ON DELETE SET NULL
 *
 * Indexes:
 *  - idx_article_type                    — drives article_type filters
 *  - idx_article_type_updated            — drives findDevelopingStoryForTopic
 *  - IDX_BFDD3168B5FF931F (auto)         — FK lookup on original_source_signal_id
 *
 * Note: column type is plain JSON (not JSONB) to stay aligned with the
 * Doctrine Types::JSON entity mapping — S55 only stores/reads the trail,
 * no JSON-path querying. Promote to JSONB + GIN index if/when we query
 * inside revision_history.
 */
final class Version20260418195022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint 55 T55.2 — add article_type, revision_history, revision_count, original_source_signal_id to articles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles ADD article_type VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD revision_history JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD revision_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE articles ADD original_source_signal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD3168B5FF931F FOREIGN KEY (original_source_signal_id) REFERENCES source_signals (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BFDD3168B5FF931F ON articles (original_source_signal_id)');
        $this->addSql('CREATE INDEX idx_article_type ON articles (article_type)');
        $this->addSql('CREATE INDEX idx_article_type_updated ON articles (article_type, updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_article_type_updated');
        $this->addSql('DROP INDEX idx_article_type');
        $this->addSql('DROP INDEX IDX_BFDD3168B5FF931F');
        $this->addSql('ALTER TABLE articles DROP CONSTRAINT FK_BFDD3168B5FF931F');
        $this->addSql('ALTER TABLE articles DROP article_type');
        $this->addSql('ALTER TABLE articles DROP revision_history');
        $this->addSql('ALTER TABLE articles DROP revision_count');
        $this->addSql('ALTER TABLE articles DROP original_source_signal_id');
    }
}
