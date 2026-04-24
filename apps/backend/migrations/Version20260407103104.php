<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407103104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase sourceUrl column length to 2048 for long Google News redirect URLs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ALTER source_url TYPE VARCHAR(2048)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ALTER source_url TYPE VARCHAR(500)');
    }
}
