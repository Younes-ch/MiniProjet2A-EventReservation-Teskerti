<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328234000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reservation status column for admin management workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reservations ADD status VARCHAR(24) DEFAULT 'confirmed' NOT NULL");
        $this->addSql("UPDATE reservations SET status = 'confirmed' WHERE status IS NULL OR status = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP status');
    }
}