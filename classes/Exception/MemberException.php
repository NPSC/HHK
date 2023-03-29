<?php
namespace HHK\Exception;

use HHK\TableLog\HouseLog;
use HHK\sec\Session;

/**
 * MemberException.php
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

class MemberException extends \RuntimeException {
    public function __construct ($message = null, $code = null, $previous = null) {
        $dbh = initPDO();
        $uS = Session::getInstance();
        HouseLog::logError($dbh, "MemberException", $message . " : " . $this->file . ":" . $this->line, (is_null($uS->username) ? '' : $uS->username));
        parent::__construct($message, $code, $previous);
    }
}
?>