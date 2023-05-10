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

    /**
     * Summary of __construct
     * @param string $TableName
     */
    public function __construct($TableName = '') {
        $this->tableName = $TableName;
    }


    /**
     * Summary of getTableName
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

}
?>