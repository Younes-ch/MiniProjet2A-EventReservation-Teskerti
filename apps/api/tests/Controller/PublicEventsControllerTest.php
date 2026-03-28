<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicEventsControllerTest extends WebTestCase
{
    use ResetsDatabaseWithSeedEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabaseWithSeedEvents();
    }

    public function testListEndpointReturnsEventsCollection(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertIsArray($data['items'] ?? null);
        $this->assertGreaterThanOrEqual(3, count($data['items']));
        $this->assertSame('midnight-resonance-2-0', $data['items'][0]['slug'] ?? null);
    }

    public function testDetailEndpointReturnsSingleEventBySlug(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events/ephemeral-visions-gallery');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertSame('ephemeral-visions-gallery', $data['slug'] ?? null);
        $this->assertSame('Ephemeral Visions Gallery', $data['title'] ?? null);
        $this->assertSame('Modern Art', $data['category'] ?? null);
    }

    public function testDetailEndpointReturnsNotFoundForUnknownSlug(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events/not-existing-event');

        $this->assertResponseStatusCodeSame(404);

        $data = $this->decodeResponse($client);
        $this->assertSame('event_not_found', $data['error'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
