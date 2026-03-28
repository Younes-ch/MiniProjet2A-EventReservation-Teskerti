<?php

namespace App\Tests\Support;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait ResetsDatabaseWithSeedEvents
{
    protected function resetDatabaseWithSeedEvents(): void
    {
        self::ensureKernelShutdown();
        static::createClient();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if ([] === $metadata) {
            self::ensureKernelShutdown();
            return;
        }

        $connection = $entityManager->getConnection();
        $connection->executeStatement('DROP TABLE IF EXISTS reservations CASCADE');
        $connection->executeStatement('DROP TABLE IF EXISTS events CASCADE');

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($metadata);

        foreach ($this->seedEventDefinitions() as $seedEvent) {
            $event = (new Event())
                ->setSlug($seedEvent['slug'])
                ->setTitle($seedEvent['title'])
                ->setSummary($seedEvent['summary'])
                ->setCategory($seedEvent['category'])
                ->setLocation($seedEvent['location'])
                ->setCity($seedEvent['city'])
                ->setStartsAt(new \DateTimeImmutable($seedEvent['starts_at']))
                ->setPriceAmount($seedEvent['price_amount'])
                ->setCurrency($seedEvent['currency'])
                ->setSeatsTotal($seedEvent['seats_total'])
                ->setSeatsAvailable($seedEvent['seats_available'])
                ->setVisualTone($seedEvent['visual_tone'])
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($event);
        }

        $entityManager->flush();
        $entityManager->clear();

        self::ensureKernelShutdown();
    }

    /**
     * @return list<array{slug: string, title: string, summary: string, category: string, location: string, city: string, starts_at: string, price_amount: float, currency: string, seats_total: int, seats_available: int, visual_tone: string}>
     */
    private function seedEventDefinitions(): array
    {
        return [
            [
                'slug' => 'midnight-resonance-2-0',
                'title' => 'Midnight Resonance 2.0',
                'summary' => 'A live electronic fusion set with immersive lighting and cinematic visuals.',
                'category' => 'Electronic Fusion',
                'location' => 'The Warehouse District',
                'city' => 'Casablanca',
                'starts_at' => '2026-10-12T20:30:00+01:00',
                'price_amount' => 45.00,
                'currency' => 'USD',
                'seats_total' => 300,
                'seats_available' => 82,
                'visual_tone' => 'indigo',
            ],
            [
                'slug' => 'ephemeral-visions-gallery',
                'title' => 'Ephemeral Visions Gallery',
                'summary' => 'An interactive modern art showcase with guided storytelling sessions.',
                'category' => 'Modern Art',
                'location' => 'Skyline Atrium',
                'city' => 'Rabat',
                'starts_at' => '2026-10-15T17:00:00+01:00',
                'price_amount' => 120.00,
                'currency' => 'USD',
                'seats_total' => 120,
                'seats_available' => 12,
                'visual_tone' => 'cyan',
            ],
            [
                'slug' => 'future-loop-ai-2026',
                'title' => 'Future Loop: AI 2026',
                'summary' => 'A one-day summit on practical AI systems, workshops, and startup demos.',
                'category' => 'Tech Summit',
                'location' => 'Innovation Hub',
                'city' => 'Tangier',
                'starts_at' => '2026-10-18T09:00:00+01:00',
                'price_amount' => 299.00,
                'currency' => 'USD',
                'seats_total' => 450,
                'seats_available' => 5,
                'visual_tone' => 'amber',
            ],
        ];
    }
}
