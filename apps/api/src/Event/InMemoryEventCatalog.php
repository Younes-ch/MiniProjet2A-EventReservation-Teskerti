<?php

namespace App\Event;

/**
 * @phpstan-type PublicEvent array{
 *     id: string,
 *     slug: string,
 *     title: string,
 *     summary: string,
 *     category: string,
 *     location: string,
 *     city: string,
 *     starts_at: string,
 *     price_amount: float,
 *     currency: string,
 *     seats_total: int,
 *     seats_available: int,
 *     visual_tone: string
 * }
 */
final class InMemoryEventCatalog
{
    /**
     * @var list<PublicEvent>
     */
    private const EVENTS = [
        [
            'id' => 'evt-midnight-resonance-2-0',
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
            'id' => 'evt-ephemeral-visions-gallery',
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
            'id' => 'evt-future-loop-ai-2026',
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

    /**
     * @return list<PublicEvent>
     */
    public function list(): array
    {
        return self::EVENTS;
    }

    /**
     * @return PublicEvent|null
     */
    public function findBySlug(string $slug): ?array
    {
        $needle = trim($slug);

        foreach (self::EVENTS as $event) {
            if ($event['slug'] === $needle) {
                return $event;
            }
        }

        return null;
    }
}