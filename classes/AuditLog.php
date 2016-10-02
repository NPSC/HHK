<?php

/**
 * AuditLog.php
 *
 * @category  Configuration
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
interface iAuditLog {

    public static function writeInsert(PDO $dbh, iTableRS $rs, $id, $user);

    public static function writeUpdate(PDO $dbh, iTableRS $rs, $id, $user);

    public static function writeDelete(PDO $dbh, iTableRS $rs, $id, $user);
}

/**
 *  Log volunteer activities - join, retire, etc.
 */
class VolunteerLog implements iAuditLog {

    const TYPE = 'vol';
    const JOIN = 'join';
    const REJOIN = 'rejoin';
    const RETIRE = 'retire';
    const DELETE = 'delete';

    /**
     * Write any updated fields to the log, according to iTableRS logme boolean.
     *
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param int $id
     * @param string $user
     */
    public static function writeInsert(PDO $dbh, iTableRS $rs, $id, $user) {

        $logRS = new ActivityRS();

        $logRS->idName->setNewVal($id);
        $logRS->Status_Code->setNewVal('a');
        $logRS->Type->setNewVal(VolunteerLog::TYPE);
        $logRS->Product_Code->setNewVal($rs->Vol_Category->getNewVal() . "|" . $rs->Vol_Code->getNewVal());
        $logRS->Other_Code->setNewVal($rs->Vol_Rank->getNewVal());
        $logRS->Action_Codes->setNewVal(VolunteerLog::JOIN);
        $logRS->Effective_Date->setNewVal(date("Y-m-d H:i:s"));
        $logRS->Source_Code->setNewVal($user);

        EditRS::insert($dbh, $logRS);
    }

    /**
     *
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param int $id
     * @param string $user
     *
     */
    public static function writeUpdate(PDO $dbh, iTableRS $rs, $id, $user) {

        $logRS = new ActivityRS();

        $logRS->idName->setNewVal($id);
        $logRS->Status_Code->setNewVal('a');
        $logRS->Type->setNewVal(VolunteerLog::TYPE);
        $logRS->Product_Code->setNewVal($rs->Vol_Category->getStoredVal() . "|" . $rs->Vol_Code->getStoredVal());
        $logRS->Other_Code->setNewVal((is_null($rs->Vol_Rank->getNewVal()) === FALSE ? $rs->Vol_Rank->getNewVal() : $rs->Vol_Rank->getStoredVal()));

        if ($rs->Vol_Status->getNewVal() != $rs->Vol_Status->getStoredVal()) {

            if ($rs->Vol_Status->getNewVal() == VolStatus::Active) {

                $logRS->Action_Codes->setNewVal(VolunteerLog::REJOIN);
            } else if ($rs->Vol_Status->getNewVal() == VolStatus::Retired) {

                $logRS->Action_Codes->setNewVal(VolunteerLog::RETIRE);
            } else {
                return;
            }
        }

        $logRS->Effective_Date->setNewVal(date("Y-m-d H:i:s"));
        $logRS->Source_Code->setNewVal($user);

        EditRS::insert($dbh, $logRS);
    }

    /**
     *
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param int $id
     * @param string $user
     */
    public static function writeDelete(PDO $dbh, iTableRS $rs, $id, $user) {

        $logRS = new ActivityRS();

        $logRS->idName->setNewVal($id);
        $logRS->Status_Code->setNewVal($rs->Vol_Status->getNewVal());
        $logRS->Type->setNewVal(VolunteerLog::TYPE);
        $logRS->Product_Code->setNewVal($rs->Vol_Category->getNewVal() . "|" . $rs->Vol_Code);
        $logRS->Other_Code->setNewVal($rs->Vol_Rank->getNewVal());
        $logRS->Action_Codes->setNewVal(VolunteerLog::DELETE);
        $logRS->Effective_Date->setNewVal(date("Y-m-d H:i:s"));
        $logRS->Source_Code->setNewVal($user);

        EditRS::insert($dbh, $logRS);
    }

}

/**
 *  Write name changes to log
 */
class NameLog implements iAuditLog {

    const AUDIT = 'audit';

    /**
     *
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param int $id
     * @param string $user
     * @param string $typeCode
     */
    public static function writeInsert(PDO $dbh, iTableRS $rs, $id, $user, $typeCode = "") {

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
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param id $id  Member ID of member
     * @param string $user  Web user name of entity doing the changes
     * @param string $typeCode
     */
    public static function writeUpdate(PDO $dbh, iTableRS $rs, $id, $user, $typeCode = "") {

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
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param int $id
     * @param string $user
     * @param string $typeCode
     */
    public static function writeDelete(PDO $dbh, iTableRS $rs, $id, $user, $typeCode = "") {

        $logText = array();
        if ($typeCode != "") {
            $typeCode = "." . $typeCode;
        }

        $logText[] = $rs->getTableName() . $typeCode . ': Record Deleted';

        NameLog::insertList($dbh, $id, $user, 'delete', $logText);
    }

    /**
     *
     * @param PDO $dbh
     * @param int $id
     * @param string $user
     * @param string $subType
     * @param array $logText
     */
    private static function insertList(PDO $dbh, $id, $user, $subType, array $logText) {

        if (count($logText) > 0) {

            $text = "";
            $query = "insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
                values(Now(), '" . NameLog::AUDIT . "', :subtype, :wp, :id, :txt);";
            $stmt = $dbh->prepare($query);

            $stmt->bindValue(':subtype', $subType, PDO::PARAM_STR);
            $stmt->bindValue(':wp', $user, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_STR);
            $stmt->bindParam(':txt', $text, PDO::PARAM_STR);

            foreach ($logText as $t) {

                $text = $t;
                $stmt->execute();
            }
        }
    }

}

?>
