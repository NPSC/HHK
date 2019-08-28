<?php
/**
 * PDOdata.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

interface iTableRS {
    public function getTableName();
}

abstract class TableRS implements iTableRS {
    /**
     *
     * @var string DB table name
     */
    protected $tableName;

    public function __construct($TableName = '') {
        $this->tableName = $TableName;
    }


    public function getTableName() {
        return $this->tableName;
    }

}


interface iDbFieldSanitizer {
    public function sanitize($v);
    public function getDbType();
}

/**
 * Class DBBlobSanatizer
 */
class DbBlobSanitizer implements iDbFieldSanitizer {

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
        return PDO::PARAM_LOB;
    }

}

/**
 * Class DBStrSanatizer
 */
class DbStrSanitizer implements iDbFieldSanitizer {

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
        return PDO::PARAM_STR;
    }

}

class DbDecimalSanitizer implements iDbFieldSanitizer {


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

class DbIntSanitizer implements iDbFieldSanitizer {

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

class DbBitSanitizer implements iDbFieldSanitizer {

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

class DbDateSanitizer implements iDbFieldSanitizer {

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

/**
 * Class DB_Field
 *
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

    /** @var iDbFieldSanitizer */
    protected $sanitizer;

    /** @var bool Log this field in a log table.  */
    protected $logField;


    /**
     *
     * @param string $col
     * @param mixed $defaultVal
     * @param iDbFieldSanitizer $sanitizer
     * @param bool $updateOnChange default: true
     */
    function __construct($col, $defaultVal, iDbFieldSanitizer $sanitizer, $updateOnChange = TRUE, $logMe = FALSE) {

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

/**
 * Class EditRS
 *
 * Contains database methods for interface iTableRS.
 *
 */
class EditRS {

    /**
     *
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param array $whereDbFieldArray
     * @param string $combiner
     * @return array
     */
    public static function select(\PDO $dbh, TableRS $rs, array $whereDbFieldArray, $combiner = "and", array $orderByDbFieldArray = array(), $ascending = TRUE) {
        $paramList = array();
        $query = "";
        $whClause = "";

        //
        $query = "select * from " . $rs->getTableName();

        foreach ($whereDbFieldArray as $key => $dbF) {

            if ($dbF instanceof DB_Field) {
                // use for array containing DataField objects
                if ($dbF->getDbType() == \PDO::PARAM_BOOL) {
                    $whClause .= " " . $combiner . " " . $dbF->getCol() . "=" . $dbF->getStoredVal();
                } else {
                    $whClause .= " " . $combiner . " " . $dbF->getCol() . "=" . $dbF->getParam();
                    $paramList[$dbF->getParam()] = $dbF->getStoredVal();
                }
            } else {
                // array of column => value pairs
                if (is_string($key)) {
                    $parm = $key;
                    if ($parm[0] != ':') {
                        $parm = ':' . $parm;
                    }
                    if (is_bool($dbF) === TRUE) {
                        $val = 0;
                        if ($dbF === TRUE) {
                            $val = 1;
                        }
                        $whClause .= " " . $combiner . " " . $key . "=" . $val;
                    } else {
                        $whClause .= " " . $combiner . " " . $key . "=" . $parm;
                        $paramList[$parm] = $$dbF;
                    }
                }
            }
        }

        $orderBy = '';
        foreach ($orderByDbFieldArray as $dbF) {
            if ($dbF instanceof DB_Field) {
                $orderBy .= $dbF->getCol() . ",";
            }
        }

        if ($orderBy != '') {
            $orderBy = ' order by ' . substr($orderBy, 0, (strlen($orderBy) - 1)) . ($ascending === FALSE ? ' desc' : '');
        }

        if ($whClause != "") {
            $whClause = substr($whClause, 4);
            $query .= " where " . $whClause;
        }

        $volStmt = $dbh->prepare($query . $orderBy, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $volStmt->execute($paramList);
        return $volStmt->fetchAll(\PDO::FETCH_ASSOC);
    }



    /**
     * Load a row into the itableRS
     *
     * @param array $row
     * @param iTableRS $rs
     */
    public static function loadRow($row, TableRS $rs) {

        if (is_array($row)) {
            foreach ($rs as $dbF) {
                if (is_a($dbF, "DB_Field")) {
                    if (isset($row[$dbF->getColUnticked()])) {
                        $dbF->setStoredVal($row[$dbF->getColUnticked()]);
                        $dbF->resetNewVal();
                    }
                }
            }
        }
    }


    /**
     *  Update the stored values after an insert operation and set the
     *  data clean flag.
     *
     * @param iTableRS $rs
     */
    public static function updateStoredVals(TableRS $rs) {

        foreach ($rs as $dbF) {

            if (is_a($dbF, "DB_Field")) {

                if (is_null($dbF->getNewVal()) === FALSE) {

                    $dbF->setStoredVal($dbF->getNewVal());
                    $dbF->resetNewVal();
                }
            }
        }
    }




    /**
     * Update one or more records in a table
     *
     * @param PDO $dbh
     * @param iTableRS $rs
     * @param array $whereDbFieldArray
     * @return int
     */
    public static function update(\PDO $dbh, TableRS $rs, array $whereDbFieldArray) {
        $setList = array();
        $paramList = array();
        $query = "";
        $whClause = "";
        $rowCount = 0;
        $changesToUpdate = FALSE;

        // collect parameter values and sql query "set" fragment pairs
        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if (!is_null($dbF->getNewVal()) && $dbF->getNewVal() != $dbF->getStoredVal()) {
                    // make
                    if ($dbF->getDbType() == \PDO::PARAM_BOOL) {
                        // Stupid PDO doesnt like bit(1) types - use the value directly instead of using a parameter
                        $setList[] = $dbF->getCol() . "=" . $dbF->getNewVal();
                    } else if ($dbF->getDbType() == \PDO::PARAM_NULL) {
                        $setList[] = $dbF->getCol() . "=null";
                    } else {
                        $setList[] = $dbF->getCol() . "=" . $dbF->getParam();
                        $paramList[] = array("param" => $dbF->getParam(), "value" => $dbF->getNewVal(), "type" => $dbF->getDbType());
                    }

                    if ($dbF->getUpdateOnChange()) {
                        $changesToUpdate = TRUE;
                    }
                }
            }
        }

        // Prepare the query if there is anything to update
        if ($changesToUpdate && count($setList) > 0) {

            // use the first set value pair
            $query = "update " . $rs->getTableName() . " set " . $setList[0];

            // run through the rest of the set's
            for ($i = 1; $i < count($setList); $i++) {
                $query .= "," . $setList[$i];
            }

            // now run through the where parameter array
            foreach ($whereDbFieldArray as $dbF) {
                if ($dbF->getDbType() == \PDO::PARAM_BOOL) {
                    $whClause .= " and " . $dbF->getCol() . "=" . $dbF->getStoredVal();
                } else {
                    $whClause .= " and " . $dbF->getCol() . "=" . $dbF->getParam();
                    $paramList[] = array("param" => $dbF->getParam(), "value" => $dbF->getStoredVal(), "type" => $dbF->getDbType());
                }

            }

            if ($whClause != "") {
                $whClause = substr($whClause, 4);
                $query .= " where " . $whClause;
            }

            $stmt = $dbh->prepare($query);

            // build statement parameters
            foreach ($paramList as $k) {
                $stmt->bindValue($k["param"], $k["value"], $k["type"]);
            }

            $stmt->execute();

            $rowCount = $stmt->rowCount();
        }
        return $rowCount;
    }



    /**
     * Insert a record into a table
     *
     * @param PDO $dbh
     * @param iTableRS $rs
     * @return int
     */
    public static function insert(\PDO $dbh, TableRS $rs) {
        $colList = "";
        $valueList = "";
        $paramList = array();
        $id = 0;

        // collect parameter values and sql query insert columns
        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if (!is_null($dbF->getNewVal())) {
                    // make
                    $colList .= "," . $dbF->getCol();

                    if ($dbF->getDbType() == \PDO::PARAM_BOOL) {
                        // Stupid PDO doesnt like bit(1) types - use the value directly instead of using a parameter
                        $valueList .= "," . $dbF->getNewVal();
                    } else if ($dbF->getDbType() == \PDO::PARAM_NULL) {
                        $valueList .= ",null";
                    } else {
                        $valueList .= "," . $dbF->getParam();
                        $paramList[] = array("param" => $dbF->getParam(), "value" => $dbF->getNewVal(), "type" => $dbF->getDbType());
                    }

                }
            }
        }

        // build query
        if ($colList != "") {
            $colList = substr($colList, 1);
            $valueList = substr($valueList, 1);

            $query = "insert into " . $rs->getTableName() . " ($colList) values ($valueList);";

            $stmt = $dbh->prepare($query);

            // build statement parameters
            foreach ($paramList as $k) {
                $stmt->bindValue($k["param"], $k["value"], $k["type"]);
            }

            $stmt->execute();

            $id = $dbh->lastInsertId();

        }

        return $id;
    }


    public static function delete(\PDO $dbh, TableRS $rs, array $whereDbFieldArray, $combiner = "and") {
        $paramList = array();
        $query = "";
        $whClause = "";

        //
        $query = "delete from " . $rs->getTableName();

        foreach ($whereDbFieldArray as $dbF) {

            if ($dbF instanceof DB_Field) {
                // use for array containing DataField objects
                if ($dbF->getDbType() == \PDO::PARAM_BOOL) {
                    $whClause .= " " . $combiner . " " . $dbF->getCol() . "=" . $dbF->getStoredVal();
                } else {
                    $whClause .= " " . $combiner . " " . $dbF->getCol() . "=" . $dbF->getParam();
                    $paramList[$dbF->getParam()] = $dbF->getStoredVal();
                }
            }
        }

        if ($whClause != "") {
            $whClause = substr($whClause, 4);
            $query .= " where " . $whClause;


            $stmt = $dbh->prepare($query);

            if ($stmt->execute($paramList) === FALSE) {
                return FALSE;
            } else {
                return TRUE;
            }
        } else {
            return FALSE;
        }
    }
}
