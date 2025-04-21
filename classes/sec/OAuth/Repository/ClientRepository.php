<?php
namespace HHK\sec\OAuth\Repository;

use HHK\sec\OAuth\Entity\Client;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class ClientRepository implements ClientRepositoryInterface
{
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public function getClientEntity(string $clientIdentifier): ClientEntityInterface|null {
        return new Client($clientIdentifier);
    }
    
    /**
     * @inheritDoc
     */
    public function validateClient(string $clientIdentifier, string|null $clientSecret, string|null $grantType): bool {
        return true;
    }
}