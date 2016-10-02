<?php
/**
 * DuplicateManager.php
 *
 * @category  Members
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once(REL_BASE_DIR . "classes" . DS . "dbo.php");

function updateDupOriginal($admin, $dupId, $keepId) {
    $pErr = new nameEditError(false);

    // Check Keep Id
    if ($keepId == 0 || $dupId == 0) {
        $pErr->errorOccured = true;
        $pErr->errMessage = "Bad Id";
        $pErr->errCode = 13;
        $pErr->theId = $keepId;
        return $pErr;
    }

    $dbo = new Dbo('');

    $resultmessage = "";
    $keepRecordMember = true;

    // Get Member basis
    $result = new stdClass();
    $arrParams = array("i", $keepId);
    $query = "select Record_Member from name where idName=?";
    if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
        if ($result->rows[0]->Record_Member == 0) {
            $keepRecordMember = false;
        } else {
            $keepRecordMember = true;
        }
    } else {
        $pErr->errorOccured = true;
        $pErr->errMessage = "updateDupOriginal: Database error";
        $pErr->errCode = 14;
        $pErr->theId = $keepId;
        return $pErr;
    }

    // Check Volunteer committees
    if (isset($_POST["cbVol"])) {
        $bitSet = filter_var($_POST["cbVol"], FILTER_VALIDATE_BOOLEAN);
        if ($bitSet) {
            $arrParams = array("iis", $dupId, $keepId, $admin);
            $query = "call `sp_move_vol_categories`(?, ?, ?)";
            if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                $resultmessage .= $result->affected_rows . " Volunteer Categories updated.  ";
           } else {
                $resultmessage .= "Volunteer Categories ERROR: " . $dbo->get_errMessage() . ".  ";
            }
        }
    }

    // Check Donations
    if (isset($_POST["cbDon"])) {
        $bitSet = filter_var($_POST["cbDon"], FILTER_VALIDATE_BOOLEAN);
        if ($bitSet) {

            $arrParams = array("iis", $keepId, $dupId, $admin);
            $query = "call `sp_move_donation`(?, ?, ?)";
            if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                $resultmessage .= $result->affected_rows . " Donor records updated.  ";
            } else {
                $resultmessage .= "Donor records ERROR: " . $dbo->get_errMessage() . ".  ";
            }
            $arrParams = array("isi", $keepId, $admin, $dupId);
            $query = "update donations set Assoc_Id = ?, Updated_By=?, Last_Updated=now() where Assoc_Id = ?";
            if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                $resultmessage .= $result->affected_rows . " Associate Id's updated.  ";
            } else {
                $resultmessage .= "Associate Id ERROR: " . $dbo->get_errMessage() . ".  ";
            }
            $arrParams = array("isi", $keepId, $admin, $dupId);
            $query = "update donations set Care_Of_Id = ?, Updated_By=?, Last_Updated=now() where Care_Of_Id = ?";
            if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                $resultmessage .= $result->affected_rows . " Care/of Id's updated.  ";
            } else {
                $resultmessage .= "Care/of Id ERROR: " . $dbo->get_errMessage() . ".  ";
            }
        }
    }


    // Organiazation issues
    if (!$keepRecordMember) {
        // Check Employees
        if (isset($_POST["cbEmp"])) {
            $bitSet = filter_var($_POST["cbEmp"], FILTER_VALIDATE_BOOLEAN);
            if ($bitSet) {
                $coName = filter_var($_POST["txtCoName"], FILTER_SANITIZE_STRING);
                $arrParams = array("isi", $keepId, $coName, $dupId);
                $query = "update name set Company_Id = ?, Company = ?  where Company_Id = ? and Record_Member=1";
                if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                    $resultmessage .= $result->affected_rows . " Employees records updated.  ";
                } else {
                    $resultmessage .= "Employees records ERROR: " . $dbo->get_errMessage() . ".  ";
                }
            }
        }
    } else {
        // Individual Issues
        //
        // Partner
        if (isset($_POST["cbpartner"])) {
            $bitSet = filter_var($_POST["cbpartner"], FILTER_VALIDATE_BOOLEAN);
            if ($bitSet) {
                $arrParams = array("isii", $keepId, RelLinkType::Spouse, $dupId, $keepId);
                $query = "update relationship set Target_Id = ? where Relation_Type=? and Target_Id=? and idName <> ?";
                if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                    if ($result->affected_rows < 1) {
                        // Try the other side
                        $arrParams = array("isii", $keepId, RelLinkType::Spouse, $dupId, $keepId);
                        $query = "update relationship set idName = ? where Relation_Type=? and idName=? and Target_Id <> ?";
                        if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                            if ($result->affected_rows == 1) {
                                $resultmessage .= $result->affected_rows . " Partner record updated.  ";
                            }
                        } else {
                            $resultmessage .= "Partner database error.  ";
                        }
                    } else {
                        $resultmessage .= $result->affected_rows . " Partner record updated.  ";
                    }
                } else {
                    $resultmessage .= "Partner database error.  ";
                }
            }
        }

        // Company
        if (isset($_POST["cbCompany"])) {
            $bitSet = filter_var($_POST["cbCompany"], FILTER_VALIDATE_BOOLEAN);
            if ($bitSet) {
                $arrParams = array("ii", $keepId, $dupId);
                $query = "update name n, name n2 set n.Company_Id = n2.Company_Id, n.Company = n2.Company  where n.idName=? and n2.idName = ?";
                if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
                    $resultmessage .= $result->affected_rows . " Company Id updated.  ";

                    // Delete company from dupId
                    $arrParams = array("i", $dupId);
                    $query = "update name set Company_Id = 0, Company = ''  where idName=? ";
                    if (($result = $dbo->iQuery($query, $arrParams)) !== false) {

                    } else {
                        $resultmessage .= "Company Id ERROR: " . $dbo->get_errMessage() . ".  ";
                    }
                } else {
                    $resultmessage .= "Company Id ERROR: " . $dbo->get_errMessage() . ".  ";
                }
            }
        }

        // Sibs
        if (isset($_POST["cbSib"])) {
//            $bitSet = filter_var($_POST["cbSib"], FILTER_VALIDATE_BOOLEAN);
//            if ($bitSet) {
//                $arrParams = array("ii", $keepId, $dupId);
//                $query = "update relationship r set r.idName=? where r.Relation_Type='" . RelLinkType::Sibling . "' and r.idName=?";
//                if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
//                    $resultmessage .= "Sibling links updated";
//                }
//            }
            $resultmessage .= "I cannot move siblings.  You will have to do these yourself.  ";
        }

        // RLTV
        if (isset($_POST["cbrltv"])) {
//            $bitSet = filter_var($_POST["cbrltv"], FILTER_VALIDATE_BOOLEAN);
//            if ($bitSet) {
//                $arrParams = array("ii", $keepId, $dupId);
//                $query = "update relationship r set r.idName=? where r.Relation_Type='" . RelLinkType::Relative . "' and r.idName=?";
//                if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
//                    $resultmessage .= "Unspecified Relative links updated";
//                }
//            }
            $resultmessage .= "I cannot move unspecified relatives.  You will have to do these yourself.  ";
        }

        // Parent - child
        if (isset($_POST["cbChild"])) {
//            $bitSet = filter_var($_POST["cbChild"], FILTER_VALIDATE_BOOLEAN);
//            if ($bitSet) {
//                $arrParams = array("ii", $keepId, $dupId);
//                $query = "update relationship r set r.idName=? where r.Relation_Type='" . RelLinkType::Parnt . "' and r.idName=?";
//                if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
//
//                    $resultmessage .= "Child links updated";
//                }
//                $arrParams = array("ii", $keepId, $dupId);
//                $query = "update relationship r set r.Target_Id=? where r.Relation_Type='" . RelLinkType::Parnt . "' and r.Target_Id=?";
//                if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
//
//                    $resultmessage .= "Parent links updated";
//                }
//            }
            $resultmessage .= "I cannot move Parents or Children.  You will have to do these yourself.  ";
        }
    }

    // update the dup member status
    $arrParams = array("ssis", MemStatus::Duplicate, $admin, $dupId, MemStatus::Duplicate);
    $query = "update name set Member_Status = ?, Updated_By=?, Last_Updated=now() where idName=? and Member_Status <> ?";
    if (($result = $dbo->iQuery($query, $arrParams)) !== false) {
        if ($result->affected_rows > 0) {
            $arrParams = array("si", $admin, $dupId);
            $query = "Insert into name_log (Date_Time, Log_Type, Sub_Type, WP_User_Id, idName, Log_Text)
                        values (Now(), 'audit', 'update', ?, ?, 'Name.Member_Status: -> ".MemStatus::Duplicate." (duplicate)')";
            $dbo->iQuery($query, $arrParams);
        }
        $resultmessage .= $result->affected_rows . " Member Status updated.";
    } else {
        $resultmessage .= "Member Status ERROR: " . $dbo->get_errMessage() . ".  ";
    }

    $pErr->errMessage = $resultmessage;
    $pErr->errorOccured = false;
    return $pErr;
}

?>
