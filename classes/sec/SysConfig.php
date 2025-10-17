<?php
namespace HHK\sec;
use HHK\Crypto;
use HHK\Exception\RuntimeException;
use HHK\TableLog\HouseLog;

/**
 * SysConfig.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * SysConfig Class
 *
 *
 */

class SysConfig {

    /** Load given category into the session.
     *
     * @param \PDO $dbh
     * @param Session $uS
     * @param string|array $category
     * @param string $tableName
     * @param bool $returnArray
     * @throws RuntimeException
     * @return void || array
     */
    public static function getCategory(\PDO $dbh, Session $uS, $category, $tableName)
    {

        if ($tableName == '' || $category == '') {
            throw new RuntimeException('System Configuration database table name or category not specified.  ');
        }

        if(is_array($category)){
            foreach($category as $key=>$cat){
                $category[$key] = "'" . $cat . "'";
            }
            $category = implode(",", $category);
        }else{
            $category = "'" . $category . "'";
        }

        try {
            $stmt = $dbh->query("select `Key`,`Value`,`Type` from `" . $tableName . "` where Category in ($category) order by `Key`");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\PDOException $e){
            if($e->getCode() === "42S02"){ //table doesn't exist
                throw new RuntimeException("Error: " . $e->errorInfo[2] . ": It looks like HHK isn't installed properly. Try running the installer.");
            }
        }

        foreach ($rows as $r) {

            $val = self::getTypedVal($r['Type'], $r['Value']);
            $key = $r['Key'];
            $uS->$key = $val;
        }

        unset($rows);
        $stmt = NULL;

    }

    /**
     * Summary of getKeyValue
     * @param \PDO $dbh
     * @param string $tableName
     * @param string $key
     * @param mixed $default
     * @throws \HHK\Exception\RuntimeException
     * @return mixed
     */
    public static function getKeyValue(\PDO $dbh, $tableName, $key, $default = null) {

        if ($tableName == '' || $key == '') {
            throw new RuntimeException('System Configuration database table name or key not specified.  ');
        }

        try{
            $stmt = $dbh->query("select `Value`,`Type` from `" . $tableName . "` where `Key` = '$key' ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            $rows = array();
        }

        if (count($rows) == 1) {
            return self::getTypedVal($rows[0]['Type'], $rows[0]['Value']);
        } else if($default !== null){
            return $default;
        }else{
            throw new RuntimeException('System Configuration key not found: ' . $key);
        }

    }

    /**
     * Summary of getKeyRecord
     * @param \PDO $dbh
     * @param string $tableName
     * @param string $key
     * @throws \HHK\Exception\RuntimeException
     * @return mixed
     */
    public static function getKeyRecord(\PDO $dbh, $tableName, $key) {

        if ($tableName == '' || $key == '') {
            throw new RuntimeException('System Configuration database table name or key not specified.  ');
        }

        try{
            $stmt = $dbh->query("select * from `" . $tableName . "` where `Key` = '$key' ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            $rows = array();
        }

        if (count($rows) == 1) {

            return $rows[0];

        }else{
            throw new RuntimeException('System Configuration key not found: ' . $key);
        }

    }



    /**
     * Summary of saveKeyValue
     * @param \PDO $dbh
     * @param string $tableName
     * @param string $key
     * @param mixed $value
     * @param string $category
     * @throws \HHK\Exception\RuntimeException
     * @return void
     */
    public static function saveKeyValue(\PDO $dbh, $tableName, $key, $value, $category = null) {

        if ($tableName == '' || $key == '') {
            throw new RuntimeException('System Configuration database table name or key not specified.  ');
        }

        if($category){
            $query = "select `Value`,`Type` from `" . $tableName . "` where `Key` = '$key' and `Category` = '$category' ";
        }else{
            $query = "select `Value`,`Type` from `" . $tableName . "` where `Key` = '$key' ";
        }
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) == 1) {

            $value = self::setValueByType($value, $rows[0]['Type']);

            $oldVal = $rows[0]['Value'];


            if ($oldVal != $value) {
                // Update table
                $parms = array(':val'=>$value, ':key'=>$key);
                if($category){
                    $query = "update `" . $tableName . "` set `Value` = :val where `Key` = :key and `Category` = :category";
                    $parms[':category'] = $category;
                }else{
                    $query = "update `" . $tableName . "` set `Value` = :val where `Key` = :key";
                }

                $stmt = $dbh->prepare($query);
                $stmt->execute($parms);

                $uS = Session::getInstance();
                $logText = $key . ':' .$oldVal . '|_|' . $value;
                HouseLog::logSysConfig($dbh, $key, $value, $logText, $uS->username, $tableName);

            }
        } else if (count($rows) > 1){
            throw new RuntimeException('Duplicate key: ' . $key);

        }else {
            if($category){
                $query = "insert into `" . $tableName . "` (`Key`, `Value`, `Type`, `Category`) values (:key, :val, 's', :category)";
                $stmt = $dbh->prepare($query);
                $stmt->execute([':key'=>$key, ':val'=>$value, ':category'=>$category]);
            }else{
                throw new RuntimeException('System Configuration key not found: ' . $key);
            }
        }
    }

    /**
     * Summary of getTypedVal
     * @param string $type
     * @param mixed $value
     * @return mixed
     */
    public static function getTypedVal($type, $value) {

        switch ($type) {
            case 'i':
                $val = (int)$value;
                break;
            case 'b':
                $val = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'ob':
                $val = Crypto::decryptMessage($value);
                break;
            default:
                $val = $value;
        }

        return $val;
    }

    /**
     * Summary of setValueByType
     * @param int $value
     * @param string $type
     * @return mixed
     */
    public static function setValueByType($value, $type) {

        switch ($type) {
            case 'i':
                $val = (int)$value;
                break;
            case 'b':
                $temp = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                if ($temp) {
                    $val = 'true';
                } else {
                    $val = 'false';
                }
                break;
            case 'ob':
                $val = Crypto::encryptMessage($value);
                break;
            default:
                $val = $value;
        }

        return $val;
    }
}