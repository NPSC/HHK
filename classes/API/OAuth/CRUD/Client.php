<?php
namespace HHK\API\OAuth\CRUD;

use HHK\Exception\RuntimeException;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\sec\UserClass;
use HHK\Tables\EditRS;
use HHK\Tables\OAuth\OauthClientRS;


class Client {

    protected \PDO $dbh;
    protected $clientId;

    protected OauthClientRS $oauthClientRS;

    protected array $activeAccessTokens;

    public function __construct(\PDO $dbh, $clientId = false) {
        $this->dbh = $dbh;
        $this->clientId = $clientId;
    }

    public function getClient(bool $includeSecret = false) {
        if($this->clientId){

            //client info
            $clientSql = "select * from v_oauth_clients where client_id = :id";
            $clientStmt = $this->dbh->prepare($clientSql);
            $clientStmt->execute(["id"=>$this->clientId]);
            $client = $clientStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$client) {
                throw new RuntimeException("Client not found");
            }

            if($includeSecret){
                $client["secret"] = decryptMessage($client["secret"]);
            }else{
                unset($client["secret"]);
            }

            return $client;
        }

        throw new RuntimeException("Client ID not found");
    }

    /**
     * Get client secret if user is admin or the client belongs to the user.
     * @return string|false Client secret or false if unauthorized.
     */
    public function getClientSecret() {
        $uS = Session::getInstance();
        $clientAr = $this->getClient(true);
        if($uS->username === $clientAr["issuedTo"] || SecurityComponent::is_Admin()){
            return $clientAr["secret"];
        }
        return false;
    }

    public function getAccessTokens(string $type = "active"){
        $tokenSql = "select * from oauth_access_tokens where client_id = :id";
        $params = [":id"=>$this->clientId];

        switch($type){
            case "active":
                $tokenSql .= " and revoked = 0 and expires_at > now()";
                break;
            default:
                return [];
        }

        $tokenSql .= " order by Timestamp desc";

        $tokenStmt = $this->dbh->prepare($tokenSql);
        $tokenStmt->execute($params);
        $tokens = $tokenStmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach($tokens as &$token){
            $token["scopes"] = json_decode($token["scopes"]);
        }
        return $tokens;
    }

    /**
     * Generate New Oauth Client
     * 
     * @param string $name
     * @param array $scopes
     * @return array{client: mixed, client_id: string, client_secret: string}
     */
    public function generateNewClient(string $name, array $scopes = []){
        $clientId = $this->generateClientId();
        $clientSecret = $this->generateRandomString();

        $client = new OauthClientRS();
        $client->client_id->setNewVal($clientId);
        $client->name->setNewVal($name);
        $client->secret->setNewVal(encryptMessage($clientSecret));
        $client->revoked->setNewVal(0);
        EditRS::insert($this->dbh, $client);

        $availableScopes = readGenLookupsPDO($this->dbh, "Oauth_Scopes");
        $stmt = $this->dbh->prepare("INSERT IGNORE INTO `oauth_client_scopes` (`oauth_client`, `oauth_scope`) VALUES (:client_id, :scope)");
        foreach($scopes as $scope){
            if(isset($availableScopes[$scope])){
                $stmt->execute(["client_id"=>$clientId, "scope"=>$scope]);
            }
        }
        
        $this->clientId = $clientId;
        $client = $this->getClient(true);
        return ["client"=>$this->getClient(), "accessTokens"=>[]];
    }

    public function updateClient(){

    }

    public function deleteClient(){

    }

    /**
     * Generate a unique Client ID
     * 
     * @param int $tries number of times to try to generate before giving up
     * @throws \HHK\Exception\RuntimeException
     * @return string
     */
    protected function generateClientId(int $tries = 10){

        for($i = 0; $i<$tries; $i++){
            $clientId = $this->generateRandomString();

            //check if exists already
            $stmt = $this->dbh->prepare("select client_id from oauth_clients where client_id = :id");
            $stmt->execute([":id"=>$clientId]);
            $exists = $stmt->fetchColumn();
            if(!$exists){
                return $clientId;
            }
        }
        throw new RuntimeException("Could not generate client ID");
    }

    protected static function generateRandomString(int $length = 32){
        return bin2hex(random_bytes($length / 2));
    }
}