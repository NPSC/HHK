<?php
/**
 * DataTableServer.php
 *
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
/**
 * Description of DataTableServer
 * @package name
 * @author Eric
 */
class DataTableServer {

    public static function createOutput(PDO $dbh, array $aColumns, $sIndexColumn, $sTable, array $dtp) {

        $parms = array();
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($dtp['iDisplayStart']) && $dtp['iDisplayLength'] != '-1') {
            $st = intval($dtp['iDisplayStart'], 10);
            $ln = intval( $dtp['iDisplayLength'], 10);
            $sLimit = "LIMIT $st, $ln";
//            $sLimit = "LIMIT " . mysqli_real_escape_string($dbCon, $dtp['iDisplayStart']) . ", " .
//                    mysqli_real_escape_string($dbCon, $dtp['iDisplayLength']);
        }


        /*
         * Ordering
         */
        $sOrder = "";
        if (isset($dtp['iSortCol_0'])) {

            for ($i = 0; $i < intval($dtp['iSortingCols']); $i++) {
                if ($dtp['bSortable_' . intval($dtp['iSortCol_' . $i])] == "true") {
                    $sOrder .= "`" . $aColumns[intval($dtp['iSortCol_' . $i])] . "` ";
                    if ($dtp['sSortDir_' . $i] == 'desc'){
                        $sOrder .= 'desc, ';
                    }else {
                        $sOrder .= 'asc, ';
                            //mysqli_real_escape_string($dbCon, $dtp['sSortDir_' . $i]) . ", ";
                    }
                }
            }


            if ($sOrder != '') {
                $sOrder = "ORDER BY " . substr_replace($sOrder, "", -2);
            }
        }


        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
        $sWhere = "";
//        if (isset($dtp['sSearch']) && $dtp['sSearch'] != "" && $dtp['sSearch'] != 'undefined') {
//            $sWhere = "WHERE (";
//            for ($i = 0; $i < count($aColumns); $i++) {
//                $sWhere .= "`" . $aColumns[$i] . "` LIKE '%" . mysqli_real_escape_string($dbCon, $dtp['sSearch']) . "%' OR ";
//            }
//            $sWhere = substr_replace($sWhere, "", -3);
//            $sWhere .= ')';
//        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($dtp['bSearchable_' . $i]) && $dtp['bSearchable_' . $i] == "true" && $dtp['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere = "WHERE ";
                } else {
                    $sWhere .= " AND ";
                }

                // Special fix for member id's
                if ($aColumns[$i] == 'idName') {
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
        $sQuery = "
		SELECT SQL_CALC_FOUND_ROWS `" . str_replace(" , ", " ", implode("`, `", $aColumns)) . "`
		FROM   $sTable
		$sWhere
		$sOrder
		$sLimit
		";
        //echo $sQuery;
        $stmt = $dbh->query($sQuery);
//        foreach ($parms as $k => $v) {
//            $stmt->bindvalue($k, $v['value'], PDO::PARAM_INT);
//        }

        //$stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //$rResult = mysqli_query($dbCon, $sQuery);

        /* Data set length after filtering */
        $sQuery = "
		SELECT FOUND_ROWS()
	";
        $stmtotal = $dbh->query($sQuery);
        $rtots = $stmtotal->fetchAll(PDO::FETCH_NUM);
        $iFilteredTotal = $rtots[0][0];
        //$rResultFilterTotal = mysqli_query($dbCon, $sQuery);
        //$aResultFilterTotal = mysqli_fetch_array($rResultFilterTotal);
        //$iFilteredTotal = $aResultFilterTotal[0];

        /* Total data set length */
//        if ($sIndexColumn == "") {
//            $sQuery = "
//		SELECT COUNT(*)
//		FROM   $sTable
//                ";
//        } else {
//
//            $sQuery = "
//		SELECT COUNT(`" . $sIndexColumn . "`)
//		FROM   $sTable
//                ";
//        }
//        $rResultTotal = mysqli_query($dbCon, $sQuery);
//        $aResultTotal = mysqli_fetch_array($rResultTotal);
//        $iTotal = $aResultTotal[0];
        $iTotal = $iFilteredTotal;

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval(isset($dtp['sEcho']) ? $dtp['sEcho']: 0),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rows as $aRow) {
        //while ($aRow = mysqli_fetch_array($rResult)) {
            $row = array();
            for ($i = 0; $i < count($aColumns); $i++) {
                if ($aColumns[$i] != ' ') {
                    /* General output */
                    $row[$aColumns[$i]] = $aRow[$aColumns[$i]];
                }
            }
            $output['aaData'][] = $row;
        }

        return $output;
    }

}

?>
