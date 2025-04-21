<?php

namespace HHK\sec\OAuth\Repository;

use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class ScopeRepository implements ScopeRepositoryInterface {

    /**
     * @inheritDoc
     */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string|null $userIdentifier = null, string|null $authCodeId = null): array {
        $scopes = [new ScopeEntity()];
        return $scopes;
    }
    
    /**
     * @inheritDoc
     */
    public function getScopeEntityByIdentifier(string $identifier): ScopeEntityInterface|null {
    }
}