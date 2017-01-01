<?php
/**
 * DataTableServer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 * Description of DataTableServer
 * @package name
 * @author Eric
 */
class DataTableServer {

    public static function createOutput(\PDO $dbh, array $aColumns, $sIndexColumn, $sTable, array $dtp) {

        //
        // Paging
        //
        $sLimit = "";
        if (isset($dtp['iDisplayStart']) && $dtp['iDisplayLength'] != '-1') {
            $st = intval($dtp['iDisplayStart'], 10);
            $ln = intval( $dtp['iDisplayLength'], 10);
            $sLimit = "LIMIT $st, $ln";
        }


        //
        // Ordering
        //
        $sOrder = "";
        if (isset($dtp['iSortCol_0'])) {

            for ($i = 0; $i < intval($dtp['iSortingCols']); $i++) {

                if ($dtp['bSortable_' . intval($dtp['iSortCol_' . $i])] == "true") {

                    $sOrder .= "`" . $aColumns[intval($dtp['iSortCol_' . $i])] . "` ";

                    if ($dtp['sSortDir_' . $i] == 'desc'){
                        $sOrder .= 'desc, ';
                    }else {
                        $sOrder .= 'asc, ';
                     }
                }
            }


            if ($sOrder != '') {
                $sOrder = "ORDER BY " . substr_replace($sOrder, "", -2);
            }
        }


        $sWhere = "";

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {

            if (isset($dtp['bSearchable_' . $i]) && $dtp['bSearchable_' . $i] == "true" && $dtp['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere = "WHERE ";
                } else {
                    $sWhere .= " AND ";
                }

                // Special fix for member id's
                if ($aColumns[$i] == 'idName' || $aColumns[$i] == 'idRoom' || $aColumns[$i] == 'idResource' || $aColumns[$i] == 'idPsg') {
                    $sWhere .= "`" . $aColumns[$i] . "` = " . intval($dtp['sSearch_' . $i], 10) . " ";
                } else {
                    $sWhere .= "`" . $aColumns[$i] . "` LIKE '%" . $dtp['sSearch_' . $i] . "%' ";
                }
            }
        }


        /*
         * SQL queries
         * Get data to display
         */
        $sQuery = "SELECT SQL_CALC_FOUND_ROWS `" . str_replace(" , ", " ", implode("`, `", $aColumns)) . "`
                FROM   $sTable
                $sWhere
                $sOrder
                $sLimit";

        $stmt = $dbh->query($sQuery);



        /* Data set length after filtering */
        $stmtflt = $dbh->query("SELECT FOUND_ROWS()");
        $rtots = $stmtflt->fetchAll(PDO::FETCH_NUM);

        $iFilteredTotal = $rtots[0][0];

        /* Total data set length */
        if ($sIndexColumn == "") {
            $sQuery = "
                SELECT COUNT(*)
                FROM   $sTable
                ";
        } else {

            $sQuery = "
                SELECT COUNT(`" . $sIndexColumn . "`)
                FROM   $sTable
                ";
        }
        $stmtotal = $dbh->query($sQuery);
        $tots = $stmtotal->fetchAll(PDO::FETCH_NUM);
        $iTotal = $tots[0][0];


        // Output
        $output = array(
            "sEcho" => intval(isset($dtp['sEcho']) ? $dtp['sEcho']: 0),
             "iTotalRecords" => $iTotal,
             "iTotalDisplayRecords" => $iFilteredTotal,
             "aaData" => array()
        );

        while ($aRow = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $row = array();
            for ($i = 0; $i < count($aColumns); $i++) {

                if ($aColumns[$i] != ' ') {
                    $row[$aColumns[$i]] = $aRow[$aColumns[$i]];
                }
            }

            $output['aaData'][] = $row;
        }

        return $output;
    }

}


