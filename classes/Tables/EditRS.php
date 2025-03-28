<?php
namespace HHK\Tables;


use HHK\Tables\Fields\DB_Field;


/**
 * EditRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Class EditRS
 *
 * Contains database methods for interface iTableRS.
 *
 */
class EditRS {

    /**
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param array $whereDbFieldArray
     * @param string $combiner
     * @return array
     */
    public static function select(\PDO $dbh, TableRSInterface $rs, array $whereDbFieldArray, $combiner = "and", array $orderByDbFieldArray = [], $ascending = TRUE) {
        $paramList = [];
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

        $volStmt = $dbh->prepare($query . $orderBy, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
        $volStmt->execute($paramList);
        return $volStmt->fetchAll(\PDO::FETCH_ASSOC);
    }



    /**
     * Load a row into the itableRS
     *
     * @param array $row
     * @param TableRSInterface $rs
     */
    public static function loadRow($row, TableRSInterface $rs) {

        if (is_array($row)) {
            foreach ($rs as $dbF) {
                //if (is_a($dbF, "DB_Field")) {
                if($dbF instanceof DB_Field){

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
     * @param TableRSInterface $rs
     */
    public static function updateStoredVals(TableRSInterface $rs) {

        foreach ($rs as $dbF) {


            if($dbF instanceof DB_Field){

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
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param array $whereDbFieldArray
     * @return int
     */
    public static function update(\PDO $dbh, TableRSInterface $rs, array $whereDbFieldArray) {
        $setList = array();
        $paramList = array();
        $query = "";
        $whClause = "";
        $rowCount = 0;
        $changesToUpdate = FALSE;

        // collect parameter values and sql query "set" fragment pairs
        foreach ($rs as $dbF) {

            if($dbF instanceof DB_Field){

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
     * Summary of isChanged
     * @param \HHK\Tables\TableRSInterface $rs
     * @return bool
     */
    public static function isChanged(TableRSInterface $rs){
        $changesToUpdate = false;
        foreach ($rs as $dbF) {

            if($dbF instanceof DB_Field){

                if (!is_null($dbF->getNewVal()) && $dbF->getNewVal() != $dbF->getStoredVal()) {

                    if ($dbF->getUpdateOnChange()) {
                        $changesToUpdate = TRUE;
                    }
                }
            }
        }
        return $changesToUpdate;
    }

    /**
     * Insert a record into a table
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @return int $id Last insert ID
     */
    public static function insert(\PDO $dbh, TableRSInterface $rs) {
        $colList = "";
        $valueList = "";
        $paramList = array();
        $id = 0;

        // collect parameter values and sql query insert columns
        foreach ($rs as $dbF) {

            if($dbF instanceof DB_Field){

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


    /**
     * Summary of delete
     * @param \PDO $dbh
     * @param \HHK\Tables\TableRSInterface $rs
     * @param mixed $whereDbFieldArray
     * @param mixed $combiner
     * @return bool
     */
    public static function delete(\PDO $dbh, TableRSInterface $rs, array $whereDbFieldArray, $combiner = "and") {
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

