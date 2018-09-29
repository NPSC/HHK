<?php
/**
 * ResourceBuilder.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'History.php');
require (CLASSES . 'CreateMarkupFromDB.php');

require (DB_TABLES . 'GenLookupsRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'AttributeRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'ItemRS.php');

require (CLASSES . 'TableLog.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Room.php');
require (CLASSES . 'HouseLog.php');
require (CLASSES . 'Purchase/RoomRate.php');
require (CLASSES . 'FinAssistance.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

function saveArchive(\PDO $dbh, $desc, $subt, $tblName) {

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
            $rows = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

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
                $glRs->Type->setNewVal(GlTypeCodes::Archive);

                $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));
                $logTextu = HouseLog::getUpdateText($glRs, $tblName . $code);
                HouseLog::logGenLookups($dbh, $tblName, $code, $logTextu, 'update', $uS->username);

            } else {

                // update
                if ($newDesc != '') {
                    $glRs->Description->setNewVal($newDesc);
                }

                $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

                if ($ctr > 0) {
                    $logText = HouseLog::getUpdateText($glRs, $tblName . $code);
                    HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'update', $uS->username);
                }

            }
        }
    }

    return $defaultCode;
}

function getSelections(\PDO $dbh, $tableName, $type) {

    $uS = Session::getInstance();

    // Generate selectors.
    $diags = readGenLookupsPDO($dbh, $tableName, 'Order');

    $tbl = new HTMLTable();

    $hdrTr = HTMLTable::makeTh(count($diags) . ' Entries') . HTMLTable::makeTh('Order')
            . ($type == GlTypeCodes::CA ? HTMLTable::makeTh('Amount') : '')
            . ($type == GlTypeCodes::HA ? HTMLTable::makeTh('Days') : '')
            . ($type == GlTypeCodes::Demographics && $uS->GuestNameColor == $tableName ? HTMLTable::makeTh('Colors (font, bkgrnd)') : '')
            . ($type == GlTypeCodes::U ? '' : $type == GlTypeCodes::m ? HTMLTable::makeTh('Use') : HTMLTable::makeTh('Delete') . HTMLTable::makeTh('Replace With'));



    $tbl->addHeaderTr($hdrTr);

    foreach ($diags as $d) {

        // Remove this item from the replacement entries.
        $tDiags = removeOptionGroups($diags);
        unset($tDiags[$d[0]]);

        $cbDelMU = '';

        if ($type == GlTypeCodes::m) {

            $ary = array('name' => 'cbDiagDel[' . $d[0] . ']', 'type' => 'checkbox', 'class' => 'hhkdiagdelcb');

            if (strtolower($d[2]) == 'y') {
                $ary['checked'] = 'checked';
            }

            $cbDelMU = HTMLTable::makeTd(HTMLInput::generateMarkup('', $ary));

        } else if ($type == GlTypeCodes::Demographics && $d[0] == 'z') {

            $cbDelMU = HTMLTable::makeTd('');

        } else if ($type != GlTypeCodes::U) {

            $cbDelMU = HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'cbDiagDel[' . $d[0] . ']', 'type' => 'checkbox', 'class' => 'hhkdiagdelcb', 'data-did' => 'selDiagDel[' . $d[0] . ']')));
        }

        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($d[1], array('name' => 'txtDiag[' . $d[0] . ']')))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($d[4], array('name' => 'txtDOrder[' . $d[0] . ']', 'size'=>'3')))
                . ($type == GlTypeCodes::HA || $type == GlTypeCodes::CA || ($type == GlTypeCodes::Demographics && $uS->GuestNameColor == $tableName) ? HTMLTable::makeTd(HTMLInput::generateMarkup($d[2], array('size' => '10', 'style' => 'text-align:right;', 'name' => 'txtDiagAmt[' . $d[0] . ']'))) : '')
                . $cbDelMU
                . ($type != GlTypeCodes::m && $type != GlTypeCodes::U ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($tDiags, ''), array('name' => 'selDiagDel[' . $d[0] . ']'))) : '')
        );
    }

    // New Entry Markup?
    if ($type != GlTypeCodes::U && $type != GlTypeCodes::m) {
        // new entry row
        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtDiag[0]')))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtDOrder[0]', 'size'=>'3')))
                . HTMLTable::makeTd('New', array('colspan' => 2))
                . ($type == GlTypeCodes::HA || $type == GlTypeCodes::CA ? HTMLTable::makeTd(HTMLInput::generateMarkup('', array('size' => '7', 'style' => 'text-align:right;', 'name' => 'txtDiagAmt[0]'))) : '')
        );
    }

    return $tbl;

}


$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;

$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();

// Kick out 'Guest' Users
if ($uS->rolecode > WebRole::WebUser) {

    exit("Unauthorized - " . HTMLContainer::generateMarkup('a', 'Continue', array('href'=>'index.php')));
}


$tabIndex = 0;
$feFileSelection = '';
$rteMsg = '';

// Get labels
$labels = new Config_Lite(LABEL_FILE);

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

    $cmd = '';
    $type = '';
    $order = 0;

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
                $rep = function($dbh, $newId, $oldId, $tableName) {
                    return $dbh->exec("update name set `$tableName` = '$newId' where `$tableName` = '$oldId';");
                };
            } else {
                $rep = function($dbh, $newId, $oldId, $tableName) {
                    return $dbh->exec("update name_demog set `$tableName` = '$newId' where `$tableName` = '$oldId';");
                };
            }

        } else {
            switch ($tableName) {

                case 'Patient_Rel_Type':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update name_guest set Relationship_Code = '$newId' where Relationship_Code = '$oldId';");
                    };

                    $verify = "Select n.Relationship_Code from name_guest n left join gen_lookups g on n.Relationship_Code = g.Code Where g.Table_Name = 'Patient_Rel_Type' and g.Code is null;";
                    break;

                case 'Diagnosis':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update hospital_stay set Diagnosis = '$newId' where Diagnosis = '$oldId';");
                    };

                    $verify = "select hs.Diagnosis from hospital_stay hs left join gen_lookups g on hs.Diagnosis = g.Code where g.Table_Name = 'Diagnosis' and g.Code is null;";
                    break;

                case 'Location':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update hospital_stay set Location = '$newId' where Location = '$oldId';");
                    };
                    break;

                case 'OSS_Codes':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update resource_use set OSS_Code = '$newId' where OSS_Code = '$oldId';");
                    };
                    break;

                case 'Utilization_Category':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update resource set Utilization_Category = '$newId' where Utilization_Category = '$oldId';");
                    };
                    break;

                case 'Ins_Type':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update insurance set `Type` = '$newId' where `Type` = '$oldId';");
                    };
                    break;

                case 'Room_Cleaning_Days':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update room set `Cleaning_Cycle_Code` = '$newId' where `Cleaning_Cycle_Code` = '$oldId';");
                    };
                    break;

                case 'NoReturnReason':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update name_demog set `No_Return` = '$newId' where `No_Return` = '$oldId';");
                    };
                    break;
            }
        }

        $amounts = array();
        if (isset($_POST['txtDiagAmt'])) {

            foreach ($_POST['txtDiagAmt'] as $k => $a) {
                if (is_numeric($a)) {
                    $a = abs($a);
                }

                $amounts[$k] = $a;
            }
        }

        $codeArray = filter_var_array($_POST['txtDiag'], FILTER_SANITIZE_STRING);
        $orderNums = filter_var_array($_POST['txtDOrder'], FILTER_SANITIZE_NUMBER_INT);

        if ($type === GlTypeCodes::m) {

            foreach ($codeArray as $c => $v) {

                $gluRs = new GenLookupsRS();
                $gluRs->Table_Name->setStoredVal($tableName);
                $gluRs->Code->setStoredVal($c);

                $rw = EditRS::select($dbh, $gluRs, array($gluRs->Table_Name, $gluRs->Code));

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

                    $upCtr = EditRS::update($dbh, $gluRs, array($gluRs->Table_Name, $gluRs->Code));

                    if ($upCtr > 0) {

                        $logText = HouseLog::getUpdateText($gluRs);
                        HouseLog::logGenLookups($dbh, $tableName, $c, $logText, "update", $uS->username);
                    }
                }
            }
        } else {
            replaceGenLk($dbh, $tableName, $codeArray, $amounts, $orderNums, (isset($_POST['cbDiagDel']) ? $_POST['cbDiagDel'] : NULL), $rep, (isset($_POST['cbDiagDel']) ? $_POST['selDiagDel'] : array()));
        }
    }


    // Generate selectors.
    $tbl = getSelections($dbh, $tableName, $type);

    echo($tbl->generateMarkup());
    exit();
}

if (isset($_POST['btnkfSave'])) {

    $tabIndex = 2;


    // room pricing
    $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
    $newDefault = $priceModel->saveEditMarkup($dbh, $_POST, $uS->username);

    if ($newDefault != '') {
        SysConfig::saveKeyValue($dbh, $uS->sconf, 'RoomRateDefault', $newDefault);
        $uS->RoomRateDefault = $newDefault;
    }

    //Static room settings.
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
            $glRs->Substitute->setNewVal($dAmt);

            EditRS::insert($dbh, $glRs);

            $logText = HouseLog::getInsertText($glRs);
            HouseLog::logGenLookups($dbh, 'Static_Room_Rate', $newCode, $logText, 'insert', $uS->username);
        }

        unset($_POST['srrDesc'][0]);
    }

    //saveArchive($dbh, $_POST['srrDesc'], $_POST['srrAmt'], 'Static_Room_Rate');
    saveGenLk($dbh, 'Static_Room_Rate', $_POST['srrDesc'], $_POST['srrAmt'], NULL);



    // Key Deposit
    if (isset($_POST['kdesc'])) {

        saveGenLk($dbh, 'Key_Deposit_Code', $_POST['kdesc'], $_POST['krate'], NULL);

        foreach ($_POST['krate'] as $k => $p) {

            if ($p > 0) {
                // update item
                $itemRs = new ItemRS();
                $itemRs->idItem->setStoredVal(ItemId::KeyDeposit);
                $rows = EditRS::select($dbh, $itemRs, array($itemRs->idItem));

                if (count($rows) == 1) {
                    $itemRs->Description->setNewVal(filter_var($_POST['kdesc'][$k], FILTER_SANITIZE_STRING));
                    EditRS::update($dbh, $itemRs, array($itemRs->idItem));
                }
            }
        }
    }

    // Visit Fee
    if (isset($_POST['vfdesc'])) {

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

                    SysConfig::saveKeyValue($dbh, $uS->sconf, 'DefaultVisitFee', $v[0]);
                    $uS->DefaultVisitFee = $v[0];
                    break;
                }
            }
        }

        // Update the item description.
        foreach ($_POST['vfrate'] as $k => $p) {

            if ($p > 0) {
                // update item
                $itemRs = new ItemRS();
                $itemRs->idItem->setStoredVal(ItemId::VisitFee);
                $rows = EditRS::select($dbh, $itemRs, array($itemRs->idItem));

                if (count($rows) == 1) {
                    $itemRs->Description->setNewVal(filter_var($_POST['vfdesc'][$k], FILTER_SANITIZE_STRING));
                    EditRS::update($dbh, $itemRs, array($itemRs->idItem));
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

                SysConfig::saveKeyValue($dbh, $uS->sconf, 'DefaultPayType', $v[0]);
                $uS->DefaultPayType = $v[0];
                break;
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

        $faRs = new Fa_CategoryRs();
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

            EditRS::update($dbh, $faRs, array($faRs->idFa_category));
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

        $hospRs = new Hospital_RS();
        $hospRs->idHospital->setStoredVal($idHosp);

        // Delete?
        if (isset($_POST['hdel'][$idHosp])) {
            EditRS::delete($dbh, $hospRs, array($hospRs->idHospital));

            // Delete any attribute entries
            $query = "delete from attribute_entity where idEntity = :id and Type = :tpe";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':id' => $idHosp, ':tpe' => Attribute_Types::Hospital));
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
            $rows = EditRS::select($dbh, $hospRs, array($hospRs->idHospital));
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
            //update
            EditRS::update($dbh, $hospRs, array($hospRs->idHospital));

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
            //insert
            EditRS::insert($dbh, $hospRs);
        }
    }
}

if (isset($_POST['btnAttrSave'])) {

    $tabIndex = 7;
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

            EditRS::delete($dbh, $atRs, array($atRs->idAttribute));

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
            $rows = EditRS::select($dbh, $atRs, array($atRs->idAttribute));
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
            //update
            EditRS::update($dbh, $atRs, array($atRs->idAttribute));
        } else {
            //insert
            EditRS::insert($dbh, $atRs);
        }
    }
}

if (isset($_POST['btnItemSave'])) {

    $tabIndex = 6;

    $sitems = $dbh->query("Select idItem, Description from item;");
    $items = $sitems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $i) {

        if ($i['idItem'] == ItemId::AddnlCharge) {
            continue;
        }

        if (isset($_POST['txtItem'][$i['idItem']])) {

            $desc = filter_var($_POST['txtItem'][$i['idItem']], FILTER_SANITIZE_STRING);

            if ($desc != '' && $desc != $i['Description']) {

                $dbh->exec("update `item` set `Description` = '$desc' where `idItem` = " . $i['idItem']);
            }
        }
    }
}

// Get selected Editor Form text
//if (isset($_POST['formEdit'])) {
//
//    $tabIndex = 6;
//
//    $cmd = filter_input(INPUT_POST, 'formEdit', FILTER_SANITIZE_STRING);
//
//    switch ($cmd) {
//
//        case 'getform':
//
//            $fn = filter_input(INPUT_POST, 'fn', FILTER_SANITIZE_STRING);
//
//            if (!$fn || $fn == '') {
//                exit(json_encode(array('warning'=>'The Form name is blank.')));
//            }
//
//            $files = readGenLookupsPDO($dbh, 'Editable_Forms');
//
//            if (isset($files[$fn])) {
//
//                if (file_exists($fn)) {
//                    exit(json_encode(array('title'=>$files[$fn][1], 'tx'=>file_get_contents($fn), 'jsn'=>file_get_contents($files[$fn][2]))));
//                } else {
//                    exit(json_encode(array('warning'=>'This Form is missing from the server library.')));
//                }
//
//            } else {
//                exit(json_encode(array('warning'=>'The Form name is not on the acceptable list.')));
//            }
//
//            break;
//
//        case 'saveform':
//
//            $formEditorText = urldecode(filter_input(INPUT_POST, 'mu', FILTER_SANITIZE_STRING));
//
//            $feFileSelection = filter_input(INPUT_POST, 'fn', FILTER_SANITIZE_STRING);
//
//            $files = readGenLookupsPDO($dbh, 'Editable_Forms');
//
//            if ($rteFileSelection == '') {
//
//                $rteMsg = 'Nothing saved. Select a Form to edit.';
//
//            } else if (isset($files[$rteFileSelection]) === FALSE) {
//
//                $rteMsg = 'Nothing saved. Form name not accepted. ';
//
//            } else if (file_exists($rteFileSelection) === FALSE) {
//
//                $rteMsg = 'Nothing saved. Form does not exist. ';
//
//            } else if ($formEditorText == '') {
//
//                $rteMsg = 'Nothing saved. Form text is blank.  ';
//
//            } else {
//
//                $rtn = file_put_contents($rteFileSelection, $formEditorText);
//
//                if ($rtn > 0) {
//                    $rteMsg = "Success - $rtn bytes saved.";
//
//                } else {
//                    $rteMsg = "Form Not Saved.";
//                }
//            }
//
//            exit(json_encode(array('response'=>$rteMsg)));
//
//            break;
//    }
//
//    exit(json_encode(array('warning'=>'Unspecified')));
//}
//

//
// Generate tab content
//
// hospital tab title
$hospitalTabTitle = $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospitals & Associations');

// Room pricing model
$rPrices = readGenLookupsPDO($dbh, 'Price_Model');
$kTbl = new HTMLTable();
$kTbl->addHeaderTr(HTMLTable::makeTh('Selected Model'));

$kTbl->addBodyTr(HTMLTable::makeTd($rPrices[$uS->RoomPriceModel][1]));

$pricingModelTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Pricing Model', array('style' => 'font-weight:bold;')) . $kTbl->generateMarkup(array('style' => 'margin:7px;')), array('style' => 'margin:7px;'));

$rescTable = ResourceView::resourceTable($dbh);
$roomTable = ResourceView::roomTable($dbh, $uS->KeyDeposit);


// Room Pricing
$priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
$fTbl = $priceModel->getEditMarkup($dbh, $uS->RoomRateDefault);

// Static room rate
$rp = readGenLookupsPDO($dbh, 'Static_Room_Rate', 'Description');

$sTbl = new HTMLTable();
$sTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('Amount'));

foreach ($rp as $r) {
    $sTbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array('name' => 'srrDesc[' . $r[0] . ']', 'size' => '16')))
            . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2], array('name' => 'srrAmt[' . $r[0] . ']', 'size' => '6', 'class' => 'number-only')))
    );
}

$sTbl->addBodyTr(HTMLTable::makeTd('New static room rate:', array('colspan' => '3')));
$sTbl->addBodyTr(
        HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'srrDesc[0]', 'size' => '16')))
        . HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', array('name' => 'srrAmt[0]', 'size' => '6', 'class' => 'number-only')))
);


$sMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Static Room Rate', array('style' => 'font-weight:bold;')) .
                $sTbl->generateMarkup(array('style' => 'float:left;margin:7px;')), array('style' => 'clear:left;float:left;margin:7px;'));


// Rate Calculator
$rcMarkup = '';

if ($priceModel->hasRateCalculator()) {

    $tbl = new HTMLTable();

    $tbl->addHeaderTr(
            HTMLTable::makeTh('Room Rate')
            . HTMLTable::makeTh('Credit')
            . ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily ? HTMLTable::makeTh('Guest Nights') : HTMLTable::makeTh('Nights'))
            . HTMLTable::makeTh('Total'));

    $attrFixed = array('id' => 'spnRateTB', 'class' => 'hhk-fxFixed', 'style' => 'margin-left:.5em;display:none;');
    $rateCategories = RoomRate::makeSelectorOptions($priceModel);


    $tbl->addBodyTr(
            HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), ''), array('name' => 'selRateCategory'))
                    . HTMLContainer::generateMarkup('span', '$' . HTMLInput::generateMarkup('', array('name' => 'txtFixedRate', 'size' => '4')), $attrFixed))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtCredit', 'size' => '4')), array('style' => 'text-align:center;'))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('1', array('name' => 'txtNites', 'size' => '4')), array('style' => 'text-align:center;'))
            . HTMLTable::makeTd('$' . HTMLContainer::generateMarkup('span', '0', array('name' => 'spnAmount')), array('style' => 'text-align:center;'))
    );

    $rcMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Rate Calculator', array('style' => 'font-weight:bold;')) .
                    $tbl->generateMarkup(array('style' => 'float:left;margin:7px;')), array('style' => 'clear:left;float:left;margin:7px;'));
}

// Wrap rate table and rate calculator
$feesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Rates', array('style' => 'font-weight:bold;'))
                . HTMLContainer::generateMarkup('div', $fTbl->generateMarkup(array('style' => 'margin:7px;')), array('style'=>'max-height:310px; overflow-y:scroll;'))
                . $rcMarkup
                . $sMarkup
                , array('style' => 'clear:left;float:left;margin:7px;'));


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

        $ptAttrs = array('type' => 'radio', 'name' => 'vfrbdefault');

        if ($uS->DefaultVisitFee == $r[0]) {
            $ptAttrs['checked'] = 'checked';
        } else {
            unset($ptAttrs['checked']);
        }

        $kTbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($r[0], $ptAttrs), array('style' => 'text-align:center;'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array('name' => 'vfdesc[' . $r[0] . ']', 'size' => '16')))
                . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2], array('name' => 'vfrate[' . $r[0] . ']', 'size' => '6', 'class' => 'number-only')))
        );
    }

    $visitFeesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee') . ' Amount', array('style' => 'font-weight:bold;')) . $kTbl->generateMarkup(array('style' => 'margin:7px;')), array('style' => 'float:left;margin:7px;'));
}


// Financial Assistance Categories
$faMarkup = '';

if ($uS->IncomeRated) {

    $faTbl = new HTMLTable();
    $faRs = new Fa_CategoryRs();
    $faRows = EditRS::select($dbh, $faRs, array());

    $faTbl->addHeaderTr(HTMLTable::makeTh('Household Size') . HTMLTable::makeTh('A') . HTMLTable::makeTh('B') . HTMLTable::makeTh('C') . HTMLTable::makeTh('D'));
    foreach ($faRows as $r) {
        $faTbl->addBodyTr(
                HTMLTable::makeTd($r['HouseHoldSize'], array('style' => 'text-align:center;'))
                . HTMLTable::makeTd('< $' . HTMLInput::generateMarkup(number_format($r['Income_A']), array('name' => 'faIa[' . $r['idFa_category'] . ']', 'size' => '4')))
                . HTMLTable::makeTd('<= $' . HTMLInput::generateMarkup(number_format($r['Income_B']), array('name' => 'faIb[' . $r['idFa_category'] . ']', 'size' => '4')))
                . HTMLTable::makeTd('<= $' . HTMLInput::generateMarkup(number_format($r['Income_C']), array('name' => 'faIc[' . $r['idFa_category'] . ']', 'size' => '4')))
                . HTMLTable::makeTd('> ' . number_format($r['Income_C']))
        );
    }

    $faTbl->addBodyTr(HTMLTable::makeTd('Key:  "<=" means Income Amount is Less Than or Equal To', array('colspan' => '5')));

    // fa calculator
    $fin = new FinAssistance($dbh, 0);
    $calcTbl = $fin->createRateCalcMarkup();

    $fcTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Financial Assistance Rate Calculator', array('style' => 'font-weight:bold;')) .
                    $calcTbl->generateMarkup(array('style' => 'float:left;margin:7px;')), array('style' => 'clear:left;float:left;margin:7px;'));

    $faMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Financial Assistance Breakpoints', array('style' => 'font-weight:bold;'))
                    . $faTbl->generateMarkup(array('style' => 'float:left;margin:7px;'))
                    . $fcTable
                    , array('style' => 'float:left;margin:7px;'));
}

// Key deposit options
$keysTable = '';
$rateTableTabTitle = 'Room Rates';

if ($uS->KeyDeposit) {
    $kFees = readGenLookupsPDO($dbh, 'Key_Deposit_Code');
    $kTbl = new HTMLTable();
    $kTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('Amount'));  //.HTMLTable::makeTh('Delete'));

    foreach ($kFees as $r) {
        $kTbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array('name' => 'kdesc[' . $r[0] . ']', 'size' => '16')))
                . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2], array('name' => 'krate[' . $r[0] . ']', 'size' => '6', 'class' => 'number-only')))
        );
    }

    $keysTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', $labels->getString('resourceBuilder', 'keyDepositLabel', 'Key Deposit') . ' Amounts', array('style' => 'font-weight:bold;'))
                    . $kTbl->generateMarkup(array('style' => 'margin:7px;'))
                    , array('style' => 'float:left;margin:7px;'));

    $rateTableTabTitle .= ' & ' . $labels->getString('resourceBuilder', 'keyDepositLabel', 'Key Deposit') . 's';
}


// Payment Types
$payTypesTable = '';

if ($uS->RoomPriceModel != ItemPriceCode::None) {

    $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');
    $ptTbl = new HTMLTable();
    $ptTbl->addHeaderTr(HTMLTable::makeTh('Default') . HTMLTable::makeTh('Description'));

    foreach ($payTypes as $r) {

        $ptAttrs = array('type' => 'radio', 'name' => 'ptrbdefault');

        if ($uS->DefaultPayType == $r[0]) {
            $ptAttrs['checked'] = 'checked';
        } else {
            unset($ptAttrs['checked']);
        }

        $ptTbl->addBodyTr(
                HTMLTable::makeTd(($r[0] == PayType::Invoice ? '' : HTMLInput::generateMarkup($r[0], $ptAttrs)), array('style' => 'text-align:center;'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], array('name' => 'ptdesc[' . $r[0] . ']', 'size' => '16')))
        );
    }

    $payTypesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Pay Types', array('style' => 'font-weight:bold;')) . $ptTbl->generateMarkup(array('style' => 'margin:7px;')), array('style' => 'float:left;margin:7px;'));
}

// Hospitals and associations
$hospRs = new Hospital_RS();
$hrows = EditRS::select($dbh, $hospRs, array());

$hospTypes = readGenLookupsPDO($dbh, 'Hospital_Type');


$constraints = new Constraints($dbh);
$hospConstraints = $constraints->getConstraintsByType(Constraint_Type::Hospital);


$hTbl = new HTMLTable();
$hths = HTMLTable::makeTh('Id') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Type') . HTMLTable::makeTh('Description') . HTMLTable::makeTh('Color') . HTMLTable::makeTh('Text Color');
foreach ($hospConstraints as $c) {
    $hths .= HTMLTable::makeTh($c->getTitle());
}

$hths .= HTMLTable::makeTh('Last Updated') . HTMLTable::makeTh('Delete');
$hTbl->addHeaderTr($hths);

foreach ($hrows as $h) {

    $hattr = array('name' => 'hstat[' . $h['idHospital'] . ']', 'type' => 'checkbox');
    if ($h['Status'] == 'a') {
        $hattr['checked'] = 'checked';
    }

    $myConsts = new ConstraintsHospital($dbh, $h['idHospital']);
    $hConst = $myConsts->getConstraints();


    $htds = HTMLTable::makeTd($h['idHospital'])
            . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Title'], array('name' => 'hTitle[' . $h['idHospital'] . ']')))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hospTypes, $h['Type'], FALSE), array('name' => 'hType[' . $h['idHospital'] . ']')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Description'], array('name' => 'hDesc[' . $h['idHospital'] . ']', 'size' => '25')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Reservation_Style'], array('name' => 'hColor[' . $h['idHospital'] . ']', 'class' => 'color', 'size' => '5')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Stay_Style'], array('name' => 'hText[' . $h['idHospital'] . ']', 'class' => 'color', 'size' => '5')));

    foreach ($hConst as $a) {
        $cbAttrs = array('name' => 'hpattr[' . $h['idHospital'] . '][' . $a['idConstraint'] . ']', 'type' => 'checkbox');
        if ($a['isActive'] == 1) {
            $cbAttrs['checked'] = 'checked';
        }
        $htds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $cbAttrs), array('style' => 'text-align:center;'));
    }


    $htds .= HTMLTable::makeTd(date('M j, Y', strtotime($h['Last_Updated'] == '' ? $h['Timestamp'] : $h['Last_Updated'])))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'hdel[' . $h['idHospital'] . ']', 'type' => 'checkbox')), array('style' => 'text-align:center;'));

    $hTbl->addBodyTr($htds);
}

// new hospital
$hTbl->addBodyTr(HTMLTable::makeTd('')
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'hTitle[0]')))
        . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hospTypes, ''), array('name' => 'hType[0]')))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'hDesc[0]')))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'hColor[0]', 'size' => '5')))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'hText[0]', 'size' => '5')))
        . HTMLTable::makeTd('Create New', array('colspan' => '4'))
);

$hospTable = $hTbl->generateMarkup();



// attributes
$attributes = new Attributes($dbh);
$arows = $attributes->getAttributes();
$attrTypes = $attributes->getAttributeTypes();

$aTbl = new HTMLTable();
$aTbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Type') . HTMLTable::makeTh('Category') . HTMLTable::makeTh('Last Updated') . HTMLTable::makeTh('Delete'));

foreach ($arows as $h) {

    $aTbl->addBodyTr(HTMLTable::makeTd($h['idAttribute'])
            . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Title'], array('name' => 'atTitle[' . $h['idAttribute'] . ']', 'size' => '30')))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($attrTypes, $h['Type'], FALSE), array('name' => 'atType[' . $h['idAttribute'] . ']')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Category'], array('name' => 'atCat[' . $h['idAttribute'] . ']')))
            . HTMLTable::makeTd($h['Last_Updated'] == '' ? '' : date('M j, Y', strtotime($h['Last_Updated'])))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'atdel[' . $h['idAttribute'] . ']', 'type' => 'checkbox')))
    );
}

// new attribute
$aTbl->addBodyTr(HTMLTable::makeTd('')
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'atTitle[0]')))
        . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($attrTypes, ''), array('name' => 'atType[0]')))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'atCat[0]')))
        . HTMLTable::makeTd('Create New', array('colspan' => '2'))
);

$attrTable = $aTbl->generateMarkup();


// Constraints
$constraintTable = $constraints->createConstraintTable($dbh);


// Form editor
//$feSelectForm = HTMLSelector::generateMarkup(
//        HTMLSelector::doOptionsMkup(removeOptionGroups(readGenLookupsPDO($dbh, 'Editable_Forms')), $feeFileSelection, TRUE)
//        , array('id'=>'frmEdSelect', 'name'=>'frmEdSelect'));



// Demographics Selection table
$tbl = getSelections($dbh, 'Demographics', 'm');
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

$rows = $stmt->fetchAll(PDO::FETCH_NUM);

$selDemos = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows, ''), array('name' => 'selDemoLookup', 'data-type'=>'d', 'class' => 'hhk-selLookup'));
$lookupErrMsg = '';



// General Lookup categories
$stmt2 = $dbh->query("select distinct `Type`, `Table_Name` from gen_lookups where `Type` in ('h','u', 'ha', 'm');");
$rows2 = $stmt2->fetchAll(PDO::FETCH_NUM);

$lkups = array();
$hasDiags = FALSE;
$hasLocs = FALSE;

foreach ($rows2 as $r) {

    if ($uS->RoomPriceModel == ItemPriceCode::None && ($r[1] == 'ExcessPays')) {
        continue;
    }

    if ($r[1] != 'Demographics') {
        $lkups[] = $r;
    }

    if ($r[1] == 'Diagnosis') {
        $hasDiags = TRUE;
    } else if ($r[1] == 'Location') {
        $hasLocs = TRUE;
    }

}

$selLookups = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($lkups, ''), array('name' => 'sellkLookup', 'class' => 'hhk-selLookup'));


// Additional charges and discounts
// Lookup categories
$stmt3 = $dbh->query("select distinct `Type`, `Table_Name` from gen_lookups where `Type` = 'ca';");
$rows3 = $stmt3->fetchAll(PDO::FETCH_NUM);
$hasAddnl = FALSE;
$hasDiscounts = FALSE;

foreach ($rows3 as $r) {
    if ($r[1] == 'Addnl_Charge') {
        $hasAddnl = TRUE;
    } else if ($r[1] == 'House_Discount') {
        $hasDiscounts = TRUE;
    }
}

$seldiscs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows3, ''), array('name' => 'seldiscs', 'class' => 'hhk-selLookup'));


// Items

$sitems = $dbh->query("Select idItem, Description from item;");
$items = $sitems->fetchAll(PDO::FETCH_NUM);

$itbl = new HTMLTable();
$itbl->addHeaderTr(HTMLTable::makeTh(count($items) . ' Items'));

foreach ($items as $d) {

    if ($d[0] == ItemId::AddnlCharge) {
        $itbl->addBodyTr(HTMLTable::makeTd('(Additional Charges)'));
    } else {
        $itbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($d[1], array('name' => 'txtItem[' . $d[0] . ']'))));
    }
}

$itemTable = $itbl->generateMarkup(array('style' => 'float:left;'));  // . $ttbl->generateMarkup();
// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
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
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo RTE_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <style>
            @media screen {
                .hhk-printmedia {display:none;}
            }
            @media print {
                .hhk-printmedia {display:inline;}
            }
        </style>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
<!--        <script type="text/javascript" src="<?php echo RTE_JS; ?>"></script>-->
        <script type="text/javascript">
    function isNumber(n) {
        "use strict";
        return !isNaN(parseFloat(n)) && isFinite(n);
    }

    var fixedRate = '<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>';

    function getRoomFees(cat) {
        if (cat != '' && cat != fixedRate) {
            // go get the total
            var ds = parseInt($('#txtNites').val(), 10);
            if (isNaN(ds)) {
                ds = 0;
            }
            var ct = parseInt($('#txtCredit').val(), 10);
            if (isNaN(ct)) {
                ct = 0;
            }
            $('#spnAmount').text('').addClass('ui-autocomplete-loading');
            $.post('ws_ckin.php', {
                cmd: 'rtcalc',
                rcat: cat,
                nites: ds,
                credit: ct
            }, function (data) {
                $('#spnAmount').text('').removeClass('ui-autocomplete-loading');
                data = $.parseJSON(data);
                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, true);
                    return;
                }
                if (data.amt) {
                    $('#spnAmount').text(data.amt);
                }
                if (data.cat) {
                    $('#selRateCategory').val(cat);
                }
            });
        }
    }
    function setupRates() {
        "use strict";
        $('#txtFixedRate').change(function () {
            if ($('#selRateCategory').val() == fixedRate) {
                var amt = parseFloat($(this).val());
                if (isNaN(amt) || amt < 0) {
                    amt = parseFloat($(this).prop("defaultValue"));
                    if (isNaN(amt) || amt < 0)
                        amt = 0;
                    $(this).val(amt);
                }
                var ds = parseInt($('#txtNites').val(), 10);
                if (isNaN(ds)) {
                    ds = 0;
                }
                $('#spnAmount').text(amt * ds);
            }
        });
        $('#txtNites, #txtCredit').change(function () {
            getRoomFees($('#selRateCategory').val());
        });
        $('#selRateCategory').change(function () {
            if ($(this).val() == fixedRate) {
                $('.hhk-fxFixed').show();
            } else {
                $('.hhk-fxFixed').hide();
                getRoomFees($(this).val());
            }
            $('#txtFixedRate').change();
        });
        $('#selRateCategory').change();
    }
    var savedRow;
    function getResource(idResc, type, trow) {
        "use strict";
        if ($('#cancelbtn').length > 0) {
            $('#cancelbtn').click();
        }
        $.post('ws_resc.php', {
            cmd: 'getResc',
            tp: type,
            id: idResc
        }, function (data) {
            if (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }
                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, true);
                    return;
                }
                if (data.row) {
                    savedRow = trow.children();
                    trow.children().remove().end().append($(data.row));
                    $('#savebtn').button().click(function () {
                        var btn = $(this);
                        saveResource(btn.data('id'), btn.data('type'), btn.data('cls'));
                    });
                    $('#cancelbtn').button().click(function () {
                        trow.children().remove().end().append(savedRow);
                        $('.reNewBtn').button();
                    });
                }
            }
        });
    }
    function getStatusEvent(idResc, type, title) {
        "use strict";
        $.post('ws_resc.php', {
            cmd: 'getStatEvent',
            tp: type,
            title: title,
            id: idResc
        }, function (data) {
            if (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }
                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, true);
                    return;
                }
                if (data.tbl) {
                    $('#statEvents').children().remove().end().append($(data.tbl));
                    $('.ckdate').datepicker({autoSize: true, dateFormat: 'M d, yy'});
                    var buttons = {
                        "Save": function () {
                            saveStatusEvent(idResc, type);
                        },
                        'Cancel': function () {
                            $(this).dialog('close');
                        }
                    };
                    $('#statEvents').dialog('option', 'buttons', buttons);
                    $('#statEvents').dialog('open');
                }
            }
        });
    }
    function saveStatusEvent(idResc, type) {
        "use strict";
        $.post('ws_resc.php', $('#statForm').serialize() + '&cmd=saveStatEvent' + '&id=' + idResc + '&tp=' + type,
                function (data) {
                    $('#statEvents').dialog('close');
                    if (data) {
                        try {
                            data = $.parseJSON(data);
                        } catch (err) {
                            alert("Parser error - " + err.message);
                            return;
                        }
                        if (data.error) {
                            if (data.gotopage) {
                                window.open(data.gotopage, '_self');
                            }
                            flagAlertMessage(data.error, true);
                            return;
                        }

                        if (data.msg && data.msg != '') {
                            flagAlertMessage(data.msg, false);
                        }

                    }
                });
    }
    function saveResource(idresc, type, clas) {
        "use strict";
        var parms = {};
        $('.' + clas).each(function () {

            if ($(this).attr('type') === 'radio' || $(this).attr('type') === 'checkbox') {
                if (this.checked !== false) {
                    parms[$(this).attr('id')] = 'on';
                }
            } else {
                parms[$(this).attr('id')] = $(this).val();
            }
        });
        $.post('ws_resc.php', {
            cmd: 'redit',
            tp: type,
            id: idresc,
            parm: parms
        }, function (data) {
            if (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }
                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, true);
                    return;
                } else if (data.roomList) {
                    $('#roomTable').children().remove().end().append($(data.roomList));
                    $('#tblroom').dataTable({
                        "dom": '<"top"if>rt<"bottom"lp><"clear">',
                        "displayLength": 50,
                        "lengthMenu": [[20, 50, -1], [20, 50, "All"]]
                    });
                } else if (data.rescList) {
                    $('#rescTable').children().remove().end().append($(data.rescList));
                    $('#tblresc').dataTable({
                        "dom": '<"top"if>rt<"bottom"lp><"clear">',
                        "displayLength": 50,
                        "lengthMenu": [[20, 50, -1], [20, 50, "All"]]
                    });
                } else if (data.constList) {
                    $('#constr').children().remove().end().append($(data.constList));
                }
                $('.reNewBtn').button();
            }
        });
    }
    $(document).ready(function () {
        "use strict";

        var tabIndex = parseInt('<?php echo $tabIndex; ?>');
        $('#btnMulti, #btnkfSave, #btnNewK, #btnNewF, #btnAttrSave, #btnhSave, #btnItemSave, .reNewBtn').button();

        $('#txtFaIncome, #txtFaSize').change(function () {
            var inc = $('#txtFaIncome').val().replace(',', ''),
                    size = $('#txtFaSize').val(),
                    errmsg = $('#spnErrorMsg');
            errmsg.text('');
            $('#txtFaIncome, #txtFaSize, #spnErrorMsg').removeClass('ui-state-highlight');
            if (inc == '' || size == '') {
                $('#spnFaCatTitle').text('');
                $('#hdnRateCat').val('');
                return false;
            }
            if (inc == '' || inc == '0' || isNaN(inc)) {
                $('#txtFaIncome').addClass('ui-state-highlight');
                errmsg.text('Fill in the Household Income').addClass('ui-state-highlight');
                return false;
            }
            if (size == '' || size == '0' || isNaN(size)) {
                $('#txtFaSize').addClass('ui-state-highlight');
                errmsg.text('Fill in the Household Size').addClass('ui-state-highlight');
                return false;
            }
            $.post('ws_ckin.php', {
                cmd: 'rtcalc',
                income: inc,
                hhsize: size,
                nites: 0
            }, function (data) {
                data = $.parseJSON(data);
                if (data.catTitle) {
                    $('#spnFaCatTitle').text(data.catTitle);
                }
                if (data.cat) {
                    $('#hdnRateCat').val(data.cat);
                }
            });
            return false;
        });
        setupRates();
        $('#mainTabs').tabs();
        $('#mainTabs').tabs("option", "active", tabIndex);
        $('#statEvents').dialog({
            autoOpen: false,
            resizable: true,
            width: 800,
            modal: true,
            title: 'Manage Status Events'
        });
        $('div#mainTabs').on('click', '.reEditBtn, .reNewBtn', function () {
            getResource($(this).attr('name'), $(this).data('enty'), $(this).parents('tr'));
        });
        $('div#mainTabs').on('click', '.reStatBtn', function () {
            getStatusEvent($(this).attr('name'), $(this).data('enty'), $(this).data('title'));
        });
        $('#tblroom, #tblresc').dataTable({
            "dom": '<"top"if>rt<"bottom"lp><"clear">',
            "displayLength": 50,
            "lengthMenu": [[20, 50, -1], [20, 50, "All"]]
        });
        $('.hhk-selLookup').change(function () {
            var $sel = $(this),
                table = $(this).find("option:selected").text(),
                type = $(this).val();

            if ($sel.data('type') === 'd') {
                table = $sel.val();
                type = 'd';
            }

            $sel.closest('form').children('div').empty().text('Loading...');
            $sel.prop('disabled', true);

            $.post('ResourceBuilder.php', {table: table, cmd: "load", tp: type},
                    function (data) {
                        $sel.prop('disabled', false);
                        if (data) {
                            $sel.closest('form').children('div').empty().append(data);
                        }
                    });
        });
        $('.hhk-saveLookup').click(function () {
            var $frm = $(this).closest('form');
            var sel = $frm.find('select.hhk-selLookup');
            var table = sel.find('option:selected').text(),
                type = $frm.find('select').val(),
                $btn = $(this);

            if (sel.data('type') === 'd') {
                table = sel.val();
                type = 'd';
            }

            if ($btn.val() === 'Saving...') {
                return;
            }

            $btn.val('Saving...');

            $.post('ResourceBuilder.php', $frm.serialize() + '&cmd=save' + '&table=' + table + '&tp=' + type,
                function(data) {
                    $btn.val('Save');
                    if (data) {
                        $frm.children('div').empty().append(data);
                    }
                });
        }).button();

        $('#btndemoSave').click(function () {
            var $frm = $(this).closest('form');

            $.post('ResourceBuilder.php', $frm.serialize() + '&cmd=save' + '&table=' + 'Demographics' + '&tp=' + 'm',
                function(data) {
                    if (data) {
                        $frm.children('div').children().remove().end().append(data);
                    }
                });
        }).button();


        // Add diagnosis and locations
        if ($('#btnAddDiags').length > 0) {
            $('#btnAddDiags').button();
        }
        if ($('#btnAddLocs').length > 0) {
            $('#btnAddLocs').button();
        }
        if ($('#btnHouseDiscs').length > 0) {
            $('#btnHouseDiscs').button();
        }
        if ($('#btnAddnlCharge').length > 0) {
            $('#btnAddnlCharge').button();
        }


        //verifyAddrs('#roomTable');
        $('input.number-only').change(function () {
            if (isNumber(this.value) === false) {
                $(this).val('0');
            }
            $(this).val(parseInt(this.value));
        });
        $('#mainTabs').show();
    });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <h1><?php echo $wInit->pageHeading; ?></h1>
            </div>
<?php echo $resultMessage ?>
            <div id="mainTabs" style="font-size: .9em; clear:left; display:none;" class="hhk-member-detail">
                <ul>
                    <li><a href="#rescTable">Resources</a></li>
                    <li><a href="#roomTable">Rooms</a></li>
                    <li><a href="#rateTable"><?php echo $rateTableTabTitle; ?></a></li>
                    <li><a href="#hospTable"><?php echo $hospitalTabTitle; ?></a></li>
                    <li><a href="#demoTable">Demographics</a></li>
                    <li><a href="#lkTable">Lookups</a></li>
<!--                    <li><a href="#agreeEdit">Forms Editor</a></li>-->
                    <li><a href="#itemTable">Items</a></li>
                    <li><a href="#attrTable">Attributes</a></li>
                    <li><a href="#constr">Constraints</a></li>
                </ul>
                <div id="rescTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                    <?php echo $rescTable; ?>
                </div>
                <div id="roomTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                    <?php echo $roomTable; ?>
                </div>
                <div id="demoTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                    <div style="float:left;">
                        <h3>Demographic Categories</h3>
                        <form id="formdemo">
                            <div>
                                <?php echo $demoSelections; ?>
                            </div>
                            <span style="margin:10px;float:right;"><input type="button" id='btndemoSave' class="hhk-savedemoCat" data-type="h" value="Save"/></span>
                        </form>
                    </div>

                    <div style="float:left; margin-left:30px;">
                        <h3>Demographics</h3>
                        <form id="formdemoCat">
                            <table><tr>
                                <th>Demographic</th>
                                <td><?php echo $selDemos; ?></td>
                                </tr>
                            </table>
                            <div id="divdemoCat"></div>
                            <span style="margin:10px;float:right;"><input type="button" id='btndemoSaveCat' class="hhk-saveLookup" data-type="d" value="Save"/></span>
                        </form>
                    </div>
                </div>
                <div id="lkTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                    <div style="float:left;">
                        <h3>General Lookups</h3>
                        <form method="POST" action="ResourceBuilder.php" id="formlk">
                            <table><tr>
                                    <th>Category</th>
                                    <td><?php echo $selLookups; ?></td>
                                </tr></table>
                            <div id="divlk" class="hhk-divLk"></div>
                            <span style="margin:10px;float:right;">
                                <?php if (!$hasDiags) { ?>
                                <input type="submit" name='btnAddDiags' id="btnAddDiags" value="Add Diagnosis"/>
                                <?php } if (!$hasLocs) { ?>
                                <input type="submit" id='btnAddLocs' name="btnAddLocs" value="Add Location"/>
                                <?php } ?>
                                <input type="button" id='btnlkSave' class="hhk-saveLookup"data-type="h" value="Save"/>
                            </span>
                        </form></div>
                    <div style="float:left; margin-left:30px;">
                        <h3>Discounts & Additional Charges</h3>
                        <form method="POST" action="ResourceBuilder.php"  id="formdisc">
                            <table><tr>
                                    <th>Category</th>
                                    <td><?php echo $seldiscs; ?></td>
                                </tr></table>
                            <div id="divdisc" class="hhk-divLk"></div>
                            <span style="margin:10px;float:right;">
                                <?php if (!$hasDiscounts) { ?>
                                <input type="submit" name='btnHouseDiscs' id="btnHouseDiscs" value="Add Discounts"/>
                                <?php } if (!$hasAddnl) { ?>
                                <input type="submit" id='btnAddnlCharge' name="btnAddnlCharge" value="Add Additional Charges"/>
                                <?php } ?>
                                <input type="button" id='btndiscSave' class="hhk-saveLookup" data-type="ha" value="Save"/>
                            </span>
                        </form>
                    </div>
                </div>
                <div id="rateTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                    <p style="padding:3px;background-color: #fff7db;float:left;">Make changes directly into the text boxes below and press 'Save'.</p>
                    <form method="POST" action="ResourceBuilder.php" name="form1">
                        <div style="clear:left;float:left;"><?php echo $pricingModelTable; ?></div>
<?php echo $visitFeesTable . $keysTable . $payTypesTable . $feesTable . $faMarkup; ?>
                        <div style="clear:both"></div>
                        <span style="margin:10px;float:right;"><input type="submit" id='btnkfSave' name="btnkfSave" value="Save"/></span>
                    </form>
                </div>
                <div id="hospTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                    <form method="POST" action="ResourceBuilder.php" name="formh">
<?php echo $hospTable; ?>
                        <div style="clear:both"></div>
                        <span style="margin:10px;float:right;"><input type="submit" id='btnhSave' name="btnhSave" value="Save"/></span>
                    </form>
                </div>
<!--                <div id="agreeEdit" class="ui-tabs-hide" >
                    <p>Select the form to edit from the following list: <?php echo $rteSelectForm; ?><span id="spnRteLoading" style="font-style: italic; display:none;">Loading...</span></p>
                    <p id="rteMsg" style="float:left;" class="ui-state-highlight"><?php echo $rteMsg; ?></p>
                    <fieldset style="clear:left; float:left; margin-top:10px;">
                        <legend><span id="spnEditorTitle" style="font-size: 1em; font-weight: bold;">Select a form</span></legend>
                        <div id="rteContainer"></div>
                    </fieldset>
                </div>-->
                <div id="itemTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                    <form method="POST" action="ResourceBuilder.php" name="formitem">
<?php echo $itemTable; ?>
                        <div style="clear:both"></div>
                        <span style="margin:10px;float:right;"><input type="submit" id='btnItemSave' name="btnItemSave" value="Save"/></span>
                    </form>
                </div>
                <div id="attrTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                    <form method="POST" action="ResourceBuilder.php" name="format">
<?php echo $attrTable; ?>
                        <div style="clear:both"></div>
                        <span style="margin:10px;float:right;"><input type="submit" id='btnAttrSave' name="btnAttrSave" value="Save"/></span>
                    </form>
                </div>
                <div id="constr" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                        <?php echo $constraintTable; ?>
                </div>
            </div>
            <div id="statEvents" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em;"></div>
        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
