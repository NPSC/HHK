<?php
/**
 * commonFunc.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

function readGenLookupsPDO(\PDO $dbh, $tbl, $orderBy = "Code")
{
    $safeTbl = str_replace("'", '', $tbl);
    $query = "SELECT `Code`, `Description`, `Substitute`, `Type`, `Order` FROM `gen_lookups` WHERE `Table_Name` = '$safeTbl' order by `$orderBy`;";
    $stmt = $dbh->query($query);

    $genArray = array();

    while ($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
        $genArray[$row["Code"]] = $row;
    }

    return $genArray;
}