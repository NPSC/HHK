<?php
namespace HHK\AuditLog;
use HHK\Tables\TableRSInterface;

/**
 * AuditLogInterface.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
interface AuditLogInterface {

    public static function writeInsert(\PDO $dbh, TableRSInterface $rs, $id, $user);

    public static function writeUpdate(\PDO $dbh, TableRSInterface $rs, $id, $user);

    public static function writeDelete(\PDO $dbh, TableRSInterface $rs, $id, $user);
}

?>