<?php
namespace HHK;

/**
 *
 * @author Eric
 *
 */
class ChartDataSource extends Google\Visualization\DataSource\DataSource
{


    public function generateDataTable(Google\Visualization\DataSource\Query\Query $query, $dbh = null)
    {
        // MySQL
        //$pdo = new PDO("mysql:host=xxx;port=xxx;dbname=xxx", "username", "password");
        return Google\Visualization\DataSource\Util\Pdo\MysqlPdoDataSourceHelper::executeQuery($query, $dbh, "varrivalTOD");

    }

    public function isRestrictedAccessMode() { return FALSE; }


    public function getCapabilities() { return Google\Visualization\DataSource\Capabilities::SQL; }
}

