<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use HHK\Exception\PaymentException;
use HHK\Integrations\GuzzleAPILogger;

class ImCurlRequest {

    private const LOG_SERVICE_NAME = 'Instamed';

    protected Client $client;

    public function __construct(\PDO $dbh) {
        $this->client = new Client([
            'handler' => GuzzleAPILogger::createStack($dbh, self::LOG_SERVICE_NAME),
        ]);
    }

    public function submit(string $parmStr, string $url, string $accountId, string $password): array {

        if ($url == '') {
            throw new PaymentException('Request is missing the URL.');
        }

        try {
            parse_str($parmStr, $params);

            $response = $this->client->request('POST', $url, [
                'auth' => [$accountId, $password],
                'form_params' => $params,
            ]);

            $transaction = [];
            parse_str($response->getBody()->getContents(), $transaction);

            return $transaction;
        } catch (GuzzleException $e) {
            throw new PaymentException('Problem contacting the payment gateway: ' . $e->getMessage());
        }
    }

}
