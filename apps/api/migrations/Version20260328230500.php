<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328230500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create events and reservations tables and seed baseline events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE events (id SERIAL NOT NULL, slug VARCHAR(180) NOT NULL, title VARCHAR(255) NOT NULL, summary TEXT NOT NULL, category VARCHAR(120) NOT NULL, location VARCHAR(255) NOT NULL, city VARCHAR(120) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, price_amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, seats_total INT NOT NULL, seats_available INT NOT NULL, visual_tone VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_events_slug ON events (slug)');

        $this->addSql('CREATE TABLE reservations (id SERIAL NOT NULL, event_id INT NOT NULL, reservation_id VARCHAR(32) NOT NULL, attendee_name VARCHAR(180) NOT NULL, attendee_email VARCHAR(180) NOT NULL, attendee_phone VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_reservations_event_id ON reservations (event_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_reservations_reservation_id ON reservations (reservation_id)');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT fk_reservations_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("INSERT INTO events (slug, title, summary, category, location, city, starts_at, price_amount, currency, seats_total, seats_available, visual_tone, created_at, updated_at) VALUES ('midnight-resonance-2-0', 'Midnight Resonance 2.0', 'A live electronic fusion set with immersive lighting and cinematic visuals.', 'Electronic Fusion', 'The Warehouse District', 'Casablanca', '2026-10-12 20:30:00', 45, 'USD', 300, 82, 'indigo', NOW(), NOW())");
        $this->addSql("INSERT INTO events (slug, title, summary, category, location, city, starts_at, price_amount, currency, seats_total, seats_available, visual_tone, created_at, updated_at) VALUES ('ephemeral-visions-gallery', 'Ephemeral Visions Gallery', 'An interactive modern art showcase with guided storytelling sessions.', 'Modern Art', 'Skyline Atrium', 'Rabat', '2026-10-15 17:00:00', 120, 'USD', 120, 12, 'cyan', NOW(), NOW())");
        $this->addSql("INSERT INTO events (slug, title, summary, category, location, city, starts_at, price_amount, currency, seats_total, seats_available, visual_tone, created_at, updated_at) VALUES ('future-loop-ai-2026', 'Future Loop: AI 2026', 'A one-day summit on practical AI systems, workshops, and startup demos.', 'Tech Summit', 'Innovation Hub', 'Tangier', '2026-10-18 09:00:00', 299, 'USD', 450, 5, 'amber', NOW(), NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT fk_reservations_event');
        $this->addSql('DROP TABLE reservations');
        $this->addSql('DROP TABLE events');
    }
}