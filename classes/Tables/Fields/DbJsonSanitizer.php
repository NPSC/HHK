<?php
namespace HHK\Tables\Fields;
/**
 * DbJsonSanitizer.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DbJsonSanitizer implements DbFieldSanitizerInterface {

    /**
     *
     * @param string $v
     * @return null|string
     */
    public function sanitize($v) {
        if (json_decode($v, true) === null) {
            return null;
        }

        if (!is_string($v)) {
            $v = "[$v]";
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