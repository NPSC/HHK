<?php
/**
 * ResourceView.php
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
 * Description of ResourceView
 * @author Eric
 */
class ResourceView {

    public static function resourceTable(PDO $dbh, $showPartitions) {

        $rooms = array();

        $attribute = new Attributes($dbh);
        $attrs = $attribute->getAttributesByType(Attribute_Types::Resource);

        $rmAtrStmt = $dbh->query("Select idEntity, idAttribute from attribute_entity where `Type` = '" . Attribute_Types::Resource . "'");
        $roomAttrs = $rmAtrStmt->fetchAll(PDO::FETCH_ASSOC);


        $stmt = $dbh->query("Select
    '' as `Edit`,
    r.idResource as `Id`,
    r.Title,
    ifnull(g.Description,'') as `Type`,
    ifnull(h.Title, '') as `Hospital`,
    ifnull(rm.Title, '') as `Room`,
    r.Util_Priority as `Priority`,
    ifnull(g2.Description,'') as 'Util Category',
    r.Background_Color as `Bkgrd Color`,
    r.Text_Color as `Text Color`,
    r.Border_Color as `Border Color`,
    r.Partition_Size as `Part. Size`
from
    resource r
        left join
    gen_lookups g ON g.Table_Name = 'Resource_Type' and g.`Code` = r.`Type`
        left join
    gen_lookups g2 ON g2.Table_Name = 'Utilization_Category' and g2.`Code` = r.`Utilization_Category`
        left join
    hospital h ON r.idSponsor = h.idHospital
        left join
    resource_room rr on r.idResource = rr.idResource
        left join
    room rm on rr.idRoom = rm.idRoom
order by r.Title;");

        $idResc = 0;
        $numResc = $stmt->rowCount();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            if ($showPartitions === FALSE) {
                unset($r['Part. Size']);
            }

            if ($idResc != $r['Id']) {

                $ra = array();
                foreach ($roomAttrs as $ras) {
                    if ($ras['idEntity'] == $r['Id']) {
                        $ra[$ras['idAttribute']] = 'x';
                    }
                }

                foreach ($attrs as $a) {
                    $r[$a['Title']] = (isset($ra[$a['idAttribute']]) ? HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check')) . HTMLContainer::generateMarkup('span', 'x', array('class'=>'hhk-printmedia')) : '');
                }

                $r['Edit'] = HTMLInput::generateMarkup('', array('id'=>$r['Id'].'rebtn', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'resc', 'class'=>'reEditBtn ui-icon ui-icon-pencil', 'style'=>'width:20px;height:20px;margin-right:.5em;display:inline;', 'title'=>'Edit This Resource'))
                    . HTMLInput::generateMarkup('', array('id'=>$r['Id'].'reDelete', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'resc', 'class'=>'reDelBtn ui-icon ui-icon-trash', 'style'=>'width:20px;height:20px;display:inline;', 'title'=>'Delete Resource'));

                $r['Status'] = HTMLInput::generateMarkup('', array('id'=>$r['Id'].'reStatbtn', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'resc', 'data-title'=>$r['Title'], 'class'=>'reStatBtn ui-icon ui-icon-wrench', 'style'=>'width:20px;height:20px;margin-left:.5em;display:inline;', 'title'=>'View Status Events'));

                $rooms[] = $r;

            } else {
                // collect room numbers
                $rm = array_pop($rooms);
                $rm['Room'] .= ', ' . $r['Room'];
                $rooms[] = $rm;
            }

            $idResc = $r['Id'];
        }

        // New Room
       $newRow = array(
            'Edit' => HTMLInput::generateMarkup('New', array('id'=>'0btnreNew', 'name'=>'0', 'type'=>'button', 'data-enty'=>'resc', 'class'=>'reNewBtn')),
            'Id' => '',
            'Title' => '',
            'Type' => '',
            'Hospital' => '',
            'Room' => '',
            'Priority' => '',
           'Util Category' =>'',
            'Bkgrd Color' => '',
            'Text Color' => '',
            'Border Color' => '',
            'Part. Size' => '',
            'status' => ''
            );

        if ($showPartitions === FALSE) {
            unset($newRow['Part. Size']);
        }

        $rooms[] = $newRow;

        return HTMLContainer::generateMarkup('h3', 'Showing '.$numResc . ' Resources') . CreateMarkupFromDB::generateHTML_Table($rooms, 'tblresc');

    }

    public static function roomTable(PDO $dbh, $keyDeposit = FALSE) {

        $rooms = array();
        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        $attribute = new Attributes($dbh);
        $attrs = $attribute->getAttributesByType(Attribute_Types::Room);

        $rmAtrStmt = $dbh->query("Select idEntity, idAttribute from attribute_entity where `Type` = '" . Attribute_Types::Room . "'");
        $roomAttrs = $rmAtrStmt->fetchAll(PDO::FETCH_ASSOC);

        $depositCol = '';
        $depositTitle = $labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit');

        if ($keyDeposit) {
            $depositCol = ", g5.Description as `$depositTitle ` ";
        }


        $stmt = $dbh->query("Select '' as `Edit`, r.idRoom as `Id`, r.Title, g.Description as `Type`, g3.Description as `Category`, r.Max_Occupants as `Max`,
r.Floor, r.Phone, g4.Description as `Static Rate`, g6.Description as `Clean Cycle` $depositCol
from room r
left join gen_lookups g on g.`Table_Name`='Room_Type' and g.`Code` = r.`Type`
left join gen_lookups g3 on g3.`Table_Name`='Room_Category' and g3.`Code`=r.Category
left join gen_lookups g4 on g4.`Table_Name`='Static_Room_Rate' and g4.`Code`=r.Rate_Code
left join gen_lookups g5 on g5.`Table_Name`='Key_Deposit_Code' and g5.`Code`=r.Key_Deposit_Code
left join gen_lookups g6 on g6.`Table_Name` = 'Room_Cleaning_Days' and g6.`Code` = r.Cleaning_Cycle_Code
order by r.Title;");

        $numResc = $stmt->rowCount();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $ra = array();
            foreach ($roomAttrs as $ras) {
                if ($ras['idEntity'] == $r['Id']) {
                    $ra[$ras['idAttribute']] = 'x';
                }
            }

            foreach ($attrs as $a) {
                $r[$a['Title']] = (isset($ra[$a['idAttribute']]) ? HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check')) . HTMLContainer::generateMarkup('span', 'x', array('class'=>'hhk-printmedia')) : '');
            }

            $r['Edit'] = HTMLInput::generateMarkup('', array('id'=>$r['Id'].'rmbtn', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'room', 'class'=>'reEditBtn ui-icon ui-icon-pencil', 'style'=>'width:20px;height:20px;margin-right:.5em;display:inline;', 'title'=>'Edit This Room'))
                . HTMLInput::generateMarkup('', array('id'=>$r['Id'].'rmDelete', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'room', 'class'=>'reDelBtn ui-icon ui-icon-trash', 'style'=>'width:20px;height:20px;display:inline;', 'title'=>'Delete Room'));


            $rooms[] = $r;
        }


        // New Room
       $newRow = array(
            'Edit' => HTMLInput::generateMarkup('New', array('id'=>'0btnrmNew', 'name'=>'0', 'type'=>'button', 'data-enty'=>'room', 'class'=>'reNewBtn')),
            'Id' => '',
            'Title' => '',
            'Type' => '',
            'Category' => '',
            'Max' => '',
            'Floor' => '',
            'Phone' => '',
            'Static Rate' => '',
            'Clean Cycle' => ''
            );

       if ($keyDeposit) {
           $newRow[$depositTitle] = '';
       }

        foreach ($attrs as $a) {
            $newRow[$a['Title']] = '';
        }


        $rooms[] = $newRow;  // array('Edit' => HTMLInput::generateMarkup('New', array('id'=>'0btnrmNew', 'name'=>'0', 'type'=>'button', 'data-enty'=>'room', 'class'=>'reNewBtn')));


        return HTMLContainer::generateMarkup('h3', 'Showing '.$numResc . ' Rooms') . CreateMarkupFromDB::generateHTML_Table($rooms, 'tblroom');

    }

    public static function saveResc_Room(PDO $dbh, $id, $type, $post, $user, $showPartitions, $keyDeposit, $visitFee) {

            if ($type == 'room') {

                return self::saveRoom($dbh, $id, $post, $user, $keyDeposit, $visitFee);

            } else if ($type == 'resc') {

                return self::saveResc($dbh, $id, $post, $user, $showPartitions);
            }

        return array("error"=>'Bad Builder Parameters');

    }

    public static function getStatusEvents(\PDO $dbh, $id, $type, $title, $resourceStatuses, $oosCodes) {

        if ($type == 'resc') {
            $whid = 'idResource';
            $title = 'Resource ' . $title;
        } else {
            $whid = 'idRoom';
            $title = 'Room ' . $title;
        }

        // Remove "Available" from resource statusus
        $rStts = array();
        foreach ($resourceStatuses as $r) {
            if ($r[0] != ResourceStatus::Available) {
                $rStts[$r[0]] = $r;
            }
        }

        $stmt = $dbh->prepare("Select * from resource_use where $whid = :id order by Start_Date");
        $stmt->execute(array(':id'=>$id));

        /* var \HTMLTable */
        $tbl = new HTMLTable();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'cbDel[' . $r['idResource_use'] . ']', 'type'=>'checkbox')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r['Start_Date'] == '' ? '' : date('M j, Y', strtotime($r['Start_Date'])), array('name'=>'txtstart[' . $r['idResource_use'] . ']', 'class'=>'ckdate')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r['End_Date'] == '' ? '' : date('M j, Y', strtotime($r['End_Date'])), array('name'=>'txtend[' . $r['idResource_use'] . ']', 'class'=>'ckdate')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($rStts, $r['Status'], FALSE), array('name'=>'selStatus[' . $r['idResource_use'] . ']')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($oosCodes, $r['OOS_Code'], TRUE), array('name'=>'selOos[' . $r['idResource_use'] . ']')))
                .HTMLTable::makeTd($r['Updated_By'])
                .HTMLTable::makeTd($r['Last_Updated'] == '' ? '' : date('M j, Y H:i', strtotime($r['Last_Updated'])))
                    );
        }

        // New event
        $tbl->addBodyTr(
                HTMLTable::makeTd('New:')
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtstart[0]', 'class'=>'ckdate')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtend[0]', 'class'=>'ckdate')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($rStts, '', TRUE), array('name'=>'selStatus[0]')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($oosCodes, '', TRUE), array('name'=>'selOos[0]')))
                .HTMLTable::makeTd('', array('colspan'=>'2')));


        $tbl->addHeaderTr(HTMLTable::makeTh('Delete').HTMLTable::makeTh('Start').HTMLTable::makeTh('End').HTMLTable::makeTh('Status').HTMLTable::makeTh('Reason').HTMLTable::makeTh('User').HTMLTable::makeTh('Last Updated'));

        $mkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h3', $title) . HTMLContainer::generateMarkup('form', $tbl->generateMarkup(), array('name'=>'statForm')), array('style'=>'font-size:.9em'));
        return array('tbl'=>$mkup);

    }

    public static function saveStatusEvents(\PDO $dbh, $idResource, $type, $post) {

        $uS = Session::getInstance();
        $reload = FALSE;
        $reply = '';
        $id = intval($idResource, 10);

        foreach ($post['selStatus'] as $k => $v) {

            $startDate = filter_var($post['txtstart'][$k], FILTER_SANITIZE_STRING);
            $endDate = filter_var($post['txtend'][$k], FILTER_SANITIZE_STRING);
            $stat = filter_var($post['selStatus'][$k], FILTER_SANITIZE_STRING);
            $oosCode = filter_var($post['selOos'][$k], FILTER_SANITIZE_STRING);

            $idRescUse = intval($k, 10);

            if ($idRescUse == 0 && ($stat == '' || $startDate == '' || $endDate == '')) {
                continue;
            }

            $ruRs = new ResourceUseRS();

            if ($idRescUse > 0) {
                $ruRs->idResource_use->setStoredVal($idRescUse);
                $rows = EditRS::select($dbh, $ruRs, array($ruRs->idResource_use));

                if (count($rows) > 0) {
                    EditRS::loadRow($rows[0], $ruRs);
                } else {
                    return array('error'=>'Status Event not found. ');
                }

                // Delete?
                if (isset($post['cbDel'][$k])) {
                    EditRS::delete($dbh, $ruRs, array($ruRs->idResource_use));
                    $reload = TRUE;
                    continue;
                }
            }

            $stDT = new DateTime($startDate);
            $enDT = new DateTime($endDate);

            // Check for resource in use
            $query = "select r.idResource from reservation r where r.Status in "
                    . "('" .ReservationStatus::Checkedout . "','" .ReservationStatus::Staying . "')
    and DATE(r.Actual_Arrival) < DATE(:dtend) and ifnull(DATE(r.Actual_Departure), DATE(r.Expected_Departure)) > DATE(:start)
    union
    select ru.idResource from resource_use ru where ru.idResource_use != :idRu and DATE(ru.Start_Date) < DATE(:ruend) and ifnull(DATE(ru.End_Date), DATE(now())) > DATE(:rustart)";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':idRu'=>$idRescUse,
                ':start'=>$stDT->format('Y-m-d'),
                ':dtend'=>$enDT->format('Y-m-d'),
                ':rustart'=>$stDT->format('Y-m-d'),
                ':ruend'=>$enDT->format('Y-m-d')));

            $inUse = FALSE;

            while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
                if ($r[0] == $id) {
                    $inUse = TRUE;
                }
            }

            if ($inUse) {
                $reply .= 'An existing visit or status record conflicts with the dates entered.  ';
                continue;
            }

            $ruRs->Start_Date->setNewVal($startDate);
            $ruRs->End_Date->setNewVal($endDate);

            if ($type == 'resc') {

                $ruRs->idResource->setNewVal($id);
                $ruRs->Status->setNewVal($stat);
                $ruRs->OOS_Code->setNewVal($oosCode);

            } else if ($type == 'room') {
                $reply .= 'Room status is unsupported.  ';
                continue;

//                $ruRs->idRoom->setNewVal($id);
//
//                if ($stat == RoomService::OutOfService) {
//                    $ruRs->Room_State->setNewVal(RoomService::OutOfService);
//                } else if ($stat == ResourceStatus::Unavailable) {
//                    $ruRs->Room_Availability->setNewVal(ResourceStatus::Unavailable);
//                }
            }

            $ruRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $ruRs->Updated_By->setNewVal($uS->username);

            $endDT = new DateTime($endDate);
            $endDT->setTime(0, 0, 0);
            $now = new DateTime();
            $now->setTime(0, 0, 0);

            $moveResv = FALSE;

            if ($endDT >= $now) {
                $moveResv = TRUE;
            }

            if ($idRescUse > 0) {
                // Update
                $num = EditRS::update($dbh, $ruRs, array($ruRs->idResource_use));
                if ($num > 0) {
                    $reload = TRUE;
                    if ($moveResv) {
                        $reply .= ReservationSvcs::moveResvAway($dbh, new DateTime($startDate), $endDT, $id, $uS->username);
                    }
                }

            } else {
                //Insert
                $idRescUse = EditRS::insert($dbh, $ruRs);
                $reload = TRUE;
                if ($moveResv) {
                    $reply .= ReservationSvcs::moveResvAway($dbh, new DateTime($startDate), $endDT, $id, $uS->username);
                }
            }
        }

        return array('reload'=>$reload, 'msg' => $reply);
    }

    public static function deleteResc_Room(PDO $dbh, $id, $type, $user) {


        if ($type == 'room') {

            // Delete Room
            if ($id > 0) {

                $room = new Room($dbh, $id);

                if ($room->deleteRoom($dbh, $user)) {

                    return array('success'=>'Room Deleted.');
                }

                return array('error'=>'Room not deleted.');

            }


        } else if ($type == 'resc') {

            // Delete Resource
            if ($id > 0) {

                $resc = Resource::getResourceObj($dbh, $id);

                if ($resc->deleteResource($dbh, $user)) {
                    return array('success'=>'Resource Deleted.');
                }

                return array('error'=>'Resource not deleted.');

            }

        }

    }

    public static function saveRoom(PDO $dbh, $idRoom, $post, $user, $keyDeposit, $visitFee) {

        $room = new Room($dbh, $idRoom);
        $rTitle = '';

        // alias
        $roomRs = $room->getRoomRS();

        if (isset($post['txtReTitle'])) {
            $rTitle = filter_var($post['txtReTitle'], FILTER_SANITIZE_STRING);
            $roomRs->Title->setNewVal($rTitle);
        }


        if ($rTitle == '') {
            return array("roomList"=>self::roomTable($dbh));
        }

        if (isset($post['txtPhone'])) {
            $roomRs->Phone->setNewVal(filter_var($post['txtPhone'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['txtFloor'])) {
            $roomRs->Floor->setNewVal(filter_var($post['txtFloor'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['txtRePriority'])) {
            $roomRs->Util_Priority->setNewVal(filter_var($post['txtRePriority'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['txtKing'])) {
            $roomRs->Beds_King->setNewVal(intval(filter_var($post['txtKing'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['txtQueen'])) {
            $roomRs->Beds_Queen->setNewVal(intval(filter_var($post['txtQueen'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['txtFull'])) {
            $roomRs->Beds_Full->setNewVal(intval(filter_var($post['txtFull'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['txtTwin'])) {
            $roomRs->Beds_Twin->setNewVal(intval(filter_var($post['txtTwin'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['txtMax'])) {
            $roomRs->Max_Occupants->setNewVal(intval(filter_var($post['txtMax'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['selReType'])) {
            $roomRs->Type->setNewVal(filter_var($post['selReType'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['selReCategory'])) {
            $roomRs->Category->setNewVal(filter_var($post['selReCategory'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['selRateCode'])) {
            $code = filter_var($post['selRateCode'], FILTER_SANITIZE_STRING);

            $roomRs->Rate_Code->setNewVal($code);

        }

        if (isset($post['selKeyCode'])) {
            $code = filter_var($post['selKeyCode'], FILTER_SANITIZE_STRING);

            $roomRs->Key_Deposit_Code->setNewVal($code);

        }

        if (isset($post['selCleanCode'])) {
            $code = filter_var($post['selCleanCode'], FILTER_SANITIZE_STRING);

            $roomRs->Cleaning_Cycle_Code->setNewVal($code);

        }

        if (isset($post['selVisitCode'])) {
            $code = filter_var($post['selVisitCode'], FILTER_SANITIZE_STRING);

            $roomRs->Visit_Fee_Code->setNewVal($code);

        }

        if (isset($post['selReClean'])) {
            $room->setStatus(filter_var($post['selReClean'], FILTER_SANITIZE_STRING));
        }

        $room->saveRoom($dbh, $user);


        // Run through the posts to find any attributes
        $capturedAttributes = array();
        foreach ($post as $k => $p) {

            if (stristr($k, 'cbat_')) {

                $parts = explode('_', $k);

                $capturedAttributes[intval($parts[1])] = intval($parts[1]);

            }
        }

        $roomAttr = new RoomAttributes($dbh, $room->getIdRoom());
        $roomAttr->saveAttributes($dbh, $capturedAttributes);

        return array("roomList"=>self::roomTable($dbh, $keyDeposit, $visitFee));
    }

    public static function saveResc(PDO $dbh, $idResc, $post, $username, $showPartitions) {

        $rType = '';
        $rTitle = '';

        if (isset($post["selReType"])) {
            $rType = filter_var($post["selReType"], FILTER_SANITIZE_STRING);
        }

        /* @var $resc \Resource */
        $resc = Resource::getResourceObj($dbh, $idResc, $rType);

        if (is_null($resc)) {
            return array("error"=>'Resource not found');
        }

        if (isset($post["txtReTitle"])) {
            $rTitle = filter_var($post["txtReTitle"], FILTER_SANITIZE_STRING);
            $resc->resourceRS->Title->setNewVal($rTitle);
        }

        if ($rTitle == '') {
            return array("rescList"=>self::resourceTable($dbh, $showPartitions));
        }

        if (isset($post["selReType"])) {
            $resc->resourceRS->Type->setNewVal(filter_var($post["selReType"], FILTER_SANITIZE_STRING));
        }
        if (isset($post["txtRePriority"])) {
            $resc->resourceRS->Util_Priority->setNewVal(filter_var($post["txtRePriority"], FILTER_SANITIZE_STRING));
        }
        if (isset($post['selUtilCat'])) {
            $resc->setUtilizationCategory(filter_var($post['selUtilCat'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['txtReBgc'])) {
            $resc->resourceRS->Background_Color->setNewVal(filter_var($post['txtReBgc'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['txtReBc'])) {
            $resc->resourceRS->Border_Color->setNewVal(filter_var($post['txtReBc'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['txtReTc'])) {
            $resc->resourceRS->Text_Color->setNewVal(filter_var($post['txtReTc'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['txtPartSize']) && $rType == ResourceTypes::Partition) {
            $resc->resourceRS->Partition_Size->setNewVal(intval(filter_var($post['txtPartSize'], FILTER_SANITIZE_NUMBER_INT), 10));
        }
        if (isset($post['selspons'])) {
            $resc->resourceRS->idSponsor->setNewVal(intval(filter_var($post['selspons'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        $setUna = FALSE;
        if ($resc->isNewResource() && isset($post['cbSetUna'])) {
            // Set resource unavailable
            $setUna = TRUE;
        }

        $resc->saveRecord($dbh, $username);


        // Set new resource unavailable
        if ($setUna) {
            // set resource unavailable
            $ruRs = new ResourceUseRS();

            $now = new DateTime();
            $now->add(new DateInterval('P1Y'));

            $ruRs->Start_Date->setNewVal('2010-01-01');
            $ruRs->End_Date->setNewVal($now->format('Y-m-d'));
            $ruRs->idResource->setNewVal($resc->getIdResource());
            $ruRs->Status->setNewVal(ResourceStatus::Unavailable);

            $ruRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $ruRs->Updated_By->setNewVal($username);

            EditRS::insert($dbh, $ruRs);

        }

        // Run through the posts to find any attributes
        $capturedAttributes = array();
        foreach ($post as $k => $p) {

            if (stristr($k, 'cbat_')) {

                $parts = explode('_', $k);

                $capturedAttributes[intval($parts[1])] = intval($parts[1]);

            }
        }

        $rescAttr = new ResourceAttributes($dbh, $resc->getIdResource());
        $rescAttr->saveAttributes($dbh, $capturedAttributes);


        // Linked rooms
        if (isset($post['selRooms'])) {

            $roomId = filter_var($post['selRooms'], FILTER_SANITIZE_NUMBER_INT);

            $resc->saveRooms($dbh, $roomId);

        }

        return array("rescList"=>self::resourceTable($dbh, $showPartitions));

    }

    public static function roomDialog(PDO $dbh, $idRoom, $roomTypes, $roomCategories, $rateCodes, $keyDepositCodes, $keyDeposit) {

        $roomRs = new RoomRs();
        $roomRs->idRoom->setStoredVal($idRoom);

        if ($idRoom > 0) {
            $rows = EditRS::select($dbh, $roomRs, array($roomRs->idRoom));
            if (count($rows) != 1) {
                return array('error'=>'Room not found.');
            }

            EditRS::loadRow($rows[0], $roomRs);
        }

        $cleaningCodes = readGenLookupsPDO($dbh, 'Room_Cleaning_Days');

        $room = new Room($dbh, 0, $roomRs);

        $cls = 'rmSave' . $room->getIdRoom();

        $saveBtn = HTMLInput::generateMarkup('Save', array('id'=>'savebtn', 'data-id'=>$room->getIdRoom(), 'data-type'=>'room', 'data-cls'=>$cls, 'type'=>'button'));
        $saveBtn .= HTMLInput::generateMarkup('Cancel', array('id'=>'cancelbtn', 'style'=>'margin-top:.2em;', 'data-id'=>$room->getIdRoom(), 'data-type'=>'room', 'data-cls'=>$cls, 'type'=>'button'));

        $tr = HTMLTable::makeTd($saveBtn) . HTMLTable::makeTd($room->getIdRoom())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($room->getTitle(), array('id'=>'txtReTitle', 'size'=>'12', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($roomTypes, $room->getType(), TRUE), array('id'=>'selReType', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($roomCategories, $room->getRoomCategory(), TRUE), array('id'=>'selReCategory', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
            // max occ
            . HTMLTable::makeTd(HTMLInput::generateMarkup($room->getMaxOccupants(), array('id'=>'txtMax', 'class'=>$cls, 'size'=>'3')), array('style'=>'padding-right:0;padding-left:0;'))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($roomRs->Floor->getStoredVal(), array('id'=>'txtFloor', 'class'=>$cls, 'size'=>'4')), array('style'=>'padding-right:0;padding-left:0;'))
            // phone
            . HTMLTable::makeTd(HTMLInput::generateMarkup($roomRs->Phone->getStoredVal(), array('id'=>'txtPhone', 'class'=>$cls . ' hhk-phoneInput', 'size'=>'10')), array('style'=>'padding-right:0;padding-left:0;'))
            // rate
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup(removeOptionGroups($rateCodes), $room->getRateCode(), FALSE), array('id'=>'selRateCode', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
            // Cleaning days
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup(removeOptionGroups($cleaningCodes), $room->getCleaningCycleCode(), FALSE), array('id'=>'selCleanCode', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'));

        if ($keyDeposit) {
            $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup(removeOptionGroups($keyDepositCodes), $room->getKeyDepositCode(), FALSE), array('id'=>'selKeyCode', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'));
        }


        $roomAttr = new RoomAttributes($dbh, $room->getIdRoom());
        $rattribute = $roomAttr->getAttributes();

        foreach ($rattribute as $a) {

            $parms = array('id'=>'cbat_'.$a['idAttribute'], 'type'=>'checkbox', 'data-idat'=>$a['idAttribute'], 'class'=>$cls);

            if ($a['isActive'] > 0) {
                $parms['checked'] = 'checked';
            }

            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $parms), array('style'=>'text-align:center;'));
        }


        return array('row'=>$tr);
    }

    public static function resourceDialog(PDO $dbh, $idResc, $resourceTypes, $hospitals) {

        $resc = Resource::getResourceObj($dbh, $idResc, ResourceTypes::Room);

        $cls = 'reDiag' . $resc->getIdResource();

        $saveBtn = HTMLInput::generateMarkup('Save', array('id'=>'savebtn', 'data-id'=>$resc->getIdResource(), 'data-type'=>'resc', 'data-cls'=>$cls, 'type'=>'button'));
        $saveBtn .= HTMLInput::generateMarkup('Cancel', array('id'=>'cancelbtn', 'style'=>'margin-top:.2em;', 'type'=>'button'));

        // New Resource?
        $stat = '';
        if ($resc->isNewResource()) {
            $stat = HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id'=>'cbSetUna', 'type'=>'checkbox', 'class'=>$cls, 'title'=>'Check to set room as unavailable from the beginning of time. '))
                . HTMLContainer::generateMarkup('label', 'Set Unavailable', array('for'=>'cbSetUna', 'style'=>'margin-left:.3em;')));
        }

        $partition = '';
        if ($resc->getType() == ResourceTypes::Partition) {
            $partition =  HTMLTable::makeTd(HTMLInput::generateMarkup($resc->getMaxOccupants(), array('id'=>'txtPartSize', 'class'=>$cls, 'size'=>'3')));
        }

        // Get rooms
        $stmt = $dbh->query("Select `idRoom` as `Code`, `Title` from room order by `Title`");

        $options = array();
        while ($r = $stmt->fetch()) {

            $options[$r['Code']] = $r;
        }

        $rms = $resc->getRooms();
        $useRooms = array();
        foreach ($rms as $k => $x) {
            $useRooms[] = $k;
        }


        $tr = HTMLTable::makeTd($saveBtn) . HTMLTable::makeTd($resc->getIdResource())
                . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->getTitle(), array('id'=>'txtReTitle', 'size'=>'10', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($resourceTypes), $resc->getType(), TRUE), array('id'=>'selReType', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
                //. HTMLTable::makeTd('', array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup(removeOptionGroups($hospitals), $resc->getIdSponsor(), TRUE), array('id'=>'selspons', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, $useRooms, TRUE), array('id'=>'selRooms', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->getUtilPriority(), array('id'=>'txtRePriority', 'class'=>$cls, 'size'=>'7')), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(readGenLookupsPDO($dbh, 'Utilization_Category'), $resc->getUtilPriority(), TRUE), array('id'=>'selUtilCat', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->resourceRS->Background_Color->getStoredVal(), array('id'=>'txtReBgc', 'class'=>$cls, 'size'=>'8')), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->resourceRS->Text_Color->getStoredVal(), array('id'=>'txtReTc', 'class'=>$cls, 'size'=>'8')), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->resourceRS->Border_Color->getStoredVal(), array('id'=>'txtReBc', 'class'=>$cls, 'size'=>'8')), array('style'=>'padding-right:0;padding-left:0;'))
                . $partition . $stat;

        $rescAttr = new ResourceAttributes($dbh, $resc->getIdResource());
        $rattribute = $rescAttr->getAttributes();

        foreach ($rattribute as $a) {

            $parms = array('id'=>'cbat_'.$a['idAttribute'], 'type'=>'checkbox', 'data-idat'=>$a['idAttribute'], 'class'=>$cls);


            if ($a['isActive'] > 0) {
                $parms['checked'] = 'checked';
            }

            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $parms), array('style'=>'text-align:center;padding-right:0;padding-left:0;'));
        }

        return array('row'=>$tr);
    }

    public static function roomListHtml(PDO $dbh, $cleanStatuses, $roomTypes, $roomCategories, $staticRates, $useKeyDeposit = FALSE, $keyDepositCodes = array()) {
        $tbl = new HTMLTable();

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        $tbl->addHeaderTr(
            HTMLTable::makeTh('Title')
            . HTMLTable::makeTh('Type')
            . HTMLTable::makeTh('Category')
            . HTMLTable::makeTh('Status')
            . HTMLTable::makeTh('Rate')
            . ($useKeyDeposit ? HTMLTable::makeTh($labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit')) : '')
            . HTMLTable::makeTh('Max Occ.')
        );

        $stmt = $dbh->prepare("Select * from room");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {

            $tbl->addBodyTr(
                HTMLTable::makeTd($r['Title'], array('class'=>'hhka'))
               . HTMLTable::makeTd($roomTypes[$r['Type']][1], array('class'=>'hhka'))
               . HTMLTable::makeTd(($r['Category'] != '' ? $roomCategories[$r['Category']][1] : ''), array('class'=>'hhka'))
               . HTMLTable::makeTd($cleanStatuses[$r['Status']][1], array('class'=>'hhk' . $cleanStatuses[$r['Status']][0]))
               . HTMLTable::makeTd($r['Rate_Code'] != '' ? '$'.$staticRates[$r['Rate_Code']][2] : '', array('style'=>'text-align:center;','class'=>'hhka'))
               . ($useKeyDeposit ? HTMLTable::makeTd(($r['Key_Deposit_Code'] != '' ? '$'.$keyDepositCodes[$r['Key_Deposit_Code']][2] : ''), array('style'=>'text-align:center;','class'=>'hhka')) : '')
               . HTMLTable::makeTd($r['Max_Occupants'], array('style'=>'text-align:center;','class'=>'hhka'))

            );

        }

        $style = "<style>td {background-color:yellow;} td.hhka {background-color:transparent;}</style>";

        return $style . $tbl->generateMarkup(array('id'=>'tblRescList', 'class'=>'display'));
    }

    public static function dirtyOccupiedRooms(\PDO $dbh) {

        $uS = Session::getInstance();
        $cleanDays = readGenLookupsPDO($dbh, 'Room_Cleaning_Days');

        $today = new DateTime();
        $today->setTime(0,0,0);

        $stmt = $dbh->query("select
    r.*,
    v.idVisit,
    ifnull(v.Arrival_Date, '') as `Arrival`
from
    room r
        left join
    resource_room rr ON r.idRoom = rr.idRoom
        join
    visit v ON rr.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "';");

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $roomRs = new RoomRs();
            EditRS::loadRow($r, $roomRs);
            $rm = new Room($dbh, 0, $roomRs);

            // Put dirty if room active for longer than days.
            if ($r['idVisit'] > 0 && $r['Status'] == RoomState::Clean && isset($cleanDays[$rm->getCleaningCycleCode()])) {

                $arrDT = $today;
                $lastCleanedDT = $today;

                if ($r['Arrival'] != '') {
                    $arrDT = new DateTime($r['Arrival']);
                    $arrDT->setTime(0, 0, 0);
                }

                if ($r['Last_Cleaned'] != '') {
                    $lastCleanedDT = new DateTime($r['Last_Cleaned']);
                    $lastCleanedDT->setTime(0, 0, 0);
                }

                // Start from the visit date if cleaned earlier...
                if ($arrDT > $lastCleanedDT) {
                    $lastCleanedDT = $arrDT;
                }

                $days = intval($cleanDays[$rm->getCleaningCycleCode()][2], 10);

                if ($days > 0 && $today->diff($lastCleanedDT, TRUE)->days >= $days) {
                    // Set room dirty
                    $rm->putDirty();
                    $rm->saveRoom($dbh, $uS->username, TRUE);
                }
            }
        }
    }

    public static function roomsClean(\PDO $dbh, $filter = '', $guestAdmin = FALSE, $printOnly = FALSE) {

        $today = new DateTime();
        $today->setTime(0,0,0);

        $returnRows = array();

        $stmt = $dbh->query("select
    r.idRoom,
    ifnull(v.idVisit, 0) as idVisit,
    r.Title,
    r.`Status`,
    ifnull(g.Description, 'Unknown') as `Status_Text`,
    r.`Cleaning_Cycle_Code`,
    ifnull(n.Name_Full, '') as `Name`,
    ifnull(v.Arrival_Date, '') as `Arrival`,
    ifnull(v.Expected_Departure, '') as `Expected_Departure`,
    r.Last_Cleaned,
    r.Notes
from
    room r
        left join
    resource_room rr ON r.idRoom = rr.idRoom
        left join
    visit v ON rr.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
        left join
    name n ON v.idPrimaryGuest = n.idName
        left join
    gen_lookups g on g.Table_Name = 'Room_Status' and g.Code = R.Status");


        // Loop rooms.
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            if ($filter == RoomState::Dirty && !($r['Status'] == RoomState::Dirty || $r['Status'] == RoomState::TurnOver)) {
                continue;
            }

            $fixedRows = array();
            $stat = '';
            $isDirty = FALSE;

            // Mangle room status
            if ($r['Status'] == RoomState::TurnOver) {
                $stat = HTMLContainer::generateMarkup('span', $r['Status_Text'], array('style'=>'background-color:yellow;'));
                $isDirty = TRUE;
            } else if ($r['idVisit'] > 0 && $r['Status'] == RoomState::Dirty) {
                $stat = HTMLContainer::generateMarkup('span', 'Active-Dirty', array('style'=>'background-color:#E3FF14;'));
                $isDirty = TRUE;
            } else if ($r['idVisit'] > 0 && $r['Status'] == RoomState::Clean) {
                $stat = HTMLContainer::generateMarkup('span', 'Active', array('style'=>'background-color:lightgreen;'));
            } else if ($r['Status'] == RoomState::Dirty) {
                $stat = HTMLContainer::generateMarkup('span', 'Dirty', array('style'=>'background-color:yellow;'));
                $isDirty = TRUE;
            } else {
                $stat = HTMLContainer::generateMarkup('span', $r['Status_Text']);
            }

            // Expected Departure
            if ($r['Expected_Departure'] != '') {

                $expDepDT = new DateTime($r['Expected_Departure']);
                $expDepDT->setTime(0, 0, 0);

                $edAttr = array();

                if ($expDepDT < $today) {
                    $edAttr['style'] = 'color:red;';
                }

                $expDep = HTMLTable::makeTd($expDepDT->format('D, M j, Y'), $edAttr);

            } else {
                $expDep = HTMLTable::makeTd('');
            }



            if ($printOnly) {
                    // reverse output
                $lines = explode("\n", $r['Notes']);
                $reverse = "";

                for ($i = (count($lines) - 1); $i >= 0; $i--) {
                    $reverse .= $lines[$i] . "<br/>";
                }

                $notes = HTMLContainer::generateMarkup('div', $reverse, array('id'=>'hhk-existgNotes'));

            } else {
                $notes = Notes::markupShell($r['Notes'], $filter.'taNotes[' . $r['idRoom'] . ']');
            }


            // action buttons
            $action = '';
            if ($printOnly === FALSE) {

                if ($isDirty) {
                $action = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbClean[' . $r['idRoom'] . ']', 'id'=>$filter.'cbClean' . $r['idRoom']))
                    .HTMLContainer::generateMarkup('label', 'Set Clean', array('for'=>$filter.'cbClean' . $r['idRoom'], 'style'=>'margin-left:.2em;')) . "<br/>";

                } else {
                    $action .= HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbDirty[' . $r['idRoom'] . ']', 'id'=>$filter.'cbDirty' . $r['idRoom']))
                        .HTMLContainer::generateMarkup('label', 'Set Dirty', array('for'=>$filter.'cbDirty' . $r['idRoom'], 'style'=>'margin-left:.2em;')) . "<br/>";
                }

                $action .= ($guestAdmin === FALSE ? '' : HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbDeln[' . $r['idRoom'] . ']', 'id'=>$filter.'cbDeln' . $r['idRoom']))
                            .HTMLContainer::generateMarkup('label', 'Delete Notes', array('for'=>$filter.'cbDeln' . $r['idRoom'], 'style'=>'margin-left:.2em;'))
                        );

            }

            $fixedRows['Room'] = $r['Title'];
            $fixedRows['Status'] = $stat;
            $fixedRows['Action'] = $action;
            $fixedRows['Occupant'] = $r['Name'];
            $fixedRows['Checked In'] = $r['Arrival'] == '' ? '' : date('D, M j', strtotime($r['Arrival']));
            $fixedRows['Expected Checkout'] = $expDep;
            $fixedRows['Last Cleaned'] = $r['Last_Cleaned'] == '' ? '' : date('D, M j', strtotime($r['Last_Cleaned']));
            $fixedRows['Notes'] = ($printOnly ? '' : HTMLTable::makeTd($notes, array('style'=>'min-width:300px;')));

            $returnRows[] = $fixedRows;
        }

        return $returnRows;
    }

    public static function showCoList(PDO $dbh, $coDate) {

        $returnRows = array();

        if ($coDate == '') {
            return $returnRows;
        }

        $stmt = $dbh->query("select
        r.idRoom,
        ifnull(v.idVisit, 0) as idVisit,
        r.`Title`,
        ifnull(n.Name_Full, '') as `Name`,
        v.Arrival_Date,
        ifnull(DATE(v.Span_End), DATE(dateDefaultNow(v.Expected_Departure))) as `Departure_Date`,
        g.`Description` as `Visit_Status`,
        r.`Last_Cleaned`,
        r.`Notes`
    from
        room r
            left join
        resource_room rr ON r.idRoom = rr.idRoom
            join
        visit v ON rr.idResource = v.idResource and v.`Status` in ('" . VisitStatus::CheckedIn . "', '" . VisitStatus::CheckedOut . "', '" . VisitStatus::NewSpan . "')
            left join
        name n ON v.idPrimaryGuest = n.idName
            left join
        gen_lookups g on g.Table_Name = 'Visit_Status' and g.Code = v.`Status`
    where ifnull(DATE(v.Span_End), DATE(dateDefaultNow(v.Expected_Departure))) = Date('$coDate');");


        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // reverse output
        $lines = explode("\n", $r['Notes']);
        $reverse = "";

        for ($i = (count($lines) - 1); $i >= 0; $i--) {
            $reverse .= $lines[$i] . "<br/>";
        }



            $returnRows[] = array(
                'Room' => $r['Title'],
                'Visit Status' => $r['Visit_Status'],
                'Primary Guest' => $r['Name'],
                'Arrival Date' => date('M j, Y', strtotime($r['Arrival_Date'])),
                'Expected Checkout' => date('M j, Y', strtotime($r['Departure_Date'])),
                'Notes' => $reverse
            );

        }

        return $returnRows;
    }

    public static function CleanLog(PDO $dbh, $id, $get) {

        require(CLASSES . 'DataTableServer.php');

        $aColumns = array('idRoom', 'Title', 'Type', 'Status_Text', 'Last_Cleaned', 'Notes', 'Username', 'Timestamp');
        $sIndexColumn = "";
        $sTable = "vcleaning_log";

        // filter by Id ...
        if ($id > 0) {
            $get["bSearchable_0"] = "true";
            $get["sSearch_0"] = $id;
        }

        $log = DataTableServer::createOutput($dbh, $aColumns, $sIndexColumn, $sTable, $get);

        // format the date column
        for ($i = 0; $i < count($log['aaData']); $i++) {

            $log['aaData'][$i]["Timestamp"] = date("c", strtotime($log['aaData'][$i]["Timestamp"]));

        }

        return $log;
    }

}


