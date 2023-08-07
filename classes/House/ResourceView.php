<?php

namespace HHK\House;

use HHK\House\Attribute\Attributes;
use HHK\Purchase\RoomRate;
use HHK\SysConst\AttributeTypes;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\CreateMarkupFromDB;
use HHK\SysConst\ResourceStatus;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\Tables\House\ResourceUseRS;
use HHK\Tables\EditRS;
use HHK\SysConst\ReservationStatus;
use HHK\House\Reservation\ReservationSvcs;
use HHK\House\Room\Room;
use HHK\House\Resource\AbstractResource;
use HHK\House\Attribute\RoomAttribute;
use HHK\House\Resource\ResourceTypes;
use HHK\House\Attribute\ResourceAttribute;
use HHK\Tables\House\RoomRS;
use HHK\SysConst\VisitStatus;
use HHK\SysConst\RoomState;
use HHK\Notes;
use HHK\DataTableServer\SSP;
use HHK\Purchase\PriceModel\AbstractPriceModel;

/**
 * ResourceView.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ResourceView
 * @author Eric
 */
class ResourceView {

    public static function resourceTable(\PDO $dbh) {

        $uS = Session::getInstance();

        $rooms = array();

        $attribute = new Attributes($dbh);
        $attrs = $attribute->getAttributesByType(AttributeTypes::Resource);

        $rmAtrStmt = $dbh->query("Select idEntity, idAttribute from attribute_entity where `Type` = '" . AttributeTypes::Resource . "'");
        $roomAttrs = $rmAtrStmt->fetchAll(\PDO::FETCH_ASSOC);


        $stmt = $dbh->query("Select
    '' as `Edit`,
    r.idResource as `Id`,
    r.Title,
    ifnull(g.Description,'') as `Type`,
    ifnull(rm.Title, '') as `Room`,
    r.Util_Priority as `Priority`,
    r.Background_Color as `Bkgrd Color`,
    r.Text_Color as `Text Color`,
    r.Retired_At as `Retired At`,
    if(date(now()) >= date(r.Retired_At), 'hhk-retired', '') as `isRetired`
from
    resource r
        left join
    gen_lookups g ON g.Table_Name = 'Resource_Type' and g.`Code` = r.`Type`
        left join
    resource_room rr on r.idResource = rr.idResource
        left join
    room rm on rr.idRoom = rm.idRoom
order by r.Retired_At, r.Title;");

        $idResc = 0;
        $numResc = $stmt->rowCount();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($idResc != $r['Id']) {

                $r["Retired At"] = (!empty($r["Retired At"]) ? (new \DateTime($r["Retired At"]))->format('M j, Y'): "");

                $ra = array();
                foreach ($roomAttrs as $ras) {
                    if ($ras['idEntity'] == $r['Id']) {
                        $ra[$ras['idAttribute']] = 'x';
                    }
                }

                foreach ($attrs as $a) {
                    $r[$a['Title']] = (isset($ra[$a['idAttribute']]) ? HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check')) . HTMLContainer::generateMarkup('span', 'x', array('class'=>'hhk-printmedia')) : '');
                }

                $r['Edit'] = HTMLInput::generateMarkup('', array('id'=>$r['Id'].'rebtn', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'resc', 'class'=>'reEditBtn ui-icon ui-icon-pencil', 'style'=>'width:20px;height:20px;margin-right:.5em;display:inline;', 'title'=>'Edit This Resource'));

                $r['Status'] = HTMLInput::generateMarkup('', array('id'=>$r['Id'].'reStatbtn', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'resc', 'data-title'=>$r['Title'], 'class'=>'reStatBtn ui-icon ui-icon-wrench', 'style'=>'width:20px;height:20px;margin-left:.5em;display:inline;', 'title'=>'View Status Events'));

                if($uS->Room_Colors != "room"){
                    unset($r["Bkgrd Color"]);
                    unset($r["Text Color"]);
                }

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
            'Room' => '',
            'Priority' => '',
            'Bkgrd Color' => '',
            'Text Color' => '',
            'Retired_At' => '',
            'status' => ''
            );

       if($uS->Room_Colors != "room"){
           unset($newRow["Bkgrd Color"]);
           unset($newRow["Text Color"]);
       }


        $rooms[] = $newRow;

        return HTMLContainer::generateMarkup('h3', 'Showing '.$numResc . ' Resources') . CreateMarkupFromDB::generateHTML_Table($rooms, 'tblresc', 'isRetired');

    }

    public static function roomTable(\PDO $dbh, $keyDeposit = FALSE, $payGW = '') {

        $rooms = array();
        // Get labels
        $labels = Labels::getLabels();

        $attribute = new Attributes($dbh);
        $attrs = $attribute->getAttributesByType(AttributeTypes::Room);

        $rmAtrStmt = $dbh->query("Select idEntity, idAttribute from attribute_entity where `Type` = '" . AttributeTypes::Room . "'");
        $roomAttrs = $rmAtrStmt->fetchAll(\PDO::FETCH_ASSOC);

        $depositCol = '';
        $depositTitle = $labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit');

        if ($keyDeposit) {
            $depositCol .= ", g5.Description as `$depositTitle` ";
        }

        if ($payGW != '') {
            $depositCol .= ", ifnull(l.Merchant, '') as `Merchant` ";
        }


        $stmt = $dbh->query("Select '' as `Edit`, r.idRoom as `Id`, r.Title, g.Description as `Type`, g3.Description as `Category`, g7.Description as `Report Category`, r.Max_Occupants as `Max`,
r.Floor, r.Phone, g4.Description as `Static Rate`, ifnull(rr.Title, '') as `Default Rate` , g6.Description as `Clean Cycle`, if(count(rcr.idResource_room) = count(resc.idResource), 'hhk-retired', '') as `isRetired` $depositCol
from room r
left join gen_lookups g on g.`Table_Name`='Room_Type' and g.`Code` = r.`Type`
left join gen_lookups g3 on g3.`Table_Name`='Room_Category' and g3.`Code`=r.Category
left join gen_lookups g4 on g4.`Table_Name`='Static_Room_Rate' and g4.`Code`=r.Rate_Code
left join gen_lookups g5 on g5.`Table_Name`='Key_Deposit_Code' and g5.`Code`=r.Key_Deposit_Code
left join gen_lookups g6 on g6.`Table_Name` = 'Room_Cleaning_Days' and g6.`Code` = r.Cleaning_Cycle_Code
left join gen_lookups g7 on g7.`Table_Name` = 'Room_Rpt_Cat' and g7.`Code` = r.Report_Category
left join location l on r.idLocation = l.idLocation
left join room_rate rr on r.Default_Rate_Category = rr.FA_Category and rr.`Status` = 'a'
left join resource_room rcr on r.idRoom = rcr.idRoom
left join resource resc on rcr.idResource = resc.idResource and date(now()) >= date(resc.Retired_At)
group by r.idRoom
order by r.Title;");

        $numResc = $stmt->rowCount();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $ra = array();
            foreach ($roomAttrs as $ras) {
                if ($ras['idEntity'] == $r['Id']) {
                    $ra[$ras['idAttribute']] = 'x';
                }
            }

            foreach ($attrs as $a) {
                $r[$a['Title']] = (isset($ra[$a['idAttribute']]) ? HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check')) . HTMLContainer::generateMarkup('span', 'x', array('class'=>'hhk-printmedia')) : '');
            }

            $r['Edit'] = HTMLInput::generateMarkup('', array('id'=>$r['Id'].'rmbtn', 'name'=>$r['Id'], 'type'=>'button', 'data-enty'=>'room', 'class'=>'reEditBtn ui-icon ui-icon-pencil', 'style'=>'width:20px;height:20px;margin-right:.5em;display:inline;', 'title'=>'Edit This Room'));


            $rooms[] = $r;
        }


        // New Room
       $newRow = array(
            'Edit' => HTMLInput::generateMarkup('New', array('id'=>'0btnrmNew', 'name'=>'0', 'type'=>'button', 'data-enty'=>'room', 'class'=>'reNewBtn')),
            'Id' => '',
            'Title' => '',
            'Type' => '',
            'Category' => '',
            'Report Category' => '',
            'Max' => '',
            'Floor' => '',
            'Phone' => '',
            'Static Rate' => '',
            'Default Rate' => '',
            'Clean Cycle' => ''
            );

       if ($keyDeposit) {
           $newRow[$depositTitle] = '';
       }

        if ($payGW != '') {
            $newRow['Merchant'] = '';
        }

        foreach ($attrs as $a) {
            $newRow[$a['Title']] = '';
        }


        $rooms[] = $newRow;  // array('Edit' => HTMLInput::generateMarkup('New', array('id'=>'0btnrmNew', 'name'=>'0', 'type'=>'button', 'data-enty'=>'room', 'class'=>'reNewBtn')));


        return HTMLContainer::generateMarkup('h3', 'Showing '.$numResc . ' Rooms') . CreateMarkupFromDB::generateHTML_Table($rooms, 'tblroom', 'isRetired');

    }

    public static function saveResc_Room(\PDO $dbh, $id, $type, $post, $user, $showPartitions, $keyDeposit) {

            if ($type == 'room') {

                return self::saveRoom($dbh, $id, $post, $user, $keyDeposit);

            } else if ($type == 'resc') {

                return self::saveResc($dbh, $id, $post, $user, $showPartitions);
            }

        return array("error"=>'Bad Builder Parameters');

    }

    public static function getStatusEvents(\PDO $dbh, $id, $type, $title, $resourceStatuses, $oosCodes) {

        $id = intval($id, 10);

        if ($id < 1) {
            return array('error'=>'Invalid resource id: '.$id);
        }

        if ($type == 'resc') {
            $whid = 'idResource';
            $title = 'Room ' . $title;
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

        $stmt = $dbh->query("Select * from resource_use where $whid = $id order by Start_Date desc");


        /* var \HTMLTable */
        $tbl = new HTMLTable();

        // New event
        $tbl->addBodyTr(
            HTMLTable::makeTd('New:')
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtstart[0]', 'class'=>'ckdate')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtend[0]', 'class'=>'ckdate')))
            .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($rStts, '', TRUE), array('name'=>'selStatus[0]')))
            .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($oosCodes, '', TRUE), array('name'=>'selOos[0]')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtNotes[0]')))
            .HTMLTable::makeTd('', array('colspan'=>'2')));

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'cbDel[' . $r['idResource_use'] . ']', 'type'=>'checkbox')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r['Start_Date'] == '' ? '' : date('M j, Y', strtotime($r['Start_Date'])), array('name'=>'txtstart[' . $r['idResource_use'] . ']', 'class'=>'ckdate')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r['End_Date'] == '' ? '' : date('M j, Y', strtotime($r['End_Date'])), array('name'=>'txtend[' . $r['idResource_use'] . ']', 'class'=>'ckdate')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($rStts, $r['Status'], FALSE), array('name'=>'selStatus[' . $r['idResource_use'] . ']')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($oosCodes, $r['OOS_Code'], TRUE), array('name'=>'selOos[' . $r['idResource_use'] . ']')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r["Notes"], array('name'=>'txtNotes[' . $r['idResource_use'] . ']')))
                .HTMLTable::makeTd($r['Updated_By'])
                .HTMLTable::makeTd($r['Last_Updated'] == '' ? '' : date('M j, Y H:i', strtotime($r['Last_Updated'])))
                    );
        }


        $tbl->addHeaderTr(HTMLTable::makeTh('Delete').HTMLTable::makeTh('Start').HTMLTable::makeTh('End').HTMLTable::makeTh('Status').HTMLTable::makeTh('Reason').HTMLTable::makeTh('Notes').HTMLTable::makeTh('User').HTMLTable::makeTh('Last Updated'));

        $mkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h3', $title) . HTMLContainer::generateMarkup('form', $tbl->generateMarkup(), array('name'=>'statForm')), array('style'=>'font-size:.9em; max-height: 450px; overflow: auto;'));
        return array('tbl'=>$mkup);

    }

    public static function saveStatusEvents(\PDO $dbh, $idResource, $type, $post) {

        $uS = Session::getInstance();
        $reload = FALSE;
        $reply = '';
        $id = intval($idResource, 10);

        foreach ($post['selStatus'] as $k => $v) {

            $startDate = filter_var($post['txtstart'][$k], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $endDate = filter_var($post['txtend'][$k], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $stat = filter_var($v, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $oosCode = filter_var($post['selOos'][$k], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $notes = filter_var($post['txtNotes'][$k], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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

            $stDT = new \DateTime($startDate);
            $enDT = new \DateTime($endDate);

            // Check for resource in use
            $query = "SELECT
    r.idResource
FROM
    reservation r
WHERE
case WHEN r.`Status` = '" .ReservationStatus::Staying . "' THEN
		DATE(r.Actual_Arrival) < DATE(:rsend)
                AND DATE(datedefaultnow(r.Expected_Departure)) > DATE(:dtstart)
	WHEN r.`Status` = '" .ReservationStatus::Checkedout . "' THEN
        DATE(r.Actual_Arrival) < DATE(:dtend)
        AND DATE(r.Actual_Departure) > DATE(:start)
        AND DATEDIFF(r.Actual_Departure, r.Actual_Arrival) > 0
	ELSE 1=2
END
UNION
SELECT
    resc.idResource
FROM resource resc
WHERE
    resc.Retired_At is not null
    AND DATE(resc.Retired_At) <= DATE(:retend)
UNION
SELECT
    ru.idResource
FROM resource_use ru
WHERE
    ru.idResource_use != :idRu
    AND DATE(ru.Start_Date) < DATE(:ruend)
    AND ifnull(DATE(ru.End_Date), DATE(now())) > DATE(:rustart)";

            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':idRu'=>$idRescUse,
                ':start'=>$stDT->format('Y-m-d'),
                ':dtstart'=>$stDT->format('Y-m-d'),
                ':dtend'=>$enDT->format('Y-m-d'),
                ':rsend'=>$enDT->format('Y-m-d'),
                ':rustart'=>$stDT->format('Y-m-d'),
                ':ruend'=>$enDT->format('Y-m-d'),
                ':retend'=>$enDT->format('Y-m-d')));

            $inUse = FALSE;

            while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
                if ($r[0] == $id) {
                    $inUse = TRUE;
                }
            }

            if ($inUse) {

                if ($idRescUse == 0) {
                    $reply .= 'An existing reservation or room status record conflicts with the dates entered.  ';
                }
                continue;
            }

            $ruRs->Start_Date->setNewVal($stDT->format('Y-m-d 10:00:00'));
            $ruRs->End_Date->setNewVal($enDT->format('Y-m-d 10:00:00'));

            if ($type == 'resc') {

                $ruRs->idResource->setNewVal($id);
                $ruRs->Status->setNewVal($stat);
                $ruRs->OOS_Code->setNewVal($oosCode);
                $ruRs->Notes->setNewVal($notes);

            } else if ($type == 'room') {
                $reply .= 'Room status is unsupported.  ';
                continue;
            }

            $ruRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $ruRs->Updated_By->setNewVal($uS->username);

            $enDT->setTime(10, 0, 0);
            $stDT->setTime(10,0,0);
            $now = new \DateTime();
            $now->setTime(10, 0, 0);

            $moveResv = FALSE;

            if ($enDT >= $now) {
                $moveResv = TRUE;
            }

            if ($idRescUse > 0) {
                // Update
                $num = EditRS::update($dbh, $ruRs, array($ruRs->idResource_use));
                if ($num > 0) {
                    $reload = TRUE;
                    if ($moveResv) {
                        $reply .= ReservationSvcs::moveResvAway($dbh, $stDT, $enDT, $id, $uS->username);
                    }
                }

            } else {
                //Insert
                $idRescUse = EditRS::insert($dbh, $ruRs);
                $reload = TRUE;
                if ($moveResv) {
                    $reply .= ReservationSvcs::moveResvAway($dbh, $stDT, $enDT, $id, $uS->username);
                }
            }
        }

        return array('reload'=>$reload, 'msg' => $reply);
    }

    public static function deleteResc_Room(\PDO $dbh, $id, $type, $user) {


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

                $resc = AbstractResource::getResourceObj($dbh, $id);

                if ($resc->deleteResource($dbh, $user)) {
                    return array('success'=>'Resource Deleted.');
                }

                return array('error'=>'Resource not deleted.');

            }

        }

    }

    public static function saveRoom(\PDO $dbh, $idRoom, $post, $user, $keyDeposit) {

        $uS = Session::getInstance();
        $room = new Room($dbh, $idRoom);
        $rTitle = '';

        // alias
        $roomRs = $room->getRoomRS();

        if (isset($post['txtReTitle'])) {
            $rTitle = filter_var($post['txtReTitle'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $roomRs->Title->setNewVal($rTitle);
        }


        if ($rTitle == '') {
            return array("roomList"=>self::roomTable($dbh, $keyDeposit));
        }

        if (isset($post['txtPhone'])) {
            $roomRs->Phone->setNewVal(filter_var($post['txtPhone'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($post['txtFloor'])) {
            $roomRs->Floor->setNewVal(filter_var($post['txtFloor'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($post['txtRePriority'])) {
            $roomRs->Util_Priority->setNewVal(filter_var($post['txtRePriority'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
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
            $roomRs->Type->setNewVal(filter_var($post['selReType'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($post['selReCategory'])) {
            $roomRs->Category->setNewVal(filter_var($post['selReCategory'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($post['selRptCategory'])) {
            $roomRs->Report_Category->setNewVal(filter_var($post['selRptCategory'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post['selRateCode'])) {
            $code = filter_var($post['selRateCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $roomRs->Rate_Code->setNewVal($code);

        }

        if (isset($post['selRateCat'])) {
            $code = filter_var($post['selRateCat'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $roomRs->Default_Rate_Category->setNewVal($code);

        }

        if (isset($post['selLocId'])) {
            $idLoc = intval(filter_var($post['selLocId'], FILTER_SANITIZE_NUMBER_INT));
            $roomRs->idLocation->setNewVal($idLoc);
        }

        if (isset($post['selKeyCode'])) {
            $code = filter_var($post['selKeyCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $roomRs->Key_Deposit_Code->setNewVal($code);

        }

        if (isset($post['selCleanCode'])) {
            $code = filter_var($post['selCleanCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $roomRs->Cleaning_Cycle_Code->setNewVal($code);

        }

        if (isset($post['selVisitCode'])) {
            $code = filter_var($post['selVisitCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $roomRs->Visit_Fee_Code->setNewVal($code);

        }

        if (isset($post['selReClean'])) {
            $room->setStatus(filter_var($post['selReClean'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
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

        $roomAttr = new RoomAttribute($dbh, $room->getIdRoom());
        $roomAttr->saveAttributes($dbh, $capturedAttributes);

        return array("roomList"=>self::roomTable($dbh, $keyDeposit, $uS->PaymentGateway));
    }

    public static function saveResc(\PDO $dbh, $idResc, $post, $username, $showPartitions) {

        $rType = '';
        $rTitle = '';

        if (isset($post["selReType"])) {
            $rType = filter_var($post["selReType"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        /* @var $resc AbstractResource */
        $resc = AbstractResource::getResourceObj($dbh, $idResc, $rType);

        if (is_null($resc)) {
            return array("error"=>'Resource not found');
        }

        if (isset($post["txtReTitle"])) {
            $rTitle = filter_var($post["txtReTitle"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $resc->resourceRS->Title->setNewVal($rTitle);
        }

        if ($rTitle == '') {
            return array("rescList"=>self::resourceTable($dbh));
        }

        if (isset($post["selReType"])) {
            $resc->resourceRS->Type->setNewVal(filter_var($post["selReType"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post["txtRePriority"])) {
            $resc->resourceRS->Util_Priority->setNewVal(filter_var($post["txtRePriority"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post['txtReBgc'])) {
            $resc->resourceRS->Background_Color->setNewVal(filter_var($post['txtReBgc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post['txtReBc'])) {
            $resc->resourceRS->Border_Color->setNewVal(filter_var($post['txtReBc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post['txtReTc'])) {
            $resc->resourceRS->Text_Color->setNewVal(filter_var($post['txtReTc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post['txtRetired'])) {
            $retiredAt = filter_var($post['txtRetired'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if($retiredAt == ''){
                $dbh->exec("UPDATE resource set `Retired_At` = null where idResource = '" . $resc->getIdResource() . "';");
            }else{
                try{
                    $retiredDT = new \DateTime($retiredAt);
                    $resc->resourceRS->Retired_At->setNewVal($retiredDT->format("Y-m-d H:i:s"));
                }catch(\Exception $e){
                    
                }
            }
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

            $now = new \DateTime();
            $now->add(new \DateInterval('P1Y'));

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

        $rescAttr = new ResourceAttribute($dbh, $resc->getIdResource());
        $rescAttr->saveAttributes($dbh, $capturedAttributes);


        // Linked rooms
        if (isset($post['selRooms'])) {

            $roomId = filter_var($post['selRooms'], FILTER_SANITIZE_NUMBER_INT);

            $resc->saveRooms($dbh, $roomId);

        }

        return array("rescList"=>self::resourceTable($dbh));

    }

    public static function roomDialog(\PDO $dbh, $idRoom, $roomTypes, $roomCategories, $reportCategories, $rateCodes, $keyDepositCodes, $keyDeposit) {

        $uS = Session::getInstance();

        $roomRs = new RoomRS();
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

        $rateCategories = RoomRate::makeSelectorOptions(AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel));


        $cls = 'rmSave' . $room->getIdRoom();

        $saveBtn = HTMLInput::generateMarkup('Save', array('id'=>'savebtn', 'class'=>'mr-2', 'data-id'=>$room->getIdRoom(), 'data-type'=>'room', 'data-cls'=>$cls, 'type'=>'button'));
        $saveBtn .= HTMLInput::generateMarkup('Cancel', array('id'=>'cancelbtn', 'style'=>'margin-top:.2em;', 'data-id'=>$room->getIdRoom(), 'data-type'=>'room', 'data-cls'=>$cls, 'type'=>'button'));

        $tr = HTMLTable::makeTd($saveBtn) . HTMLTable::makeTd($room->getIdRoom())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($room->getTitle(), array('id'=>'txtReTitle', 'size'=>'12', 'class'=>$cls)))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($roomTypes, $room->getType(), TRUE), array('id'=>'selReType', 'class'=>$cls)))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($roomCategories, $room->getRoomCategory(), TRUE), array('id'=>'selReCategory', 'class'=>$cls)))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($reportCategories, $room->getReportCategory(), TRUE), array('id'=>'selRptCategory', 'class'=>$cls)))
            // max occ
            . HTMLTable::makeTd(HTMLInput::generateMarkup($room->getMaxOccupants(), array('id'=>'txtMax', 'class'=>$cls, 'size'=>'3')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($roomRs->Floor->getStoredVal(), array('id'=>'txtFloor', 'class'=>$cls, 'size'=>'4')))
            // phone
        . HTMLTable::makeTd(HTMLInput::generateMarkup($roomRs->Phone->getStoredVal(), array('id'=>'txtPhone', 'name'=>'txtPhone', 'type'=>'text', 'autocomplete'=>"off", 'class'=>$cls . ' hhk-phoneInput', 'size'=>'10')))
            // Static rate
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup(removeOptionGroups($rateCodes), $room->getRateCode(), FALSE), array('id'=>'selRateCode', 'class'=>$cls)))
            // Default rate category
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), $room->getDefaultRateCategory(), TRUE), array('id'=>'selRateCat', 'class'=>$cls)))
            // Cleaning days
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup(removeOptionGroups($cleaningCodes), $room->getCleaningCycleCode(), FALSE), array('id'=>'selCleanCode', 'class'=>$cls)));

        if ($keyDeposit) {
            $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup(removeOptionGroups($keyDepositCodes), $room->getKeyDepositCode(), FALSE), array('id'=>'selKeyCode', 'class'=>$cls)));
        }

        if ($uS->PaymentGateway != '') {

            $gstmt = $dbh->query("Select idLocation, Title from location where ifnull(Merchant, '') != '';");
            $ccGateways = $gstmt->fetchAll(\PDO::FETCH_NUM);

            $opts = array();

            // Furn into options
            foreach ($ccGateways as $l) {
                $opts[] = array(0=>$l[0], 1=> ucfirst($l[1]));
            }

            $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($opts, $room->getIdLocation(), FALSE), array('id'=>'selLocId', 'class'=>$cls)));

        }

        $roomAttr = new RoomAttribute($dbh, $room->getIdRoom());
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

    public static function resourceDialog(\PDO $dbh, $idResc, $resourceTypes, $hospitals) {

        $uS = Session::getInstance();

        $resc = AbstractResource::getResourceObj($dbh, $idResc, ResourceTypes::Room);

        $cls = 'reDiag' . $resc->getIdResource();

        $saveBtn = HTMLInput::generateMarkup('Save', array('id'=>'savebtn', 'class'=>'mr-2', 'data-id'=>$resc->getIdResource(), 'data-type'=>'resc', 'data-cls'=>$cls, 'type'=>'button'));
        $saveBtn .= HTMLInput::generateMarkup('Cancel', array('id'=>'cancelbtn', 'style'=>'margin-top:.2em;', 'type'=>'button'));

        // New Resource?
        $stat = HTMLTable::makeTd();
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

        $retiredAt = ($resc->getRetiredAtDT() instanceof \DateTimeInterface ? $resc->getRetiredAtDT()->format("M j, Y"):'');

        $tr = HTMLTable::makeTd($saveBtn) . HTMLTable::makeTd($resc->getIdResource())
                . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->getTitle(), array('id'=>'txtReTitle', 'size'=>'10', 'class'=>$cls)))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($resourceTypes), $resc->getType(), TRUE), array('id'=>'selReType', 'class'=>$cls)))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, $useRooms, TRUE), array('id'=>'selRooms', 'class'=>$cls)))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->getUtilPriority(), array('id'=>'txtRePriority', 'class'=>$cls, 'size'=>'7')))
                . ($uS->Room_Colors == "room" ?
                    HTMLTable::makeTd(HTMLInput::generateMarkup($resc->resourceRS->Background_Color->getStoredVal(), array('id'=>'txtReBgc', 'class'=>$cls, 'size'=>'8')))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($resc->resourceRS->Text_Color->getStoredVal(), array('id'=>'txtReTc', 'class'=>$cls, 'size'=>'8')))
                : '')
                . HTMLTable::makeTd(HTMLInput::generateMarkup($retiredAt, array('id'=>'txtRetired', 'size'=>'15', 'class'=>"ckdate " . $cls)))
                . $partition . $stat;

        $rescAttr = new ResourceAttribute($dbh, $resc->getIdResource());
        $rattribute = $rescAttr->getAttributes();

        foreach ($rattribute as $a) {

            $parms = array('id'=>'cbat_'.$a['idAttribute'], 'type'=>'checkbox', 'data-idat'=>$a['idAttribute'], 'class'=>$cls);


            if ($a['isActive'] > 0) {
                $parms['checked'] = 'checked';
            }

            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $parms), array('style'=>'text-align:center;'));
        }

        return array('row'=>$tr);
    }

    /**
     * Summary of dirtyOccupiedRooms: Implements the automatic Cleaning Cycle for each occupied room
     *
     * @param \PDO $dbh
     * @return void
     */
    public static function dirtyOccupiedRooms(\PDO $dbh) {

        $cleanDays = readGenLookupsPDO($dbh, 'Room_Cleaning_Days');

        $today = new \DateTime();
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

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $roomRs = new RoomRs();
            EditRS::loadRow($r, $roomRs);
            $rm = new Room($dbh, 0, $roomRs);

            // Put dirty if room active for longer than days.
            if ($r['idVisit'] > 0 && ($r['Status'] == RoomState::Clean || $r['Status'] == RoomState::Ready) && isset($cleanDays[$rm->getCleaningCycleCode()])) {

                $arrDT = $today;
                $lastCleanedDT = $today;

                if ($r['Arrival'] != '') {
                    $arrDT = new \DateTime($r['Arrival']);
                    $arrDT->setTime(0, 0, 0);
                }

                if ($r['Last_Cleaned'] != '') {
                    $lastCleanedDT = new \DateTime($r['Last_Cleaned']);
                    $lastCleanedDT->setTime(0, 0, 0);
                } else {
                    $lastCleanedDT = $arrDT;
                }

                // Start from the visit date if cleaned earlier...
                if ($arrDT > $lastCleanedDT) {
                    $lastCleanedDT = $arrDT;
                }

                $cycleDays = intval($cleanDays[$rm->getCleaningCycleCode()][2], 10);

                if ($cycleDays > 0 && $today->diff($lastCleanedDT, TRUE)->days >= $cycleDays) {
                    // Set room dirty
                    $rm->putDirty();
                    $rm->saveRoom($dbh, 'CleanCycle', TRUE);
                }
            }
        }
    }

    public static function roomsClean(\PDO $dbh, $filter = '', $guestAdmin = FALSE, $printOnly = FALSE) {

        $uS = Session::getInstance();
        $today = new \DateTime();
        $today->setTime(0,0,0);

        $returnRows = array();

        $beginDT = new \DateTime();

        $endDT = new \DateTime();
        //$endDT->add(new \DateInterval('P2D'));

        $roomStatuses = readGenLookupsPDO($dbh, 'Room_Status');

        //Resource grouping controls
        $rescGroups = readGenLookupsPDO($dbh, 'Room_Group');

        $rescGroupBy = '';
        $genJoin = '';
        $orderBy = 'r.Util_Priority';

        if (isset($rescGroups[$uS->CalResourceGroupBy])) {
            $rescGroupBy = $uS->CalResourceGroupBy;
        }


        foreach ($rescGroups as $g) {

            if ($rescGroupBy === $g[0]) {

                $genJoin = " left join `gen_lookups` gr on gr.`Table_Name` = '" . $g[2] . "' and gr.`Code` = r." . $g[0] . " ";
                $orderBy = "gr.`Order`, " . $orderBy;
                break;
            }
        }

        $stmt = $dbh->query("select
    r.idRoom,
    ifnull(v.idVisit, 0) as idVisit,
    r.Title,
    gr.Description as `Group_Title`,
    re.Util_Priority,
    r.`Status`,
    ifnull(g.Description, 'Unknown') as `Status_Text`,
    r.`Cleaning_Cycle_Code`,
    ifnull(n.Name_Full, '') as `Name`,
    if(count(s.idName) > 0, count(s.idName), '') as `numGuests`,
    ifnull(v.Arrival_Date, '') as `Arrival`,
    ifnull(v.Expected_Departure, '') as `Expected_Departure`,
    r.Last_Cleaned,
    r.Last_Deep_Clean,
    ifnull(r.Notes, '') as `Notes`
from
    room r
        left join
    resource_room rr ON r.idRoom = rr.idRoom
        left join
    resource re on rr.idResource = re.idResource
        left join
    visit v ON rr.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
        left join
    name n ON v.idPrimaryGuest = n.idName
        left join
    stays s on v.idVisit = s.idVisit and v.Span = s.Visit_Span and s.Status = 'a'
        left join
    gen_lookups g on g.Table_Name = 'Room_Status' and g.Code = r.Status
        left join
    gen_lookups g3 on g3.Table_Name = 'Room_Cleaning_Days' and g3.`Code` = r.Cleaning_Cycle_Code
        left join
    resource_use ru on rr.idResource = ru.idResource  and ru.`Status` = '" . ResourceStatus::Unavailable . "'  and DATE(ru.Start_Date) <= DATE('" . $endDT->format('Y-m-d') . "') and DATE(ru.End_Date) > DATE('" . $beginDT->format('Y-m-d') . "')
    $genJoin
where g3.Substitute > 0 and ru.idResource_use is null
    and (re.Retired_At is null or re.Retired_At > date(now()))
group by rr.idResource
ORDER BY $orderBy;");

        // Loop rooms.
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($filter == RoomState::Dirty &&
                    !($r['Status'] == RoomState::Dirty || $r['Status'] == RoomState::TurnOver || ($uS->HouseKeepingSteps > 1 && $r['Status'] == RoomState::Clean && $r['idVisit'] == 0))) {
                continue;
            }


            $expDeparture = $r['Expected_Departure'];
            $arrival = $r['Arrival'];
            $lastCleaned = $r['Last_Cleaned'];
            $lastDeepClean = $r['Last_Deep_Clean'];
            $notes = '';
            $action = '';

            $fixedRows = array();
            $stat = '';
            $isDirty = FALSE;
            $isClean = FALSE;

            // Mangle room status
            if ($r['idVisit'] > 0) {
                // active room

                if ($r['Status'] == RoomState::Dirty || $r['Status'] == RoomState::TurnOver) {
                    $stat = HTMLContainer::generateMarkup('span', 'Active-' . $r['Status_Text'], array('style'=>'background-color:yellow;'));
                    $isDirty = TRUE;

                } else if ($r['Status'] == RoomState::Clean || $r['Status'] == RoomState::Ready) {
                    $stat = HTMLContainer::generateMarkup('span', 'Active-' . $r['Status_Text'], array('style'=>'background-color:#bbf7b2;'));

                } else {
                    $stat = HTMLContainer::generateMarkup('span', 'Active-' . $r['Status_Text']);
                }

            } else {
                // Inactive room

                if ($r['Status'] == RoomState::TurnOver || $r['Status'] == RoomState::Dirty) {
                    $stat = HTMLContainer::generateMarkup('span', $r['Status_Text'], array('style'=>'background-color:yellow;'));
                    $isDirty = TRUE;

                } else if ($r['Status'] == RoomState::Ready) {
                    $stat = HTMLContainer::generateMarkup('span', $r['Status_Text'], array('style'=>'background-color:#3fff0f;'));

                } else {
                    $stat = HTMLContainer::generateMarkup('span', $r['Status_Text']);
                    $isClean = TRUE;
                }
            }

            $lastDeepClean = $r['Last_Deep_Clean'] == '' ? '' : date('M d, Y', strtotime($r['Last_Deep_Clean']));

            if ($printOnly) {

                $stat = strip_tags($stat);

                // reverse notes output
                $notes = implode('  ||', array_reverse(explode("\n", $r['Notes'])));

                // format dates
                $expDeparture = $r['Expected_Departure'] == '' ? '' : date('M d, Y', strtotime($expDeparture));
                $arrival = $r['Arrival'] == '' ? '' : date('M d, Y', strtotime($arrival));
                $lastCleaned = $r['Last_Cleaned'] == '' ? '' : date('M d, Y', strtotime($r['Last_Cleaned']));
                $lastDeepClean = $r['Last_Deep_Clean'] == '' ? '' : date('M d, Y', strtotime($r['Last_Deep_Clean']));

            } else {
                $notes = Notes::markupShell($r['Notes'], $filter.'taNotes[' . $r['idRoom'] . ']');
                $lastDeepClean = HTMLInput::generateMarkup($lastDeepClean, array("type"=>"text", "class"=>"ckdate","name"=>$filter . 'deepCleanDate[' . $r['idRoom'] . ']'));

                if ($isDirty) {
                    $action = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbClean[' . $r['idRoom'] . ']', 'id'=>$filter.'cbClean' . $r['idRoom']))
                    .HTMLContainer::generateMarkup('label', 'Set '.$roomStatuses[RoomState::Clean][1], array('for'=>$filter.'cbClean' . $r['idRoom'], 'style'=>'margin-left:.2em;')) . "<br/>";

                } else if ($isClean && $uS->HouseKeepingSteps > 1) {
                    $action .= HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbReady[' . $r['idRoom'] . ']', 'id'=>$filter.'cbReady' . $r['idRoom']))
                        .HTMLContainer::generateMarkup('label', 'Set '.$roomStatuses[RoomState::Ready][1], array('for'=>$filter.'cbReady' . $r['idRoom'], 'style'=>'margin-left:.2em;')) . "<br/>";
                    $action .= HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbDirty[' . $r['idRoom'] . ']', 'id'=>$filter.'cbDirty' . $r['idRoom']))
                        .HTMLContainer::generateMarkup('label', 'Set '.$roomStatuses[RoomState::Dirty][1], array('for'=>$filter.'cbDirty' . $r['idRoom'], 'style'=>'margin-left:.2em;')) . "<br/>";
                } else {
                    $action .= HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbDirty[' . $r['idRoom'] . ']', 'id'=>$filter.'cbDirty' . $r['idRoom']))
                        .HTMLContainer::generateMarkup('label', 'Set '.$roomStatuses[RoomState::Dirty][1], array('for'=>$filter.'cbDirty' . $r['idRoom'], 'style'=>'margin-left:.2em;')) . "<br/>";
                }

                $action .= ($guestAdmin === FALSE ? '' : HTMLInput::generateMarkup('', array('type'=>'checkbox', 'class'=>'hhk-hkcb', 'name'=>$filter.'cbDeln[' . $r['idRoom'] . ']', 'id'=>$filter.'cbDeln' . $r['idRoom']))
                            .HTMLContainer::generateMarkup('label', 'Delete Notes', array('for'=>$filter.'cbDeln' . $r['idRoom'], 'style'=>'margin-left:.2em;'))
                        );

            }

            $fixedRows['Group_Title'] = $r['Group_Title'];
            $fixedRows['Room'] = $r['Title'];
            $fixedRows['Status'] = $stat;
            $fixedRows['Action'] = $action;
            $fixedRows['Occupant'] = $r['Name'];
            $fixedRows['numGuests'] = $r['numGuests'];
            $fixedRows['Checked_In'] = $arrival;
            $fixedRows['Expected_Checkout'] = $expDeparture;
            $fixedRows['Last_Cleaned'] = $lastCleaned;
            $fixedRows['Last_Deep_Clean'] = $lastDeepClean;
            $fixedRows['Notes'] = $notes;

            $returnRows[] = $fixedRows;
        }

        return $returnRows;
    }

	public static function showCiList(\PDO $dbh, $startCiDate, $endCiDate) {

        $returnRows = array();

        if ($endCiDate == '') {
	         $returnRows[] = array(
	            'Primary Guest' => '',
				'Guests' => '',
				'Arrival Date' => '',
				'Expected Departure' => '',
				'Room' => '',
				'Nights' => '',
	        );
            return $returnRows;
        }

        $stmt = $dbh->query("
        	select pg.`Name_Full` as 'Primary Guest', rp.`Number_Guests` as 'Guests', DATE(rp.`Expected_Arrival`) as 'Arrival Date', DATE(rp.`Expected_Departure`) as 'Expected Departure', rp.`Title` as 'Room', DATEDIFF(rp.`Expected_Departure`, rp.`Expected_Arrival`) as 'Nights'
			from vresv_patient rp
			left join `name` pg on rp.idGuest = pg.idName
			where rp.`Status` in ('" . ReservationStatus::Committed . "', '" . ReservationStatus::UnCommitted . "', '" . ReservationStatus::Waitlist . "') "
                . "and DATE(`Expected_Arrival`) <= DATE('$endCiDate') order by `Expected_Arrival`;
        ");

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function showCoList(\PDO $dbh, $startCoDate, $endCoDate) {

        $returnRows = array();

        if ($startCoDate == '' || $endCoDate == '') {
	         $returnRows[] = array(
	            'Room' => "",
	            'Visit Status' => "",
	            'Primary Guest' => "",
	            'Guests' => "",
	            'Arrival Date' => "",
	            'Expected Checkout' => "",
	            'Notes' => ""
	        );
            return $returnRows;
        }

        $stmt = $dbh->query("select
        r.idRoom,
        ifnull(v.idVisit, 0) as idVisit,
        r.`Title`,
        ifnull(n.Name_Full, '') as `Name`,
        v.Arrival_Date,
        ifnull(DATE(v.Span_End), DATE(datedefaultnow(v.Expected_Departure))) as `Departure_Date`,
        g.`Description` as `Visit_Status`,
        r.`Last_Cleaned`,
        ifnull(r.`Notes`, '') as `Notes`
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
    where ifnull(DATE(v.Span_End), DATE(datedefaultnow(v.Expected_Departure))) between Date('$startCoDate') and Date('$endCoDate');");


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

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
            'Arrival Date' => $r['Arrival_Date'] == '' ? '' : $r['Arrival_Date'],
            'Expected Checkout' => $r['Departure_Date'] == '' ? '' : $r['Departure_Date'],
            'Notes' => $reverse
        );

        }

        return $returnRows;
    }

    public static function CleanLog(\PDO $dbh, $id, $get) {

        $columns = array(

            array( 'db' => 'Title',  'dt' => 'Room' ),
            array( 'db' => 'Type',   'dt' => 'Type' ),
            array( 'db' => 'Status_Text',     'dt' => 'Status' ),
            array( 'db'  => 'Last_Cleaned', 'dt' => 'Last Cleaned' ),
            array( 'db'  => 'Last_Deep_Clean', 'dt' => 'Last Deep Clean' ),
            array( 'db' => 'Notes',   'dt' => 'Notes' ),
            array( 'db' => 'Username',     'dt' => 'User' ),
            array( 'db' => 'Timestamp', 'dt' => 'Timestamp')
        );

        return SSP::simple($get, $dbh, "vcleaning_log", 'idRoom', $columns);
    }

}
?>