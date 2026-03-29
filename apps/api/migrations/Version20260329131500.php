<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329131500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add checked_in_at column for admin QR ticket validation workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ADD checked_in_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP checked_in_at');
    }
}
