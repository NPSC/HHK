<?php
namespace HHK\Tables\Fields;
/**
 * DbBlobSanitizer.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DbBlobSanitizer implements DbFieldSanitizerInterface {

    /**
     *
     * @param int $maxLength
     */
    function __construct() {
    }

    /**
     *
     * @param string $v
     * @return null|string
     */
    public function sanitize($v) {
        if (is_null($v)) {
            return null;
        }

        return $v;
    }

    /**
     *
     * @return int
     */
    public function getDbType(){
        return \PDO::PARAM_LOB;
    }

}
?>