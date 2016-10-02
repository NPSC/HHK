<?php
/**
 * WaitlistSvcs.php
 *
 *
 * @category  House
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 *
 */
class WaitlistSvcs {


    /**
     *
     * @param PDO $dbh
     * @param array $get
     * @param string $ao
     * @return array
     */
    public static function getWaitlist(PDO $dbh, $get, $ao) {
        require_once(CLASSES . 'DataTableServer.php');


        $aColumns = array("Status_Title", "Timestamp", "Patient_Last", "Guest_Last", "Arrival_Date", "Expected_Duration",
            "Phone", "Notes", "Status", "Email", "Number_Adults", "Number_Children", "Final_Status", "Patient_First", "Guest_First", "idGuest", "idPatient", "Hospital_Title", "Hospital", "Final_Status_Date", "idWaitlist");
        $sIndexColumn = "";
        $sTable = "vwaitlist";

        // Initial sort by stats
        if ($get['sEcho'] < 2) {
            $get['iSortCol_0'] = 0;
        }

        if ($ao == "1") {
            // filter by status
            $get["bSearchable_8"] = "true";
            $get["sSearch_8"] = 'a';
        }


        $log = DataTableServer::createOutput($dbh, $aColumns, $sIndexColumn, $sTable, $get);


        // format the columns
        for ($i = 0; $i < count($log['aaData']); $i++) {

            $ent = $log['aaData'][$i];
            $log['aaData'][$i]["Timestamp"] = date("c", strtotime($log['aaData'][$i]["Timestamp"]));
            $log['aaData'][$i]["Arrival_Date"] = date("c", strtotime($ent["Arrival_Date"]));
            $log['aaData'][$i]["Final_Status_Date"] = $ent['Final_Status_Date'] == '' ? '' : date("c", strtotime($ent["Final_Status_Date"]));

            $guest = $log['aaData'][$i]["Guest_First"] . " " . $log['aaData'][$i]["Guest_Last"];
            if ($log['aaData'][$i]["idGuest"] > 0) {
                $log['aaData'][$i]["Guest_Name"] = HTMLContainer::generateMarkup('a', $guest, array('href' => 'GuestEdit.php?id=' . $log['aaData'][$i]["idGuest"]));
            } else {
                $log['aaData'][$i]["Guest_Name"] = $guest;
            }

            $pat = $log['aaData'][$i]["Patient_First"] . " " . $log['aaData'][$i]["Patient_Last"];
//            if ($log['aaData'][$i]["idPatient"] > 0) {
//                $log['aaData'][$i]["Patient_Name"] = HTMLContainer::generateMarkup('a', $pat, array('href' => '../admin/NameEdit.php?id=' . $log['aaData'][$i]["idPatient"]));
//            } else {
            $log['aaData'][$i]["Patient_Name"] = $pat;
//            }


            $log['aaData'][$i]["Action"] = HTMLContainer::generateMarkup(
                            'ul', HTMLContainer::generateMarkup('li', '<a href="#">Action</a>' .
                                    HTMLContainer::generateMarkup('ul', HTMLContainer::generateMarkup('li', '<a href="#" onclick="wlEdit(5,' . $log['aaData'][$i]["idWaitlist"] . ')">Edit</a>')
                                            . HTMLContainer::generateMarkup('li', '<a href="#" onclick="wlEdit(3,' . $log['aaData'][$i]["idWaitlist"] . ')">Checkin</a>')
                                            . HTMLContainer::generateMarkup('li', '<a href="#" onclick="wlEdit(2,' . $log['aaData'][$i]["idWaitlist"] . ')">Delete</a>')
                            )), array('class' => 'wlmenu'));
        }

        return $log;
    }

    /**
     *
     * @param PDO $dbh
     * @param int $id
     * @param string $role
     * @return array
     */
    public static function getWLName(PDO $dbh, $id, $role) {

        if ($id == 0) {
            return array('error' => 'Missing Id.  ');
        }

        if ($role == 'g') {
            $query = "select
vg.Id,
vg.Name_First as `G_First`,
vg.Name_Last as `G_Last`,
vg.Preferred_Phone as `G_Phone`,
vg.Preferred_Email as `G_Email`,
ifnull(vp.Id, 0) as `P_Id`,
ifnull(vp.Name_First, '') as `P_First`,
ifnull(vp.Name_Last, '') as `P_Last`,
ifnull(p.Hospital_Code, '') as `Hospital`
from vmember_listing vg left join name_guest ng on vg.Id = ng.idName
left join psg p on ng.idPsg = p.idPsg
left join vmember_listing vp on vp.Id = p.idPatient
where vg.Id = :id";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(':id' => $id));
            if ($stmt->rowCount() == 1) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array(
                    'g' => array(
                        'id' => $id,
                        'last' => $rows[0]['G_Last'],
                        'first' => $rows[0]['G_First'],
                        'phone' => $rows[0]['G_Phone'],
                        'email' => $rows[0]['G_Email']
                    ),
                    'p' => array(
                        'id' => $rows[0]['P_Id'],
                        'last' => $rows[0]['P_Last'],
                        'first' => $rows[0]['P_First']
                    ),
                    'hospital' => $rows[0]['Hospital']
                );
            } else {
                return array('error' => 'Guest not found.  Id = ' . $id);
            }
        } else if ($role == 'p') {
            $query = "select
vp.Name_First as `P_First`,
vp.Name_Last as `P_Last`,
ifnull(p.Hospital_Code, '') as `Hospital`
from vmember_listing vp
left join psg p on vp.Id = p.idPatient
where vp.Id = :id";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(':id' => $id));
            if ($stmt->rowCount() == 1) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array(
                    'p' => array(
                        'id' => $id,
                        'last' => $rows[0]['P_Last'],
                        'first' => $rows[0]['P_First']
                    ),
                    'hospital' => $rows[0]['Hospital']
                );
            } else {
                return array('error' => 'Patient not found.  Id = ' . $id);
            }
        } else {
            return array('error' => 'invalid or missing Role.  ');
        }
    }

    /**
     *
     * @param PDO $dbh
     * @param int $wlid
     * @return array
     */
    public static function deleteWLEntry(PDO $dbh, $wlid) {

        Waitlist::deleteEntry($dbh, $wlid);
        return array('success' => 'Entry Deleted');
    }

    /**
     *
     * @param PDO $dbh
     * @param array $parms
     * @return array
     */
    public static function saveWLEntry(PDO $dbh, $parms) {

        $uS = Session::getInstance();
        $num = Waitlist::saveDialog($dbh, $parms, $uS->username);

        if ($num > 0) {
            return array('success' => 'Saved.');
        }
    }


}
