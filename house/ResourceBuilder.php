<?php

use HHK\AlertControl\AlertMessage;
use HHK\sec\{Session, WebInit};
use HHK\Tables\EditRS;
use HHK\Tables\GenLookupsRS;
use HHK\TableLog\HouseLog;
use HHK\Config_Lite\Config_Lite;
use HHK\SysConst\GLTypeCodes;
use HHK\House\Reservation\Reservation_1;
use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput, HTMLSelector};
use HHK\SysConst\WebRole;
use HHK\sec\SysConfig;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Tables\Item\ItemRS;
use HHK\SysConst\ItemId;
use HHK\SysConst\PayType;
use HHK\Tables\House\Fa_CategoryRS;
use HHK\Tables\Registration\HospitalRS;
use HHK\SysConst\AttributeTypes;
use HHK\House\Constraint\ConstraintsHospital;
use HHK\Tables\Attribute\AttributeRS;
use HHK\SysConst\ItemType;
use HHK\Tables\DocumentRS;
use HHK\House\ResourceView;
use HHK\SysConst\ItemPriceCode;
use HHK\Purchase\RoomRate;
use HHK\Purchase\FinAssistance;
use HHK\SysConst\ConstraintType;
use HHK\House\Constraint\Constraints;
use HHK\House\Attribute\Attributes;
use HHK\Purchase\TaxedItem;
use HHK\SysConst\RoomRateCategories;
use HHK\sec\Labels;
use HHK\Document\FormTemplate;
use HHK\House\Insurance\InsuranceType;
use HHK\House\Insurance\Insurance;

/**
 * ResourceBuilder.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2013-2021` <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

const DIAGNOSIS_TABLE_NAME = 'Diagnosis';

const LOCATION_TABLE_NAME = 'Location';

const RESERV_STATUS_TABLE_NAME = 'lookups';

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

function saveArchive(\PDO $dbh, $desc, $subt, $tblName)
{
    $defaultCode = '';

    if (isset($desc)) {

        $uS = Session::getInstance();

        foreach ($desc as $k => $r) {

            $code = trim(filter_var($k, FILTER_SANITIZE_STRING));

            if ($code == '' || $tblName == '') {
                continue;
            }

            $glRs = new GenLookupsRS();
            $glRs->Table_Name->setStoredVal($tblName);
            $glRs->Code->setStoredVal($code);
            $rows = EditRS::select($dbh, $glRs, array(
                $glRs->Table_Name,
                $glRs->Code
            ));

            if (count($rows) < 1) {
                continue;
            }

            EditRS::loadRow($rows[0], $glRs);

            $newDesc = '';

            if ($r != '') {
                $newDesc = filter_var($r, FILTER_SANITIZE_STRING);
            } else {
                continue;
            }

            if (isset($subt[$code])) {
                $newSubt = filter_var($subt[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            } else {
                continue;
            }

            // Check if value changed.
            if ($glRs->Substitute->getStoredVal() != $newSubt) {

                // Create new entry
                $newRs = new GenLookupsRS();
                $defaultCode = incCounter($dbh, 'codes');

                $newRs->Table_Name->setNewVal($tblName);
                $newRs->Code->setNewVal($defaultCode);
                $newRs->Description->setNewVal($newDesc);
                $newRs->Substitute->setNewVal($newSubt);

                EditRS::insert($dbh, $newRs);
                $logText = HouseLog::getInsertText($newRs, $tblName);
                HouseLog::logGenLookups($dbh, $tblName, $defaultCode, $logText, 'insert', $uS->username);

                // Update Old
                $glRs->Type->setNewVal(GLTypeCodes::Archive);

                $ctr = EditRS::update($dbh, $glRs, array(
                    $glRs->Table_Name,
                    $glRs->Code
                ));
                $logTextu = HouseLog::getUpdateText($glRs, $tblName . $code);
                HouseLog::logGenLookups($dbh, $tblName, $code, $logTextu, 'update', $uS->username);
            } else {

                // update
                if ($newDesc != '') {
                    $glRs->Description->setNewVal($newDesc);
                }

                $ctr = EditRS::update($dbh, $glRs, array(
                    $glRs->Table_Name,
                    $glRs->Code
                ));

                if ($ctr > 0) {
                    $logText = HouseLog::getUpdateText($glRs, $tblName . $code);
                    HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'update', $uS->username);
                }
            }
        }
    }

    return $defaultCode;
}

function getSelections(\PDO $dbh, $tableName, $type, $labels)
{
    $uS = Session::getInstance();

    if ($tableName == $labels->getString('hospital', 'diagnosis', DIAGNOSIS_TABLE_NAME)) {
        $tableName = DIAGNOSIS_TABLE_NAME;
    } else if ($tableName == $labels->getString('hospital', 'location', LOCATION_TABLE_NAME)) {
        $tableName = LOCATION_TABLE_NAME;
    }

    // Generate selectors.
    if ($tableName == RESERV_STATUS_TABLE_NAME) {
        $lookups = readLookups($dbh, $type, "Code", true);
        $diags = array();

        // get Cancel Codes
        foreach ($lookups as $lookup) {
            if (Reservation_1::isRemovedStatus($lookup["Code"])) {
                $diags[] = $lookup;
            }
        }

    }else if($tableName == "insurance_type") {

        $stmt = $dbh->query("SELECT
    `t`.`idInsurance_type` as 'Table_Name', `t`.`Title` as 'Description',if(`t`.`Status` = 'a','y',''),'', `t`.`List_Order` as 'Order'
FROM
    `insurance_type` `t`
Order by `t`.`List_Order`;");

        $diags = $stmt->fetchAll(\PDO::FETCH_NUM);


    } else {
        $diags = readGenLookupsPDO($dbh, $tableName, 'Order');
    }

    $tbl = new HTMLTable();

    $hdrTr =
    HTMLTable::makeTh(count($diags) . ' Entries') . ($tableName != RESERV_STATUS_TABLE_NAME ? HTMLTable::makeTh('Order') : '') . ($type == GlTypeCodes::CA ? HTMLTable::makeTh('Amount') : '') . ($type == GlTypeCodes::HA ? HTMLTable::makeTh('Days') : '') . ($type == GlTypeCodes::Demographics && $uS->GuestNameColor == $tableName ? HTMLTable::makeTh('Colors (font, bkgrnd)') : '') . ($type == GlTypeCodes::U ? '' : ($type == GlTypeCodes::m || $tableName == RESERV_STATUS_TABLE_NAME ? HTMLTable::makeTh('Use') : HTMLTable::makeTh('Delete') . HTMLTable::makeTh('Replace With')));

    $tbl->addHeaderTr($hdrTr);

    foreach ($diags as $d) {

        // Remove this item from the replacement entries.
        $tDiags = removeOptionGroups($diags);
        unset($tDiags[$d[0]]);

        $cbDelMU = '';

        if ($type == GlTypeCodes::m || ($tableName == RESERV_STATUS_TABLE_NAME && ($d[0] == "c1" || $d[0] == "c2" || $d[0] == "c3" || $d[0] == "c4"))) {

            $ary = array(
                'name' => 'cbDiagDel[' . $d[0] . ']',
                'type' => 'checkbox',
                'class' => 'hhkdiagdelcb'
            );

            if (strtolower($d[2]) == 'y') {
                $ary['checked'] = 'checked';
            }

            $cbDelMU = HTMLTable::makeTd(HTMLInput::generateMarkup('', $ary));
        } else if (($type == GlTypeCodes::Demographics && $d[0] == 'z') || $tableName == RESERV_STATUS_TABLE_NAME) {

            $cbDelMU = HTMLTable::makeTd('');
        } else if ($type != GlTypeCodes::U) {

            $cbDelMU = HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
                'name' => 'cbDiagDel[' . $d[0] . ']',
                'type' => 'checkbox',
                'class' => 'hhkdiagdelcb',
                'data-did' => 'selDiagDel[' . $d[0] . ']'
            )));
        }

        $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($d[1], array(
            'name' => 'txtDiag[' . $d[0] . ']'
        ))) . ($tableName != RESERV_STATUS_TABLE_NAME ? HTMLTable::makeTd(HTMLInput::generateMarkup($d[4], array(
            'name' => 'txtDOrder[' . $d[0] . ']',
            'size' => '3'
        ))) : '') . ($type == GlTypeCodes::HA || $type == GlTypeCodes::CA || ($type == GlTypeCodes::Demographics && $uS->GuestNameColor == $tableName) ? HTMLTable::makeTd(HTMLInput::generateMarkup($d[2], array(
            'size' => '10',
            'style' => 'text-align:right;',
            'name' => 'txtDiagAmt[' . $d[0] . ']'
        ))) : '') . $cbDelMU . ($type != GlTypeCodes::m && $type != GlTypeCodes::U && $tableName != RESERV_STATUS_TABLE_NAME ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($tDiags, ''), array(
            'name' => 'selDiagDel[' . $d[0] . ']'
        ))) : ''));
    }

    // New Entry Markup?
    if ($type != GlTypeCodes::U && $type != GlTypeCodes::m && $tableName != RESERV_STATUS_TABLE_NAME) {
        // new entry row
        $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
            'name' => 'txtDiag[0]'
        ))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
            'name' => 'txtDOrder[0]',
            'size' => '3'
        ))) . HTMLTable::makeTd('New', array(
            'colspan' => 2
        )) . ($type == GlTypeCodes::HA || $type == GlTypeCodes::CA ? HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
            'size' => '7',
            'style' => 'text-align:right;',
            'name' => 'txtDiagAmt[0]'
        ))) : ''));
    }

    return $tbl;
}

$dbh = $wInit->dbh;

$uS = Session::getInstance();

// Kick out 'Guest' Users
if ($uS->rolecode > WebRole::WebUser) {

    exit("Unauthorized - " . HTMLContainer::generateMarkup('a', 'Continue', array(
        'href' => 'index.php'
    )));
}

$tabIndex = 0;

$rteMsg = '';
$rateTableErrorMessage = '';
$itemMessage = '';
$formType = '';
$demoMessage = '';

// Get labels
$labels = Labels::getLabels();

// Add diags and locations buttons
if (isset($_POST['btnAddDiags'])) {
    $dbh->exec("insert into gen_lookups (`Table_Name`, `Code`, `Description`, `Type`, `Order`) values ('Diagnosis', 'q9', 'New Entry', 'h', 10 )");
    $tabIndex = 5;
}

if (isset($_POST['btnAddLocs'])) {
    $dbh->exec("insert into gen_lookups (`Table_Name`, `Code`, `Description`, `Type`, `Order`) values ('Location', 'q9', 'New Entry', 'h', 10 )");
    $tabIndex = 5;
}

// Add House Discounts and additional charges.
if (isset($_POST['btnHouseDiscs'])) {
    $dbh->exec("insert into gen_lookups (`Table_Name`, `Code`, `Description`, `Type`, `Order`) values ('House_Discount', 'q9', 'New Entry', 'ca', 10 )");
    $tabIndex = 5;
}

if (isset($_POST['btnAddnlCharge'])) {
    $dbh->exec("insert into gen_lookups (`Table_Name`, `Code`, `Description`, `Type`, `Order`) values ('Addnl_Charge', 'q9', 'New Entry', 'ca', 10 )");
    $tabIndex = 5;
}

// Lookups
if (isset($_POST['table'])) {

    $tableName = filter_var($_POST['table'], FILTER_SANITIZE_STRING);

    if ($tableName == '') {
        echo '';
        exit();
    }

    if ($tableName == $labels->getString('hospital', 'diagnosis', DIAGNOSIS_TABLE_NAME)) {
        $tableName = DIAGNOSIS_TABLE_NAME;
    } else if ($tableName == $labels->getString('hospital', 'location', LOCATION_TABLE_NAME)) {
        $tableName = LOCATION_TABLE_NAME;
    } else if ($tableName == "ReservStatus") {
        $tableName = RESERV_STATUS_TABLE_NAME;
    }

    $cmd = '';
    $type = '';

    if (isset($_POST['cmd'])) {
        $cmd = filter_var($_POST['cmd'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['tp'])) {
        $type = filter_var($_POST['tp'], FILTER_SANITIZE_STRING);
    }

    // Save
    if ($cmd == 'save' && isset($_POST['txtDiag'])) {

        // Check for a new entry
        if (isset($_POST['txtDiag'][0]) && $_POST['txtDiag'][0] != '') {

            // new entry
            $dText = filter_var($_POST['txtDiag'][0], FILTER_SANITIZE_STRING);
            $aText = '';

            if ($tableName == 'Patient_Rel_Type') {
            	$aText = $labels->getString('MemberType', 'visitor', 'Guest').'s';
            }

            if (isset($_POST['txtDiagAmt'][0])) {
                $aText = filter_var($_POST['txtDiagAmt'][0], FILTER_SANITIZE_STRING);
            }

            $orderNumber = 0;
            if (isset($_POST['txtDOrder'][0])) {
                $orderNumber = intval(filter_var($_POST['txtDOrder'][0], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            // Check for an entry with the same description
            $stmt = $dbh->query("Select count(*) from gen_lookups where `Table_Name` = '$tableName' and LOWER(`Description`) = '" . strtolower($dText) . "';");
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($rows[0][0] == 0) {
                // Not there.
                $newCode = 'g' . incCounter($dbh, 'codes');

                $glRs = new GenLookupsRS();
                $glRs->Table_Name->setNewVal($tableName);
                $glRs->Code->setNewVal($newCode);
                $glRs->Description->setNewVal($dText);
                $glRs->Substitute->setNewVal($aText);
                $glRs->Type->setNewVal($type);
                $glRs->Order->setNewVal($orderNumber);

                EditRS::insert($dbh, $glRs);

                $logText = HouseLog::getInsertText($glRs);
                HouseLog::logGenLookups($dbh, $tableName, $newCode, $logText, "insert", $uS->username);
            }

            unset($_POST['txtDiag'][0]);
        }

        $rep = NULL;

        $demos = readGenLookupsPDO($dbh, 'Demographics');

        // Define the return functions.
        if (isset($demos[$tableName])) {

            if ($tableName == 'Gender') {
                $rep = function ($dbh, $newId, $oldId, $tableName) {
                    return $dbh->exec("update name set `$tableName` = '$newId' where `$tableName` = '$oldId';");
                };
            } else {
                $rep = function ($dbh, $newId, $oldId, $tableName) {
                    return $dbh->exec("update name_demog set `$tableName` = '$newId' where `$tableName` = '$oldId';");
                };
            }
        } else {
            switch ($tableName) {

                case 'Patient_Rel_Type':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update name_guest set Relationship_Code = '$newId' where Relationship_Code = '$oldId';");
                    };

                    $verify = "Select n.Relationship_Code from name_guest n left join gen_lookups g on n.Relationship_Code = g.Code Where g.Table_Name = 'Patient_Rel_Type' and g.Code is null;";
                    break;

                case 'Diagnosis':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update hospital_stay set Diagnosis = '$newId' where Diagnosis = '$oldId';");
                    };

                    $verify = "select hs.Diagnosis from hospital_stay hs left join gen_lookups g on hs.Diagnosis = g.Code where g.Table_Name = 'Diagnosis' and g.Code is null;";
                    break;

                case 'Location':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update hospital_stay set Location = '$newId' where Location = '$oldId';");
                    };
                    break;

                case 'OSS_Codes':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update resource_use set OSS_Code = '$newId' where OSS_Code = '$oldId';");
                    };
                    break;

                case 'Utilization_Category':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update resource set Utilization_Category = '$newId' where Utilization_Category = '$oldId';");
                    };
                    break;

                case 'Ins_Type':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update insurance set `Type` = '$newId' where `Type` = '$oldId';");
                    };
                    break;

                case 'Room_Cleaning_Days':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update room set `Cleaning_Cycle_Code` = '$newId' where `Cleaning_Cycle_Code` = '$oldId';");
                    };
                    break;

                case 'NoReturnReason':

                    $rep = function ($dbh, $newId, $oldId) {
                        return $dbh->exec("update name_demog set `No_Return` = '$newId' where `No_Return` = '$oldId';");
                    };
                    break;
            }
        }

        $amounts = array();
        if (isset($_POST['txtDiagAmt'])) {

            foreach ($_POST['txtDiagAmt'] as $k => $a) {
                if (is_numeric($a)) {
                    $a = floatval($a);
                }

                $amounts[$k] = $a;
            }
        }

        $codeArray = filter_var_array($_POST['txtDiag'], FILTER_SANITIZE_STRING);
        $orderNums = (isset($_POST['txtDOrder']) ? filter_var_array($_POST['txtDOrder'], FILTER_SANITIZE_NUMBER_INT) : array());

        if ($type === GlTypeCodes::m) {

            foreach ($codeArray as $c => $v) {

                $gluRs = new GenLookupsRS();
                $gluRs->Table_Name->setStoredVal($tableName);
                $gluRs->Code->setStoredVal($c);

                $rw = EditRS::select($dbh, $gluRs, array(
                    $gluRs->Table_Name,
                    $gluRs->Code
                ));

                if (count($rw) == 1) {

                    $gluRs = new GenLookupsRS();
                    EditRS::loadRow($rw[0], $gluRs);

                    $use = '';
                    if (isset($_POST['cbDiagDel'][$c])) {
                        $use = 'y';
                    }

                    $orderNumber = 0;
                    if (isset($_POST['txtDOrder'][$c])) {
                        $orderNumber = intval(filter_var($_POST['txtDOrder'][$c], FILTER_SANITIZE_NUMBER_INT), 10);
                    }

                    $desc = '';
                    if (isset($_POST['txtDiag'][$c])) {
                        $desc = filter_var($_POST['txtDiag'][$c], FILTER_SANITIZE_STRING);
                    }

                    $gluRs->Description->setNewVal($desc);
                    $gluRs->Substitute->setNewVal($use);
                    $gluRs->Order->setNewVal($orderNumber);

                    $upCtr = EditRS::update($dbh, $gluRs, array(
                        $gluRs->Table_Name,
                        $gluRs->Code
                    ));

                    if ($upCtr > 0) {

                        $logText = HouseLog::getUpdateText($gluRs);
                        HouseLog::logGenLookups($dbh, $tableName, $c, $logText, "update", $uS->username);
                    }
                }
            }
        } else if (isset($_POST['selmisc'])) {
            replaceLookups($dbh, $_POST['selmisc'], $codeArray, (isset($_POST['cbDiagDel']) ? $_POST['cbDiagDel'] : array()));
        } else {
            replaceGenLk($dbh, $tableName, $codeArray, $amounts, $orderNums, (isset($_POST['cbDiagDel']) ? $_POST['cbDiagDel'] : NULL), $rep, (isset($_POST['cbDiagDel']) ? $_POST['selDiagDel'] : array()));
        }
    }

    if($cmd == "load" && $tableName == "insurance"){
        $insurance = new Insurance();
        $insurance->loadInsurances($dbh, $type);
        echo $insurance->generateTblMarkup();
        exit();
    }

    // Generate selectors.
    if (isset($_POST['selmisc'])) {
        $tbl = getSelections($dbh, RESERV_STATUS_TABLE_NAME, $_POST['selmisc'], $labels);
    } else {
        $tbl = getSelections($dbh, $tableName, $type, $labels);
    }

    echo ($tbl->generateMarkup());
    exit();
}

if (isset($_POST['btnkfSave'])) {

    $tabIndex = 2;

    // room pricing
    $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
    $newDefault = $priceModel->saveEditMarkup($dbh, $_POST, $uS->username);

    if ($newDefault != '') {
        SysConfig::saveKeyValue($dbh, 'sys_config', 'RoomRateDefault', $newDefault);
        $uS->RoomRateDefault = $newDefault;
    }

    // Static room settings.
    if (isset($_POST['srrDesc'][0]) && $_POST['srrDesc'][0] != '') {

        // new entry
        $dText = filter_var($_POST['srrDesc'][0], FILTER_SANITIZE_STRING);
        $dAmt = filter_var($_POST['srrAmt'][0], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        // Check for an entry with the same description
        $stmt = $dbh->query("Select count(*) from gen_lookups where `Table_Name` = 'Static_Room_Rate' and LOWER(`Description`) = '" . strtolower($dText) . "';");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        if ($rows[0][0] == 0) {
            // Not there.
            $newCode = 'g' . incCounter($dbh, 'codes');

            $glRs = new GenLookupsRS();
            $glRs->Table_Name->setNewVal('Static_Room_Rate');
            $glRs->Code->setNewVal($newCode);
            $glRs->Description->setNewVal($dText);
            $glRs->Substitute->setNewVal($dAmt == 0 ? '0' : number_format($dAmt, 2));

            EditRS::insert($dbh, $glRs);

            $logText = HouseLog::getInsertText($glRs);
            HouseLog::logGenLookups($dbh, 'Static_Room_Rate', $newCode, $logText, 'insert', $uS->username);
        }

        unset($_POST['srrDesc'][0]);
    }

    // saveArchive($dbh, $_POST['srrDesc'], $_POST['srrAmt'], 'Static_Room_Rate');
    saveGenLk($dbh, 'Static_Room_Rate', $_POST['srrDesc'], $_POST['srrAmt'], NULL);

    // Key Deposit
    if (isset($_POST['kdesc'])) {

        // Dave deposit
        saveGenLk($dbh, 'Key_Deposit_Code', $_POST['kdesc'], $_POST['krate'], NULL);

        // Copy to item
        foreach ($_POST['krate'] as $k => $p) {

            if ($p > 0) {
                // update item
                $itemRs = new ItemRS();
                $itemRs->idItem->setStoredVal(ItemId::KeyDeposit);
                $rows = EditRS::select($dbh, $itemRs, array(
                    $itemRs->idItem
                ));

                if (count($rows) == 1) {
                    $itemRs->Description->setNewVal(filter_var($_POST['kdesc'][$k], FILTER_SANITIZE_STRING));
                    EditRS::update($dbh, $itemRs, array(
                        $itemRs->idItem
                    ));
                }
            }
        }
    }

    // Visit Fee
    if (isset($_POST['vfdesc'])) {

        // new visit fee defined?
        if (isset($_POST['vfdesc'][0])) {

            $newDesc = filter_var($_POST['vfdesc'][0], FILTER_SANITIZE_STRING);
            $newRate = filter_var($_POST['vfrate'][0], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            if ($newDesc != '') {
                // Add a cleaning fee?

                // Look for existing fee
                $glRs = new GenLookupsRS();
                $glRs->Table_Name->setStoredVal('Visit_Fee_Code');
                $glRs->Description->setStoredVal($newDesc);
                $rows = EditRS::select($dbh, $glRs, array(
                    $glRs->Table_Name,
                    $glRs->Description
                ));

                if (count($rows) > 0) {
                    $rateTableErrorMessage = HTMLContainer::generateMarkup('p', 'Visit fee code "' . $newDesc . '" is already defined. ', array(
                        'style' => 'color:red;'
                    ));
                } else {

                    // Insert new cleaning fee
                    $glRs = new GenLookupsRS();
                    $newCode = incCounter($dbh, 'codes');

                    $glRs->Table_Name->setNewVal('Visit_Fee_Code');
                    $glRs->Description->setNewVal($newDesc);
                    $glRs->Substitute->setNewVal($newRate);
                    $glRs->Code->setNewVal($newCode);

                    EditRS::insert($dbh, $glRs);
                    $logText = HouseLog::getInsertText($glRs, 'Visit_Fee_Code');
                    HouseLog::logGenLookups($dbh, 'Visit_Fee_Code', $newCode, $logText, 'insert', $uS->username);
                }
            }
        }

        $vfDefault = '';

        if (isset($_POST['vfrbdefault'])) {
            $vfDefault = filter_var($_POST['vfrbdefault'], FILTER_SANITIZE_STRING);
        }

        // Amount Changed?
        if (($defaultCode = saveArchive($dbh, $_POST['vfdesc'], $_POST['vfrate'], 'Visit_Fee_Code')) != '') {
            $vfDefault = $defaultCode;
        }

        // Save the default visit fee selection.
        if ($vfDefault != '') {

            $vFees = readGenLookupsPDO($dbh, 'Visit_Fee_Code');

            foreach ($vFees as $v) {

                if ($v[0] == $vfDefault) {

                    SysConfig::saveKeyValue($dbh, 'sys_config', 'DefaultVisitFee', $v[0]);
                    $uS->DefaultVisitFee = $v[0];
                    break;
                }
            }
        }
    }

    // Pay type default
    if (isset($_POST['ptrbdefault'])) {

        $vfDefault = filter_var($_POST['ptrbdefault'], FILTER_SANITIZE_STRING);
        $vFees = readGenLookupsPDO($dbh, 'Pay_Type');

        foreach ($vFees as $v) {

            if ($v[0] == $vfDefault && $v[0] != PayType::Invoice) {

                SysConfig::saveKeyValue($dbh, 'sys_config', 'DefaultPayType', $v[0]);
                $uS->DefaultPayType = $v[0];
                break;
            }
        }
    }

    // Payment types GL Codes
    if (isset($_POST['ptGlCode'])) {

    	$stmtp = $dbh->query("select idPayment_method, Gl_Code from payment_method");
    	$payMethods = $stmtp->fetchAll(\PDO::FETCH_NUM);

    	foreach ($payMethods as $t) {

    		if (isset($_POST['ptGlCode'][$t[0]])) {
    			$gl = filter_var($_POST['ptGlCode'][$t[0]], FILTER_SANITIZE_STRING);

    			$dbh->exec("Update payment_method set Gl_Code = '$gl' where idPayment_method = ". $t[0]);
    		}
    	}
    }

    // Excess Pay
    if (isset($_POST['epdesc'][$uS->VisitExcessPaid])) {

        saveGenLk($dbh, 'ExcessPays', $_POST['epdesc'], array(), NULL);
    }

    // Financial Asst. Break points.
    if (isset($_POST['faIa'])) {

        $faIa = filter_var_array($_POST['faIa'], FILTER_SANITIZE_NUMBER_INT);
        $faIb = filter_var_array($_POST['faIb'], FILTER_SANITIZE_NUMBER_INT);
        $faIc = filter_var_array($_POST['faIc'], FILTER_SANITIZE_NUMBER_INT);

        $faRs = new Fa_CategoryRS();
        $faRows = EditRS::select($dbh, $faRs, array());

        foreach ($faRows as $r) {
            $faRs = new Fa_CategoryRs();
            EditRS::loadRow($r, $faRs);

            $idFa = $faRs->idFa_category->getStoredVal();

            $faRs->Income_A->setNewVal(str_replace(',', '', str_replace('$', '', $faIa[$idFa])));
            $faRs->Income_B->setNewVal(str_replace(',', '', str_replace('$', '', $faIb[$idFa])));
            $faRs->Income_C->setNewVal(str_replace(',', '', str_replace('$', '', $faIc[$idFa])));
            $faRs->Income_D->setNewVal((str_replace(',', '', str_replace('$', '', $faIc[$idFa] + 1))));
            $faRs->Updated_By->setNewVal($uS->username);
            $faRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            EditRS::update($dbh, $faRs, array(
                $faRs->idFa_category
            ));
        }
    }
}

if (isset($_POST['btnhSave'])) {

    $tabIndex = 3;
    $postedHosp = array();
    if (isset($_POST['hTitle'])) {
        $postedHosp = filter_var_array($_POST['hTitle'], FILTER_SANITIZE_STRING);
    }

    foreach ($postedHosp as $hid => $title) {

        $idHosp = intval($hid, 10);

        $hospRs = new HospitalRS();
        $hospRs->idHospital->setStoredVal($idHosp);

        // Delete?
        if (isset($_POST['hdel'][$idHosp])) {

        	// Change status to "Retired"
        	$hospRs->Status->setNewVal('r');
        	EditRS::update($dbh, $hospRs, array($hospRs->idHospital));

            // Delete any attribute entries
            $query = "delete from attribute_entity where idEntity = :id and Type = :tpe";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':id' => $idHosp,
                ':tpe' => AttributeTypes::Hospital
            ));
            continue;
        }

        // Type
        if (isset($_POST['hType'][$idHosp])) {
            $type = filter_var($_POST['hType'][$idHosp], FILTER_SANITIZE_STRING);
        } else {
            continue;
        }

        if (isset($_POST['hDesc'][$idHosp])) {
            $desc = filter_var($_POST['hDesc'][$idHosp], FILTER_SANITIZE_STRING);
        } else {
            $desc = '';
        }

        // background Color
        $rCSS = '';
        if (isset($_POST['hColor'][$idHosp])) {
            $rCSS = filter_var($_POST['hColor'][$idHosp], FILTER_SANITIZE_STRING);
        }

        // Text Color
        $vCSS = '';
        if (isset($_POST['hText'][$idHosp])) {
            $vCSS = filter_var($_POST['hText'][$idHosp], FILTER_SANITIZE_STRING);
        }

        // New Hospital?
        if ($title == '' || $type == '') {
            // No new hospitals this time
            continue;
        }

        if ($idHosp > 0) {
            $rows = EditRS::select($dbh, $hospRs, array(
                $hospRs->idHospital
            ));
            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $hospRs);
            } else {
                continue;
            }
        }

        $hospRs->Title->setNewVal($title);
        $hospRs->Type->setNewVal($type);
        $hospRs->Description->setNewVal($desc);
        $hospRs->Status->setNewVal('a');
        $hospRs->Reservation_Style->setNewVal($rCSS);
        $hospRs->Stay_Style->setNewVal($vCSS);
        $hospRs->Updated_By->setNewVal($uS->username);
        $hospRs->Last_Updated->setNewVal(date('Y-m-d'));

        if ($idHosp > 0) {
            // update
            EditRS::update($dbh, $hospRs, array(
                $hospRs->idHospital
            ));

            // Check attributes
            $capturedAttributes = array();

            if (isset($_POST['hpattr'][$idHosp])) {

                foreach ($_POST['hpattr'][$idHosp] as $k => $v) {
                    $capturedAttributes[$k] = $k;
                }
            }

            $cHosp = new ConstraintsHospital($dbh, $idHosp);
            $cHosp->saveConstraints($dbh, $capturedAttributes);
        } else {
            // insert
            EditRS::insert($dbh, $hospRs);
        }
    }
}

if (isset($_POST['btnAttrSave'])) {

    $tabIndex = 9;
    $postedAttr = array();
    if (isset($_POST['atTitle'])) {
        $postedAttr = filter_var_array($_POST['atTitle'], FILTER_SANITIZE_STRING);
    }

    foreach ($postedAttr as $hid => $title) {

        $idAttr = intval($hid, 10);

        $atRs = new AttributeRS();
        $atRs->idAttribute->setStoredVal($idAttr);

        // Delete?
        if (isset($_POST['atdel'][$idAttr])) {

            EditRS::delete($dbh, $atRs, array(
                $atRs->idAttribute
            ));

            // delete from attribute_entity
            $dbh->query("Delete from attribute_entity where idAttribute = $idAttr");
            $dbh->query("Delete from constraint_attribute where idAttribute = $idAttr");

            continue;
        }

        // Type
        if (isset($_POST['atType'][$idAttr])) {
            $type = intval(filter_var($_POST['atType'][$idAttr], FILTER_SANITIZE_NUMBER_INT), 10);
        } else {
            continue;
        }

        if (isset($_POST['atCat'][$idAttr])) {
            $cat = filter_var($_POST['atCat'][$idAttr], FILTER_SANITIZE_STRING);
        } else {
            $cat = '';
        }

        // New Attr?
        if ($title == '' || $type == '') {
            // No new hospitals this time
            continue;
        }

        if ($idAttr > 0) {
            $rows = EditRS::select($dbh, $atRs, array(
                $atRs->idAttribute
            ));
            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $atRs);
            } else {
                continue;
            }
        }

        $atRs->Title->setNewVal($title);
        $atRs->Type->setNewVal($type);
        $atRs->Category->setNewVal($cat);
        $atRs->Status->setNewVal('a');
        $atRs->Updated_By->setNewVal($uS->username);
        $atRs->Last_Updated->setNewVal(date('Y-m-d'));

        if ($idAttr > 0) {
            // update
            EditRS::update($dbh, $atRs, array(
                $atRs->idAttribute
            ));
        } else {
            // insert
            EditRS::insert($dbh, $atRs);
        }
    }
}

if (isset($_POST['btnItemSave'])) {

    $tabIndex = 6;

    // item-item table
    $iistmt = $dbh->query("Select * from item_item");
    $taxItemMap = $iistmt->fetchAll(\PDO::FETCH_ASSOC);

    $sitems = $dbh->query("Select  i.idItem, itm.Type_Id, i.Description, i.Gl_Code, i.Percentage
    from item i left join item_type_map itm on itm.Item_Id = i.idItem");
    $items = $sitems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $i) {

        $idItem = intval($i['idItem']);

        if ($i['Type_Id'] == ItemType::Tax || $idItem < 1) {
            continue;
        }

        if (isset($_POST['txtItem'][$idItem])) {

        	$desc = filter_var($_POST['txtItem'][$idItem], FILTER_SANITIZE_STRING);

        	$dbh->exec("update `item` set `Description` = '$desc' where `idItem` = " . $idItem);
        }

        if (isset($_POST['txtGlCode'][$idItem])) {

        	$glCode = filter_var($_POST['txtGlCode'][$idItem], FILTER_SANITIZE_STRING);

        	$dbh->exec("update `item` set `Gl_Code` = '$glCode' where `idItem` = " . $idItem);
        }

        if (isset($_POST['cbtax'][$idItem])) {
            // Define tax items for each item.
            foreach ($_POST['cbtax'][$idItem] as $t) {

                $idTaxItem = intval(filter_var($t, FILTER_SANITIZE_NUMBER_INT), 10);

                if ($idTaxItem > 0) {
                    $dbh->exec("Replace into item_item (idItem, Item_Id) values ($idItem, $idTaxItem)");
                }
            }
        }

        // delete unchecked tax items
        foreach ($taxItemMap as $m) {
            if ($m['idItem'] == $idItem && ! isset($_POST['cbtax'][$idItem][$m['Item_Id']])) {

                $dbh->exec("delete from item_item where idItem = $idItem and Item_Id = " . $m['Item_Id']);
            }
        }
    }
}

if (isset($_POST['btnTaxSave'])) {
    $tabIndex = 7;

    $sitems = $dbh->query("Select i.idItem, i.Description, i.Gl_Code, i.Percentage, i.Timeout_Days, i.First_Order_Id, i.Last_Order_Id
        from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = " . ItemType::Tax);
    $items = $sitems->fetchAll(PDO::FETCH_ASSOC);

    // Get the latest visit id
    $stmt = $dbh->query("select max(idVisit) from visit");
    $vrows = $stmt->fetchAll(\PDO::FETCH_NUM);
    $maxVisitId = $vrows[0][0];
    $nextVisitId = $maxVisitId + 1;

    // Save any changes to existing items.
    foreach ($items as $i) {

        if (isset($_POST['txttItem'][$i['idItem']])) {

            $desc = filter_var($_POST['txttItem'][$i['idItem']], FILTER_SANITIZE_STRING);
            $glCode = filter_var($_POST['txttGlCode'][$i['idItem']], FILTER_SANITIZE_STRING);
            $percentage = filter_var($_POST['txttPercentage'][$i['idItem']], FILTER_SANITIZE_STRING);
            $maxDays = filter_var($_POST['txttMaxDays'][$i['idItem']], FILTER_SANITIZE_STRING);
            $last = $i['Last_Order_Id'];
            $first = $i['First_Order_Id'];

            if ($maxDays != $i['Timeout_Days'] || $percentage != $i['Percentage']) {

                if ($last != 0) {
                    $itemMessage = HTMLContainer::generateMarkup('span', 'Cannot change that tax item.', array(
                        'style' => 'color:red;'
                    ));
                } else {

                    // save this one with the last order id
                    $dbh->exec("update `item` set `Description` = '$desc', `Gl_Code` = '$glCode', Last_Order_Id = $maxVisitId " . " where `idItem` = " . $i['idItem']);

                    // Create the a new item with the new percentage or maxDays
                    $dbh->exec("insert into `item` (`Description`, `Gl_Code`, `Percentage`, `Timeout_Days`, First_Order_Id) " . "Values ('$desc', '$glCode', '$percentage', '$maxDays', $nextVisitId)");

                    $newItemId = $dbh->lastInsertId();

                    // Add to the item type map
                    if ($newItemId > 0) {
                        $dbh->exec("insert into `item_type_map` Values ('" . $newItemId . "', '" . ItemType::Tax . "')");
                    }

                    // Get the items these tax
                    $tstmt = $dbh->query("SELECT idItem from item_item where Item_Id = " . $i['idItem']);

                    // add to item_item to connet with the taxed item id.
                    while ($t = $tstmt->fetch(\PDO::FETCH_NUM)) {
                        $dbh->exec("Insert into item_item (idItem, Item_Id) values (" . $t[0] . ", $newItemId)");
                    }
                }
            } else {

                $dbh->exec("update `item` set `Description` = '$desc', `Gl_Code` = '$glCode'" . " where `idItem` = " . $i['idItem']);
            }
        }
    }

    // New tax item?
    if (isset($_POST['txttItem'][0]) && $_POST['txttItem'][0] != '') {

        $desc = filter_var($_POST['txttItem'][0], FILTER_SANITIZE_STRING);
        $glCode = filter_var($_POST['txttGlCode'][0], FILTER_SANITIZE_STRING);
        $percentage = filter_var($_POST['txttPercentage'][0], FILTER_SANITIZE_STRING);
        $maxDays = filter_var($_POST['txttMaxDays'][0], FILTER_SANITIZE_STRING);

        $dbh->exec("insert into `item` (`Description`, `Gl_Code`, `Percentage`, `Timeout_Days`, First_Order_Id) Values ('$desc', '$glCode', '$percentage', '$maxDays', $nextVisitId)");

        if ($dbh->lastInsertId() > 0) {
            $dbh->exec("insert into `item_type_map` Values ('" . $dbh->lastInsertId() . "', '" . ItemType::Tax . "')");
        }
    }
}

// Get forms
if (isset($_POST['ldfm'])) {

    $formDef = '';
    $formTitle = '';

    $formType = filter_var($_POST['ldfm'], FILTER_SANITIZE_STRING);

    $rarry = readGenLookupsPDO($dbh, 'Form_Upload');

    // get available doc replacements
    $replacementStmt = $dbh->query("SELECT `idTemplate_tag`, `Tag_Title`, `Tag_Name` FROM `template_tag` WHERE `Doc_Name` = '$formType'");
    $replacementRows = $replacementStmt->fetchAll();
    $rTbl = new HTMLTable();

    $rTbl->addHeaderTr(HTMLTable::makeTh('Name') . HTMLTable::makeTh('Code'));

    foreach ($replacementRows as $row) {
        $rTbl->addBodyTr(HTMLTable::makeTd($row[1]) . HTMLTable::makeTd($row[2]));
    }

    // Look for a match
    foreach ($rarry as $f) {

        if ($formType === $f['Code']) {
            $formDef = $f['Substitute'];
            $formTitle = $f['Description'];
            break;
        }
    }

    if (empty($formDef)) {

        $formDef = "FormDef-" . incCounter($dbh, 'codes');
        $dbh->exec("UPDATE `gen_lookups` SET `Substitute` = '$formDef' WHERE `Table_Name` = 'Form_Upload' AND `Code` = '$formType'");
    }

    $formstmt = $dbh->query("Select g.`Code`, g.`Description`, d.`Doc`, d.idDocument from `document` d join gen_lookups g on d.idDocument = g.`Substitute` where g.`Table_Name` = '$formDef' order by g.Order asc");
    $docRows = $formstmt->fetchAll();

    $li = '';
    $tabContent = '';

    //set help text
    $help = '';

    foreach ($docRows as $r) {

        $li .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $r['Description'], array(
            'href' => '#' . $r['Code']
        )), array('class'=>'hhk-sortable', 'data-code'=>$r['Code']));

        $tabContent .= HTMLContainer::generateMarkup('div',  $help .($r['Doc'] ? HTMLContainer::generateMarkup('fieldset', '<legend style="font-weight: bold;">Current Form</legend>' . $r['Doc'], array(
            'id' => 'form' . $r['idDocument'], 'class'=> 'p-3 mb-3 user-agent-spacing')): '') .
            '<div class="row"><div class="col-10 uploadFormDiv ui-widget-content" style="display: none;"><form enctype="multipart/form-data" action="ResourceBuilder.php" method="POST" class="d-inline-block" style="padding: 5px 7px;">
<input type="hidden" name="docId" value="' . $r['idDocument'] . '"/><input type="hidden" name="filefrmtype" value="' . $formType . '"/><input type="hidden" name="docUpload" value="true">
Upload new HTML file: <input name="formfile" type="file" required accept="text/html" />
<input type="submit" value="Save Form" />
</form><form action="ResourceBuilder.php" method="POST" class="d-inline-block"><input type="hidden" name="docCode" value="' . $r['Code'] . '"><input type="hidden" name="formDef" value="' . $formDef . '"><input type="hidden" name="docfrmtype" value="' . $formType . '"/><input type="hidden" name="delfm" value="true"><button type="submit" value="Delete Form"><span class="ui-icon ui-icon-trash"></span>Delete Form</button></form></div><div class="col-2" style="text-align: center;"><button class="replaceForm" style="margin: 6px 0;">Replace Form</button></div></div>', array(
            'id' => $r['Code']
        ));
    }

    if (count($replacementRows) > 0) {

        // add replacements tab
        $li .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Replacement Codes', array(
            'href' => '#replacements'
        )), array(
            'id' => 'liReplacements',
            'style' => 'float: right;'
        ));

        $tabContent .= HTMLContainer::generateMarkup('div', '<div class="mb-3">You may use the following codes in your document to personalize the document to each ' .$labels->getString('MemberType', 'guest', 'Guest').'</div>' . $rTbl->generateMarkup(), array(
            'id' => 'replacements'
        ));
    }

    // Make the final tab control
    $ul = HTMLContainer::generateMarkup('ul', $li, array());
    $output = HTMLContainer::generateMarkup('div', $ul . $tabContent, array(
        'id' => 'regTabDiv',
        'data-formDef' => $formDef
    ));

    $dataArray['type'] = $formType;
    $dataArray['title'] = $formTitle;
    $dataArray['mkup'] = $output;

    echo json_encode($dataArray);

    exit();
}

// Upload a new form
if (isset($_POST['docUpload'])) {

    $tabIndex = 8;

    if (isset($_POST['filefrmtype'])) {
    	$formType = filter_var($_POST['filefrmtype'], FILTER_SANITIZE_STRING);
    }

    $mimetype = mime_content_type($_FILES['formfile']['tmp_name']);

    if (! empty($_FILES['formfile']['tmp_name']) && ($mimetype == "text/html" || $mimetype == "text/plain") ) {

        $docId = - 1;
        if (isset($_POST['docId'])) {
            $docId = intval(filter_var($_POST['docId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        // Get the file and convert it.
        $file = file_get_contents($_FILES['formfile']['tmp_name']);
        $doc = iconv('Windows-1252', 'UTF-8', $file);
        $uName = $uS->username;

        $ustmt = $dbh->prepare("update document set Doc = ?, Updated_By = ?, Last_Updated = now() where idDocument = ?");
        $ustmt->bindParam(1, $doc, PDO::PARAM_LOB);
        $ustmt->bindParam(2, $uName);
        $ustmt->bindParam(3, $docId);
        $dbh->beginTransaction();
        $ustmt->execute();
        $dbh->commit();
    }
}

if (isset($_POST['delfm']) && isset($_POST['docCode']) && isset($_POST['formDef'])) {

    $docCode = filter_var($_POST['docCode'], FILTER_SANITIZE_STRING);
    $formDef = filter_var($_POST['formDef'], FILTER_SANITIZE_STRING);

    $tabIndex = 8;

    $dbh->exec("UPDATE `document` d JOIN `gen_lookups` g ON g.`Table_Name` = '$formDef' AND g.`Code` = '$docCode' SET d.`status` = 'd' WHERE `idDocument` = g.`Substitute`");
    $dbh->exec("DELETE FROM gen_lookups where `Table_Name` = '$formDef' AND `Code` = '$docCode'");

    if (isset($_POST['docfrmtype'])) {
    	$formType = filter_var($_POST['docfrmtype'], FILTER_SANITIZE_STRING);
    }

}

// Make sure Content-Type is application/json
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (stripos($content_type, 'application/json') !== false) {
    // Read the input stream
    $body = file_get_contents("php://input");
    $data = json_decode($body);

    if($data->cmd == "reorderfm"){
        $output = "";
        try{
            foreach($data->order as $i=>$v){
                $dbh->exec("UPDATE `gen_lookups` SET `Order` = $i WHERE `Table_Name` = '" . $data->formDef . "' AND `Code` = '$v';");
            }
        }catch(\Exception $e){
            echo json_encode(["status"=>"error", "message"=>$e->getMessage()]);
            exit;
        }
        echo json_encode(["status"=>"success"]);
    }

    exit;
}

if (isset($_POST['txtformLang'])) {

    $tabIndex = 8;
    $lang = trim(filter_var($_POST['txtformLang'], FILTER_SANITIZE_STRING));
    $formDef = '';
    $formTitle = '';

    if (isset($_POST['hdnFormType'])) {
    	$formType = filter_var($_POST['hdnFormType'], FILTER_SANITIZE_STRING);
    }

    if ($lang != '') {

        $rarry = readGenLookupsPDO($dbh, 'Form_Upload');

        // Look for a match
        foreach ($rarry as $f) {

            if ($formType === $f['Code']) {
                $formDef = $f['Substitute'];
                $formTitle = $f['Description'];
                break;
            }
        }

        if (empty($formDef) === FALSE) {

            $docId = 0;
            $langCode = '';

            // lookup teh language
            $lstmt = $dbh->query("Select `ISO_639_1` as `Code` from `language` where `Title` = '$lang';");
            $langRows = $lstmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($langRows) > 0) {
                // Ah, a recognized language
                $langCode = $langRows[0]['Code'];
            } else {
                $langCode = incCounter($dbh, 'codes');
            }

            if ($langCode != '') {

                // Code already exist?
                $formstmt = $dbh->query("Select g.`Code`, g.`Description` from gen_lookups g where g.`Table_Name` = '$formDef' and g.`Code` = '$langCode'");
                $docRows = $formstmt->fetchAll();

                if (count($docRows) == 0) {

                    // Create document entry
                    $docRs = new DocumentRS();
                    $docRs->Title->setNewVal($formTitle);
                    $docRs->Category->setNewVal('form');
                    $docRs->Language->setNewVal($langCode);
                    $docRs->Type->setNewVal('html');
                    $docRs->Created_By->setNewVal($uS->username);
                    $docRs->Status->setNewVal('a');

                    $docId = EditRS::insert($dbh, $docRs);

                    // Add index to GenLookups
                    if ($docId > 0) {
                        $genRs = new GenLookupsRS();
                        $genRs->Table_Name->setNewVal($formDef);
                        $genRs->Code->setNewVal($langCode);
                        $genRs->Description->setNewVal($lang);
                        $genRs->Substitute->setNewVal($docId);

                        EditRS::insert($dbh, $genRs);
                    }
                }
            }
        }
    }
}


//
// Generate tab content
//


// hospital tab title
$hospitalTabTitle = $labels->getString('hospital', 'hospital', 'Hospitals & Associations');

// Room pricing model
$rPrices = readGenLookupsPDO($dbh, 'Price_Model');
$kTbl = new HTMLTable();
$kTbl->addHeaderTr(HTMLTable::makeTh('Selected Model'));

$kTbl->addBodyTr(HTMLTable::makeTd($rPrices[$uS->RoomPriceModel][1]));

$pricingModelTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Pricing Model', array(
    'style' => 'font-weight:bold;'
)) . $kTbl->generateMarkup(array(
    'style' => 'margin:7px;'
)), array(
    'style' => 'margin:7px;'
));

// Room and Resourse lists
$rescTable = ResourceView::resourceTable($dbh);
$roomTable = ResourceView::roomTable($dbh, $uS->KeyDeposit, $uS->PaymentGateway);

// Room Pricing
$priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
$fTbl = $priceModel->getEditMarkup($dbh, $uS->RoomRateDefault);

// Static room rate
$rp = readGenLookupsPDO($dbh, 'Static_Room_Rate', 'Description');

$sTbl = new HTMLTable();
$sTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('Amount'));

foreach ($rp as $r) {
    $sTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array(
        'name' => 'srrDesc[' . $r[0] . ']',
        'size' => '16'
    ))) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2], array(
        'name' => 'srrAmt[' . $r[0] . ']',
        'size' => '6',
        'class' => 'number-only'
    ))));
}

$sTbl->addBodyTr(HTMLTable::makeTd('New static room rate:', array(
    'colspan' => '3'
)));
$sTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'srrDesc[0]',
    'size' => '16'
))) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', array(
    'name' => 'srrAmt[0]',
    'size' => '6',
    'class' => 'number-only'
))));

$sMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Static Room Rate', array(
    'style' => 'font-weight:bold;'
)) . $sTbl->generateMarkup(array(
    'style' => 'float:left;margin:7px;'
)), array(
    'style' => 'clear:left;float:left;margin:7px;'
));

// Rate Calculator
$rcMarkup = '';

if ($priceModel->hasRateCalculator()) {

    $tbl = new HTMLTable();

    $tbl->addHeaderTr(HTMLTable::makeTh('Room Rate') . HTMLTable::makeTh('Credit') . ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily ? HTMLTable::makeTh($labels->getString('MemberType', 'guest', 'Guest').' Nights') : HTMLTable::makeTh('Nights')) . HTMLTable::makeTh('Total'));

    $attrFixed = array(
        'id' => 'spnRateTB',
        'class' => 'hhk-fxFixed',
        'style' => 'margin-left:.5em;display:none;'
    );
    $rateCategories = RoomRate::makeSelectorOptions($priceModel);

    $tbl->addBodyTr(HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), ''), array(
        'name' => 'selRateCategory'
    )) . HTMLContainer::generateMarkup('span', '$' . HTMLInput::generateMarkup('', array(
        'name' => 'txtFixedRate',
        'size' => '4'
    )), $attrFixed)) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
        'name' => 'txtCredit',
        'size' => '4'
    )), array(
        'style' => 'text-align:center;'
    )) . HTMLTable::makeTd(HTMLInput::generateMarkup('1', array(
        'name' => 'txtNites',
        'size' => '4'
    )), array(
        'style' => 'text-align:center;'
    )) . HTMLTable::makeTd('$' . HTMLContainer::generateMarkup('span', '0', array(
        'name' => 'spnAmount'
    )), array(
        'style' => 'text-align:center;'
    )));

    $rcMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Rate Calculator', array(
        'style' => 'font-weight:bold;'
    )) . $tbl->generateMarkup(array(
        'style' => 'float:left;margin:7px;'
    )), array(
        'style' => 'clear:left;float:left;margin:7px;'
    ));
}

// Wrap rate table and rate calculator
$feesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Rates', array(
    'style' => 'font-weight:bold;'
)) . HTMLContainer::generateMarkup('div', $fTbl->generateMarkup(array(
    'style' => 'margin:7px;'
)), array(
    'style' => 'max-height:310px; overflow-y:scroll;'
)) . $rcMarkup . $sMarkup, array(
    'style' => 'clear:left;float:left;margin:7px;'
));

// Visit Fees - cleaning fees
$visitFeesTable = '';

if ($uS->VisitFee) {

    $kFees = readGenLookupsPDO($dbh, 'Visit_Fee_Code');
    $kTbl = new HTMLTable();
    $kTbl->addHeaderTr(HTMLTable::makeTh('Default') . HTMLTable::makeTh('Description') . HTMLTable::makeTh('Amount'));

    foreach ($kFees as $r) {

        if ($r['Type'] == GlTypeCodes::Archive) {
            continue;
        }

        $ptAttrs = array(
            'type' => 'radio',
            'name' => 'vfrbdefault'
        );

        if ($uS->DefaultVisitFee == $r[0]) {
            $ptAttrs['checked'] = 'checked';
        } else {
            unset($ptAttrs['checked']);
        }

        $kTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($r[0], $ptAttrs), array(
            'style' => 'text-align:center;'
        )) . HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array(
            'name' => 'vfdesc[' . $r[0] . ']',
            'size' => '16'
        ))) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2], array(
            'name' => 'vfrate[' . $r[0] . ']',
            'size' => '6',
            'class' => 'number-only'
        ))));
    }

    // add empty fee row
    $kTbl->addBodyTr(HTMLTable::makeTd('', array(
        'style' => 'text-align:center;'
    )) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
        'name' => 'vfdesc[0]',
        'size' => '16'
    ))) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', array(
        'name' => 'vfrate[0]',
        'size' => '6',
        'class' => 'number-only'
    ))));

    $visitFeesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee') . ' Amount', array(
        'style' => 'font-weight:bold;'
    )) . $kTbl->generateMarkup(array(
        'style' => 'margin:7px;'
    )), array(
        'style' => 'float:left;margin:7px;'
    ));
}

// Financial Assistance Categories
$faMarkup = '';

if ($uS->IncomeRated) {

    $faTbl = new HTMLTable();
    $faRs = new Fa_CategoryRs();
    $faRows = EditRS::select($dbh, $faRs, array());

    $faTbl->addHeaderTr(HTMLTable::makeTh('Household Size') . HTMLTable::makeTh('A') . HTMLTable::makeTh('B') . HTMLTable::makeTh('C') . HTMLTable::makeTh('D'));
    foreach ($faRows as $r) {
        $faTbl->addBodyTr(HTMLTable::makeTd($r['HouseHoldSize'], array(
            'style' => 'text-align:center;'
        )) . HTMLTable::makeTd('< $' . HTMLInput::generateMarkup(number_format($r['Income_A']), array(
            'name' => 'faIa[' . $r['idFa_category'] . ']',
            'size' => '4'
        ))) . HTMLTable::makeTd('<= $' . HTMLInput::generateMarkup(number_format($r['Income_B']), array(
            'name' => 'faIb[' . $r['idFa_category'] . ']',
            'size' => '4'
        ))) . HTMLTable::makeTd('<= $' . HTMLInput::generateMarkup(number_format($r['Income_C']), array(
            'name' => 'faIc[' . $r['idFa_category'] . ']',
            'size' => '4'
        ))) . HTMLTable::makeTd('> ' . number_format($r['Income_C'])));
    }

    $faTbl->addBodyTr(HTMLTable::makeTd('Key:  "<=" means Income Amount is Less Than or Equal To', array(
        'colspan' => '5'
    )));

    // fa calculator
    $fin = new FinAssistance($dbh, 0);
    $calcTbl = $fin->createRateCalcMarkup();

    $fcTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Financial Assistance Rate Calculator', array(
        'style' => 'font-weight:bold;'
    )) . $calcTbl->generateMarkup(array(
        'style' => 'float:left;margin:7px;'
    )), array(
        'style' => 'clear:left;float:left;margin:7px;'
    ));

    $faMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Financial Assistance Breakpoints', array(
        'style' => 'font-weight:bold;'
    )) . $faTbl->generateMarkup(array(
        'style' => 'float:left;margin:7px;'
    )) . $fcTable, array(
        'style' => 'float:left;margin:7px;'
    ));
}

// Key deposit options
$keysTable = '';
$rateTableTabTitle = 'Room Rates';

if ($uS->KeyDeposit) {
    $kFees = readGenLookupsPDO($dbh, 'Key_Deposit_Code');
    $kTbl = new HTMLTable();
    $kTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('Amount')); // .HTMLTable::makeTh('Delete'));

    foreach ($kFees as $r) {
        $kTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array(
            'name' => 'kdesc[' . $r[0] . ']',
            'size' => '16'
        ))) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2], array(
            'name' => 'krate[' . $r[0] . ']',
            'size' => '6',
            'class' => 'number-only'
        ))));
    }

    $keysTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', $labels->getString('resourceBuilder', 'keyDepositLabel', 'Key Deposit') . ' Amounts', array(
        'style' => 'font-weight:bold;'
    )) . $kTbl->generateMarkup(array(
        'style' => 'margin:7px;'
    )), array(
        'style' => 'float:left;margin:7px;'
    ));

    $rateTableTabTitle .= ' & ' . $labels->getString('resourceBuilder', 'keyDepositLabel', 'Key Deposit') . 's';
}

// Payment Types
$payTypesTable = '';

if ($uS->RoomPriceModel != ItemPriceCode::None) {

	$payMethods = array();
	$stmtp = $dbh->query("select idPayment_method, Gl_Code from payment_method");
	while ($t = $stmtp->fetch(\PDO::FETCH_NUM)) {
		$payMethods[$t[0]] = $t[1];
	}
	$payMethods[''] = '';


    $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');
    $ptTbl = new HTMLTable();
    $ptTbl->addHeaderTr(HTMLTable::makeTh('Default') . HTMLTable::makeTh('Description') . HTMLTable::makeTh('GL Code'));

    foreach ($payTypes as $r) {

        $ptAttrs = array(
            'type' => 'radio',
            'name' => 'ptrbdefault'
        );

        if ($uS->DefaultPayType == $r[0]) {
            $ptAttrs['checked'] = 'checked';
        } else {
            unset($ptAttrs['checked']);
        }

        $ptTbl->addBodyTr(
        		HTMLTable::makeTd(($r[0] == PayType::Invoice ? '' : HTMLInput::generateMarkup($r[0], $ptAttrs)), array('style' => 'text-align:center;'))
        		. HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array('name' => 'ptdesc[' . $r[0] . ']', 'size' => '16')))
        		. HTMLTable::makeTd(HTMLInput::generateMarkup($payMethods[$r[2]], array('name' => 'ptGlCode[' . $r[2] . ']', 'size' => '19')))
        );
    }

    $payTypesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Pay Types', array(
        'style' => 'font-weight:bold;'
    )) . $ptTbl->generateMarkup(array(
        'style' => 'margin:7px;'
    )), array(
        'style' => 'float:left;margin:7px;'
    ));
}

// Hospitals and associations
$hospRs = new HospitalRS();
$hrows = EditRS::select($dbh, $hospRs, array(), '', array($hospRs->Status, $hospRs->Title));

$hospTypes = readGenLookupsPDO($dbh, 'Hospital_Type');

$constraints = new Constraints($dbh);
$hospConstraints = $constraints->getConstraintsByType(ConstraintType::Hospital);

$hTbl = new HTMLTable();
$hths = HTMLTable::makeTh('Id') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Type') . HTMLTable::makeTh('Description') . HTMLTable::makeTh('Color') . HTMLTable::makeTh('Text Color');
foreach ($hospConstraints as $c) {
    $hths .= HTMLTable::makeTh($c->getTitle());
}

$hths .= HTMLTable::makeTh('Last Updated') . HTMLTable::makeTh('Retire');
$hTbl->addHeaderTr($hths);

foreach ($hrows as $h) {

	if ($h['Title'] == '(None)' && $h['Type'] == 'a') {
		continue;
	}

    $myConsts = new ConstraintsHospital($dbh, $h['idHospital']);
    $hConst = $myConsts->getConstraints();

    $htds = HTMLTable::makeTd($h['idHospital']) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Title'], array(
        'name' => 'hTitle[' . $h['idHospital'] . ']'
    ))) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hospTypes, $h['Type'], FALSE), array(
        'name' => 'hType[' . $h['idHospital'] . ']'
    ))) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Description'], array(
        'name' => 'hDesc[' . $h['idHospital'] . ']',
        'size' => '25'
    ))) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Reservation_Style'], array(
        'name' => 'hColor[' . $h['idHospital'] . ']',
        'class' => 'color',
        'size' => '5'
    ))) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Stay_Style'], array(
        'name' => 'hText[' . $h['idHospital'] . ']',
        'class' => 'color',
        'size' => '5'
    )));

    foreach ($hConst as $a) {
        $cbAttrs = array(
            'name' => 'hpattr[' . $h['idHospital'] . '][' . $a['idConstraint'] . ']',
            'type' => 'checkbox'
        );
        if ($a['isActive'] == 1) {
            $cbAttrs['checked'] = 'checked';
        }
        $htds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $cbAttrs), array(
            'style' => 'text-align:center;'
        ));
    }

    $hdelAtr = array(
    		'name' => 'hdel[' . $h['idHospital'] . ']',
    		'type' => 'checkbox'
    );

    $rowAtr = array();

    if ($h['Status'] == 'r') {
    	$hdelAtr['checked'] = 'checked';
    	$rowAtr['style'] = 'background-color:lightgray;';
    }

    $htds .= HTMLTable::makeTd(date('M j, Y', strtotime($h['Last_Updated'] == '' ? $h['Timestamp'] : $h['Last_Updated'])))
    	. HTMLTable::makeTd(HTMLInput::generateMarkup('', $hdelAtr), array(
        'style' => 'text-align:center;'
    ));

    $hTbl->addBodyTr($htds, $rowAtr);
}

// new hospital
$hTbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'hTitle[0]'
))) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hospTypes, ''), array(
    'name' => 'hType[0]'
))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'hDesc[0]'
))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'hColor[0]',
    'size' => '5'
))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'hText[0]',
    'size' => '5'
))) . HTMLTable::makeTd('Create New', array(
    'colspan' => '4'
)));

$hospTable = $hTbl->generateMarkup();

// attributes
$attributes = new Attributes($dbh);
$arows = $attributes->getAttributes();
$attrTypes = $attributes->getAttributeTypes();

$aTbl = new HTMLTable();
$aTbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Type') . HTMLTable::makeTh('Category') . HTMLTable::makeTh('Last Updated') . HTMLTable::makeTh('Delete'));

foreach ($arows as $h) {

    $aTbl->addBodyTr(HTMLTable::makeTd($h['idAttribute']) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Title'], array(
        'name' => 'atTitle[' . $h['idAttribute'] . ']',
        'size' => '30'
    ))) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($attrTypes, $h['Type'], FALSE), array(
        'name' => 'atType[' . $h['idAttribute'] . ']'
    ))) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Category'], array(
        'name' => 'atCat[' . $h['idAttribute'] . ']'
    ))) . HTMLTable::makeTd($h['Last_Updated'] == '' ? '' : date('M j, Y', strtotime($h['Last_Updated']))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
        'name' => 'atdel[' . $h['idAttribute'] . ']',
        'type' => 'checkbox'
    ))));
}

// new attribute
$aTbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'atTitle[0]'
))) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($attrTypes, ''), array(
    'name' => 'atType[0]'
))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'atCat[0]'
))) . HTMLTable::makeTd('Create New', array(
    'colspan' => '2'
)));

$attrTable = $aTbl->generateMarkup();

// Constraints
$constraintTable = $constraints->createConstraintTable($dbh);

// Demographics Selection table
$tbl = getSelections($dbh, 'Demographics', 'm', $labels);
$demoSelections = $tbl->generateMarkup();

// Demographics category selectors
$stmt = $dbh->query("SELECT DISTINCT
    `g`.`Table_Name`, g2.Description
FROM
    `gen_lookups` `g`
        JOIN
    `gen_lookups` `g2` ON `g`.`Table_Name` = `g2`.`Code`
        AND `g2`.`Table_Name` = 'Demographics'
        AND `g2`.`Substitute` = 'y'
WHERE
    `g`.`Type` = 'd';");

$rows = $stmt->fetchAll(\PDO::FETCH_NUM);

$selDemos = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows, ''), array(
    'name' => 'selDemoLookup',
    'data-type' => 'd',
    'class' => 'hhk-selLookup'
));

$insuranceType = new InsuranceType();

// save insurance types
if(isset($_POST["insuranceTypes"])){
    $insuranceType = new InsuranceType();
    $insuranceType->save($dbh, $_POST);
}

$insuranceType->loadInsuranceTypes($dbh);
$selInsTypes = $insuranceType->generateSelector();

if(isset($_POST["insurances"])){
    $insurance = new Insurance();
    $return = $insurance->save($dbh, $_POST);
    if(is_array($return)){
        echo json_encode($return);
        exit;
    }
}

$lookupErrMsg = '';

// General Lookup categories
$stmt2 = $dbh->query("select distinct `Type`, `Table_Name` from gen_lookups where `Type` in ('h','u', 'ha', 'm');");
$rows2 = $stmt2->fetchAll(\PDO::FETCH_NUM);

$lkups = array();
$hasDiags = FALSE;
$hasLocs = FALSE;

foreach ($rows2 as $r) {

    if ($uS->RoomPriceModel == ItemPriceCode::None && ($r[1] == 'ExcessPays')) {
        continue;
    }

    if ($r[1] == DIAGNOSIS_TABLE_NAME) {
        $hasDiags = TRUE;
        $r[1] = $labels->getString('hospital', 'diagnosis', DIAGNOSIS_TABLE_NAME);
    } else if ($r[1] == LOCATION_TABLE_NAME) {
        $hasLocs = TRUE;
        $r[1] = $labels->getString('hospital', 'location', LOCATION_TABLE_NAME);
    }

    if ($r[1] != 'Demographics') {
        $lkups[] = $r;
    }
}

$selLookups = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($lkups, ''), array(
    'name' => 'sellkLookup',
    'class' => 'hhk-selLookup'
));

// Additional charges and discounts
// Lookup categories
$stmt3 = $dbh->query("select distinct `Type`, `Table_Name` from gen_lookups where `Type` = 'ca';");
$rows3 = $stmt3->fetchAll(\PDO::FETCH_NUM);
$hasAddnl = FALSE;
$hasDiscounts = FALSE;

foreach ($rows3 as $r) {
    if ($r[1] == 'Addnl_Charge') {
        $hasAddnl = TRUE;
    } else if ($r[1] == 'House_Discount') {
        $hasDiscounts = TRUE;
    }
}

$seldiscs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows3, ''), array(
    'name' => 'seldiscs',
    'class' => 'hhk-selLookup'
));

// Misc Codes (cancel codes, etc
$rows4 = [
    [
        "ReservStatus",
        "Reservation Cancel Codes"
    ]
];

$selmisc = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows4, ''), array(
    'name' => 'selmisc',
    'class' => 'hhk-selLookup'
));

// Items
$sitems = $dbh->query("Select  i.idItem, itm.Type_Id, i.Description, i.Gl_Code, i.Percentage, i.Last_Order_Id
    from item i left join item_type_map itm on itm.Item_Id = i.idItem");
$items = $sitems->fetchAll(\PDO::FETCH_ASSOC);

$itbl = new HTMLTable();

$ths = HTMLTable::makeTh('Description') . HTMLTable::makeTh('GL Code');
$colCounter = array();

// Make tax columns
foreach ($items as $d) {

    if ($d['Type_Id'] == ItemType::Tax && $d['Last_Order_Id'] == 0) {

        $ths .= HTMLTable::makeTh($d['Description'] . ' (' . TaxedItem::suppressTrailingZeros($d['Percentage']) . ')');
        $colCounter[] = $d['idItem'];
    }
}

// item-item table
$iistmt = $dbh->query("Select * from item_item");
$taxItemMap = $iistmt->fetchAll(\PDO::FETCH_ASSOC);

$itbl->addHeaderTr($ths);

foreach ($items as $d) {

    if ($d['Type_Id'] == 2) {
        continue;
    }

    $trs = '';

    if ($d['idItem'] == ItemId::AddnlCharge) {
        $trs .= HTMLTable::makeTd('(Additional Charges)') . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Gl_Code'], array(
            'name' => 'txtGlCode[' . $d['idItem'] . ']'
        )));
    } else {
        $trs .= HTMLTable::makeTd(HTMLInput::generateMarkup($d['Description'], array(
            'name' => 'txtItem[' . $d['idItem'] . ']'
        ))) . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Gl_Code'], array(
            'name' => 'txtGlCode[' . $d['idItem'] . ']'
        )));
    }

    foreach ($colCounter as $c) {

        $attrs = array(
            'type' => 'checkbox',
            'name' => 'cbtax[' . $d['idItem'] . '][' . $c . ']'
        );

        // Look for tax item connection
        foreach ($taxItemMap as $m) {
            if ($m['idItem'] == $d['idItem'] && $m['Item_Id'] == $c) {
                $attrs['checked'] = 'checked';
            }
        }

        $trs .= HTMLTable::makeTd(HTMLInput::generateMarkup($c, $attrs), array(
            'style' => 'text-align:center;'
        ));
    }

    $itbl->addBodyTr($trs);
}

$itemTable = $itbl->generateMarkup(array(
    'style' => 'float:left;'
));

// Taxes
$tstmt = $dbh->query("Select i.idItem, i.Description, i.Gl_Code, i.Percentage, i.Timeout_Days, i.First_Order_Id, i.Last_Order_Id
from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = " . ItemType::Tax . " order by i.Last_Order_Id");
$titems = $tstmt->fetchAll(\PDO::FETCH_ASSOC);
$hotTaxes = 0;
$lastId = 0;

$tiTbl = new HTMLTable();

foreach ($titems as $d) {

    $trArry = [];

    if ($d['Last_Order_Id'] == 0) {
        $hotTaxes ++;
    }

    if ($d['Last_Order_Id'] > 0) {
        $trArry['style'] = 'background-color:yellow;';
    }

    $lastId = $d['Last_Order_Id'];

    $tiTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($d['Description'], array(
        'name' => 'txttItem[' . $d['idItem'] . ']',
        'size' => '18'
    ))) . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Gl_Code'], array(
        'name' => 'txttGlCode[' . $d['idItem'] . ']',
        'size' => '18'
    ))) . HTMLTable::makeTd(HTMLInput::generateMarkup(number_format($d['Percentage'], 3), array(
        'name' => 'txttPercentage[' . $d['idItem'] . ']',
        'size' => '8'
    ))) . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Timeout_Days'], array(
        'name' => 'txttMaxDays[' . $d['idItem'] . ']',
        'size' => '5'
    ))) . HTMLTable::makeTd($d['First_Order_Id']) . HTMLTable::makeTd($d['Last_Order_Id']), $trArry);
}

// New Tax item
$tiTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'txttItem[0]',
    'placeholder' => 'New Tax',
    'size' => '18'
))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'txttGlCode[0]',
    'size' => '18'
))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'txttPercentage[0]',
    'size' => '8'
))) . HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
    'name' => 'txttMaxDays[0]',
    'size' => '5'
))));

$tiTbl->addHeaderTr(HTMLTable::makeTh($hotTaxes . ' Taxes' . (count($titems) > $hotTaxes ? ' and ' . (count($titems) - $hotTaxes) . ' Old taxes' : ''), array(
    'colspan' => '6'
)));
$tiTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('GL Code') . HTMLTable::makeTh('Percentage') . HTMLTable::makeTh('Max Days') . HTMLTable::makeTh('First Visit') . HTMLTable::makeTh('Last Visit'));

$taxTable = $tiTbl->generateMarkup(array(
    'style' => 'float:left;'
));

// Form Upload

$rteSelectForm = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups(readGenLookupsPDO($dbh, 'Form_Upload')), $formType, TRUE), array(
    'name' => 'selFormUpload'
));

// Form Builder
$forms = FormTemplate::listTemplates($dbh);
$formTbl = new HTMLTable();
$formTbl->addHeaderTr(HTMLTable::makeTh('Referral Forms', array('colspan'=>'4')));
$formTbl->addHeaderTr(HTMLTable::makeTh('Actions') . HTMLTable::makeTh('ID') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Status'));
if(count($forms) > 0){
    foreach($forms as $form){
        $formTbl->addBodyTr(HTMLTable::makeTd('<button class="editForm hhk-btn" data-docId="' . $form['idDocument'] . '">Edit</button>') . HTMLTable::makeTd($form['idDocument']) . HTMLTable::makeTd($form['Title']) . HTMLTable::makeTd($form['Status']));
    }
}


// Instantiate the alert message control
$alertMsg = new AlertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(AlertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("uh-oh");

$resultMessage = $alertMsg->createMarkup();



?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo FAVICON; ?>

	<script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
	<script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
	<script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
	<script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
	<script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
	<script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
	<script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
	<script type="text/javascript" src="../js/formBuilder/form-builder.min.js"></script>
	<script type="text/javascript" src="js/formBuilder.js"></script>
	<script type="text/javascript" src="<?php echo RESCBUILDER_JS; ?>"></script>
</head>
<body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
<?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
		<div style="float: left; margin-right: 20px; margin-top: 10px;">
			<h1><?php echo $wInit->pageHeading; ?>&nbsp; (Any changes require everybody to log out and log back in!)</h1>
		</div>
<?php echo $resultMessage ?>
            <div id="mainTabs"
			style="font-size: .9em; clear: left; display: none;"
			class="hhk-member-detail">
			<ul>
				<li><a href="#rescTable">Resources</a></li>
				<li><a href="#roomTable">Rooms</a></li>
				<li><a href="#rateTable"><?php echo $rateTableTabTitle; ?></a></li>
				<li><a href="#hospTable"><?php echo $hospitalTabTitle; ?></a></li>
				<?php if($uS->InsuranceChooser){ ?>
				<li><a href="#insTable">Insurance</a></li>
				<?php } ?>
				<li><a href="#demoTable">Demographics</a></li>
				<li><a href="#lkTable">Lookups</a></li>
				<li><a href="#itemTable">Items</a></li>
				<li><a href="#taxTable">Taxes</a></li>
				<li><a href="#formUpload">Forms Upload</a></li>
				<li><a href="#formBuilder">Form Builder</a></li>
				<li><a href="#attrTable">Attributes</a></li>
				<li><a href="#constr">Constraints</a></li>
			</ul>
			<div id="rescTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide"
				style="font-size: .9em;">
                    <?php echo $rescTable; ?>
                </div>
			<div id="roomTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide"
				style="font-size: .9em;">
                    <?php echo $roomTable; ?>
                </div>
            <?php if($uS->InsuranceChooser){ ?>
			<div id="insTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
				<div><?php echo $demoMessage; ?></div>
				<div style="float: left;">
					<h3>Insurance Types</h3>
					<?php echo $insuranceType->generateEditMarkup(); ?>
				</div>

				<div style="float: left; margin-left: 30px;">
					<h3>Insurance Companies</h3>
					<form id="formdemoCat">
						<table>
							<tr>
								<th>Insurance</th>
								<td><?php echo $selInsTypes; ?></td>
							</tr>
						</table>
						<div id="divdemoCat"></div>
					</form>
				</div>
			</div>
			<?php } ?>
			<div id="demoTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
				<div><?php echo $demoMessage; ?></div>
				<div style="float: left;">
					<h3>Demographic Categories</h3>
					<form id="formdemo">
						<div>
                                <?php echo $demoSelections; ?>
                            </div>
						<span style="margin: 10px; float: right;"><input type="button"
							id='btndemoSave' class="hhk-savedemoCat" data-type="h"
							value="Save" /></span>
					</form>
				</div>

				<div style="float: left; margin-left: 30px;">
					<h3>Demographics</h3>
					<form id="formdemoCat">
						<table>
							<tr>
								<th>Demographic</th>
								<td><?php echo $selDemos; ?></td>
							</tr>
						</table>
						<div id="divdemoCat"></div>
						<span style="margin: 10px; float: right;"><input type="button"
							id='btndemoSaveCat' class="hhk-saveLookup" data-type="d"
							value="Save" /></span>
					</form>
				</div>
			</div>
			<div id="lkTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide"
				style="font-size: .9em;">
				<div style="float: left;">
					<h3>General Lookups</h3>
					<form method="POST" action="ResourceBuilder.php" id="formlk">
						<table>
							<tr>
								<th>Category</th>
								<td><?php echo $selLookups; ?></td>
							</tr>
						</table>
						<div id="divlk" class="hhk-divLk"></div>
						<span style="margin: 10px; float: right;">
                                <?php if (!$hasDiags) { ?>
                                <input type="submit" name='btnAddDiags'
							id="btnAddDiags" value="Add Diagnosis" />
                                <?php } if (!$hasLocs) { ?>
                                <input type="submit" id='btnAddLocs'
							name="btnAddLocs" value="Add Location" />
                                <?php } ?>
                                <input type="button" id='btnlkSave'
							class="hhk-saveLookup" data-type="h" value="Save" />
						</span>
					</form>
				</div>
				<div style="float: left; margin-left: 30px;">
					<h3>Discounts &amp; Additional Charges</h3>
					<form method="POST" action="ResourceBuilder.php" id="formdisc">
						<table>
							<tr>
								<th>Category</th>
								<td><?php echo $seldiscs; ?></td>
							</tr>
						</table>
						<div id="divdisc" class="hhk-divLk"></div>
						<span style="margin: 10px; float: right;">
                                <?php if (!$hasDiscounts) { ?>
                                <input type="submit"
							name='btnHouseDiscs' id="btnHouseDiscs" value="Add Discounts" />
                                <?php } if (!$hasAddnl) { ?>
                                <input type="submit" id='btnAddnlCharge'
							name="btnAddnlCharge" value="Add Additional Charges" />
                                <?php } ?>
                                <input type="button" id='btndiscSave'
							class="hhk-saveLookup" data-type="ha" value="Save" />
						</span>
					</form>
				</div>
				<div style="float: left; margin-left: 30px;">
					<h3>Miscelaneous Lookups</h3>
					<form method="POST" action="ResourceBuilder.php" id="formmisc">
						<table>
							<tr>
								<th>Category</th>
								<td><?php echo $selmisc; ?></td>
							</tr>
						</table>
						<div id="divmisc" class="hhk-divLk"></div>
						<span style="margin: 10px; float: right;"> <input type="button"
							id='btnmiscSave' class="hhk-saveLookup" data-type="ha"
							value="Save" />
						</span>
					</form>
				</div>

			</div>
			<div id="rateTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
				<p style="padding: 3px; background-color: #fff7db;">Make changes
					directly into the text boxes below and press 'Save'.</p>
                    <?php echo $rateTableErrorMessage; ?>
                    <form method="POST" action="ResourceBuilder.php"
					name="form1">
					<div style="clear: left; float: left;"><?php echo $pricingModelTable; ?></div>
<?php echo $visitFeesTable . $keysTable . $payTypesTable . $feesTable . $faMarkup; ?>
                        <div style="clear: both"></div>
					<span style="margin: 10px; float: right;"><input type="submit"
						id='btnkfSave' name="btnkfSave" value="Save" /></span>
				</form>
			</div>
			<div id="hospTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
				<form method="POST" action="ResourceBuilder.php" name="formh">
<?php echo $hospTable; ?>
                        <div style="clear: both"></div>
					<span style="margin: 10px; float: right;"><input type="submit"
						id='btnhSave' name="btnhSave" value="Save" /></span>
				</form>
			</div>
			<div id="formUpload" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
				<p>Select the form to upload: <?php echo $rteSelectForm; ?>
                     <span id="spnFrmLoading" style="font-style: italic; display: none;">Loading...</span>
                     <input type="button" id="btnNewForm" value="New Form" style="display:none;" />
				</p>
				<p id="rteMsg" style="float: left;" class="ui-state-highlight"><?php echo $rteMsg; ?></p>
				<div id="divUploadForm" style="margin-top: 1em;"></div>
			</div>
			<div id="formBuilder" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
			</div>
			<div id="itemTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
				<form method="POST" action="ResourceBuilder.php" name="formitem">
<?php echo $itemTable; ?>
                        <div style="clear: both"></div>
					<span style="margin: 10px; float: right;"><input type="submit"
						id='btnItemSave' name="btnItemSave" value="Save" /></span>
				</form>
			</div>
			<div id="taxTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                    <?php echo $itemMessage; ?>
                    <form method="POST" action="ResourceBuilder.php"
					name="formtax">
<?php echo $taxTable; ?>
                        <div style="clear: both"></div>
					<span style="margin: 10px; float: right;"><input type="submit"
						id='btnItemSave' name="btnTaxSave" value="Save" /></span>
				</form>
			</div>
			<div id="attrTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
				<form method="POST" action="ResourceBuilder.php" name="format">
<?php echo $attrTable; ?>
                        <div style="clear: both"></div>
					<span style="margin: 10px; float: right;"><input type="submit"
						id='btnAttrSave' name="btnAttrSave" value="Save" /></span>
				</form>
			</div>
			<div id="constr" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                        <?php echo $constraintTable; ?>
                </div>
		</div>
		<div id="divNewForm" class="hhk-tdbox hhk-visitdialog"
			style="font-size: .9em; display: none;">
			<form method="POST" action="ResourceBuilder.php" id="formFormNew"
				name="formFormNew">
				<table>
					<tr>
						<th colspan="2"><span id="spanFrmTypeTitle"></span></th>
					</tr>
					<tr>
						<th>Language or other title</th>
						<td><input id="txtformLang" name="txtformLang" type="text"
							value='' /></td>
					</tr>
				</table>
				<input type="hidden" id="hdnFormType" name="hdnFormType" />
			</form>
		</div>
		<div id="statEvents" class="hhk-tdbox hhk-visitdialog"
			style="font-size: .9em;"></div>
		<input type="hidden" id='fixedRate' value="<?php RoomRateCategories::Fixed_Rate_Category;?>" />
	</div>
	<!-- div id="contentDiv"-->
	<script type="text/javascript">

		$(document).ready(function(){
			$('#formBuilder').hhkFormBuilder({
				labels: {
					hospital: "<?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>",
					guest: "<?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?>",
					patient: "<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>",
					diagnosis: "<?php echo $labels->getString('hospital', 'diagnosis', 'Diagnosis'); ?>",
					location: "<?php echo $labels->getString('hospital', 'location', 'Unit'); ?>",
					referralAgent: "<?php echo $labels->getString('hospital', 'referralAgent', 'Referral Agent'); ?>",
					treatmentStart: "<?php echo $labels->getString('hospital', 'treatmentStart', 'Treatement Start'); ?>",
					treatmentEnd: "<?php echo $labels->getString('hospital', 'treatmentEnd', 'Treatment End'); ?>",
					mrn: "<?php echo $labels->getString('hospital', 'MRN', 'MRN'); ?>"
				},
				fieldOptions: {
					county: "<?php echo $uS->county; ?>",
					doctor: "<?php echo $uS->Doctor; ?>",
					referralAgent: "<?php echo $uS->ReferralAgent; ?>"
				},
				demogs: <?php echo json_encode(readGenLookupsPDO($dbh, 'Demographics')); ?>
			});
		});

	</script>
</body>
</html>