<?php
namespace HHK\Exception;

use HHK\TableLog\HouseLog;
use HHK\sec\Session;

/**
 * UnexpectedValueException.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *
 * @author Eric Crane
 */

class UnexpectedValueException extends \UnexpectedValueException {
    public function __construct ($message = null, $code = null, $previous = null) {
        $dbh = initPDO();
        $uS = Session::getInstance();
        HouseLog::logError($dbh, "UnexpectedValueException", $message . " : " . $this->file . ":" . $this->line, (is_null($uS->username) ? '' : $uS->username));
    }
}

?>