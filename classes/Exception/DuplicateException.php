<?php
namespace HHK\Exception;

use HHK\TableLog\HouseLog;
use HHK\sec\Session;

/**
 * DuplicateException.php
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

class DuplicateException extends \Exception {

    public function __construct ($message = null, $code = null, $previous = null) {
        $dbh = initPDO();
        $uS = Session::getInstance();
        HouseLog::logError($dbh, "DuplicateException", $message . " : " . $this->file . ":" . $this->line, $uS->username);


        parent::__construct ($message, $code, $previous);

    }
}
?>