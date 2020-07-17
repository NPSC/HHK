<?php
namespace Tables\Fields;
/**
 * PDOdata.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DB_Field {

    /** @var mixed */
    protected $storedVal;

    /** @var mixed */
    protected $defaultVal;

    /** @var mixed */
    protected $newVal;

    /** @var string */
    protected $col;

    /** @var bool */
    protected $updateOnChange;

    /** @var DbFieldSanitizerInterface */
    protected $sanitizer;

    /** @var bool Log this field in a log table.  */
    protected $logField;


    /**
     *
     * @param string $col
     * @param mixed $defaultVal
     * @param DbFieldSanitizerInterface $sanitizer
     * @param bool $updateOnChange default: true
     */
    function __construct($col, $defaultVal, DbFieldSanitizerInterface $sanitizer, $updateOnChange = TRUE, $logMe = FALSE) {

        $this->setCol($col);
        $this->sanitizer = $sanitizer;
        $this->setStoredVal($defaultVal);

        $this->updateOnChange = $updateOnChange;
        $this->logField = $logMe;

    }

    public function logMe() {
        return $this->logField;
    }

    /**
     *
     * @return int
     */
    public function getDbType() {
        return $this->sanitizer->getDbType();
    }

    /**
     *
     * @return bool
     */
    public function getUpdateOnChange() {
        return $this->updateOnChange;
    }

    /**
     *
     * @return mixed
     */
    public function getStoredVal() {
        return $this->storedVal;
    }

    /**
     *
     * @param mixed $val
     */
    public function setStoredVal($val) {
        $this->storedVal = $this->sanitizer->sanitize($val);
        return $this;
     }

     /**
      *
      * @return mixed
      */
    public function getDefaultVal() {
        return $this->defaultVal;
    }

    /**
     *
     * @return string
     */
    public function getCol() {
        return '`' . $this->col . '`';
    }

    /**
     *
     * @return string
     */
    public function getColUnticked() {
        return $this->col;
    }

    /**
     *
     * @param string $col
     */
    protected function setCol($col) {
        $this->col = $col;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getParam() {
        return ":" . $this->col;
    }

    /**
     *
     * @return mixed
     */
    public function getNewVal() {
        return $this->newVal;
    }

    /**
     *
     * @param mixed $newVal
     */
    public function setNewVal($newVal) {
        $this->newVal = $this->sanitizer->sanitize($newVal);
        return $this;
    }

    public function resetNewVal() {
        $this->newVal = null;
    }

    public function __toString() {
        return (string)$this->getStoredVal();
    }

}
