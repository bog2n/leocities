<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250716154320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dir (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BAAB7A10727ACA70 ON dir (parent_id)');
        $this->addSql('CREATE TABLE inode (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F63BD578727ACA70 ON inode (parent_id)');
        $this->addSql('ALTER TABLE dir ADD CONSTRAINT FK_BAAB7A10727ACA70 FOREIGN KEY (parent_id) REFERENCES inode (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inode ADD CONSTRAINT FK_F63BD578727ACA70 FOREIGN KEY (parent_id) REFERENCES dir (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE extent ADD inode_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE extent ADD CONSTRAINT FK_260E594871E72450 FOREIGN KEY (inode_id) REFERENCES inode (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_260E594871E72450 ON extent (inode_id)');
        $this->addSql('ALTER TABLE "user" ADD root_inode_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649ABA4EB6 FOREIGN KEY (root_inode_id) REFERENCES inode (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649ABA4EB6 ON "user" (root_inode_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE extent DROP CONSTRAINT FK_260E594871E72450');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649ABA4EB6');
        $this->addSql('ALTER TABLE dir DROP CONSTRAINT FK_BAAB7A10727ACA70');
        $this->addSql('ALTER TABLE inode DROP CONSTRAINT FK_F63BD578727ACA70');
        $this->addSql('DROP TABLE dir');
        $this->addSql('DROP TABLE inode');
        $this->addSql('DROP INDEX UNIQ_8D93D649ABA4EB6');
        $this->addSql('ALTER TABLE "user" DROP root_inode_id');
        $this->addSql('DROP INDEX IDX_260E594871E72450');
        $this->addSql('ALTER TABLE extent DROP inode_id');
    }
}
