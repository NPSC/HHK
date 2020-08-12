<?php
namespace HHK\Tables;
/**
 * AbstractTableRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractTableRS implements TableRSInterface {
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
?>