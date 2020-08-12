<?php
namespace HHK\Tables\Fields;
/**
 * DbIntSanitizer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DbIntSanitizer implements DbFieldSanitizerInterface {

    function __construct() {}

    /**
     *
     * @param string $v
     * @return int
     */
    public function sanitize($v) {
        return intval($v, 10);
    }

    /**
     *
     * @return int
     */
    public function getDbType(){
        return \PDO::PARAM_INT;
    }

}
?>