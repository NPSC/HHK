<?php
namespace HHK\Tables\Fields;

/**
 * DbDecimalSanitizer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DbDecimalSanitizer implements DbFieldSanitizerInterface {


    function __construct() {}

    /**
     *
     * @param string $v
     * @return string|null
     */
    public function sanitize($v) {
        if (is_null($v)) {
            $v = "0.00";
        }

        if (!is_string($v)) {
            $v = "$v";
        }

        if ($v == "" || $v == "0") {
            $v = "0.00";
        }

        return $v;
    }

    /**
     *
     * @return int
     */
    public function getDbType(){
        return \PDO::PARAM_STR;
    }

}
?>