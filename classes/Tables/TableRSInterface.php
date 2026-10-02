<?php
namespace HHK\Tables;

/**
 * TableRSInterface.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * @extends \IteratorAggregate<string, Fields\DB_Field>
 */
interface TableRSInterface extends \IteratorAggregate {
    public function getTableName();
}
?>