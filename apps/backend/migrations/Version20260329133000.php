<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add AuthorType enum field and emailDomain to authors table.
 */
final class Version20260329133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type (AuthorType enum) and email_domain columns to authors table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authors ADD type VARCHAR(20) NOT NULL DEFAULT \'journalist\'');
        $this->addSql('ALTER TABLE authors ADD email_domain VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authors DROP type');
        $this->addSql('ALTER TABLE authors DROP email_domain');
    }
}
