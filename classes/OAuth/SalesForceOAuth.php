<?php
namespace HHK\OAuth;

use GuzzleHttp\{Client, RequestOptions};
use HHK\CrmExport\Salesforce\SalesforceManager;
use HHK\Exception\RuntimeException;


/**
 * Handles the OAuth login and token request process
 *
 * @author wireland
 *
 */
class SalesForceOAuth extends AbstractOAuth{

    public function __construct(\PDO $dbh, Credentials $credentials){
        parent::__construct($dbh, $credentials);
    }

    public function requestToken(){

        $requestOptions = [
            RequestOptions::AUTH => [$this->credentials->getClientId(), $this->credentials->getClientSecret()],
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'client_credentials',
                'client_id'=> $this->credentials->getClientId(),
                'client_secret' => $this->credentials->getClientSecret()
            ]
        ];

        return $this->sendTokenRequest($requestOptions);
    }

    protected function getTokenTtl(object $_tokenResponse): int {
        return 6600; // 110 min — conservative, below Salesforce's 2-hr default session
    }

    public function validateTokenResponse($data): bool{
        $hash = hash_hmac(
            'sha256',
            $data->id . $data->issued_at,
            $this->credentials->getClientSecret(),
            true
            );
        if (base64_encode($hash) !== $data->signature) {
            throw new RuntimeException('OAuth access token is invalid');
        }
        $this->accessToken = $data->access_token; // Valid access token
        $this->instanceURL = $data->instance_url;  //
        return true;
    }

    public function getLogServiceName(): string{
        return SalesforceManager::LOG_SERVICE_NAME;
    }
}