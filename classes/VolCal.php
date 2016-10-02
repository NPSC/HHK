<?php
/**
 * VolCal.php
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of VolCal
 * @package name
 * @author Eric
 */
class VolCal {


    public static function MoveEvent(PDO $dbh, $gets, UserCategories $cats) {
    // Event drops (from dragging).
        $events = array();
        $expr = "";
        $allday = 0;

        if (isset($gets['id']) && $gets["id"] != "") {
            $idMcal = intval(filter_var($gets["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            //$res = queryDB($dbcon, "select * from mcalendar where E_Status = 'a' and idmcalendar =:id;");
            $stmt = $dbh->query("select * from mcalendar where E_Status = 'a' and idmcalendar = $idMcal;");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) == 1) {
                $r = $rows[0];
                $evt = new cEventClass();
                $evt->LoadFromDB($r);
                if ($cats->runAuthorization($evt->get_volCat(), $evt->get_volCode(), $evt->get_idName())) {

                    $dayDelta = intval(filter_var($gets['dayDelta'], FILTER_SANITIZE_NUMBER_INT), 10);
                    $minDelta = intval(filter_Var($gets['minuteDelta'], FILTER_SANITIZE_NUMBER_INT),10);

                    if ($dayDelta < 0 || $minDelta < 0) {
                        $expr = "-";
                    }

                    $expr .= abs($dayDelta) . " 00:" . abs($minDelta);

                    if ($gets["allDay"]) {
                        $allday = 1;
                    } else {
                        $allday = 0;
                    }

                    $query = "update mcalendar set E_Start = date_add(E_Start,INTERVAL '$expr' DAY_MINUTE),
                        E_End = case when E_End = null then null else date_add(E_End,INTERVAL '$expr' DAY_MINUTE) end,
                        E_AllDay = $allday, Updated_By = '" . $cats->get_Username() . "', Last_Updated=now()
                        where idmcalendar = '" . $idMcal . "';";
                    $stmt = $dbh->query($query);
                    //queryDB($dbcon, $query);
                    //$ar = mysqli_affected_rows($dbcon);
                    if ($stmt->rowCount() == 1) {
                        $events = array("success" => "Appointment Changed.");
                    } else {
                        $events = array("success" => "Appointment Not Changed.");
                    }
                } else {
                    $events = array("error" => "Insufficient authorization");
                }
            } else {
                $events = array("error" => "Could Not Find Appointment Id");
            }
        } else {
            // missing id???
            $events = array("error" => "Missing or Bad Appointment Id.");
        }

        return $events;
    }

    public static function ResizeEvent(PDO $dbh, $gets, UserCategories $cats) {
    // Event resizing.  Can only add or subtract from the end time.
        $events = array();
        $expr = "";

        if (isset($gets['id']) && $gets["id"] != "") {
            $idMcal = intval(filter_var($gets["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            //$res = queryDB($dbcon, "select * from mcalendar where E_Status = 'a' and idmcalendar ='" . $gets["id"] . "';");
            $stmt = $dbh->query("select * from mcalendar where E_Status = 'a' and idmcalendar = $idMcal;");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) == 1) {
                $r = $rows[0];
                    $evt = new cEventClass();
                    $evt->LoadFromDB($r);
                    if ($cats->runAuthorization($evt->get_volCat(), $evt->get_volCode(), $evt->get_idName())) {

                    $dayDelta = intval(filter_var($gets['dayDelta'], FILTER_SANITIZE_NUMBER_INT), 10);
                    $minDelta = intval(filter_Var($gets['minuteDelta'], FILTER_SANITIZE_NUMBER_INT),10);

                    if ($dayDelta < 0 || $minDelta < 0)
                        $expr = "-";

                    $expr .= abs($dayDelta) . " 00:" . abs($minDelta);

                        $query = "update mcalendar set E_End = case when E_End = null then null else date_add(E_End,INTERVAL '$expr' DAY_MINUTE) end,
                        Updated_By = '" . $cats->get_Username() . "', Last_Updated=now()
                            where idmcalendar = " . $idMcal . ";";

                    $stmt = $dbh->query($query);
                    if ($stmt->rowCount() == 1) {
                        $events = array("success" => "Appointment Updated");
                    } else {
                        $events = array("success" => "Appointment Not changed");
                    }

                } else {
                    $events = array("error" => "Insufficient authorization");
                }
            } else {
                $events = array("error" => "Could Not Find Appointment Id.");
            }
        } else {
            // missing id???
            $events = array("error" => "Missing or Bad Appointment Id.");
        }

        return $events;
    }

    public static function DeleteCalEvent(PDO $dbh, $eId, $delAll, $justMe, $sendemail, UserCategories $cats) {
        $events = array();

        $idMcal = intval($eId, 10);
        if ($idMcal > 0) {
            $query = "select m.*, ifnull(v.Name_First, '') as First, ifnull(v.Name_Last, '') as Last, g.Description as Vol_Description, ifnull(g3.Description, 'n') as `Show_Email_Delete`
            from mcalendar m left join vmember_listing v on m.idName = v.Id left join gen_lookups g on g.Table_Name = m.E_Vol_Category and g.Code = m.E_Vol_Code
            left join gen_lookups g3 on g3.Table_Name = 'Cal_Show_Delete_Email' and g3.Code = concat(m.E_Vol_Category, m.E_Vol_Code)
            where m.idmcalendar = $idMcal and m.E_Status = 'a'";

            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


            if (count($rows) == 1) {
                $r = $rows[0];

                $rptId = intval($r["E_Rpt_Id"], 10);
                $evt = new cEventClass();
                $evt->LoadFromDB($r);

                // Check for primary user delete, or admin role
                if (($cats->get_IdName() == $evt->get_idName() && $cats->runAuthorization($evt->get_volCat(), $evt->get_volCode(), $evt->get_idName()))
                        || SecurityComponent::is_Admin($cats->get_role(), $cats->get_Username())) {
                    // Kill it/them
                    if ($rptId == 0 || $delAll == 0) {
                        //$query = "delete from mcalendar where idmcalendar = '$eId';";
                        $query = "update mcalendar set E_Status = '" . Vol_Calendar_Status::Deleted . "', Last_Updated=now(), Updated_By='" . $cats->get_Username() . "' where idmcalendar = '$idMcal';";
                    } else {
                        //$query = "delete from mcalendar where idmcalendar = '$eId' or ( E_Rpt_Id > 0 and E_Rpt_Id = $rptId and Date(E_Start) > now());";
                        $query = "update mcalendar set E_Status = '" . Vol_Calendar_Status::Deleted . "', Last_Updated=now(), Updated_By='" . $cats->get_Username() . "' where idmcalendar = $idMcal or ( E_Rpt_Id > 0 and E_Rpt_Id = $rptId);";
                    }

                    $stmt = $dbh->query($query);

                    $ar = $stmt->rowCount();

                    if ($ar > 0) {
                        // events successfully deleted
                        $events = array("success" => "y", "rptid" => $rptId, "num" => $ar);

                        // email the admin?
                        if ($sendemail != 0 || $evt->get_showEmailDelete() == 1) {
                            self::emailAdmin($evt, $ar);
                        }

                    } else {
                        // Event id not found??
                        $events = array("error" => "Could Not Find Appointment Id.");
                    }

                    // Check for secondry user delete
                } else if ($cats->get_IdName() == $evt->get_idName2() && $cats->runAuthorization($evt->get_volCat(), $evt->get_volCode(), $evt->get_idName2())) {
                    if ($justMe == 0) {
                        $events = array("success" => "y", "num"=>0);
                    } else {
                        // Remove partner id.

                        if ($rptId == 0 || $delAll == 0) {
                            $query = "update mcalendar set idName2=0, Last_Updated=now(), Updated_By='" . $cats->get_Username() . "' where idmcalendar = $idMcal;";
                        } else {
                            $query = "update mcalendar set idName2=0, Last_Updated=now(), Updated_By='" . $cats->get_Username() . "' where idmcalendar = $idMcal or ( E_Rpt_Id > 0 and E_Rpt_Id = $rptId and Date(E_Start) > now());";
                        }

                    $stmt = $dbh->query($query);

                    $ar = $stmt->rowCount();

                        if ($ar > 0) {
                            $events = array("success" => "y", "rptid" => $rptId, "justme" => $justMe, "num" => $ar);
                        } else {
                            // Event id not found??
                            $events = array("error" => "Could Not Find Appointment Id.");
                        }
                    }

                } else {
                    // Unauthorized
                    $events = array("error" => "Username '" . $cats->get_Username() . "' is not authorized.");
                }
            } else {
                $events = array("error" => "Could Not Find Appointment Id.");
            }
        } else {
            // missing id???
            $events = array("error" => "Missing parameters");
        }

        return $events;
    }

    public static function UpdateCalEvent(PDO $dbh, cEventClass $evt, UserCategories $cats) {

        $events = array();
        $rtnMessage = "";

        If ($evt->get_id() == "" || $evt->get_start() == "" || $evt->get_Title() == "") {
            $events = array("error" => "Appointment Id is not defined.");
            return $events;
        }

        $calRS = new MCalendarRS();
        $calRS->idmcalendar->setStoredVal($evt->get_id());
        $rows = EditRS::select($dbh, $calRS, array($calRS->idmcalendar));

        if (count($rows) == 0) {
            $events = array("error" => $rtnMessage . "The requested Appointment was not found.");
            return $events;
        }

        EditRS::loadRow($rows[0], $calRS);

        if ($cats->runAuthorization($calRS->E_Vol_Category->getStoredVal(), $calRS->E_Vol_Code->getStoredVal(), $calRS->idName->getStoredVal()) === FALSE) {
            // check second id
            if ($cats->runAuthorization($calRS->E_Vol_Category->getStoredVal(), $calRS->E_Vol_Code->getStoredVal(), $calRS->idName2->getStoredVal()) === FALSE) {
                $events = array("error" => "Insufficient authorization");
                return $events;
            }
        }

        if ($calRS->E_Status == Vol_Calendar_Status::Deleted) {
            return array('error'=>'Event is deleted.');
        }


        $calRS->E_Start->setNewVal(date("Y-m-d H:i:s", strtotime($evt->get_start())));
        $calRS->E_End->setNewVal(date("Y-m-d H:i:s", strtotime($evt->get_end())));
        $calRS->E_Title->setNewVal($evt->get_title());
        $calRS->E_Description->setNewVal($evt->get_description());
        $calRS->E_Take_Overable->setNewVal($evt->get_relieve());
        $calRS->E_Locked->setNewVal($evt->get_locked());
        $calRS->E_Editable->setNewVal($evt->get_editable());
        $calRS->E_Fixed_In_Time->setNewVal($evt->get_fixed());
        $calRS->idName->setNewVal($evt->get_idName());
        $calRS->idName2->setNewVal($evt->get_idName2());
        $calRS->E_Rpt_Id->setNewVal($evt->get_reptId());
        $calRS->E_Show_All->setNewVal($evt->get_selectAddAll());


        // Log the time?
        if ($evt->get_logTime() == 1 && $calRS->E_Status->getStoredVal() != Vol_Calendar_Status::Logged) {

            $endTicks = strtotime($evt->get_end());

            if ($endTicks < time()) {

                // Log the time
                $rtnMessage .= self::logVolTime($dbh, $evt, $cats);
                $calRS->E_Status->setNewVal(Vol_Calendar_Status::Logged);
                $calRS->E_Locked->setNewVal(1);
                $evt->set_timeLogged(1);
            } else {
                // Event not over yet.  Cannot log Time???
                $rtnMessage .= "Volunteer time cannot be logged until after the end of the appointment, event or shift.";
            }
        }

        $calRS->Updated_By->setNewVal($cats->get_Username());
        $calRS->Last_Updated->setNewVal(date("c"));

        $n = EditRS::update($dbh, $calRS, array($calRS->idmcalendar));
        if ($n > 0) {
            $rtnMessage .= "Calendar record updated. ";

            if ($evt->get_idName() > 0) {
                $query = "select `Id`, concat(Name_First, ' ', Name_Last) as `Name` from vmember_listing where `Id` = :id or `Id` = :id2;";
                $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $stmt->execute(array(":id" => $evt->get_idName(), ":id2" => $evt->get_idName2()));
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($stmt->rowCount() > 0) {
                    foreach ($rows as $r) {
                        if ($r["Id"] == $evt->get_idName()) {
                            $evt->set_memberName($r["Name"]);
                        } else if ($r["Id"] == $evt->get_idName2()) {
                            $evt->set_memberName2($r["Name"]);
                        }
                    }
                }
            } else {
                $evt->set_memberName("-");
                $evt->set_memberName2("");
            }
        }


        $events["success"] = $rtnMessage;
        $events["event"] = $evt->fillEventsArray(0);

        return $events;
    }

    public static function getEvent(PDO $dbh, $eid) {
        $events = array();

        $query = "select * from vcategory_events where idmcalendar = :id and E_Status <> :stat;";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->bindValue(":id", $eid, PDO::PARAM_STR);
        $stmt->bindValue(":stat", Vol_Calendar_Status::Deleted, PDO::PARAM_STR);

        $stmt->execute();
        $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($row) == 1) {
            $evt = new cEventClass();
            $evt->LoadFromDB($row[0]);

            $evt->getEventColorsPDO($dbh);

            $events = $evt->fillEventsArray(0);

            // Little fix for direct javascript date definition
            $events["start"] = date('c', strtotime($events["start"]));
            $events["end"] = date('c', strtotime($events["end"]));
        } else {
            $events = array("warning" => "Appointment Not Found.");
        }
        return $events;
    }

    public static function getListView(PDO $dbh, $startTime, $endTime, $vcc, UserCategories $cats) {

        $events = array();
        $cols = array("E_Title", "E_Start", "E_End", "Name", "Category", "id", "E_Rpt_Id", "E_Status");

        if ($startTime == "") {
            $beginDate = date("Y-m-1"); // first of this month
        } else {
            $beginDate = date("Y-m-d", strtotime($startTime));
        }

        if ($endTime == "") {
            $b = getDate(strtotime($beginDate));
            $endDate = ($b["year"] + 1) . "-" . $b["mon"] . "-" . $b["mday"];
        } else {
            $endDate = date("Y-m-d", strtotime($endTime));
        }

        $myid = $cats->get_IdName();


        $stmt = null;
        $authorized = false;

        $pts = $cats->splitVcc($vcc);
        if (count($pts) == 3) {
            if ($pts[2] == VolRank::Chair or $pts[2] == VolRank::CoChair) {
                $authorized = true;
            }
        }

        if ($cats->get_role() <= WebRole::Admin) {
            $authorized = true;
        }

        if ($authorized && count($pts) >= 2) {
            // Get all events for a category
            $query = "select distinct m.E_Title, m.E_Start, m.E_End, concat(ifnull(v.Name_First, ''), ' ', ifnull(v.Name_Last, '')) as `Name`, g.Description as `Category`,
                m.idmcalendar as `id`, m.E_Rpt_Id, m.E_Status
                from mcalendar m left join vmember_listing v on (m.idName = v.Id or m.idName2 = v.Id)
                left join gen_lookups g on g.Table_Name = m.E_Vol_Category and g.Code = m.E_Vol_Code
                where m.E_Status <> 'd' and (m.E_Start >= :beginDate and m.E_Start < :endDate or m.E_End >= :beginDate2 and m.E_End < :endDate2)
                and m.E_Vol_Category=:vcat and m.E_Vol_Code=:vcod order by m.E_Start limit 200";

            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->bindValue(":beginDate", $beginDate, PDO::PARAM_STR);
            $stmt->bindValue(":endDate", $endDate, PDO::PARAM_STR);
            $stmt->bindValue(":beginDate2", $beginDate, PDO::PARAM_STR);
            $stmt->bindValue(":endDate2", $endDate, PDO::PARAM_STR);
            $stmt->bindValue(":vcat", $pts[0], PDO::PARAM_STR);
            $stmt->bindValue(":vcod", $pts[1], PDO::PARAM_STR);
        } else if (count($pts) >= 2) {
            // Get my events for a category
            $query = "select distinct m.E_Title, E_Start, E_End, concat(ifnull(v.Name_First, ''),' ', ifnull(v.Name_Last, '')) as `Name`, g.Description as `Category`,
                m.idmcalendar as `id`, m.E_Rpt_Id, m.E_Status
                from mcalendar m left join vmember_listing v on (m.idName = v.Id or m.idName2 = v.Id)
                left join gen_lookups g on g.Table_Name = m.E_Vol_Category and g.Code = m.E_Vol_Code
                where m.E_Status <> 'd' and (m.E_Start >= :beginDate and m.E_Start < :endDate or m.E_End >= :beginDate2 and m.E_End < :endDate2)
                and m.E_Vol_Category=:vcat and m.E_Vol_Code=:vcod and v.Id = :id order by m.E_Start limit 200";

            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->bindValue(":beginDate", $beginDate, PDO::PARAM_STR);
            $stmt->bindValue(":endDate", $endDate, PDO::PARAM_STR);
            $stmt->bindValue(":beginDate2", $beginDate, PDO::PARAM_STR);
            $stmt->bindValue(":endDate2", $endDate, PDO::PARAM_STR);
            $stmt->bindValue(":vcat", $pts[0], PDO::PARAM_STR);
            $stmt->bindValue(":vcod", $pts[1], PDO::PARAM_STR);
            $stmt->bindValue(":id", $myid, PDO::PARAM_INT);
        } else {
            // get "all my events"
            $query = "select  E_Title, E_Start, E_End, concat(First,' ', Last) as `Name`, Vol_Description as Category, idmcalendar as `id`, E_Rpt_Id, E_Status
                from vmy_events where E_Status <> 'd'
            and ((E_Start >= :beginDate and E_Start <= :endDate) or (E_End >= :beginDate2 and E_End <= :endDate2))
            and (idName = :id or idName2 = :id2 )
            order by E_Start limit 200;";
    //        $query = "select distinct m.E_Title, E_Start, E_End, concat(ifnull(v.Name_First, ''),' ', ifnull(v.Name_Last, '')) as `Name`, g.Description as `Category`,
    //            m.idmcalendar as `id`, m.E_Rpt_Id, m.E_Status
    //            from mcalendar m left join vmember_listing v on (m.idName = v.Id or m.idName2 = v.Id)
    //            left join gen_lookups g on g.Table_Name = m.E_Vol_Category and g.Code = m.E_Vol_Code
    //            where m.E_Status <> 'd' and (m.E_Start >= :beginDate and m.E_Start < :endDate or m.E_End >= :beginDate and m.E_End < :endDate)
    //            and v.Id = :id order by m.E_Start limit 200;";

            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->bindValue(":beginDate", $beginDate, PDO::PARAM_STR);
            $stmt->bindValue(":endDate", $endDate, PDO::PARAM_STR);
            $stmt->bindValue(":beginDate2", $beginDate, PDO::PARAM_STR);
            $stmt->bindValue(":endDate2", $endDate, PDO::PARAM_STR);
            $stmt->bindValue(":id", $myid, PDO::PARAM_INT);
            $stmt->bindValue(":id2", $myid, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $aaData = array();

        if ($stmt->rowCount() > 0) {

            foreach ($rows as $r) {

                unset($aaData);
                for ($i = 0; $i < count($cols); $i++) {

                    if ($cols[$i] == "E_Start") {
                        $aaData[$cols[$i]] = date("c", strtotime($r[$cols[$i]]));
                    } else if ($cols[$i] == "E_End") {
                        $aaData[$cols[$i]] = date("c", strtotime($r[$cols[$i]]));
                    } else if ($cols[$i] == "E_Status") {
                        if ($r[$cols[$i]] == Vol_Calendar_Status::Logged) {
                            $aaData[$cols[$i]] = "<span class='ui-icon ui-icon-check'></span>";
                        } else {
                            $aaData[$cols[$i]] = '';
                        }
                    } else {
                        $aaData[$cols[$i]] = $r[$cols[$i]];
                    }
                }
                $events["aaData"][] = $aaData;
            }

        } else {
            $events["aaData"] = array();
        }

        $events["start"] = date("m/d/Y", strtotime($beginDate));
        $events["end"] = date("m/d/Y", strtotime($endDate));
        return $events;
    }

    public static function GetCalView(PDO $dbh, $startTime, $endTime, $houseCal, $vcc, UserCategories $cats) {

        $events = array();
        $getShells = FALSE;

        if ($startTime == 0 || $endTime == 0) {
            return array("error" => "Server Says: Missing some parameters");
        }

        $hc = "or Cal_House = 'y'";
        if ($houseCal == 0) {
            $hc = "";
        }

        $beginDate = date('Y-m-d', $startTime);
        $EndDate = date('Y-m-d', $endTime + 86400);

        $pts = $cats->splitVcc($vcc);
        if (count($pts) >= 2) {

            $query = "select * from vcategory_events
            where E_Status <> 'd' and ((E_Start >= :bdt and E_Start <= :edt) or (E_End >= :bdt2 and E_End <= :edt2))
            and ((E_Vol_Category=:vcat and E_Vol_Code=:vcod) $hc);";

            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':bdt'=>$beginDate, ':edt'=>$EndDate, ':bdt2'=>$beginDate, ':edt2'=>$EndDate, ':vcat'=>$pts[0], ':vcod'=>$pts[1]));
            $getShells = TRUE;

        } else {

            $myid = $cats->get_IdName();

            // get "all my events"
            $query = "select * from vmy_events where E_Status <> 'd' and (idName = :id or idName2 = :id $hc)
            and ((E_Start >= :bdt and E_Start <= :edt) or (E_End >= :bdt2 and E_End <= :edt2))
            and (idName = :id or idName2 = :id2 or E_Show_All = 1 $hc);";

            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':bdt'=>$beginDate, ':edt'=>$EndDate, ':bdt2'=>$beginDate, ':edt2'=>$EndDate, ':id'=>$myid, ':id2'=>$myid));
        }

        // Get events within time span.
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $regEvents = array();

        foreach ($rows as $r) {

            $evt = new cEventClass();
            $evt->LoadFromDB($r);

            $evt->getEventColorsPDO($dbh);

            $events[] = $evt->fillEventsArray(0);

            //collect tagged regular events for checking against the shell events.
            if ($evt->get_shellId() > 0) {
                $regEvents[] = $evt;
            }
        }



        // Get shell events
        if ($getShells) {

            // get shell event prototypes
            $query = "select m.*, g.Description as Vol_Description
            from shell_events m left join gen_lookups g on g.Table_Name = m.Vol_Cat and g.Code = m.Vol_Code
            where Date_Start < :edt and Status = 'a' and Vol_Cat=:vcat and Vol_Code=:vcod;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':edt'=>$EndDate, ':vcat'=>$pts[0], ':vcod'=>$pts[1]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $s = array();

            // pickup all the shell events.
            foreach ($rows as $r) {
                $shell = new shellEvent_Class();
                $shell->loadFromDbRow($r);

                $s[] = $shell;
            }


            // Look through shell events if any
            if (!empty($s)) {

                $idCount = 1;
                $lastDate = '';

                // each day in period
                for ($j = $startTime; $j <= $endTime; $j = $j + 86400) {

                    $thisDate = date('Y-m-d', $j);
                    if ($thisDate == $lastDate) {
                        continue;
                    }

                    $lastDate = $thisDate;

                    $wDte = getdate($j);

                    // each shell event
                    foreach ($s as $shell) {
                        $wd = substr($wDte['weekday'], 0, 3);
                        $days = $shell->get_days();
                        $todayDate = date('Y-m-d', $j);
                        $st = $todayDate . "T" . $shell->get_Time_Start();
                        $ed = $todayDate . "T" . $shell->get_Time_End();

                        if ($days[$wd] == 1) {
                            // set event for today if not already taken
                            // if day and vcc are identical, and regular event was a shell, then don't put this shell up.
                            $isThere = false;

                            // See if there is a regular event that takes over this event
                            if (!empty($regEvents)) {
                                foreach ($regEvents as $ws) {

                                    //if ($ws->get_start() == $st && $ws->get_end() == $ed
                                    //        && $ws->get_volCat() == $shell->get_Vol_Cat() && $ws->get_volCode() == $shell->get_Vol_Code()) {
                                    if ($ws->get_shellId() == $shell->get_idshell_Events() && date('Y-m-d', strtotime($ws->get_start())) == $todayDate) {

                                        $isThere = true;
                                    }
                                }
                            }
                            // only put shell events up in the future and today
                            if (!$isThere && ($j >= strtotime(date("Y-m-d")) || $cats->get_role() <= 10 )) {
                                $events[] = $shell->fillEventArray($st, $ed, $idCount);
                                $idCount++;
                            }
                        }
                    }
                }
            }
        }

        return $events;
    }

    public static function GetDayView(PDO $dbh, $startTime, $endTime, UserCategories $cats) {

        $events = array();

        if ($startTime == 0 || $endTime == 0) {
            return array("error" => "Server Says: Missing some parameters");
        }


        $beginDate = date('Y-m-d', $startTime);
        $EndDate = date('Y-m-d', $endTime + 86400);


        $query = "select * from vcategory_events
        where E_Status <> 'd' and ((E_Start >= :bdt and E_Start <= :edt) or (E_End >= :bdt2 and E_End <= :edt2));";

        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':bdt'=>$beginDate, ':edt'=>$EndDate, ':bdt2'=>$beginDate, ':edt2'=>$EndDate));
        $getShells = FALSE;


        // Get events within time span.
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $regEvents = array();

        foreach ($rows as $r) {

            $evt = new cEventClass();
            $evt->LoadFromDB($r);

            $evt->getEventColorsPDO($dbh);

            $events[] = $evt->fillEventsArray(0);

            //collect tagged regular events for checking against the shell events.
            if ($evt->get_shellId() > 0) {
                $regEvents[] = $evt;
            }
        }



        // Get shell events
        if ($getShells) {

            // get shell event prototypes
            $query = "select m.*, g.Description as Vol_Description
            from shell_events m left join gen_lookups g on g.Table_Name = m.Vol_Cat and g.Code = m.Vol_Code
            where Date_Start < :edt and Status = 'a' and Vol_Cat=:vcat and Vol_Code=:vcod;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':edt'=>$EndDate, ':vcat'=>$pts[0], ':vcod'=>$pts[1]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $s = array();

            // pickup all the shell events.
            foreach ($rows as $r) {
                $shell = new shellEvent_Class();
                $shell->loadFromDbRow($r);

                $s[] = $shell;
            }


            // Look through shell events if any
            if (!empty($s)) {

                $idCount = 1;

                // each day in period
                for ($j = $startTime; $j <= $endTime; $j = $j + 86400) {
                    $wDte = getdate($j);

                    // each shell event
                    foreach ($s as $shell) {
                        $wd = substr($wDte['weekday'], 0, 3);
                        $days = $shell->get_days();
                        $todayDate = date('Y-m-d', $j);
                        $st = $todayDate . "T" . $shell->get_Time_Start();
                        $ed = $todayDate . "T" . $shell->get_Time_End();

                        if ($days[$wd] == 1) {
                            // set event for today if not already taken
                            // if day and vcc are identical, and regular event was a shell, then don't put this shell up.
                            $isThere = false;

                            // See if there is a regular event that takes over this event
                            if (!empty($regEvents)) {
                                foreach ($regEvents as $ws) {

                                    //if ($ws->get_start() == $st && $ws->get_end() == $ed
                                    //        && $ws->get_volCat() == $shell->get_Vol_Cat() && $ws->get_volCode() == $shell->get_Vol_Code()) {
                                    if ($ws->get_shellId() == $shell->get_idshell_Events() && date('Y-m-d', strtotime($ws->get_start())) == $todayDate) {

                                        $isThere = true;
                                    }
                                }
                            }
                            // only put shell events up in the future and today
                            if (!$isThere && ($j >= strtotime(date("Y-m-d")) || $cats->get_role() <= 10 )) {
                                $events[] = $shell->fillEventArray($st, $ed, $idCount);
                                $idCount++;
                            }
                        }
                    }
                }
            }
        }

        return $events;
    }

    public static function CreateCalEvent(PDO $dbh, cEventClass $evt, UserCategories $cats) {

        $events = array();
        $alreadyTaken = array();
        $rptId = 0;
        $removedEvents = 0;
        $alreadyMine = 0;
        $takenOver = 0;


        if (!$cats->runAuthorization($evt->get_volCat(), $evt->get_volCode(), $cats->get_IdName())) {
            $events = array("error" => "This Username is not authorized to create new calendar events:  " . $cats->get_Username());
            return $events;
        }

        If ($evt->get_title() == "" || $evt->get_start() == "") {
            $events = array("error" => "Appointment not added, Missing some parameters. ");
            return $events;
        }

        $evt->getEventColorsPDO($dbh);

        // Returns the new event + any repeats.
        $repeatedEvents = self::generateRepeatEvents($evt);

        // Shell Event?
        if ($evt->get_shellId() > 0) {
            // Get all existing events for this shell?
            $atk = self::checkForExistingEvent($dbh, $evt->get_shellId(), $repeatedEvents);

            if (count($atk) > 0) {

                // Remove matching events from the event list
                foreach ($atk as $k => $v) {

                    if (isset($repeatedEvents[$k])) {
                        // remove from list.
                        unset($repeatedEvents[$k]);

                        // My event?
                        if ($v["idName"] != $evt->get_idName()) {
                            // Not my event
                            // Can this person take the event over?
                            if ($v["E_Take_Overable"] == 1) {
                                // take over this event
                                $takenOver++;
                                // TODO: update event in mcalendar table

                            } else {
                                $removedEvents++;
                                $alreadyTaken[] = $v;
                            }
                        } else {
                            // already is my event
                            $alreadyMine++;
    //                        // Use this repeat ID?
    //                        if ($v["E_Rpt_Id"] > 0) {
    //                            $rptId = intval($v["E_Rpt_Id"]);
    //                        }
                        }
                    }
                }

                // Any events left?  If not, then all events were taken or already mine...
                if (count($repeatedEvents) == 0) {
                    // Events all gone.
                    if (count($atk) == $alreadyMine) {
                        // they were all mine
                        return array("error" => "These are already my shifts.");
                    } else {
                        // Someone else got them before me.
                        return array("error" => "Someone else already grabbed these shifts.");
                    }
                }
            }
        }

        // Need a repeat Id?
        if (count($repeatedEvents) > 1 && $rptId === 0) {
            // get the next id for repeat Id
            $dbh->query("CALL IncrementCounter('repeater', @num);");
            foreach ($dbh->query("SELECT @num") as $row) {
                $rptId = $row[0];
            }
            if ($rptId == 0) {
                return array("error" => "Event Repeater counter not set up.");
            }
        }

        // Insert into the DB...
        $evtsInserted = self::insertEvents($dbh, $evt, $repeatedEvents, $cats->get_Username(), $rptId);

        if ($evtsInserted > 0) {
            if ($evtsInserted == 1) {
                $events["success"] = "Appointment saved";
            } else {
                $events["success"] = $evtsInserted . " appointments saved";
            }

            if ($evt->get_repeater() == 0) {
                // Single event
                if ($evt->get_idName() > 0) {
                    $query = "select `Id`, concat(Name_First, ' ', Name_Last) as `Name` from vmember_listing where `Id` = :id or `Id` = :id2;";
                    $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                    $stmt->execute(array(":id" => $evt->get_idName(), ":id2"=>$evt->get_idName2()));

                    if ($stmt->rowCount() > 0) {
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                            if ($r["Id"] == $evt->get_idName()) {
                                $evt->set_memberName($r["Name"]);
                            } else if ($r["Id"] == $evt->get_idName2()) {
                                $evt->set_memberName2($r["Name"]);
                            }
                        }
                    }

                } else {
                    $evt->set_memberName("-");
                    $evt->set_memberName2("");
                }
                $events["event"] = $evt->fillEventsArray(0);

            } else {
                // Multiple events
                $events["repeatmsg"] = array(
                    "shlid" => $evt->get_shellId(),
                    "enew" => $evtsInserted,
                    "mine" => $alreadyMine,
                    "removed" => $removedEvents,
                    "taken" => $takenOver
                );
            }
        } else {
            $events = array("error" => "Appointnemt(s) Not added.");
        }

        return $events;
    }

    protected static function generateRepeatEvents(cEventClass $evt) {
    // There are two timeframes, the start and end of a single event, and the Date start and end of the repeated events
        $g = array();
        $evtStartDT = new DateTime($evt->get_start());
        $evtEndDT = new DateTime($evt->get_end());
        $eventInterval = $evtStartDT->diff($evtEndDT, TRUE);

        // Load the initial event
        $g[$evtStartDT->format("Y-m-d")] = array("start" => $evtStartDT->format("Y-m-d H:i:s"), "end" => $evtEndDT->format("Y-m-d H:i:s"));

        $qty = intval($evt->get_reptdQty(), 10);

        if ($evt->get_repeater() == 0 || $qty < 1 || $qty > 12) {
            return $g;
        }

//        $now = getDate();
//        $toMonth = ($qty + $now["mon"]) % 12;
//
//        if ($toMonth <= $now["mon"]) {
//            $toDate = strtotime(($now["year"] + 1) . "-" . $toMonth . "-" . $now["mday"]);
//        } else {
//            $toDate = strtotime($now["year"] . "-" . $toMonth . "-" . $now["mday"]);
//        }

        $uS = Session::getInstance();
        $futureLimit = $uS->FutureLimit;
        $maxRepeatEvents = $uS->MaxRepeatEvent;

        if ($futureLimit == 0 || $maxRepeatEvents == 0) {
            return $g;
        }

//        $deltaTicks = 0;
        $interval = '';

        switch ($evt->get_reptdUnits()) {

            case "w1":
//                $deltaTicks = 86400 * 7;
                $interval = 'P7D';
                $qty = ($qty * 4) - 1;
                break;

            case "w2":
//                $deltaTicks = 86400 * 14;
                $interval = 'P14D';
                $qty = ($qty * 2) - 1;
                break;

            case "w4":
//                $deltaTicks = 86400 * 28;
                $interval = 'P1M';
                break;

            case "m1":
                if ($qty > 1) {
                    $h = self::monthlyRepeaters($evt, ($futureLimit * 12));
                    $g = array_merge($g, $h);
                }
                return $g;
                break;

            default:
                return $g;
        }

        $period = new DatePeriod($evtStartDT, new DateInterval($interval), $qty, DatePeriod::EXCLUDE_START_DATE);

        foreach ($period as $date) {

            $g[$date->format("Y-m-d")] = array("start" => $date->format("Y-m-d H:i:s"), "end" => $date->add($eventInterval)->format("Y-m-d H:i:s"));

        }


//        $plusYearsTicks = (86400 * (365 * $futureLimit)) + time();
//        $startTicks = strtotime($evt->get_start());
//        $endTicks = strtotime($evt->get_end());
//
//        $count = 1;
//
//        if ($deltaTicks > 0) {
//            for ($j = time() + $deltaTicks; $j < $toDate; $j = $j + $deltaTicks) {
//
//                //  the start and end time
//                $dt = $count++ * $deltaTicks;
//
//                // No more than x year ahead
//                if ($startTicks + $dt > $plusYearsTicks) {
//                    return $g;
//                }
//
//                // Safety valve exit
//                if ($count >= $maxRepeatEvents || $count > 60) {
//                    return $g;
//                }
//
//                // Add event to list
//                $g[date("Y-m-d", $startTicks + $dt)] = array("start" => date("Y-m-d H:i:s", $startTicks + $dt), "end" => date("Y-m-d H:i:s", $endTicks + $dt));
//            }
//        }
//
        return $g;
    }

    protected static function monthlyRepeaters(cEventClass $evt, $maxEvents) {
        // the first event is already set.
        $h = array();
        $weekDef = array(0 => '', 1 => 'first ', 2 => 'second ', 3 => 'third ', 4 => 'fourth ');
        $monthDef = array(0 => "", 1 => "January ", 2 => "February ", 3 => "March ", 4 => "April ", 5 => "May ", 6 => "June ",
            7 => "July ", 8 => "August ", 9 => "September ", 10 => "October ", 11 => "November ", 12 => "December ");

        $startTicks = strtotime($evt->get_start());
        $endTicks = strtotime($evt->get_end());
        $durationTicks = $endTicks - $startTicks;

        $startDay = getDate($startTicks);
        $month = $startDay["mon"];
        $year = $startDay["year"];
    //    $weekday = $startDay["weekday"];
        $hours = $startDay["hours"];
        $minutes = $startDay["minutes"];

        $wom = ceil($startDay["mday"] / 7);
        if ($wom > 4) {
            $wom = 4;
        }

        $startHourTicks = ($hours * 3600) + ($minutes * 60);

        $occurrences = $evt->get_reptdQty();
        if ($occurrences > $maxEvents) {
            $occurrences = $maxEvents;
        }

        for ($i = 1; $i < $occurrences; $i++) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }

            $str = $weekDef[$wom] . $startDay["weekday"] . " of " . $monthDef[$month] . $year;
            $targetTicks = strtotime($str);

            $h[date("Y-m-d", $targetTicks)] = array(
                "start" => date("Y-m-d H:i:s", $targetTicks + $startHourTicks),
                "end" => date("Y-m-d H:i:s", ($targetTicks + $startHourTicks + $durationTicks)));
            //"first sat of July 2008"
        }

        return $h;
    }

    protected static function checkForExistingEvent(PDO $dbh, $idShell, array $events) {

        $alreadyTaken = array();

        if (count($events) == 0) {
            return $alreadyTaken;
        }

        $startDate = "";
        $id = 0;

        // Make sure there is not already an event assigned for ths shell on this day
        $query = "select idmcalendar, E_Start, idName, E_Take_Overable, E_Rpt_Id from mcalendar
        where E_Status='a' and E_Shell_Id = :idShell and DATE(E_Start) = :ds limit 1;";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->bindParam(':idShell', $id, PDO::PARAM_INT);
        $stmt->bindParam(":ds", $startDate, PDO::PARAM_STR);

        $id = intval($idShell, 10);

        // Execute the statement multiple times in this loop.
        foreach ($events as $item) {
            $startDate = date("Y-m-d", strtotime($item["start"]));
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $alreadyTaken[$startDate] = $rows[0];
            }
        }

        return $alreadyTaken;
    }

    protected static function insertEvents(PDO $dbh, cEventClass $firstEvent, array $repeatedEvents, $userName, $rptId) {

        $cnt = 0;

        foreach ($repeatedEvents as $evt) {

            $calRS = new McalendarRS();

            $calRS->idName->setNewVal($firstEvent->get_idName());
            $calRS->idName2->setNewVal($firstEvent->get_idName2());
            $calRS->E_Title->setNewVal($firstEvent->get_title());
            $calRS->E_Description->setNewVal($firstEvent->get_description());
            $calRS->E_AllDay->setNewVal($firstEvent->get_allDay());
            $calRS->E_Editable->setNewVal($firstEvent->get_editable());
            $calRS->E_ClassName->setNewVal($firstEvent->get_className());
            $calRS->E_Vol_Category->setNewVal($firstEvent->get_volCat());
            $calRS->E_Vol_Code->setNewVal($firstEvent->get_volCode());
            $calRS->E_Take_Overable->setNewVal($firstEvent->get_relieve());
            $calRS->E_Fixed_In_Time->setNewVal($firstEvent->get_fixed());
            $calRS->E_Locked->setNewVal($firstEvent->get_locked());
            $calRS->E_Shell_Id->setNewVal($firstEvent->get_shellId());
            $calRS->E_Rpt_Id->setNewVal($rptId);
            $calRS->E_Show_All->setNewVal($firstEvent->get_selectAddAll());

            $calRS->E_Status->setNewVal(Vol_Calendar_Status::Active);
            $calRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $calRS->Updated_By->setNewVal($userName);

            $calRS->E_Start->setNewVal($evt["start"]);
            $calRS->E_End->setNewVal($evt["end"]);

            $temp = EditRS::insert($dbh, $calRS);

            if ($cnt == 0) {
                $firstEvent->set_id($temp);
            }
            $cnt++;
        }

        return $cnt;
    }

    protected static function logVolTime(PDO $dbh, cEventClass $evt, UserCategories $cats) {

        $logRS = new Volunteer_HoursRS();
        $logRS->idmcalendar->setStoredVal($evt->get_id());

        $rows = EditRS::select($dbh, $logRS, array($logRS->idmcalendar));
        if (count($rows) > 0) {
            return "Event already logged.  ";
        }

        $logRS->idmcalendar->setNewVal($evt->get_id());
        $logRS->idName->setNewVal($evt->get_idName());
        $logRS->idName2->setNewVal($evt->get_idName2());
        $logRS->Start->setNewVal($evt->get_start());
        $logRS->End->setNewVal($evt->get_end());
        $logRS->Vol_Category->setNewVal($evt->get_volCat());
        $logRS->Vol_Code->setNewVal($evt->get_volCode());
        $logRS->Logged_By->setNewVal($cats->get_Username());
        $logRS->Date_Logged->setNewVal(date('y-m-d H:i:s'));

        $dur = strtotime($evt->get_end()) - strtotime($evt->get_start());
        $dur = $dur / 3600;  //hours
        $logRS->Hours->setNewVal($dur, 2);

        $logRS->Status->setNewVal("a");
        $logRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $logRS->Updated_By->setNewVal($cats->get_Username());

        $temp = EditRS::insert($dbh, $logRS);

        return 'Logged ' . number_format($dur, 2) . ' hours.  ';

    }

    public static function emailAdmin(cEventClass $evt, $numEvents) {

        // Get the site configuration object
        $config = new Config_Lite(ciCFG_FILE);

        if ($config->getString("vol_email", "Admin_Address", "") == "") {
            return false;
        }

       $mail = prepareEmail($config);

       $mail->From = $config->getString("vol_email", "ReturnAddress", "");
       $mail->addReplyTo($config->getString("vol_email", "ReturnAddress", ""));
       $mail->FromName = $config->getString('site', 'Site_Name', 'Hospitality HouseKeeper');
       $mail->addAddress($config->getString("vol_email", "Admin_Address", ""));     // Add a recipient
       $mail->isHTML(true);

       $mail->Subject = "Shift Cancellation Notice for " . $evt->get_volDescription();

        // Set the dates with the correct timezone
        $start = new DateTime($evt->get_start());
        $start->setTimezone(new DateTimeZone($config->getString("calendar", "TimeZone", "America/Chicago")));
        $end = new DateTime($evt->get_end());
        $end->setTimezone(new DateTimeZone($config->getString("calendar", "TimeZone", "America/Chicago")));

        $mail->msgHTML('
<html><head>
<style type="text/css">
TH { padding: 3px 7px;}
TD {padding: 3px 7px;
    vertical-align: top;}
table{ border-collapse:collapse;}
.tdBox { border: 1px solid #D4CCB0;
    vertical-align: top;}
.tdlabel { text-align: right;
    font-size: .9em;}
</style>
</head>
    <body>
       <p>Shift Cancellation - Member Name: ' . $evt->get_memberName() . ';  Id: ' . $evt->get_idName() .'</p>
       <p>Number of events deleted: '.$numEvents.'</p>
        <table>
        <caption>' . $evt->get_volDescription() . '</caption>
            <tr>
                <th class="tdlabel tdBox">Title</th><td class="tdBox"><span>'.$evt->get_title().'</span></td>
            </tr>
            <tr>
                <th class="tdlabel tdBox">Start</th><td class="tdBox"><span>'. $start->format('m/d/Y g:i a').'</span></td>
            </tr>
            <tr>
                <th class="tdlabel tdBox">End</th><td class="tdBox"><span>'. $end->format('m/d/Y g:i a').'</span></td>
            </tr>
        </table>
</body></html>');


        return $mail->send();
    }

}

