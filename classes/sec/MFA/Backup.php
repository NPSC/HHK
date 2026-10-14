<?php

namespace HHK\sec\MFA;

use HHK\sec\UserClass;

/**
 * Backup.php
 *
 * Facilitates Multifactor authentication backup codes
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Backup extends AbstractMultiFactorAuth {

    /**
     * @param array $userAr
     */
    protected $idName;

    public function __construct(array $userAr){
        $this->secret = $userAr['backupSecret'];
        $this->username = $userAr['User_Name'];
        $this->idName = $userAr['idName'];
    }

    public function verifyCode(\PDO $dbh, string $code): bool
    {
        if(strlen($code) == 6){
            $availableCodes = $this->getCodes();
            $isValid = in_array($code, $availableCodes);
            $isUsed = $this->isCodeUsed($dbh, $code);
            return ($isValid && $isUsed == 0);
        }else{
            return false;
        }
    }

    private function isCodeUsed(\PDO $dbh, string $code) : int
    {
        $query = "select count(*) from w_user_passwords where `idUser` = :idUser and Enc_PW = :code";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(
            ':idUser'=>$this->idName,
            ':code'=>$code
        ));

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        $isUsed = $rows[0][0];

        if($isUsed == 0){
            //set code as used
            $query = "insert into w_user_passwords (`idUser`, `Enc_PW`) values (:idUser, :code)";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':idUser'=>$this->idName,
                ':code'=>$code
            ));
        }

        return $isUsed;
    }

    /**
     * The user's backup codes, derived from their backup secret.
     * @return list<string>
     */
    public function getCodes(): array
    {
        $codeStr = substr(hash_hmac('sha256', $this->username, $this->secret), 0, 60);
        $codesAr = str_split($codeStr, 6);
        return $codesAr;
    }

    public function saveSecret(\PDO $dbh): bool {
        if($this->username && $this->secret != ''){
            $this->writeSecretColumn($dbh, 'backupSecret', $this->secret);
            return true;
        }else{
            return false;
        }
    }

    public function disable(\PDO $dbh) : bool
    {
        if($this->username && $this->secret != ''){
            $this->writeSecretColumn($dbh, 'backupSecret', '');
            $this->secret = '';
            return true;
        }else{
            return false;
        }
    }


}

?>