<?php
/**
 * TableLog.php
 *
 *
 *
 * @category  Configuration
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of TableLog
 *
 * @author Eric
 */
abstract class TableLog {

    public static function getInsertText(iTableRS $rs) {

        $logText = array();

        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if ($dbF->logMe() && !is_null($dbF->getNewVal()) && $dbF->getNewVal() != "") {
                    $logText[$dbF->getCol()] =  $dbF->getNewVal();
                }
            }
        }

        return $logText;
    }

    public static function getUpdateText(iTableRS $rs) {

        $logText = array();

        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if ($dbF->logMe() && !is_null($dbF->getNewVal()) && $dbF->getNewVal() != $dbF->getStoredVal()) {

                    $stored = '';
                    if (!is_null($dbF->getStoredVal())) {
                        $stored = $dbF->getStoredVal();
                    }

                    $logText[$dbF->getCol()] = $stored . '|_|' . $dbF->getNewVal();
                }
            }
        }

        return $logText;
    }

    public static function getDeleteText(iTableRS $rs, $idPrimaryKey) {

        $logText = array();

        $logText[$rs->getTableName()] = $idPrimaryKey;

        return $logText;
    }


    protected static function insertLog(PDO $dbh, TableRS $logRS) {

        $rt = EditRS::insert($dbh, $logRS);
        return $rt;
    }

    protected static function encodeLogText($logText) {
        return json_encode($logText);
    }

    protected static function checkLogText($logText) {

        $rtn = FALSE;

        if (is_array($logText) && count($logText) > 0) {
            $rtn = TRUE;
        } else if (is_array($logText) === FALSE && strlen($logText) > 0) {
            $rtn = TRUE;
        }

        return $rtn;
    }

    public static function parseLogText($logTextEntry) {

        if ($logTextEntry != '') {
            $decod = json_decode($logTextEntry);
            $tbl = new HTMLTable();

            foreach ($decod as $k => $v) {

                $key = str_ireplace('`', '', $k);

                $tbl->addBodyTr(HTMLTable::makeTd($key) . HTMLTable::makeTd(self::decodeUpdate($v)));

            }

            return $tbl->generateMarkup();
        }
        return '';
    }

    protected static function decodeUpdate($t) {

        if (stristr($t, '|_|') === FALSE) {
            // not found.
            return $t;
        } else {
            $parts = explode('|', $t);
            if (count($parts) == 3) {
                return $parts[0] . ' --> ' . $parts[2];
            } else {
                return $t;
            }
        }
    }

}
