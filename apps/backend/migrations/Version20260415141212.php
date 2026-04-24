<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415141212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enriched_at field to press_releases for content enrichment tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ADD enriched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN press_releases.enriched_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases DROP enriched_at');
    }
}
