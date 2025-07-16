<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250716130711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $default_quota = 65536; // 32MB

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD quota_limit INT NOT NULL DEFAULT '.
            $default_quota);
        $this->addSql('ALTER TABLE "user" ADD quota_used INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT quota_check CHECK (quota_used <= quota_limit)');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT quota_nonzero CHECK (quota_used >= 0)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT quota_check');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT quota_nonzero');
        $this->addSql('ALTER TABLE "user" DROP quota_limit');
        $this->addSql('ALTER TABLE "user" DROP quota_used');
    }
}
