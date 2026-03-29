<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add seat_labels column to reservations for seat selection map workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ADD COLUMN IF NOT EXISTS seat_labels JSON');
        $this->addSql("UPDATE reservations SET seat_labels = '[]'::json WHERE seat_labels IS NULL");
        $this->addSql('ALTER TABLE reservations ALTER COLUMN seat_labels SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP COLUMN IF EXISTS seat_labels');
    }
}
