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
        $this->addSql('CREATE TABLE event_seats (id SERIAL NOT NULL, event_id INT NOT NULL, reserved_by_reservation_id INT DEFAULT NULL, seat_label VARCHAR(16) NOT NULL, zone VARCHAR(64) NOT NULL, seat_status VARCHAR(24) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_seats_event_label ON event_seats (event_id, seat_label)');
        $this->addSql('CREATE INDEX idx_event_seats_reservation ON event_seats (reserved_by_reservation_id)');
        $this->addSql('ALTER TABLE event_seats ADD CONSTRAINT fk_event_seats_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_seats ADD CONSTRAINT fk_event_seats_reservation FOREIGN KEY (reserved_by_reservation_id) REFERENCES reservations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE event_checkins (id SERIAL NOT NULL, reservation_id INT NOT NULL, checked_in_by VARCHAR(180) NOT NULL, checked_in_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, method VARCHAR(24) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_event_checkins_reservation ON event_checkins (reservation_id)');
        $this->addSql('ALTER TABLE event_checkins ADD CONSTRAINT fk_event_checkins_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("INSERT INTO event_seats (event_id, seat_label, zone, seat_status) SELECT event.id, 'S-' || LPAD(seat_index::text, 4, '0'), 'main', 'available' FROM events event CROSS JOIN LATERAL generate_series(1, event.seats_total) AS seat_index");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_checkins DROP CONSTRAINT fk_event_checkins_reservation');
        $this->addSql('ALTER TABLE event_seats DROP CONSTRAINT fk_event_seats_event');
        $this->addSql('ALTER TABLE event_seats DROP CONSTRAINT fk_event_seats_reservation');
        $this->addSql('DROP TABLE event_checkins');
        $this->addSql('DROP TABLE event_seats');
    }
}
