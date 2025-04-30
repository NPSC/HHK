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
        $dbh = initPDO(true);
        $stmt = $dbh->prepare("SELECT * FROM `oauth_clients` WHERE `client_id` = :client_id limit 1");
        $stmt->execute(array(
            'client_id' => $clientIdentifier,
        ));
        $clientRow = $stmt->fetch();
        $client = new ClientEntity();

        $client->setIdentifier($clientRow['client_id']);
        $client->setName($clientRow['name']);
        $client->setConfidential(true);

        return $client;

    }
    
    /**
     * @inheritDoc
     */
    public function validateClient(string $clientIdentifier, string|null $clientSecret, string|null $grantType): bool {
        $dbh = initPDO(true);
        $stmt = $dbh->prepare("SELECT `client_id` FROM `oauth_clients` WHERE `client_id` = :client_id AND `secret` = :client_secret");
        $stmt->execute(array(
            'client_id' => $clientIdentifier,
            'client_secret' => $clientSecret
        ));
        $client = $stmt->fetch();

        if ($client) {
            return true;
        } else {
            return false;
        }
    }
}