<?php
namespace HHK\sec\OAuth\Repository;

use HHK\sec\OAuth\Entity\ClientEntity;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class ClientRepository implements ClientRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function getClientEntity(string $clientIdentifier): ClientEntityInterface|null {
        $client = new ClientEntity();

        $client->setIdentifier($clientIdentifier);
        $client->setName('HHK');
        $client->setConfidential(true);

        return $client;

    }
    
    /**
     * @inheritDoc
     */
    public function validateClient(string $clientIdentifier, string|null $clientSecret, string|null $grantType): bool {
        return true;
    }
}