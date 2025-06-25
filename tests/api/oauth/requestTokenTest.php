<?php
namespace Tests\Api\Oauth;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class RequestTokenTest extends TestCase
{

    protected Client $client;

    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => '/api/',
            'http_errors' => false
        ]);
    }

    public function testRequestTokenSuccess()
    {
        
        $response = $this->client->post('oauth2/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => '',
                'client_secret'=>''
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode(), 'Expected status 200 for /oauth2/token');
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data, 'Expected JSON array response for /oauth2/token');
        $this->assertArrayHasKey('access_token', $data, 'Expected JSON array key "access_token"');
    }

    public function testRequestTokenInvalidSecret()
    {
        $response = $this->client->post('oauth2/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => '',
                'client_secret'=>''
            ]
        ]);

        $this->assertEquals(401, $response->getStatusCode(), 'Expected status 401 for /oauth2/token with wrong client secret');
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data, 'Expected JSON array response for /oauth2/token with wrong client secret');
        $this->assertArrayHasKey('error', $data, 'Expected JSON array key "error"');
        $this->assertEquals("invalid_client", $data["error"], 'Expected JSON array key "error" = "invalid_client"');
    }

}