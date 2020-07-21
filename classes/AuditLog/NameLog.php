<?php
namespace HHK\AuditLog;

use HHK\Tables\TableRSInterface;

/**
 * AuditLog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *  Write name changes to log
 */
class NameLog implements AuditLogInterface {

    const AUDIT = 'audit';

    /**
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param int $id
     * @param string $user
     * @param string $typeCode
     */
    public static function writeInsert(\PDO $dbh, TableRSInterface $rs, $id, $user, $typeCode = "") {

        $logText = array();
        if ($typeCode != "") {
            $typeCode = "." . $typeCode;
        }

        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if ($dbF->logMe() && !is_null($dbF->getNewVal()) && $dbF->getNewVal() != "") {
                    $logText[] = $rs->getTableName() . '.' . $dbF->getCol() . $typeCode . ':  -> ' . $dbF->getNewVal();
                }
            }
        }

        NameLog::insertList($dbh, $id, $user, 'new', $logText);
    }

    /**
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param int $id  Member ID of member
     * @param string $user  Web user name of entity doing the changes
     * @param string $typeCode
     */
    public static function writeUpdate(\PDO $dbh, TableRSInterface $rs, $id, $user, $typeCode = "") {

        $logText = array();
        if ($typeCode != "") {
            $typeCode = "." . $typeCode;
        }

        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if ($dbF->logMe() && !is_null($dbF->getNewVal()) && $dbF->getNewVal() != $dbF->getStoredVal()) {

                    $stored = '';
                    if (!is_null($dbF->getStoredVal())) {
                        $stored = $dbF->getStoredVal();
                    }

                    $logText[] = $rs->getTableName() . '.' . $dbF->getCol() . $typeCode . ': ' . $stored . ' -> ' . $dbF->getNewVal();
                }
            }
        }

        NameLog::insertList($dbh, $id, $user, 'update', $logText);
    }

    /**
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param int $id
     * @param string $user
     * @param string $typeCode
     */
    public static function writeDelete(\PDO $dbh, TableRSInterface $rs, $id, $user, $typeCode = "") {

        $logText = array();
        if ($typeCode != "") {
            $typeCode = "." . $typeCode;
        }

        $logText[] = $rs->getTableName() . $typeCode . ': Record Deleted';

        NameLog::insertList($dbh, $id, $user, 'delete', $logText);
    }

    /**
     *
     * @param \PDO $dbh
     * @param int $id
     * @param string $user
     * @param string $subType
     * @param array $logText
     */
    private static function insertList(\PDO $dbh, $id, $user, $subType, array $logText) {

        if (count($logText) > 0) {

            $text = "";
            $query = "insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
                values(Now(), '" . NameLog::AUDIT . "', :subtype, :wp, :id, :txt);";
            $stmt = $dbh->prepare($query);

            $stmt->bindValue(':subtype', $subType, \PDO::PARAM_STR);
            $stmt->bindValue(':wp', $user, \PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, \PDO::PARAM_STR);
            $stmt->bindParam(':txt', $text, \PDO::PARAM_STR);

            foreach ($logText as $t) {

                $text = $t;
                $stmt->execute();
            }
        }
    }

}

?>