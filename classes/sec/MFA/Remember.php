<?php

namespace HHK\sec\MFA;

use HHK\sec\Session;
use HHK\sec\UserClass;

class Remember
{

    protected $userAr;
    protected $defaultExpSeconds;
    protected $cookieName;

    public function __construct(array $userAr){
        $uS = Session::getInstance();
        $this->userAr = $userAr;
        $this->defaultExpSeconds = ($uS->rememberTwoFA != '' ? time() + 60*60*24*$uS->rememberTwoFA : 0);
        $this->cookieName = 'HHK' . ($uS->databaseName ? $uS->databaseName : "")."remember".$userAr['idName'];
    }

    public function rememberMe(\PDO $dbh): bool
    {

        if(isset($_COOKIE[$this->cookieName])){
            unset($_COOKIE[$this->cookieName]);
        }

        if($this->defaultExpSeconds > 0){

            $this->generateToken();

            setcookie($this->cookieName, $this->token, $this->defaultExpSeconds, '/');

            return $this->saveToken($dbh);
        }

        return false;
    }

    public function verifyToken(\PDO $dbh): bool
    {
        $cookie = (isset($_COOKIE[$this->cookieName]) ? $_COOKIE[$this->cookieName] : false);
        $verified = false;

        if($cookie && $this->defaultExpSeconds > 0){

            $stmt = $dbh->prepare("select * from w_user_tokens where `idName` = :idName and `Token` = :token and `Expires` > :now LIMIT 1");
            $stmt->execute(array(
                ':idName'=>$this->userAr['idName'],
                ':token'=>$cookie,
                ':now'=>time()
            ));

            if(count($stmt->fetchAll()) == 1){
                $verified = true;
            }else{
                unset($_COOKIE[$this->cookieName]);
                $this->deleteTokens($dbh);
            }
        }
        return $verified;
    }

    private function generateToken(): void
    {
        $this->token = bin2hex(random_bytes(32));
    }

    private function saveToken(\PDO $dbh): bool
    {
        $sql = "INSERT INTO w_user_tokens (`idName`,`Token`, `Expires`, `IP_Address`) VALUES(:idName, :token, :expires, :ipAddress)";

        $stmt = $dbh->prepare($sql);

        $stmt->execute(array(
            ':idName'=>$this->userAr['idName'],
            ':token'=>$this->token,
            ':expires'=>$this->defaultExpSeconds,
            ':ipAddress'=>UserClass::getRemoteIp()
        ));

        $rowCount = $stmt->rowCount();

        return $rowCount;
    }

    public function deleteTokens(\PDO $dbh, bool $allUserTokens = false) : int
    {
        if($allUserTokens){ //delete all user tokens
            $sql = "DELETE FROM w_user_tokens where `idName` = :idName";
        }else{ //only delete expired tokens
            $sql = "DELETE FROM w_user_tokens where `idName` = :idName and `Expires` < " . time();
        }

        $stmt = $dbh->prepare($sql);

        $stmt->execute(array(
            ':idName'=>$this->userAr['idName']
        ));

        $rowCount = $stmt->rowCount();

        return $rowCount;
    }

    public function getTokens(\PDO $dbh)
    {
        $sql = "SELECT `IP_Address`, `Expires`, `Timestamp` FROM w_user_tokens where `idName` = :idName and `Expires` > :now";

        $stmt = $dbh->prepare($sql);

        $stmt->execute(array(
            ':idName'=>$this->userAr['idName'],
            ':now'=>time()
        ));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}
?>