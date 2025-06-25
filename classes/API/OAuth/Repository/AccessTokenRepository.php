<?php
namespace HHK\API\OAuth\Repository;

use HHK\API\OAuth\Entity\AccessTokenEntity;
use League\OAuth2\Server\Exception\OAuthServerException;
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

        $authorizedScopes = ClientRepository::getAuthorizedScopes($clientEntity->getIdentifier());

        if(count($scopes) == 0){
            foreach ($authorizedScopes as $scope) {
                $accessToken->addScope($scope);
            }
        }else{
            foreach ($scopes as $scope) {
                if(in_array($scope, $authorizedScopes)){ // if the client is authorized for the requested scope
                    $accessToken->addScope($scope);
                }
            }
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
        try{
            $dbh = initPDO(true);
            $stmt = $dbh->prepare("select id from `oauth_access_tokens` WHERE id = :id and revoked = 0;");
            $stmt->execute(array(
                ':id' => $tokenId,
            ));
            $token = $stmt->fetch();

            if ($token) {
                return false;
            }
        }catch (\Exception $e) {
            
        }

        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void {
        try{
            //TODO: Save Access Token to db
            $dbh = initPDO(true);
            $stmt = $dbh->prepare("INSERT INTO `oauth_access_tokens` (`id`, `idName`, `client_id`, `name`, `scopes`, `revoked`, `expires_at`) VALUES (:id, :idName, :client_id, :name, :scopes, :revoked, :expires_at);");
            $stmt->execute(array(
                ':id' => $accessTokenEntity->getIdentifier(),
                ':idName' => $accessTokenEntity->getUserIdentifier(),
                ':client_id' => $accessTokenEntity->getClient()->getIdentifier(),
                ':name' => "",
                ':scopes' => json_encode($accessTokenEntity->getScopes()),
                ':revoked' => 0,
                ':expires_at' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            ));
        }catch (\Exception $e) {
            throw new OAuthServerException("Failed to save access token:" . $e->getMessage(), 500, "", 500, null, null, $e);
        }
    }
    
    /**
     * @inheritDoc
     */
    public function revokeAccessToken(string $tokenId): void {
        //TODO: Revoke Access Token
        try{
            $dbh = initPDO(true);
            $stmt = $dbh->prepare("UPDATE `oauth_access_tokens` SET revoked = 1 WHERE id = :id;");
            $stmt->execute(array(
                ':id' => $tokenId,
            ));
        }catch (\Exception $e) {
            
        }
    }
}