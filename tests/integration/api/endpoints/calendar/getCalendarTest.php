<?php
namespace Tests\Integration\Api\Endpoints\Calendar;

use DateInterval;
use DateTime;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class getCalendarTest extends TestCase
{

    protected Client $client;
    protected string $accessToken;

    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => '/api/v1/',
            'http_errors' => false
        ]);

    }

    public function testCalendarNextWeekSuccess()
    {
        $response = $this->client->get('calendar', [
            'form_params' => [
                'startDate' => (new DateTime())->format("Y-m-d"),
                'endDate' => (new DateTime())->add(new DateInterval("P1W")),
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode(), 'Expected status 200 for /calendar');
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data, 'Expected JSON array response for /calendar');
        $this->assertArrayHasKey('startDate', $data, 'Expected JSON array key "startDate"');
        $this->assertArrayHasKey('endDate', $data, 'Expected JSON array key "endDate"');
    }

}