<?php
namespace HHK\Tables\Fields;
/**
 * DbDateSanitizer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DbDateSanitizer implements DbFieldSanitizerInterface {

    /** @var string */
    protected $format;

    /** @var bool */
    protected $isNull = false;

    /**
     *
     * @param string $format
     */
    function __construct($format = "Y-m-d") {
        $this->format = $format;
    }

    /**
     *
     * @param string $v
     * @return string|null
     */
    public function sanitize($v) {
        if (is_null($v)) {
            $this->isNull = true;
            return '';
        }

        if ($v != "") {

            if (($unixTime = strtotime($v)) !== false) {
                $this->isNull = false;
                return date($this->format, $unixTime);
            } else {
                $this->isNull = TRUE;
                return '';
            }

        } else {
            $this->isNull = TRUE;
            return '';
        }
    }

    /**
     *
     * @return int
     */
    public function getDbType() {
        if ($this->isNull === false) {
            return \PDO::PARAM_STR;
        } else {
            return \PDO::PARAM_NULL;
        }
    }

}
?>