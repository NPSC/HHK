<?php
namespace HHK\Tables;

use HHK\Tables\Fields\DB_Field;
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

    /**
     * Iterate the public DB_Field properties, keyed by property name
     * @return \Generator<string, DB_Field>
     */
    public function getIterator(): \Generator {
        foreach ((new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isInitialized($this) && ($dbF = $prop->getValue($this)) instanceof DB_Field) {
                yield $prop->getName() => $dbF;
            }
        }
    }

}
?>