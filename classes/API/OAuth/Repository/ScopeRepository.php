<?php

namespace HHK\API\OAuth\Repository;

use HHK\API\OAuth\Entity\ScopeEntity;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class ScopeRepository implements ScopeRepositoryInterface {

    /**
     * @inheritDoc
     */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string|null $userIdentifier = null, string|null $authCodeId = null): array 
    {
        return $scopes;
    }
    
    /**
     * @inheritDoc
     */
    public function getScopeEntityByIdentifier(string $identifier): ScopeEntityInterface|null {
    
        $dbh = initPDO(true);
        $scopes = readGenLookupsPDO($dbh, 'Oauth_Scopes');

        if (array_key_exists($identifier, $scopes) === false) {
            return null;
        }

        $scope = new ScopeEntity();
        $scope->setIdentifier($identifier);

        return $scope;
    }
}