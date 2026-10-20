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

    use Auditable;

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
                        $paramList[$parm] = $dbF;
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
            foreach (get_object_vars($rs) as $dbF) {
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

        foreach (get_object_vars($rs) as $dbF) {


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
        foreach (get_object_vars($rs) as $dbF) {

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
            // (own placeholder names, so a column can be both set and matched on)
            foreach ($whereDbFieldArray as $dbF) {
                if ($dbF->getDbType() == \PDO::PARAM_BOOL) {
                    $whClause .= " and " . $dbF->getCol() . "=" . $dbF->getStoredVal();
                } else {
                    $whClause .= " and " . $dbF->getCol() . "=" . $dbF->getParam() . "_wh";
                    $paramList[] = array("param" => $dbF->getParam() . "_wh", "value" => $dbF->getStoredVal(), "type" => $dbF->getDbType());
                }

            }

            if ($whClause != "") {
                $whClause = substr($whClause, 4);
                $query .= " where " . $whClause;
            }

            $beforeRows = self::auditBeforeWrite($dbh, $rs, $whereDbFieldArray);

            $stmt = $dbh->prepare($query);

            // build statement parameters
            foreach ($paramList as $k) {
                $stmt->bindValue($k["param"], $k["value"], $k["type"]);
            }

            $stmt->execute();

            $rowCount = $stmt->rowCount();

            if ($rowCount > 0) {
                self::auditUpdate($dbh, $rs, $beforeRows);
            }
        }
        return $rowCount;
    }

    /**
     * Load one record by its key, set new values on it and update it.  Loading first means a new value
     * that equals a field's default is still written.
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs an empty RS of the table
     * @param array $keyVals column => value, must match exactly one row
     * @param array $newVals column => new value
     * @return int rows updated (0 when the key did not match exactly one row)
     */
    public static function updateFields(\PDO $dbh, TableRSInterface $rs, array $keyVals, array $newVals): int {

        $where = [];
        foreach ($keyVals as $col => $val) {
            $rs->$col->setStoredVal($val);
            $where[] = $rs->$col;
        }

        $rows = self::select($dbh, $rs, $where);

        if (count($rows) != 1) {
            return 0;
        }

        self::loadRow($rows[0], $rs);

        foreach ($newVals as $col => $val) {
            $rs->$col->setNewVal($val);
        }

        $count = self::update($dbh, $rs, $where);
        self::updateStoredVals($rs);

        return $count;
    }

    /**
     * Update the row matching $keyVals, or insert one (with the key and new values) if there isn't one.
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs an empty RS of the table
     * @param array $keyVals column => value, must match at most one row
     * @param array $newVals column => new value
     * @return int rows written (0 when the existing row already had these values)
     */
    public static function upsert(\PDO $dbh, TableRSInterface $rs, array $keyVals, array $newVals): int {

        $where = [];
        foreach ($keyVals as $col => $val) {
            $rs->$col->setStoredVal($val);
            $where[] = $rs->$col;
        }

        $rows = self::select($dbh, $rs, $where);

        if (count($rows) > 1) {
            throw new \RuntimeException('upsert key matched ' . count($rows) . ' rows in ' . $rs->getTableName());
        }

        if (count($rows) == 1) {
            self::loadRow($rows[0], $rs);

            foreach ($newVals as $col => $val) {
                $rs->$col->setNewVal($val);
            }

            $count = self::update($dbh, $rs, $where);
            self::updateStoredVals($rs);
            return $count;
        }

        $class = get_class($rs);
        $newRs = new $class();
        foreach ($keyVals + $newVals as $col => $val) {
            $newRs->$col->setNewVal($val);
        }
        self::insert($dbh, $newRs);

        return 1;
    }

    /**
     * Change $col from $oldVal to $newVal in every row that has it, e.g. when a lookup code is replaced.
     * The column must be updateOnChange.
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs an empty RS of the table
     * @param string $col
     * @param mixed $oldVal
     * @param mixed $newVal
     * @return int rows updated
     */
    public static function replaceValue(\PDO $dbh, TableRSInterface $rs, string $col, $oldVal, $newVal): int {

        if ($oldVal == $newVal) {
            return 0;
        }

        $rs->$col->setStoredVal($oldVal);
        $rs->$col->setNewVal($newVal);

        return self::update($dbh, $rs, [$rs->$col]);
    }

    /**
     * Update the rows matching a where clause EditRS can't express (ranges, IN, joins), one row at a time
     * by primary key, so each change goes through EditRS::update.
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs an empty RS of the table
     * @param string $whereSql the SQL after WHERE, with named parameters
     * @param array $params
     * @param array $newVals column => new value
     * @return int rows updated
     */
    public static function updateWhere(\PDO $dbh, TableRSInterface $rs, string $whereSql, array $params, array $newVals): int {

        $count = 0;
        foreach (self::selectWhere($dbh, $rs, $whereSql, $params) as [$rowRs, $pkWhere]) {

            foreach ($newVals as $col => $val) {
                $rowRs->$col->setNewVal($val);
            }

            $count += self::update($dbh, $rowRs, $pkWhere);
        }

        return $count;
    }

    /**
     * Delete the rows matching a where clause EditRS can't express (ranges, IN, joins), one row at a time
     * by primary key, so each delete goes through EditRS::delete.
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs an empty RS of the table
     * @param string $whereSql the SQL after WHERE, with named parameters
     * @param array $params
     * @return int rows deleted
     */
    public static function deleteWhere(\PDO $dbh, TableRSInterface $rs, string $whereSql, array $params = []): int {

        $count = 0;
        foreach (self::selectWhere($dbh, $rs, $whereSql, $params) as [$rowRs, $pkWhere]) {
            if (self::delete($dbh, $rowRs, $pkWhere)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Load each row matching $whereSql into its own RS.
     *
     * @return array each [rs, primary key DB_Fields]
     */
    private static function selectWhere(\PDO $dbh, TableRSInterface $rs, string $whereSql, array $params): array {

        $pkCols = self::primaryKeyCols($dbh, $rs->getTableName());
        $class = get_class($rs);

        // without a key each row's update would have no where clause
        if (count($pkCols) == 0) {
            throw new \RuntimeException('Table ' . $rs->getTableName() . ' has no primary key');
        }

        $stmt = $dbh->prepare("select * from `" . $rs->getTableName() . "` where " . $whereSql);
        $stmt->execute($params);

        $loaded = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $rowRs = new $class();
            self::loadRow($r, $rowRs);

            $pkWhere = [];
            foreach ($pkCols as $col) {
                $pkWhere[] = $rowRs->$col;
            }

            $loaded[] = [$rowRs, $pkWhere];
        }

        return $loaded;
    }

    /**
     * Summary of isChanged
     * @param \HHK\Tables\TableRSInterface $rs
     * @return bool
     */
    public static function isChanged(TableRSInterface $rs){
        $changesToUpdate = false;
        foreach (get_object_vars($rs) as $dbF) {

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
        foreach (get_object_vars($rs) as $dbF) {

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

            self::auditInsert($dbh, $rs, $id);

        }

        return $id;
    }


    /**
     * Summary of delete
     * @param \PDO $dbh
     * @param \HHK\Tables\TableRSInterface $rs
     * @param array $whereDbFieldArray
     * @param string $combiner
     * @return bool
     */
    public static function delete(\PDO $dbh, TableRSInterface $rs, array $whereDbFieldArray, string $combiner = "and") {
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

            $beforeRows = self::auditBeforeWrite($dbh, $rs, $whereDbFieldArray, $combiner);

            $stmt = $dbh->prepare($query);

            if ($stmt->execute($paramList) === FALSE) {
                return FALSE;
            } else {
                self::auditDelete($dbh, $rs, $beforeRows);
                return TRUE;
            }
        } else {
            return FALSE;
        }
    }
}

