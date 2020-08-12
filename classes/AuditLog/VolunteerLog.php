<?php
namespace HHK\AuditLog;
use HHK\SysConst\VolStatus;
use HHK\Tables\{EditRS, TableRSInterface, ActivityRS};

/**
 * VolunteerLog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *  Log volunteer activities - join, retire, etc.
 */
class VolunteerLog implements AuditLogInterface {

    const TYPE = 'vol';
    const JOIN = 'join';
    const REJOIN = 'rejoin';
    const RETIRE = 'retire';
    const DELETE = 'delete';

    /**
     * Write any updated fields to the log, according to iTableRS logme boolean.
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param int $id
     * @param string $user
     */
    public static function writeInsert(\PDO $dbh, TableRSInterface $rs, $id, $user) {

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
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param int $id
     * @param string $user
     *
     */
    public static function writeUpdate(\PDO $dbh, TableRSInterface $rs, $id, $user) {

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
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param int $id
     * @param string $user
     */
    public static function writeDelete(\PDO $dbh, TableRSInterface $rs, $id, $user) {

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
?>