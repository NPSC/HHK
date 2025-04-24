<?php
namespace HHK\sec\OAuth\Repository;

use HHK\sec\OAuth\Entity\AccessTokenEntity;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = null): AccessTokenEntityInterface {
        $accessToken = new AccessTokenEntity();

        $accessToken->setClient($clientEntity);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        if ($userIdentifier !== null) {
            $accessToken->setUserIdentifier((string) $userIdentifier);
        }

        return $accessToken;
    }
    
    /**
     * @inheritDoc
     */
    public function isAccessTokenRevoked(string $tokenId): bool {
        // TODO: Implement isAccessTokenRevoked() method.
        return false;
    }
    
    /**
     * @inheritDoc
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void {
        //TODO: Save Access Token to db
    }
    
    /**
     * @inheritDoc
     */
    public function revokeAccessToken(string $tokenId): void {
        //TODO: Revoke Access Token
    }
}