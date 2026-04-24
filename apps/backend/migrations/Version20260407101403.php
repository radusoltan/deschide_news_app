<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407101403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sourcePublisherDomain to press_releases for RSS source tag data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ADD source_publisher_domain VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases DROP source_publisher_domain');
    }
}
