<?php

namespace HHK\House;

use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput, HTMLSelector};
use HHK\Tables\EditRS;
use HHK\Tables\Registration\VehicleRS;
use HHK\sec\Labels;

/**
 * Vehicle.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Vehicle
 * @package name
 * @author Eric
 */
class Vehicle {

    /**
     * Summary of getRecords
     * @param \PDO $dbh
     * @param int $idReg
     * @return array
     */
    public static function getRecords(\PDO $dbh, $idReg, int $idResv = 0) {

        $rows = array();

        if ($idReg > 0 && $idResv > 0){
            
            $stmt = $dbh->query("select v.*, n.Name_Full, rv.idReservation from vehicle v left join name n on v.idName = n.idName left join reservation_vehicle rv on v.idVehicle = rv.idVehicle where v.idRegistration = $idReg and (rv.idReservation = $idResv or rv.idReservation IS NULL)");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }else if ($idReg > 0) {

            $stmt = $dbh->query("select v.*, n.Name_Full from vehicle v left join name n on v.idName = n.idName where v.idRegistration = $idReg");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }

        return $rows;

    }

    /**
     * Summary of searchTag
     * @param \PDO $dbh
     * @param mixed $tag
     * @return array
     */
    public static function searchTag(\PDO $dbh, $tag) {

        $events = array();

        if ($tag != '') {

            $tag = addslashes($tag) . '%';
            $stmt = $dbh->query("SELECT COUNT(v.idVehicle),
    v.*,
    IFNULL(r.Title, '') AS `Room`,
    IFNULL(vs.idVisit, 0) AS `idVisit`,
    IFNULL(n.Name_Full, '') AS `Patient`,
    IFNULL(n.idName, 0) AS `idName`
FROM
    vehicle v
        LEFT JOIN
    visit vs ON vs.`Status` = 'a'
        AND vs.idRegistration = v.idRegistration
        LEFT JOIN
    resource r ON vs.idResource = r.idResource
		LEFT JOIN
	registration rg on v.idRegistration = rg.idRegistration
		LEFT JOIN
	name_guest ng on rg.idPsg = ng.idPsg and ng.Relationship_Code = 'slf'
		LEFT JOIN
	`name` n on ng.idName = n.idName
WHERE
    v.License_Number LIKE '$tag' GROUP BY v.idVehicle");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $namArray = $r;

                $namArray['id'] = $r["idName"];
                $namArray['value'] = $r["License_Number"] . ': ' . $r['Make'] . ' ' . $r['Model'] . ', Color: ' . $r['Color'] . ', State Registration: ' . $r['State_Reg'];

                $events[] = $namArray;
            }

            if (count($events) == 0) {
                $events[] = array("id" => 0, "value" => "Nothing Returned");
            }
        }

        return $events;
    }

    /**
     * Summary of createVehicleMarkup
     * @param \PDO $dbh
     * @param int $idReg
     * @param mixed $noVehicle
     * @param mixed $refVehicle
     * @return string
     */
    public static function createVehicleMarkup(\PDO $dbh, $idReg, $idResv, $noVehicle, $refVehicle = []) {

        // work on the state
        $stateList = array('', 'AB', 'AE', 'AL', 'AK', 'AR', 'AZ', 'BC', 'CA', 'CO', 'CT', 'CZ', 'DC', 'DE', 'FL', 'GA', 'GU', 'HI', 'IA', 'ID', 'IL', 'IN', 'KS',
            'KY', 'LA', 'LB', 'MA', 'MB', 'MD', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NB', 'NC', 'ND', 'NE', 'NF', 'NH', 'NJ', 'NM', 'NS', 'NT', 'NV', 'NY', 'OH',
            'OK', 'ON', 'OR', 'PA', 'PE', 'PR', 'PQ', 'RI', 'SC', 'SD', 'SK', 'TN', 'TX', 'UT', 'VA', 'VI', 'VT', 'WA', 'WI', 'WV', 'WY');

        $rows = self::getRecords($dbh, $idReg, $idResv);

        $tbl = new HTMLTable();

        $labels = Labels::getLabels();

        $hasRef = FALSE;

        foreach ($rows as $r) {

            $carRS = new VehicleRs();
            EditRS::loadRow($r, $carRS);
            $idPrefix = $carRS->idVehicle->getStoredVal();
            $stateOpt = '';

            foreach ($stateList as $s) {

                if ($carRS->State_Reg->getStoredVal() == $s) {
                    $stateOpt .= "<option value='" . $s . "' selected='selected'>" . $s . "</option>";
                } else {
                    $stateOpt .= "<option value='" . $s . "'>" . $s . "</option>";
                }
            }

            if (self::checkMatch($carRS, $refVehicle)) {
                $hasRef = TRUE;
            }
            $thisResvMkup = "";
            if ($idResv > 0) {
                $thisResvCbAttrs = ["type"=>"checkbox", "name"=>"cbVehResv[".$idPrefix."]", 'id'=>$idPrefix.'cbVehResv', 'class'=>'hhk-vehicle'];

                if($r["idReservation"] == $idResv){
                    $thisResvCbAttrs["checked"] = "checked";
                }
            
                $thisResvMkup = HTMLTable::makeTd(HTMLInput::generateMarkup("", $thisResvCbAttrs), ["style" => "text-align: center;"]);
            }

            $tbl->addBodyTr(
                //HTMLTable::makeTd(HTMLSelector::generateMarkup( , array('name'=>'selVehGuest['.$idPrefix.']')))
                ($idResv > 0 ? $thisResvMkup : "")
                .HTMLTable::makeTd(HTMLInput::generateMarkup($carRS->Make->getStoredVal(), array('name'=>'txtVehMake[' .$idPrefix.']', 'id'=>$idPrefix.'txtVehMake','class'=>'hhk-vehicle ','size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($carRS->Model->getStoredVal(), array('name'=>'txtVehModel[' .$idPrefix.']', 'id'=>$idPrefix.'txtVehModel', 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($carRS->Color->getStoredVal(), array('name'=>'txtVehColor[' . $idPrefix.']', 'id'=>$idPrefix.'txtVehColor', 'class'=>'hhk-vehicle', 'size'=>'7')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup($stateOpt, array('name'=>'selVehLicense[' .$idPrefix.']', 'id'=>$idPrefix.'selVehLicense', 'class'=>'hhk-vehicle')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($carRS->License_Number->getStoredVal(), array('name'=>'txtVehLic['.$idPrefix.']', 'id'=>$idPrefix.'txtVehLic', 'class'=>'hhk-vehicle', 'size'=>'8')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($carRS->Note->getStoredVal(), array('name'=>'txtVehNote[' . $idPrefix.']', 'id'=>$idPrefix.'txtVehColor', 'class'=>'hhk-vehicle')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'cbVehDel[' .$idPrefix.']', 'type'=>'checkbox', 'class'=>'hhk-vehicle ')), array('style'=>'text-align:center;'))
                );
        }


        // new cars
        $stateOpt = '';

        foreach ($stateList as $s) {

            if ('' == $s) {
                $stateOpt .= "<option value='" . $s . "' selected='selected'>" . $s . "</option>";
            } else {
                $stateOpt .= "<option value='" . $s . "'>" . $s . "</option>";
            }
        }

        $idx = array('a', 'b','c','d');
        $x = 1;

        // Check for referral Vehicle selected
        if ($hasRef === FALSE && isset($refVehicle['model']) && ($refVehicle['model'] != '' || $refVehicle['make'] != '')) {

            $i = 'a';
            $idx = array('b','c','d');

            if (isset($refVehicle['state']) && $refVehicle['state'] != '') {

                $opts = '';

                foreach ($stateList as $s) {

                    if ($refVehicle['state'] == $s) {
                        $opts .= "<option value='" . $s . "' selected='selected'>" . $s . "</option>";
                    } else {
                        $opts .= "<option value='" . $s . "'>" . $s . "</option>";
                    }
                }

            } else {
                $opts = $stateOpt;
            }

            $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup((isset($refVehicle['make']) ? $refVehicle['make']: ""), array('name'=>"txtVehMake[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup((isset($refVehicle['model']) ? $refVehicle['model'] : ""), array('name'=>"txtVehModel[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup((isset($refVehicle['color']) ? $refVehicle['color']: ""), array('name'=>"txtVehColor[$i]", 'class'=>'hhk-vehicle', 'size'=>'7')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup($opts, array('name'=>"selVehLicense[$i]", 'class'=>'hhk-vehicle hhk-US-States')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup((isset($refVehicle['license']) ? $refVehicle['license']: ""), array('name'=>"txtVehLic[$i]", 'class'=>'hhk-vehicle', 'size'=>'8')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup((isset($refVehicle['note']) ? $refVehicle['note']: ""), array('name'=>"txtVehNote[$i]", 'class'=>'hhk-vehicle')))
                .HTMLTable::makeTd('(Referral)')
                , array('id'=>"trVeh$x"));

            $x++;
        }

        foreach ($idx as $i) {

            $tbl->addBodyTr(
                ($idResv > 0 ? HTMLTable::makeTd(HTMLInput::generateMarkup("", array('name'=>"cbVehResv[$i]", 'class'=>'hhk-vehicle', 'type'=>'checkbox')), ["style" => "text-align: center;"]) : "")
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehMake[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehModel[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehColor[$i]", 'class'=>'hhk-vehicle', 'size'=>'7')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup($stateOpt, array('name'=>"selVehLicense[$i]", 'class'=>'hhk-vehicle hhk-US-States')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehLic[$i]", 'class'=>'hhk-vehicle', 'size'=>'8')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehNote[$i]", 'class'=>'hhk-vehicle')))
                .HTMLTable::makeTd('')
                , array('style'=>($i == 'a' && count($rows) == 0 ? '' : 'display:none;'), 'id'=>"trVeh$x"));

            $x++;
        }

        $nextVehButton = HTMLInput::generateMarkup('Next Vehicle', array('type'=>'button', 'id'=>'btnNextVeh', 'class'=>'my-2'));

        $tbl->addHeaderTr(
            ($idResv > 0 ? HTMLTable::makeTh('This Visit') : "")
                .HTMLTable::makeTh('Make')
                .HTMLTable::makeTh('Model')
                .HTMLTable::makeTh('Color')
                .HTMLTable::makeTh('Registered')
                .HTMLTable::makeTh($labels->getString('referral', 'licensePlate', 'License Plate'))
                .HTMLTable::makeTh($labels->getString('referral', 'vehicleNotes', 'Notes'))
                .HTMLTable::makeTh('Delete')
                );


        $cars = HTMLContainer::generateMarkup('div', $tbl->generateMarkup() . $nextVehButton, array('id'=>'tblVehicle'));

        $noV = array('name'=>'cbNoVehicle', 'type'=>'checkbox', 'class'=>'hhk-vehicle', 'title'=>'Check for no vehicle');
        if ($noVehicle == '1') {
            $noV['checked'] = 'checked';
        }

        $mk1 = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Vehicle', array('style'=>'font-weight:bold;'))
                    .($idResv > 0 ? HTMLContainer::generateMarkup('div', HTMLInput::generateMarkup('', $noV)
                    .HTMLContainer::generateMarkup('label', ' No vehicle this visit', array('for'=>'cbNoVehicle', 'title'=>'Check for no vehicle')), array('style'=>'margin:.3em;')) : "")
                    . HTMLContainer::generateMarkup('p', '', array('id'=>'vehValidate', 'style'=>'color:red;'))
                    . $cars, array('class'=>'hhk-panel')),
                    array('style'=>'display: inline-block', 'class'=>'mr-3'));

        return $mk1;
    }


    protected static function checkMatch(VehicleRs $carRs, $refVehicle) {

        if (isset($refVehicle['model']) && isset($refVehicle['license'])) {

            // Check model and license
            if ($carRs->Model->getStoredVal() == $refVehicle['model']) {
                return TRUE;
            } else if ($carRs->License_Number->getStoredVal() == $refVehicle['license']) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Summary of saveVehicle
     * @param \PDO $dbh
     * @param int $idReg
     * @return string
     */
    public static function saveVehicle(\PDO $dbh, $idReg, int $idResv = 0) {
        $rtnMsg = "";

        $args = [
            'txtVehMake' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY],
            'txtVehLic' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY],
            'txtVehModel' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY],
            'txtVehColor' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY],
            'selVehLicense' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY],
            'txtVehNote' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY],
            'selVehGuest' => ['filter' => FILTER_SANITIZE_NUMBER_INT, 'flags' => FILTER_FORCE_ARRAY],
        ];

        $post = filter_input_array(INPUT_POST, $args);

        // Find any deletes
        if (isset($_POST['cbVehDel']) && is_array($_POST['cbVehDel'])) {

            foreach ($_POST['cbVehDel'] as $k => $v) {

                $idVehicle = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

                if ($idVehicle > 0) {
                    $carRs = new VehicleRs();
                    $carRs->idVehicle->setStoredVal($idVehicle);
                    EditRS::delete($dbh, $carRs, array($carRs->idVehicle));
                }
            }
        }

        if (!isset($post['txtVehMake'])) {
            return $rtnMsg;
        }

        $rows = self::getRecords($dbh, $idReg);
        $vehs = [];

        foreach ($rows as $r) {

            $carRS = new VehicleRs();
            EditRS::loadRow($r, $carRS);

            $vehs[$carRS->idVehicle->getStoredVal()] = $carRS;

        }


        foreach ($post['txtVehMake'] as $k => $v) {

            // ignore deleted vehicles
            if (isset($_POST['cbVehDel'][$k]) && filter_has_var(INPUT_POST, $_POST['cbVehDel'][$k])) {
                continue;
            }

            $idVehicle = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);
            $carRS = new VehicleRs();

            if ($idVehicle > 0 && isset($vehs[$idVehicle])) {
                $carRS = $vehs[$idVehicle];
            }

            $make = '';
            if (isset($post["txtVehMake"][$k])) {
                $make = $post["txtVehMake"][$k];
                $carRS->Make->setNewVal($make);
            }

            $plate = '';
            if (isset($post["txtVehLic"][$k])) {
                $plate = $post["txtVehLic"][$k];
                $carRS->License_Number->setNewVal($plate);
            }

            if (isset($post["txtVehModel"][$k])) {
                $carRS->Model->setNewVal($post["txtVehModel"][$k]);
            }
            if (isset($post["txtVehColor"][$k])) {
                $carRS->Color->setNewVal($post["txtVehColor"][$k]);
            }
            if (isset($post["selVehLicense"][$k])) {
                $carRS->State_Reg->setNewVal($post["selVehLicense"][$k]);
            }

            $note = '';
            if (isset($post["txtVehNote"][$k])) {
                $note = $post["txtVehNote"][$k];
                $carRS->Note->setNewVal($note);
            }
            if (isset($post["selVehGuest"][$k])) {
                $idGuest = intVal($post["selVehGuest"][$k], 10);
                $carRS->idName->setNewVal($idGuest);
            }

            if($idResv > 0){
                if (isset($_POST['cbVehResv'][$k])) {
                    $stmt = $dbh->prepare("INSERT IGNORE INTO `reservation_vehicle` (`idReservation`, `idVehicle`, `idName`) VALUES (:idReservation, :idVehicle, :idName);");
                    $stmt->execute([":idReservation"=>$idResv, ":idVehicle"=>$k, ":idName"=>0]);
                }else{
                    $stmt = $dbh->prepare("DELETE FROM `reservation_vehicle` WHERE `idReservation` = :idReservation AND `idVehicle` = :idVehicle;");
                    $stmt->execute([":idReservation"=>$idResv, ":idVehicle"=>$k]);
                }
                $rtnMsg = "Vehicles Updated. ";
            }

            if ($idVehicle == 0 && ($make != '' || $plate != '' || $note != '')) {
                //
                $carRS->idRegistration->setNewVal($idReg);

                $n = EditRS::insert($dbh, $carRS);
                if ($n > 0) {
                    $rtnMsg = "Vehicle Added.  ";
                }

            } else if ($carRS->idVehicle->getStoredVal() > 0) {

                // Update
                $n = EditRS::update($dbh, $carRS, [$carRS->idVehicle, $carRS->idRegistration]);

                if ($n > 0) {
                    $rtnMsg = "Vehicle Updated.  ";
                }

            }
        }

        return $rtnMsg;
    }


}

