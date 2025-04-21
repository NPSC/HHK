<?php
namespace HHK\sec\OAuth\Repository;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = null): AccessTokenEntityInterface {
    }
    
    /**
     * @inheritDoc
     */
    public function isAccessTokenRevoked(string $tokenId): bool {
    }
    
    /**
     * @inheritDoc
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void {
    }
    
    /**
     * @inheritDoc
     */
    public function revokeAccessToken(string $tokenId): void {
    }
}