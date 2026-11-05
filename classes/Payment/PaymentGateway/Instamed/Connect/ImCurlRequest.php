<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use HHK\Exception\PaymentException;
use HHK\Integrations\GuzzleAPILogger;
use HHK\Payment\PaymentGateway\Instamed\InstamedGateway;
use Psr\Http\Message\ResponseInterface;

class ImCurlRequest {

    private const LOG_SERVICE_NAME = 'Instamed';

    /** Sub_Type logged for the SSO (hosted page) initialization request */
    public const SSO_LOG_SUBTYPE = 'SSOInit';

    protected Client $client;

    public function __construct(\PDO $dbh) {
        $this->client = new Client([
            'handler' => GuzzleAPILogger::createStack($dbh, self::LOG_SERVICE_NAME),
        ]);
    }

    /**
     * POST an NVP request and return the parsed name/value response body.
     */
    public function submit(string $parmStr, string $url, string $accountId, string $password): array {

        parse_str($parmStr, $params);

        $response = $this->post($url, [
            'auth' => [$accountId, $password],
            'form_params' => $params,
            // log each step under its transactionAction; falls back to the URL path when absent
            GuzzleAPILogger::SUBTYPE_OPTION => $params['transactionAction'] ?? null,
        ]);

        $transaction = [];
        parse_str($response->getBody()->getContents(), $transaction);

        return $transaction;
    }

    /**
     * POST an SSO initialization request (responseActionType=header).
     *
     * Instamed answers with a relayState header whose query string carries
     * token, respCode and respMessage. Returns those values plus the full
     * relayState URL, or an empty array if the header is missing.
     */
    public function submitSso(string $parmStr, string $url): array {

        parse_str($parmStr, $params);

        $response = $this->post($url, [
            'form_params' => $params,
            // the relayState header is on Instamed's direct reply
            'allow_redirects' => false,
            GuzzleAPILogger::SUBTYPE_OPTION => self::SSO_LOG_SUBTYPE,
        ]);

        // getHeaderLine is case-insensitive (Instamed has sent both relayState and relaystate)
        $relayState = $response->getHeaderLine(InstamedGateway::RELAY_STATE);

        if ($relayState === '') {
            return [];
        }

        $result = [];
        parse_str((string) parse_url($relayState, PHP_URL_QUERY), $result);
        $result[InstamedGateway::RELAY_STATE] = $relayState;

        return $result;
    }

    private function post(string $url, array $options): ResponseInterface {

        if ($url == '') {
            throw new PaymentException('Request is missing the URL.');
        }

        try {
            return $this->client->request('POST', $url, $options);
        } catch (GuzzleException $e) {
            throw new PaymentException('Problem contacting the payment gateway: ' . $e->getMessage());
        }
    }

}
