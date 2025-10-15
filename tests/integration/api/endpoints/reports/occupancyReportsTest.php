<?php
namespace Tests\Integration\Endpoints\Reports;

use DateInterval;
use DateTime;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class OccupancyReportsTest extends TestCase
{

    protected Client $client;

    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => '/api/v1/',
            'http_errors' => false
        ]);

    }

    public function testOccupancyAllTime()
    {
        $response = $this->client->get('reports/occupancy/alltime', [

        ]);

        $this->assertEquals(200, $response->getStatusCode(), 'Expected status 200 for /repots/occupancy/alltime');
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data, 'Expected JSON array response for /calendar');
        $this->assertArrayHasKey('startDate', $data, 'Expected JSON array key "startDate"');
        $this->assertArrayHasKey('endDate', $data, 'Expected JSON array key "endDate"');
    }

    public function testOccupancyToday()
    {
        $response = $this->client->get('reports/occupancy/today', [

        ]);

        $this->assertEquals(200, $response->getStatusCode(), 'Expected status 200 for /repots/occupancy/today');
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data, 'Expected JSON array response for /calendar');
        $this->assertArrayHasKey('startDate', $data, 'Expected JSON array key "startDate"');
        $this->assertArrayHasKey('endDate', $data, 'Expected JSON array key "endDate"');
    }

}