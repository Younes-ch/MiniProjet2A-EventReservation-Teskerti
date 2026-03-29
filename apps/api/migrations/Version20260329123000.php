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
        $this->addSql("ALTER TABLE reservations ADD qr_code_token VARCHAR(64) DEFAULT '' NOT NULL");
        $this->addSql("UPDATE reservations SET qr_code_token = md5(reservation_id || '-legacy-token') WHERE qr_code_token = ''");
        $this->addSql('CREATE UNIQUE INDEX uniq_reservations_qr_code_token ON reservations (qr_code_token)');
        $this->addSql('ALTER TABLE reservations ALTER COLUMN qr_code_token DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_reservations_qr_code_token');
        $this->addSql('ALTER TABLE reservations DROP qr_code_token');
    }
}
