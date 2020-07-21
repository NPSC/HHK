<?php
namespace HHK\Tables\Fields;
/**
 * DbStrSanitizer.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DbStrSanitizer implements DbFieldSanitizerInterface {

    /** @var int */
    protected $maxLength;

    /**
     *
     * @param int $maxLength
     */
    function __construct($maxLength) {
        $this->maxLength = $maxLength;
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

        if (!is_string($v)) {
            $v = "$v";
        }

        if (strlen($v) > $this->maxLength) {
            $v = substr($v, 0, $this->maxLength);
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