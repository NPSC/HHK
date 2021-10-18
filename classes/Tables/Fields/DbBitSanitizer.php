<?php
namespace HHK\Tables\Fields;
/**
 * DbBitSanitizer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DbBitSanitizer implements DbFieldSanitizerInterface {

    function __construct() {}

    /**
     *
     * @param int $val
     * @return int
     */
    public function sanitize($val) {
        if ($val == '1' || $val === TRUE || ord($val) == 1) {
            $val = 1;
        } else {
            $val = 0;
        }
        return $val;
    }

    /**
     *
     * @return int
     */
    public function getDbType(){
        return \PDO::PARAM_BOOL;
    }

}
?>