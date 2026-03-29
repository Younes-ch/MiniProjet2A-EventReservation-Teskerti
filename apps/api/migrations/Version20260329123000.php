<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reservation qr code token for ticket issuance and download validation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ADD COLUMN IF NOT EXISTS qr_code_token VARCHAR(64)');
        $this->addSql("UPDATE reservations SET qr_code_token = md5(reservation_id || '-legacy-token') WHERE qr_code_token IS NULL OR qr_code_token = ''");
        $this->addSql('ALTER TABLE reservations ALTER COLUMN qr_code_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_reservations_qr_code_token ON reservations (qr_code_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_reservations_qr_code_token');
        $this->addSql('ALTER TABLE reservations DROP COLUMN IF EXISTS qr_code_token');
    }
}
