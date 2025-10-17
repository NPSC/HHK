<?php
namespace HHK\API\OAuth\Repository;

use HHK\API\OAuth\Entity\ClientEntity;
use HHK\API\OAuth\Entity\ScopeEntity;
use HHK\Common;
use HHK\Crypto;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class ClientRepository implements ClientRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function getClientEntity(string $clientIdentifier): ClientEntityInterface|null {
        $dbh = Common::initPDO(true);
        $stmt = $dbh->prepare("SELECT * FROM `oauth_clients` WHERE `client_id` = :client_id AND `revoked` = 0 limit 1");
        $stmt->execute(array(
            'client_id' => $clientIdentifier,
        ));

        if ($stmt->rowCount() == 0) {
            return null;
        }
        
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
        $dbh = Common::initPDO(true);
        $stmt = $dbh->prepare("SELECT `client_id` FROM `oauth_clients` WHERE `client_id` = :client_id AND `secret` = :client_secret");
        $stmt->execute(array(
            'client_id' => $clientIdentifier,
            'client_secret' => Crypto::encryptMessage($clientSecret)
        ));
        $client = $stmt->fetch();

        if ($client) {
            return true;
        } else {
            return false;
        }
    }

    public static function getAuthorizedScopes(string $clientIdentifier): array
    {
        $scopes = [];

        $dbh = Common::initPDO(true);
        $stmt = $dbh->prepare("SELECT `cs`.`oauth_scope` FROM `oauth_clients` `c` LEFT JOIN `oauth_client_scopes` `cs` ON `c`.`client_id` = `cs`.`oauth_client` WHERE `c`.`client_id` = :client_id AND `c`.`revoked` = 0");
        $stmt->execute(array(
            'client_id' => $clientIdentifier
        ));
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach($rows as $scopeName){
            $scope = new ScopeEntity();
            $scope->setIdentifier($scopeName);
            $scopes[] = $scope;
        }
        return $scopes;
    }
}