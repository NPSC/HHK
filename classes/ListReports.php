<?php
/**
 * ListReports.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Listreports
 *
 * @author Will
 */
class ListReports {

    public static function loadList (\PDO $dbh, $guestId, $psgId, $parms) {

        $columns = array(
            array( 'db' => 'Date',  'dt' => 'Date' ),
            array( 'db' => 'Guest', 'dt' => 'Guest'),
            array( 'db' => 'Author',   'dt' => 'User' ),
            array( 'db' => 'Title', 'dt' => 'Title'),
            array( 'db' => 'Description', 'dt' => 'Description'),
            array( 'db' => 'Status', 'dt' => 'Status'),
            array( 'db' => 'idReport', 'dt' => 'ReportId'),
            array( 'db' => 'Action', 'dt' => 'Action')
        );

        $dbView = 'vgetIncidentlisting';
        $priKey = 'idReport';
        $whereClause = "";

        if ($guestId == '' && $psgId == '') {
            return array('error'=>'The Guest Id and/or PSG Id are missing.');
        }
        
        if($guestId != ''){
	        $whereClause .= "Guest_Id = $guestId";
        }

		if($psgId != ''){
			if($whereClause != ''){
				$whereClause .= ' AND ';
			}
			$whereClause .= "Psg_Id = $psgId";
		}

        return SSP::complex ( $parms, $dbh, $dbView, $priKey, $columns, null, $whereClause );

    }

}
