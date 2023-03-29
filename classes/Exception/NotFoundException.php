<?php
namespace HHK\Exception;

use HHK\TableLog\HouseLog;
use HHK\sec\Session;

/**
 * NotFoundException.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *
 * @author Will Ireland
 */

class NotFoundException extends \UnexpectedValueException {
    public function __construct ($message = null, $code = null, $previous = null) {
        $dbh = initPDO();
        $uS = Session::getInstance();
        HouseLog::logError($dbh, "NotFoundException", $message . " : " . $this->file . ":" . $this->line, (is_null($uS->username) ? '' : $uS->username));
        parent::__construct($message, $code, $previous);
    }
}

?>