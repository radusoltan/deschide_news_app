<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420101032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T57.03 (ADR-023 D2) — unique index on llm_agent_call_log.invocation_id '
             . 'to support O(log n) lookups during LlmInvocationLogger::attachVerdict().';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_llm_call_invocation_id ON llm_agent_call_log (invocation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_llm_call_invocation_id');
    }
}
