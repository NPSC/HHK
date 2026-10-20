<?php
namespace HHK\Tables;

use HHK\AuditLog\LogOptions;

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
     * Activity log settings for this table (see Auditable).  Override to tune or opt out.
     * Same method name as spatie/laravel-activitylog's LogsActivity.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults();
    }

}
?>