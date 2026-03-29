<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329174500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_seats and event_checkins tables for seat inventory and check-in audit trails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS event_seats (id SERIAL NOT NULL, event_id INT NOT NULL, reserved_by_reservation_id INT DEFAULT NULL, seat_label VARCHAR(16) NOT NULL, zone VARCHAR(64) NOT NULL, seat_status VARCHAR(24) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_event_seats_event_label ON event_seats (event_id, seat_label)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_event_seats_reservation ON event_seats (reserved_by_reservation_id)');
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_event_seats_event') THEN ALTER TABLE event_seats ADD CONSTRAINT fk_event_seats_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE; END IF; END $$;");
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_event_seats_reservation') THEN ALTER TABLE event_seats ADD CONSTRAINT fk_event_seats_reservation FOREIGN KEY (reserved_by_reservation_id) REFERENCES reservations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE; END IF; END $$;");

        $this->addSql('CREATE TABLE IF NOT EXISTS event_checkins (id SERIAL NOT NULL, reservation_id INT NOT NULL, checked_in_by VARCHAR(180) NOT NULL, checked_in_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, method VARCHAR(24) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_event_checkins_reservation ON event_checkins (reservation_id)');
        $this->addSql("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_event_checkins_reservation') THEN ALTER TABLE event_checkins ADD CONSTRAINT fk_event_checkins_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE; END IF; END $$;");

        $this->addSql("INSERT INTO event_seats (event_id, seat_label, zone, seat_status) SELECT event.id, 'S-' || LPAD(seat_index::text, 4, '0'), 'main', 'available' FROM events event CROSS JOIN LATERAL generate_series(1, event.seats_total) AS seat_index ON CONFLICT (event_id, seat_label) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE IF EXISTS event_checkins DROP CONSTRAINT IF EXISTS fk_event_checkins_reservation');
        $this->addSql('ALTER TABLE IF EXISTS event_seats DROP CONSTRAINT IF EXISTS fk_event_seats_event');
        $this->addSql('ALTER TABLE IF EXISTS event_seats DROP CONSTRAINT IF EXISTS fk_event_seats_reservation');
        $this->addSql('DROP TABLE IF EXISTS event_checkins');
        $this->addSql('DROP TABLE IF EXISTS event_seats');
    }
}
