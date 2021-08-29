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

    public static function getRecords(\PDO $dbh, $idReg) {

        $rows = array();

        if ($idReg > 0) {

            $stmt = $dbh->query("select v.*, n.Name_Full from vehicle v left join name n on v.idName = n.idName where v.idRegistration = $idReg");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

         }

        return $rows;

    }

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

    public static function createVehicleMarkup(\PDO $dbh, $idReg, $noVehicle, $refVehicle = []) {

        // work on the state
        $stateList = array('', 'AB', 'AE', 'AL', 'AK', 'AR', 'AZ', 'BC', 'CA', 'CO', 'CT', 'CZ', 'DC', 'DE', 'FL', 'GA', 'GU', 'HI', 'IA', 'ID', 'IL', 'IN', 'KS',
            'KY', 'LA', 'LB', 'MA', 'MB', 'MD', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NB', 'NC', 'ND', 'NE', 'NF', 'NH', 'NJ', 'NM', 'NS', 'NT', 'NV', 'NY', 'OH',
            'OK', 'ON', 'OR', 'PA', 'PE', 'PR', 'PQ', 'RI', 'SC', 'SD', 'SK', 'TN', 'TX', 'UT', 'VA', 'VI', 'VT', 'WA', 'WI', 'WV', 'WY');

        $rows = self::getRecords($dbh, $idReg);

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

            $tbl->addBodyTr(
                //HTMLTable::makeTd(HTMLSelector::generateMarkup( , array('name'=>'selVehGuest['.$idPrefix.']')))
                HTMLTable::makeTd(HTMLInput::generateMarkup($carRS->Make->getStoredVal(), array('name'=>'txtVehMake[' .$idPrefix.']', 'id'=>$idPrefix.'txtVehMake','class'=>'hhk-vehicle ','size'=>'10')))
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
                HTMLTable::makeTd(HTMLInput::generateMarkup($refVehicle['make'], array('name'=>"txtVehMake[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($refVehicle['model'], array('name'=>"txtVehModel[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($refVehicle['color'], array('name'=>"txtVehColor[$i]", 'class'=>'hhk-vehicle', 'size'=>'7')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup($opts, array('name'=>"selVehLicense[$i]", 'class'=>'hhk-vehicle hhk-US-States')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($refVehicle['license'], array('name'=>"txtVehLic[$i]", 'class'=>'hhk-vehicle', 'size'=>'8')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehNote[$i]", 'class'=>'hhk-vehicle')))
                .HTMLTable::makeTd('(Referral)')
                , array('id'=>"trVeh$x"));

            $x++;
        }

        foreach ($idx as $i) {

            $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehMake[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehModel[$i]", 'class'=>'hhk-vehicle', 'size'=>'10')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehColor[$i]", 'class'=>'hhk-vehicle', 'size'=>'7')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup($stateOpt, array('name'=>"selVehLicense[$i]", 'class'=>'hhk-vehicle hhk-US-States')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehLic[$i]", 'class'=>'hhk-vehicle', 'size'=>'8')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>"txtVehNote[$i]", 'class'=>'hhk-vehicle')))
                .HTMLTable::makeTd('')
                , array('style'=>($i == 'a' && count($rows) == 0 ? '' : 'display:none;'), 'id'=>"trVeh$x"));

            $x++;
        }

        $nextVehButton = HTMLInput::generateMarkup('Next Vehicle', array('type'=>'button', 'id'=>'btnNextVeh'));

        $tbl->addHeaderTr(
                HTMLTable::makeTh('Make')
                .HTMLTable::makeTh('Model')
                .HTMLTable::makeTh('Color')
                .HTMLTable::makeTh('Registered')
                .HTMLTable::makeTh($labels->getString('referral', 'licensePlate', 'License Plate'))
                .HTMLTable::makeTh('Notes')
                .HTMLTable::makeTh('Delete')
                );


        $cars = HTMLContainer::generateMarkup('div', $tbl->generateMarkup() . $nextVehButton, array('id'=>'tblVehicle'));

        $noV = array('name'=>'cbNoVehicle', 'type'=>'checkbox', 'class'=>'hhk-vehicle', 'title'=>'Check for no vehicle');
        if ($noVehicle == '1') {
            $noV['checked'] = 'checked';
        }

        $mk1 = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Vehicle', array('style'=>'font-weight:bold;'))
                    .HTMLContainer::generateMarkup('div', HTMLInput::generateMarkup('', $noV)
                    .HTMLContainer::generateMarkup('label', ' No vehicle this visit', array('for'=>'cbNoVehicle', 'title'=>'Check for no vehicle')), array('style'=>'margin:.3em;'))
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

    public static function saveVehicle(\PDO $dbh, $pData, $idReg) {
        $rtnMsg = "";

        // Find any deletes
        if (isset($pData['cbVehDel'])) {

            foreach ($pData['cbVehDel'] as $k => $v) {

                $idVehicle = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

                if ($idVehicle > 0) {
                    $carRs = new VehicleRs();
                    $carRs->idVehicle->setStoredVal($idVehicle);
                    EditRS::delete($dbh, $carRs, array($carRs->idVehicle));
                }
            }
        }

        if (!isset($pData['txtVehMake'])) {
            return $rtnMsg;
        }

        $rows = self::getRecords($dbh, $idReg);
        $vehs = array();

        foreach ($rows as $r) {

            $carRS = new VehicleRs();
            EditRS::loadRow($r, $carRS);

            $vehs[$carRS->idVehicle->getStoredVal()] = $carRS;

        }



        foreach ($pData['txtVehMake'] as $k => $v) {

            // ignore deleted vehicles
            if (isset($pData['cbVehDel'][$k])) {
                continue;
            }

            $idVehicle = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);
            $carRS = new VehicleRs();

            if ($idVehicle > 0 && isset($vehs[$idVehicle])) {
                $carRS = $vehs[$idVehicle];
            }



            $make = '';
            if (isset($pData["txtVehMake"][$k])) {
                $make = filter_var($pData["txtVehMake"][$k], FILTER_SANITIZE_STRING);
                $carRS->Make->setNewVal($make);
            }

            $plate = '';
            if (isset($pData["txtVehLic"][$k])) {
                $plate = filter_var($pData["txtVehLic"][$k], FILTER_SANITIZE_STRING);
                $carRS->License_Number->setNewVal($plate);
            }

            if (isset($pData["txtVehModel"][$k])) {
                $carRS->Model->setNewVal(filter_var($pData["txtVehModel"][$k], FILTER_SANITIZE_STRING));
            }
            if (isset($pData["txtVehColor"][$k])) {
                $carRS->Color->setNewVal(filter_var($pData["txtVehColor"][$k], FILTER_SANITIZE_STRING));
            }
            if (isset($pData["selVehLicense"][$k])) {
                $carRS->State_Reg->setNewVal(filter_var($pData["selVehLicense"][$k], FILTER_SANITIZE_STRING));
            }

            $note = '';
            if (isset($pData["txtVehNote"][$k])) {
                $note = filter_var($pData["txtVehNote"][$k], FILTER_SANITIZE_STRING);
                $carRS->Note->setNewVal($note);
            }
            if (isset($pData["selVehGuest"][$k])) {
                $idGuest = intVal(filter_var($pData["selVehGuest"][$k], FILTER_SANITIZE_STRING), 10);
                $carRS->idName->setNewVal($idGuest);
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
                $n = EditRS::update($dbh, $carRS, array($carRS->idVehicle, $carRS->idRegistration));

                if ($n > 0) {
                    $rtnMsg = "Vehicle Updated.  ";
                }

            }
        }

        return $rtnMsg;
    }


}

