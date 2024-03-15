<?php

use HHK\Checklist;
use HHK\Document\FormTemplate;
use HHK\House\Attribute\Attributes;
use HHK\House\Constraint\Constraints;
use HHK\House\Constraint\ConstraintsHospital;
use HHK\House\Insurance\Insurance;
use HHK\House\Insurance\InsuranceType;
use HHK\House\RegistrationForm\CustomRegisterForm;
use HHK\House\Report\ResourceBldr;
use HHK\House\ResourceView;
use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput, HTMLSelector};
use HHK\Purchase\FinAssistance;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Purchase\RoomRate;
use HHK\Purchase\TaxedItem;
use HHK\sec\{Session, WebInit};
use HHK\sec\Labels;
use HHK\sec\SysConfig;
use HHK\SysConst\AttributeTypes;
use HHK\SysConst\ConstraintType;
use HHK\SysConst\GLTypeCodes;
use HHK\SysConst\HospitalType;
use HHK\SysConst\ItemId;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\ItemType;
use HHK\SysConst\PayType;
use HHK\SysConst\RateStatus;
use HHK\SysConst\RoomRateCategories;
use HHK\SysConst\WebRole;
use HHK\TableLog\HouseLog;
use HHK\Tables\Attribute\AttributeRS;
use HHK\Tables\DocumentRS;
use HHK\Tables\EditRS;
use HHK\Tables\GenLookupsRS;
use HHK\Tables\House\Rate_BreakpointRS;
use HHK\Tables\House\Room_RateRS;
use HHK\Tables\Item\ItemRS;
use HHK\Tables\Registration\HospitalRS;

/**
 * ResourceBuilder.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2013-2021` <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("homeIncludes.php");

const DIAGNOSIS_TABLE_NAME = 'Diagnosis';

const LOCATION_TABLE_NAME = 'Location';

const RESERV_STATUS_TABLE_NAME = 'lookups';

const MAX_FINANCIAL_RATE_CATEGORIES = 16;
const MAX_FINANCIAL_HOUSEHOLDS = 20;

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}


$dbh = $wInit->dbh;

$uS = Session::getInstance();

// Kick out 'Guest' Users
if ($uS->rolecode > WebRole::WebUser) {

    exit("Unauthorized - " . HTMLContainer::generateMarkup('a', 'Continue',
        [
            'href' => 'index.php'
        ]
    ));
}

$tabIndex = 0;

$rteMsg = '';
$rateTableErrorMessage = '';
$itemMessage = '';
$formType = '';
$demoMessage = '';
$breakpointMessage = '';


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
    ResourceBldr::checkLookups($dbh, $_POST, $labels);
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
        $dText = filter_var($_POST['srrDesc'][0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $rows = EditRS::select($dbh, $itemRs,
                    [
                        $itemRs->idItem
                    ]
                );

                if (count($rows) == 1) {
                    $itemRs->Description->setNewVal(filter_var($_POST['kdesc'][$k], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                    EditRS::update($dbh, $itemRs,
                        [
                            $itemRs->idItem
                        ]
                    );
                }
            }
        }
    }

    // Visit Fee
    if (isset($_POST['vfdesc'])) {

        // new visit fee defined?
        if (isset($_POST['vfdesc'][0])) {

            $newDesc = filter_var($_POST['vfdesc'][0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $newRate = filter_var($_POST['vfrate'][0], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            if ($newDesc != '') {
                // Add a cleaning fee?

                // Look for existing fee
                $glRs = new GenLookupsRS();
                $glRs->Table_Name->setStoredVal('Visit_Fee_Code');
                $glRs->Description->setStoredVal($newDesc);
                $rows = EditRS::select($dbh, $glRs,
                    [
                        $glRs->Table_Name,
                        $glRs->Description
                    ]
                );

                if (count($rows) > 0) {
                    $rateTableErrorMessage = HTMLContainer::generateMarkup('p', 'Visit fee code "' . $newDesc . '" is already defined. ',
                        [
                            'style' => 'color:red;'
                        ]
                    );
                } else {

                    // Insert new cleaning fee
                    $glRs = new GenLookupsRS();
                    $newCode = incCounter($dbh, 'codes');

                    $glRs->Table_Name->setNewVal('Visit_Fee_Code');
                    $glRs->Description->setNewVal($newDesc);
                    $glRs->Substitute->setNewVal($newRate);
                    $glRs->Code->setNewVal($newCode);

                    EditRS::insert($dbh, $glRs);
                    $logText = HouseLog::getInsertText($glRs);
                    HouseLog::logGenLookups($dbh, 'Visit_Fee_Code', $newCode, $logText, 'insert', $uS->username);
                }
            }
        }

        $vfDefault = '';

        if (isset($_POST['vfrbdefault'])) {
            $vfDefault = filter_var($_POST['vfrbdefault'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // Amount Changed?
        if (($defaultCode = ResourceBldr::saveArchive($dbh, $_POST['vfdesc'], $_POST['vfrate'], 'Visit_Fee_Code')) != '') {
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

        $vfDefault = filter_var($_POST['ptrbdefault'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $gl = filter_var($_POST['ptGlCode'][$t[0]], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                $dbh->exec("Update payment_method set Gl_Code = '$gl' where idPayment_method = " . $t[0]);
            }
        }
    }

    // Excess Pay
    if (isset($_POST['epdesc'][$uS->VisitExcessPaid])) {

        saveGenLk($dbh, 'ExcessPays', $_POST['epdesc'], [], NULL);
    }


    // Financial Asst. Break points.
    if ($uS->IncomeRated) {

        $letters = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k', 'm', 'n', 'p', 'r', 's', 't'];

        $newHhSize = 0;
        $newRateSize = 0;
        $currentHhSize = 0;
        $ratCats = [];

        $stmt = $dbh->query("Select max(`Household_Size`) from rate_breakpoint;");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) === 1) {
            $currentHhSize = $rows[0][0];
        }

        $stmt = $dbh->query("select Rate_Breakpoint_Category from room_rate WHERE Rate_Breakpoint_Category != '' AND  `Status` = '" . RateStatus::Active . "' ORDER BY `Rate_Breakpoint_Category`");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        foreach ($rows as $r) {
            $ratCats[] = $r[0];
        }


        // New Household Size
        if (isset($_POST['incrHhSize'])) {
            $newHhSize = intval(filter_input(INPUT_POST, 'incrHhSize', FILTER_SANITIZE_NUMBER_INT), 10);

            if ($newHhSize > MAX_FINANCIAL_HOUSEHOLDS) {
                $newHhSize = MAX_FINANCIAL_HOUSEHOLDS;
            }
        }

        // New rate category size
        if (isset($_POST['incrRateCat'])) {
            $newRateSize = intval(filter_input(INPUT_POST, 'incrRateCat', FILTER_SANITIZE_NUMBER_INT), 10);

            if ($newRateSize > MAX_FINANCIAL_RATE_CATEGORIES) {
                $newRateSize = MAX_FINANCIAL_RATE_CATEGORIES;
            }
        }

        // Update Household Size
        if ($newHhSize > $currentHhSize) {
            // Add additional household records.

            $idRow = 0;

            for ($i = $currentHhSize + 1; $i <= $newHhSize; $i++) {

                foreach ($ratCats as $c) {
                    // Insert new sizes
                    $rbRs = new Rate_BreakpointRS();

                    $rbRs->Household_Size->setNewVal($i);
                    $rbRs->Rate_Category->setNewVal($c);

                    $idRow = EditRS::insert($dbh, $rbRs);

                }
            }

            // Log new size
            $logText = 'Household Size increased to ' . $newHhSize;
            HouseLog::logFinAssist($dbh, 'insert', $idRow, $logText, $uS->username);

            $currentHhSize = $newHhSize;

        } else if ($newHhSize > 0 && $newHhSize < $currentHhSize) {

            // Delete the extra size rows
            $numDeleted = $dbh->exec("delete from `rate_breakpoint` where Household_Size > $newHhSize");

            if ($numDeleted > 0) {
                $logText = 'Deleted household sizes greater than ' . $newHhSize;
                HouseLog::logFinAssist($dbh, 'delete', 0, $logText, $uS->username);
            }

            $currentHhSize = $newHhSize;
        }

        // Update number of rate categories
        if ($newRateSize > count($ratCats)) {

            $idRow = 0;

            for ($r = 1; $r <= $currentHhSize; $r++) {

                for ($i = count($ratCats); $i < $newRateSize; $i++) {

                    $rbRs = new Rate_BreakpointRS();

                    $rbRs->Household_Size->setNewVal($r);
                    $rbRs->Rate_Category->setNewVal($letters[$i]);

                    $idRow = EditRS::insert($dbh, $rbRs);
                }

            }

            // Log change
            $logText = 'Adding ' . ($newRateSize - count($ratCats)) . ' new rate categor' . (($newRateSize - count($ratCats)) > 1 ? 'ies' : 'y') . ' to each household size.';
            HouseLog::logFinAssist($dbh, 'insert', $idRow, $logText, $uS->username);


            // Add new room rates to room_rate table
            for ($i = count($ratCats); $i < $newRateSize; $i++) {

                $rpRs = new Room_RateRS();

                $rpRs->Reduced_Rate_1->setNewVal(10.00);
                $rpRs->Reduced_Rate_2->setNewVal(12.00);
                $rpRs->Reduced_Rate_3->setNewVal(15.00);
                $rpRs->Min_Rate->setNewVal(5.00);
                $rpRs->FA_Category->setNewVal($priceModel->getNewRateCategory());
                $rpRs->Rate_Breakpoint_Category->setNewVal($letters[$i]);
                $rpRs->PriceModel->setNewVal($priceModel->getPriceModelCode());
                $rpRs->Title->setNewVal('Rate ' . strtoupper($letters[$i]));
                $rpRs->Updated_By->setNewVal($uS->username);
                $rpRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $rpRs->Status->setNewVal(RateStatus::Active);

                $idRoomRate = EditRS::insert($dbh, $rpRs);

                // Log action.
                HouseLog::logRoomRate($dbh, 'insert', $idRoomRate, HouseLog::getInsertText($rpRs), $uS->username);

                //reload rate cats
                $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
            }
        } else if ($newRateSize > 0 && $newRateSize < count($ratCats)) {
            // remove rate categories
            $lastCat = $ratCats[$newRateSize - 1];

            // Delete the extra size rows
            $numDeleted = $dbh->exec("delete from `rate_breakpoint` where Rate_Category > '$lastCat';");

            if ($numDeleted > 0) {
                $logText = 'Deleted Rate Categories over ' . $lastCat;
                HouseLog::logFinAssist($dbh, 'delete', 0, $logText, $uS->username);

                $dbh->exec("update `room_rate` set `Status` = '" . RateStatus::NotActive . "' where Rate_Breakpoint_Category > '$lastCat';");
            }
        }

        $lastBreakpoints = [];

        // Update breakpoints from POST
        foreach ($letters as $l) {

            if (isset($_POST['rateBp' . $l])) {

                if ($l == $ratCats[count($ratCats) - 1]) {
                    $breakPoints = $lastBreakpoints;
                } else {
                    $breakPoints = filter_var_array($_POST['rateBp' . $l], FILTER_SANITIZE_NUMBER_INT);

                }

                foreach ($breakPoints as $index => $bpVal) {

                    $rbRs = new Rate_BreakpointRS();
                    $rbRs->Household_Size->setStoredVal($index + 1);
                    $rbRs->Rate_Category->setStoredVal($l);
                    $rbRs->Breakpoint->setNewVal($bpVal);

                    $rowCount = EditRS::update($dbh, $rbRs, [$rbRs->Household_Size, $rbRs->Rate_Category]);

                    if ($rowCount > 0) {
                        $logText = HouseLog::getInsertText($rbRs);
                        HouseLog::logFinAssist($dbh, 'update', $rowCount, $logText, $uS->username);
                    }
                }

                $lastBreakpoints = $breakPoints;
            }
        }
    }
}

if (isset($_POST['btnhSave'])) {

    $tabIndex = 3;
    $postedHosp = [];
    if (isset($_POST['hTitle'])) {
        $postedHosp = filter_var_array($_POST['hTitle'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    foreach ($postedHosp as $hid => $title) {

        $idHosp = intval($hid, 10);

        $hospRs = new HospitalRS();
        $hospRs->idHospital->setStoredVal($idHosp);

        // Delete?
        if (isset($_POST['hdel'][$idHosp])) {

            // Change status to "Retired"
            $hospRs->Status->setNewVal('r');
            EditRS::update($dbh, $hospRs, [$hospRs->idHospital]);

            // Delete any attribute entries
            $query = "delete from attribute_entity where idEntity = :id and Type = :tpe";
            $stmt = $dbh->prepare($query);
            $stmt->execute(
                [
                    ':id' => $idHosp,
                    ':tpe' => AttributeTypes::Hospital
                ]
            );
            continue;
        }

        // Type
        if (isset($_POST['hType'][$idHosp])) {
            $type = filter_var($_POST['hType'][$idHosp], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            continue;
        }

        if (isset($_POST['hDesc'][$idHosp])) {
            $desc = filter_var($_POST['hDesc'][$idHosp], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $desc = '';
        }

        // background Color
        $rCSS = '';
        if (isset($_POST['hColor'][$idHosp])) {
            $rCSS = filter_var($_POST['hColor'][$idHosp], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // Text Color
        $vCSS = '';
        if (isset($_POST['hText'][$idHosp])) {
            $vCSS = filter_var($_POST['hText'][$idHosp], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // hide?
        $hide = 0;
        if (isset($_POST['hhide'][$idHosp])) {
            $hide = 1;
        }

        // New Hospital?
        if ($title == '' || $type == '') {
            // No new hospitals this time
            continue;
        }

        if ($idHosp > 0) {
            $rows = EditRS::select($dbh, $hospRs,
                [
                    $hospRs->idHospital
                ]
            );
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
        $hospRs->Hide->setNewVal($hide);
        $hospRs->Reservation_Style->setNewVal($rCSS);
        $hospRs->Stay_Style->setNewVal($vCSS);
        $hospRs->Updated_By->setNewVal($uS->username);
        $hospRs->Last_Updated->setNewVal(date('Y-m-d'));

        if ($idHosp > 0) {

            // bug 898 cannot change an assoc to a hospital
            if ($hospRs->Type->getStoredVal() == HospitalType::Association && $type == HospitalType::Hospital) {
                continue;
            }

            // update
            EditRS::update($dbh, $hospRs,
                [
                    $hospRs->idHospital
                ]
            );

            // Check attributes
            $capturedAttributes = [];

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

    $tabIndex = 10;
    $postedAttr = [];
    if (isset($_POST['atTitle'])) {
        $postedAttr = filter_var_array($_POST['atTitle'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    foreach ($postedAttr as $hid => $title) {

        $idAttr = intval($hid, 10);

        $atRs = new AttributeRS();
        $atRs->idAttribute->setStoredVal($idAttr);

        // Delete?
        if (isset($_POST['atdel'][$idAttr])) {

            EditRS::delete($dbh, $atRs,
                [
                    $atRs->idAttribute
                ]
            );

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
            $cat = filter_var($_POST['atCat'][$idAttr], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $cat = '';
        }

        // New Attr?
        if ($title == '' || $type == '') {
            // No new hospitals this time
            continue;
        }

        if ($idAttr > 0) {
            $rows = EditRS::select($dbh, $atRs,
                [
                    $atRs->idAttribute
                ]
            );
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
            EditRS::update($dbh, $atRs,
                [
                    $atRs->idAttribute
                ]
            );
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

            $desc = filter_var($_POST['txtItem'][$idItem], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $dbh->exec("update `item` set `Description` = '$desc' where `idItem` = " . $idItem);
        }

        if (isset($_POST['txtGlCode'][$idItem])) {

            $glCode = filter_var($_POST['txtGlCode'][$idItem], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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
            if ($m['idItem'] == $idItem && !isset($_POST['cbtax'][$idItem][$m['Item_Id']])) {

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

            $desc = filter_var($_POST['txttItem'][$i['idItem']], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $glCode = filter_var($_POST['txttGlCode'][$i['idItem']], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $percentage = filter_var($_POST['txttPercentage'][$i['idItem']], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $maxDays = filter_var($_POST['txttMaxDays'][$i['idItem']], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $last = $i['Last_Order_Id'];
            $first = $i['First_Order_Id'];

            if ($maxDays != $i['Timeout_Days'] || $percentage != $i['Percentage']) {

                if ($last != 0) {
                    $itemMessage = HTMLContainer::generateMarkup('span', 'Cannot change that tax item.',
                        [
                            'style' => 'color:red;'
                        ]
                    );
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

        $desc = filter_var($_POST['txttItem'][0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $glCode = filter_var($_POST['txttGlCode'][0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $percentage = filter_var($_POST['txttPercentage'][0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $maxDays = filter_var($_POST['txttMaxDays'][0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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

    $formType = filter_var($_POST['ldfm'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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

    $formstmt = $dbh->query("Select g.`Code`, g.`Description`, d.`Doc`, d.idDocument, ifnull(d.Abstract, '') as `Abstract` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` where g.`Table_Name` = '$formDef' order by g.Order asc");
    $docRows = $formstmt->fetchAll();

    $li = '';
    $tabContent = '';

    //set help text
    $help = '';

    $editMkup = '';

    foreach ($docRows as $r) {

        if ($formType == 'ra' && $uS->RegForm == "3") {
            $regSettings = [];
            if (!empty($r['Abstract']) && @json_decode($r['Abstract'], true)) {
                $regSettings = json_decode($r['Abstract'], true);
            }

            $regForm = new CustomRegisterForm($r['Code'], $regSettings);
            $editMkup = $regForm->getEditMkup();
        }

        //subject line
        $subjectLine = "";
        try {
            $abstract = json_decode($r["Abstract"], true);
            if (isset($abstract['subjectLine'])) {
                $subjectLine = $abstract['subjectLine'];
            }
        } catch (\Exception $e) {

        }

        $li .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $r['Description'],
            [
                'href' => '#' . $r['Code'],
                'id' => "docTab-" . $r['Code']
            ]
        ), ['class' => 'hhk-sortable', 'data-code' => $r['Code']]);

        $tabContent .= HTMLContainer::generateMarkup('div', $help . ($r['Doc'] ? HTMLContainer::generateMarkup('fieldset', '<legend style="font-weight: bold;">Current Form</legend>' . $r['Doc'],
                [
                    'id' => 'form' . $r['idDocument'],
                    'class' => 'p-3 mb-3 user-agent-spacing'
                ]
        ) : '') .
            '<div><div class="d-inline-block p-3 uploadFormDiv ui-widget-content ui-corner-all"><form enctype="multipart/form-data" action="ResourceBuilder.php" method="POST" style="padding: 5px 7px;">
<input type="hidden" name="docId" value="' . $r['idDocument'] . '"/>' .

            ($editMkup != '' ? $editMkup : '') .

            ($formType == 'c' || $formType == 's' ? '<div class="form-group mb-3"><label for="emailSubjectLine">Email Subject Line: </label><input type="text" name="emailSubjectLine" placeholder="Email Subject Line" value="' . $subjectLine . '" size="35"></div>' : '') .
            '<input type="hidden" name="filefrmtype" value="' . $formType . '"/>' .
            '<input type="hidden" name="docAction">' .
            '<input type="hidden" name="formDef" value="' . $formDef . '">' .
            '<input type="hidden" name="docCode" value="' . $r["Code"] . '">' .
            '<div class="form-group mb-3"><label for="formfile">Upload new HTML file: </label><input name="formfile" type="file" accept="text/html" /></div>' .
            '<div class="form-group mb-3"><small>File must have UTF-8 or Windows-1252 caracter encoding. <br>Other character sets may produce unexpected behavior</small></div>' .
            '<div class="hhk-flex" style="justify-content: space-evenly">' .
            '<button type="submit" id="docDelFm"><span class="ui-icon ui-icon-trash"></span>Delete Form</button>' .
            '<button type="submit" id="docSaveFm"><span class="ui-icon ui-icon-disk"></span>Save Form</button>' .
            '</div>' .
            '</form></div></div>',
            [
                'id' => $r['Code']
            ]
        );
    }

    if (count($replacementRows) > 0) {

        // add replacements tab
        $li .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Replacement Codes',
                [
                    'href' => '#replacements'
                ]
        ),
            [
                'id' => 'liReplacements',
                'style' => 'float: right;'
            ]
        );

        $tabContent .= HTMLContainer::generateMarkup('div', '<div class="mb-3">You may use the following codes in your document to personalize the document to each ' . $labels->getString('MemberType', 'guest', 'Guest') . '</div>' . $rTbl->generateMarkup(), array(
            'id' => 'replacements'
        )
        );
    }

    // Make the final tab control
    $ul = HTMLContainer::generateMarkup('ul', $li, []);
    $output = HTMLContainer::generateMarkup('div', $ul . $tabContent,
        [
            'id' => 'regTabDiv',
            'data-formDef' => $formDef
        ]
    );

    $dataArray['type'] = $formType;
    $dataArray['title'] = $formTitle;
    $dataArray['mkup'] = $output;

    echo json_encode($dataArray);

    exit();
}

// Upload a new form
if (isset($_POST['docAction']) && $_POST["docAction"] == "docUpload") {

    try {
        $tabIndex = 8;

        $uName = $uS->username;

        $docId = -1;
        if (isset($_POST['docId'])) {
            $docId = intval(filter_var($_POST['docId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $docCode = '';
        if (isset($_POST['docCode'])) {
            $docCode = filter_var($_POST['docCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $formType = "";
        if (isset($_POST['filefrmtype'])) {
            $formType = filter_var($_POST['filefrmtype'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $subjectLine = "";
        $abstract = [];
        if (isset($_POST["emailSubjectLine"])) {
            $subjectLine = filter_var($_POST["emailSubjectLine"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $abstract["subjectLine"] = $subjectLine;
        }

        if ($formType == 'ra' && $uS->RegForm == "3" && isset($_POST["regForm"][$docCode])) {
            $regForm = new CustomRegisterForm();
            $abstract = $regForm->validateSettings($_POST['regForm'][$docCode]);
        }

        $applyAll = FALSE;
        if (isset($_POST['regForm']['misc']['applyAll'])) {
            $applyAll = TRUE;
        }

        $abstract = json_encode($abstract);

        $mimetype = "";
        if (!empty($_FILES['formfile']['tmp_name'])) {
            $mimetype = mime_content_type($_FILES['formfile']['tmp_name']);
        }

        $sql = "UPDATE `document` SET Abstract = :abstract, ";
        if (!empty($_FILES['formfile']['tmp_name']) && ($mimetype == "text/html" || $mimetype == "text/plain")) {
            // Get the file and convert it.
            $file = file_get_contents($_FILES['formfile']['tmp_name']);
            if (mb_detect_encoding($file, ["UTF-8"], true) !== false) { //test for UTF-8
                $doc = $file;
            } else { //assume Windows-1252
                $doc = iconv('Windows-1252', 'UTF-8//TRANSLIT', $file); // add //TRANSLIT for special character conversion
            }
            $sql .= "Doc = :doc, ";
        }
        $sql .= "Updated_By = :updatedBy, Last_Updated = now() where idDocument = :idDoc";

        $ustmt = $dbh->prepare($sql);

        if (!empty($_FILES['formfile']['tmp_name']) && ($mimetype == "text/html" || $mimetype == "text/plain")) {
            $ustmt->bindParam(":doc", $doc, PDO::PARAM_LOB);
        }
        $ustmt->bindParam(":abstract", $abstract);
        $ustmt->bindParam(":updatedBy", $uName);
        $ustmt->bindParam(":idDoc", $docId);
        $dbh->beginTransaction();
        $ustmt->execute();

        if ($applyAll) {
            $applyAllSql = "UPDATE `document` d join gen_lookups g on d.idDocument = g.Substitute and g.Table_Name = 'Reg_Agreement' SET Abstract = :abstract";
            $astmt = $dbh->prepare($applyAllSql);
            $astmt->bindParam(":abstract", $abstract);
            $astmt->execute();
        }

        $dbh->commit();

        echo json_encode(["docCode" => $docCode, "success" => "Form saved successfully"]);
        exit();
    } catch (\Exception $e) {
        $dbh->rollBack();
        echo json_encode(["error" => "Could not save form: " . $e->getMessage()]);
        exit();
    }
}

// Delete a form
if (isset($_POST['docAction']) && $_POST['docAction'] == "docDelete" && isset($_POST['docCode']) && isset($_POST['formDef'])) {
    try {
        $docCode = filter_var($_POST['docCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $formDef = filter_var($_POST['formDef'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $tabIndex = 8;

        $dbh->exec("UPDATE `document` d JOIN `gen_lookups` g ON g.`Table_Name` = '$formDef' AND g.`Code` = '$docCode' SET d.`status` = 'd' WHERE `idDocument` = g.`Substitute`");
        $dbh->exec("DELETE FROM gen_lookups where `Table_Name` = '$formDef' AND `Code` = '$docCode'");

        if (isset($_POST['docfrmtype'])) {
            $formType = filter_var($_POST['docfrmtype'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        echo json_encode(["success" => "Form deleted successfully"]);
        exit();
    } catch (\Exception $e) {
        echo json_encode(["error" => "Could not delete form: " . $e->getMessage()]);
        exit();
    }

}

// Make sure Content-Type is application/json
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (stripos($content_type, 'application/json') !== false) {
    // Read the input stream
    $body = file_get_contents("php://input");
    $data = json_decode($body);

    if ($data->cmd == "reorderfm") {
        $output = "";
        try {
            foreach ($data->order as $i => $v) {
                $dbh->exec("UPDATE `gen_lookups` SET `Order` = $i WHERE `Table_Name` = '" . $data->formDef . "' AND `Code` = '$v';");
            }
        } catch (\Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            exit;
        }
        echo json_encode(["status" => "success"]);
    }

    exit;
}

if (isset($_POST['txtformLang'])) {

    $tabIndex = 8;
    $lang = trim(filter_var($_POST['txtformLang'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $formDef = '';
    $formTitle = '';

    if (isset($_POST['hdnFormType'])) {
        $formType = filter_var($_POST['hdnFormType'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

                if ($docId > 0) {
                    echo json_encode(["success" => "New form created successfully", "docCode" => $formType]);
                } else {
                    echo json_encode(["error" => "Error creating form"]);
                }
                exit;
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

$pricingModelTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Pricing Model',
        [
            'style' => 'font-weight:bold;'
        ]
) . $kTbl->generateMarkup(
            [
                'style' => 'margin:7px;'
            ]
        ),
    [
        'style' => 'margin:7px;'
    ]
);

// Room and Resourse lists
$rescTable = ResourceView::resourceTable($dbh);
$roomTable = ResourceView::roomTable($dbh, $uS->KeyDeposit, $uS->PaymentGateway);

// Room Pricing
$priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
$fTbl = $priceModel->getEditMarkup($dbh, $uS->RoomRateDefault, $uS->IncomeRated);

// Static room rate
$rp = readGenLookupsPDO($dbh, 'Static_Room_Rate', 'Description');

$sTbl = new HTMLTable();
$sTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('Amount'));

foreach ($rp as $r) {
    $sTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($r[1],
        [
            'name' => 'srrDesc[' . $r[0] . ']',
            'size' => '16'
        ]
    )) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2],
                    [
                        'name' => 'srrAmt[' . $r[0] . ']',
                        'size' => '6',
                        'class' => 'number-only'
                    ]
                )));
}

$sTbl->addBodyTr(HTMLTable::makeTd('New static room rate:',
    [
        'colspan' => '3'
    ]
));
$sTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('',
    [
        'name' => 'srrDesc[0]',
        'size' => '16'
    ]
)) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup('',
                [
                    'name' => 'srrAmt[0]',
                    'size' => '6',
                    'class' => 'number-only'
                ]
            )));

$sMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Static Room Rate',
        [
            'style' => 'font-weight:bold;'
        ]
) . $sTbl->generateMarkup(
            [
                'style' => 'float:left;margin:7px;'
            ]
        ),
    [
        'style' => 'clear:left;float:left;margin:7px;'
    ]
);

// Rate Calculator
$rcMarkup = '';

if ($priceModel->hasRateCalculator()) {

    $tbl = new HTMLTable();

    $tbl->addHeaderTr(HTMLTable::makeTh('Room Rate') . HTMLTable::makeTh('Credit') . ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily ? HTMLTable::makeTh($labels->getString('MemberType', 'guest', 'Guest') . ' Nights') : HTMLTable::makeTh('Nights')) . HTMLTable::makeTh('Total'));

    $attrFixed = [
        'id' => 'spnRateTB',
        'class' => 'hhk-fxFixed',
        'style' => 'margin-left:.5em;display:none;'
    ];
    $rateCategories = RoomRate::makeSelectorOptions($priceModel);

    $tbl->addBodyTr(HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), ''),
        [
            'name' => 'selRateCategory'
        ]
    ) . HTMLContainer::generateMarkup('span', '$' . HTMLInput::generateMarkup('',
                    [
                        'name' => 'txtFixedRate',
                        'size' => '4'
                    ]
                ), $attrFixed)) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                    [
                        'name' => 'txtCredit',
                        'size' => '4'
                    ]
                ),
                [
                    'style' => 'text-align:center;'
                ]
            ) . HTMLTable::makeTd(HTMLInput::generateMarkup('1',
                    [
                        'name' => 'txtNites',
                        'size' => '4'
                    ]
                ),
                [
                    'style' => 'text-align:center;'
                ]
            ) . HTMLTable::makeTd('$' . HTMLContainer::generateMarkup('span', '0',
                    [
                        'name' => 'spnAmount'
                    ]
                ),
                [
                    'style' => 'text-align:center;'
                ]
            ));

    $rcMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Rate Calculator',
            [
                'style' => 'font-weight:bold;'
            ]
    ) . $tbl->generateMarkup(
                [
                    'style' => 'float:left;margin:7px;'
                ]
            ),
        [
            'style' => 'clear:left;float:left;margin:7px;'
        ]
    );
}

// Wrap rate table and rate calculator
$feesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Room Rates',
        [
            'style' => 'font-weight:bold;'
        ]
) . HTMLContainer::generateMarkup('div', $fTbl->generateMarkup(
                [
                    'style' => 'margin:7px;'
                ]
            ),
            [
                'style' => 'max-height:310px; overflow-y:scroll;'
            ]
        ) . $rcMarkup . $sMarkup,
    [
        'style' => 'clear:left;float:left;margin:7px;'
    ]
);

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

        $ptAttrs = [
            'type' => 'radio',
            'name' => 'vfrbdefault'
        ];

        if ($uS->DefaultVisitFee == $r[0]) {
            $ptAttrs['checked'] = 'checked';
        } else {
            unset($ptAttrs['checked']);
        }

        $kTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($r[0], $ptAttrs),
            [
                'style' => 'text-align:center;'
            ]
        ) . HTMLTable::makeTd(HTMLInput::generateMarkup($r[1],
                        [
                            'name' => 'vfdesc[' . $r[0] . ']',
                            'size' => '16'
                        ]
                    )) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2],
                        [
                            'name' => 'vfrate[' . $r[0] . ']',
                            'size' => '6',
                            'class' => 'number-only'
                        ]
                    )));
    }

    // add empty fee row
    $kTbl->addBodyTr(HTMLTable::makeTd('',
        [
            'style' => 'text-align:center;'
        ]
    ) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                    [
                        'name' => 'vfdesc[0]',
                        'size' => '16'
                    ]
                )) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup('',
                    [
                        'name' => 'vfrate[0]',
                        'size' => '6',
                        'class' => 'number-only'
                    ]
                )));

    $visitFeesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee') . ' Amount',
            [
                'style' => 'font-weight:bold;'
            ]
    ) . $kTbl->generateMarkup(
                [
                    'style' => 'margin:7px;'
                ]
            ),
        [
            'style' => 'float:left;margin:7px;'
        ]
    );
}

// Financial Assistance Categories
$faMarkup = '';

if ($uS->IncomeRated) {

    $ratCats = [];
    $hhs = [];

    $faTbl = new HTMLTable();
    $headerTr = HTMLTable::makeTh('Household Size');

    // preload all rate categories and make header row
    $stmt = $dbh->query("select Rate_Breakpoint_Category from room_rate WHERE Rate_Breakpoint_Category != '' AND  `Status` = '" . RateStatus::Active . "' ORDER BY `Rate_Breakpoint_Category`");

    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
        $ratCats[] = $r[0];
        $headerTr .= HTMLTable::makeTh(strtoupper($r[0]));
    }

    $faTbl->addHeaderTr($headerTr);

    // Limit the breakpoints
    $catList = '';
    // Make cdl of rate categories
    foreach ($ratCats as $c) {
        if ($catList == '') {
            $catList .= "'$c'";
        } else {
            $catList .= ",'$c'";
        }
    }

    $rbRows = [];

    if ($catList != '') {
        $stmt = $dbh->query("Select * from `rate_breakpoint` where `Rate_Category` in (" . $catList . ") ORDER BY `Household_Size`, `Rate_Category`");

        //$rbRows = EditRS::select($dbh, $rbRs, array(), 'and', array($rbRs->Household_Size, $rbRs->Rate_Category));
    }

    $hhSize = 1;
    $tr = '';
    $valueCheck = 0;
    $lastBreakpoint = 0;

    // Breakpoints table
    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        //foreach ($rbRows as $r) {

        $rbRs = new Rate_BreakpointRS();
        EditRS::loadRow($r, $rbRs);

        if ($hhSize != $rbRs->Household_Size->getStoredVal()) {

            $faTbl->addBodyTr(HTMLTable::makeTd($hhSize, ['style' => 'text-align:center;']) . $tr);
            $tr = '';
            $valueCheck = 0;
            $lastBreakpoint = 0;
        }

        $attr = '';

        if ($rbRs->Rate_Category->getStoredVal() == $ratCats[count($ratCats) - 1]) {
            // Last Rate
            $symb = '> $';

            if ($lastBreakpoint == $rbRs->Breakpoint->getStoredVal()) {
                $bpStyle = '';
                $attr = 'readonly';
            } else {
                $breakpointMessage = "Bad breakpoint value(s). ";
                $bpStyle = 'color:red;';
                $attr = 'readonly';
            }

        } else if ($rbRs->Rate_Category->getStoredVal() == $ratCats[0]) {
            // First Rate
            $symb = '< $';

            if ($valueCheck < $rbRs->Breakpoint->getStoredVal()) {
                $bpStyle = '';
            } else {
                $breakpointMessage = "Bad breakpoint value(s). ";
                $bpStyle = 'color:red;';
            }

        } else {
            // Inbetween rates
            $symb = '<= $';

            if ($valueCheck <= $rbRs->Breakpoint->getStoredVal()) {
                $bpStyle = '';
            } else {
                $breakpointMessage = "Bad breakpoint value(s). ";
                $bpStyle = 'color:red;';
            }

        }

        $valueCheck = $rbRs->Breakpoint->getStoredVal();
        $lastBreakpoint = $rbRs->Breakpoint->getStoredVal();

        $tr .= HTMLTable::makeTd($symb . HTMLInput::generateMarkup(
            $rbRs->Breakpoint->getStoredVal() == 0 ? '' : number_format($rbRs->Breakpoint->getStoredVal()),
                ['name' => 'rateBp' . $rbRs->Rate_Category->getStoredVal() . '[]', 'size' => '6', 'style' => $bpStyle, $attr => '']
        )
        );

        $hhSize = $rbRs->Household_Size->getStoredVal();
    }

    // Last one
    $faTbl->addBodyTr(HTMLTable::makeTd($hhSize, ['style' => 'text-align:center;']) . $tr);

    // breakpoint error message
    if ($breakpointMessage != '') {
        $faTbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $breakpointMessage, ['style' => 'color:red;']), ['style' => 'text-align:center;', 'colspan' => (count($ratCats) + 1)]));
    }

    // Increase Household size
    $faTbl->addBodyTr(HTMLTable::makeTd('Set the total Household Size to: ' . HTMLInput::generateMarkup('', ['name' => 'incrHhSize', 'size' => '3']),
        [
            'colspan' => (count($ratCats) + 1)
        ]
    ));

    // Increase Rate Categories
    $faTbl->addBodyTr(HTMLTable::makeTd('Set the total number of rate categories (currently at ' . count($ratCats) . ') to: ' . HTMLInput::generateMarkup('', ['name' => 'incrRateCat', 'size' => '3']),
        [
            'colspan' => (count($ratCats) + 1)
        ]
    ));

    // fa calculator
    $fin = new FinAssistance($dbh, 0);
    $calcTbl = $fin->createRateCalcMarkup();

    $fcTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Financial Assistance Rate Calculator',
            [
                'style' => 'font-weight:bold;'
            ]
    ) . $calcTbl->generateMarkup(
                [
                    'style' => 'float:left;margin:7px;'
                ]
            ),
        [
            'style' => 'clear:left;float:left;margin:7px;'
        ]
    );

    $faMarkup = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Financial Assistance Breakpoints',
            [
                'style' => 'font-weight:bold;'
            ]
    ) . $faTbl->generateMarkup(
                [
                    'style' => 'float:left;margin:7px;'
                ]
            ) . $fcTable,
        [
            'style' => 'float:left;margin:7px;'
        ]
    );
}

// Key deposit options
$keysTable = '';
$rateTableTabTitle = 'Room Rates';

if ($uS->KeyDeposit) {
    $kFees = readGenLookupsPDO($dbh, 'Key_Deposit_Code');
    $kTbl = new HTMLTable();
    $kTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('Amount')); // .HTMLTable::makeTh('Delete'));

    foreach ($kFees as $r) {
        $kTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($r[1],
            [
                'name' => 'kdesc[' . $r[0] . ']',
                'size' => '16'
            ]
        )) . HTMLTable::makeTd('$' . HTMLInput::generateMarkup($r[2],
                        [
                            'name' => 'krate[' . $r[0] . ']',
                            'size' => '6',
                            'class' => 'number-only'
                        ]
                    )));
    }

    $keysTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', $labels->getString('resourceBuilder', 'keyDepositLabel', 'Key Deposit') . ' Amounts',
            [
                'style' => 'font-weight:bold;'
            ]
    ) . $kTbl->generateMarkup(
                [
                    'style' => 'margin:7px;'
                ]
            ),
        [
            'style' => 'float:left;margin:7px;'
        ]
    );

    $rateTableTabTitle .= ' & ' . $labels->getString('resourceBuilder', 'keyDepositLabel', 'Key Deposit') . 's';
}

// Payment Types
$payTypesTable = '';

if ($uS->RoomPriceModel != ItemPriceCode::None) {

    $payMethods = [];
    $stmtp = $dbh->query("select idPayment_method, Gl_Code from payment_method");
    while ($t = $stmtp->fetch(\PDO::FETCH_NUM)) {
        $payMethods[$t[0]] = $t[1];
    }
    $payMethods[''] = '';


    $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');
    $ptTbl = new HTMLTable();
    $ptTbl->addHeaderTr(HTMLTable::makeTh('Default') . HTMLTable::makeTh('Description') . HTMLTable::makeTh('GL Code'));

    foreach ($payTypes as $r) {

        $ptAttrs = [
            'type' => 'radio',
            'name' => 'ptrbdefault'
        ];

        if ($uS->DefaultPayType == $r[0]) {
            $ptAttrs['checked'] = 'checked';
        } else {
            unset($ptAttrs['checked']);
        }

        $ptTbl->addBodyTr(
            HTMLTable::makeTd(($r[0] == PayType::Invoice ? '' : HTMLInput::generateMarkup($r[0], $ptAttrs)), ['style' => 'text-align:center;'])
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r[1], ['name' => 'ptdesc[' . $r[0] . ']', 'size' => '16']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($payMethods[$r[2]], ['name' => 'ptGlCode[' . $r[2] . ']', 'size' => '19']))
        );
    }

    $payTypesTable = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Pay Types',
            [
                'style' => 'font-weight:bold;'
            ]
    ) . $ptTbl->generateMarkup(
                [
                    'style' => 'margin:7px;'
                ]
            ),
        [
            'style' => 'float:left;margin:7px;'
        ]
    );
}

// Hospitals and associations
$hospRs = new HospitalRS();
$hrows = EditRS::select($dbh, $hospRs, [], '', [$hospRs->Status, $hospRs->Title]);

$hospTypes = readGenLookupsPDO($dbh, 'Hospital_Type');

$constraints = new Constraints($dbh);
$hospConstraints = $constraints->getConstraintsByType(ConstraintType::Hospital);

$hTbl = new HTMLTable();
$hths = HTMLTable::makeTh('Id') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Type') . HTMLTable::makeTh('Description') . HTMLTable::makeTh('Color') . HTMLTable::makeTh('Text Color');
foreach ($hospConstraints as $c) {
    $hths .= HTMLTable::makeTh($c->getTitle());
}

$hths .= HTMLTable::makeTh('Last Updated') . HTMLTable::makeTh('Retire') . HTMLTable::makeTh('Hide from calendar list');
$hTbl->addHeaderTr($hths);

foreach ($hrows as $h) {

    if ($h['Title'] == '(None)' && $h['Type'] == 'a') {
        continue;
    }

    $myConsts = new ConstraintsHospital($dbh, $h['idHospital']);
    $hConst = $myConsts->getConstraints();

    $htds = HTMLTable::makeTd($h['idHospital']) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Title'],
        [
            'name' => 'hTitle[' . $h['idHospital'] . ']'
        ]
    )) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hospTypes, $h['Type'], FALSE),
                    [
                        'name' => 'hType[' . $h['idHospital'] . ']'
                    ]
                )) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Description'],
                    [
                        'name' => 'hDesc[' . $h['idHospital'] . ']',
                        'size' => '25'
                    ]
                )) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Reservation_Style'],
                    [
                        'name' => 'hColor[' . $h['idHospital'] . ']',
                        'type' => 'color',
                        'class' => 'color',
                        'size' => '5'
                    ]
                )) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Stay_Style'],
                    [
                        'name' => 'hText[' . $h['idHospital'] . ']',
                        'type' => 'color',
                        'class' => 'color',
                        'size' => '5'
                    ]
                ));

    foreach ($hConst as $a) {
        $cbAttrs = [
            'name' => 'hpattr[' . $h['idHospital'] . '][' . $a['idConstraint'] . ']',
            'type' => 'checkbox'
        ];
        if ($a['isActive'] == 1) {
            $cbAttrs['checked'] = 'checked';
        }
        $htds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $cbAttrs),
            [
                'style' => 'text-align:center;'
            ]
        );
    }

    $hdelAtr = [
        'name' => 'hdel[' . $h['idHospital'] . ']',
        'type' => 'checkbox'
    ];

    $hHideAtr = [
        'name' => 'hhide[' . $h['idHospital'] . ']',
        'type' => 'checkbox'
    ];

    $rowAtr = [];

    if ($h['Status'] == 'r') {
        $hdelAtr['checked'] = 'checked';
        $rowAtr['style'] = 'background-color:lightgray;';
    }

    if ($h['Hide']) {
        $hHideAtr['checked'] = 'checked';
    }

    $htds .= HTMLTable::makeTd(date('M j, Y', strtotime($h['Last_Updated'] == '' ? $h['Timestamp'] : $h['Last_Updated'])))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', $hdelAtr), ['style' => 'text-align:center;'])
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', $hHideAtr), ['style' => 'text-align:center;']);

    $hTbl->addBodyTr($htds, $rowAtr);
}

// new hospital
$hTbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd(HTMLInput::generateMarkup('',
    [
        'name' => 'hTitle[0]',
        'placeholder' => 'New'
    ]
)) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hospTypes, ''),
                [
                    'name' => 'hType[0]'
                ]
            )) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                [
                    'name' => 'hDesc[0]'
                ]
            )) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                [
                    'name' => 'hColor[0]',
                    'type' => 'color',
                    'size' => '5'
                ]
            )) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                [
                    'name' => 'hText[0]',
                    'type' => 'color',
                    'size' => '5'
                ]
            )) . HTMLTable::makeTd('Create New',
            [
                'colspan' => '5'
            ]
        ));

$hospTable = $hTbl->generateMarkup();

// attributes
$attributes = new Attributes($dbh);
$arows = $attributes->getAttributes();
$attrTypes = $attributes->getAttributeTypes();

$aTbl = new HTMLTable();
$aTbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Type') . HTMLTable::makeTh('Category') . HTMLTable::makeTh('Last Updated') . HTMLTable::makeTh('Delete'));

foreach ($arows as $h) {

    $aTbl->addBodyTr(HTMLTable::makeTd($h['idAttribute']) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Title'],
        [
            'name' => 'atTitle[' . $h['idAttribute'] . ']',
            'size' => '30'
        ]
    )) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($attrTypes, $h['Type'], FALSE),
                    [
                        'name' => 'atType[' . $h['idAttribute'] . ']'
                    ]
                )) . HTMLTable::makeTd(HTMLInput::generateMarkup($h['Category'],
                    [
                        'name' => 'atCat[' . $h['idAttribute'] . ']'
                    ]
                )) . HTMLTable::makeTd($h['Last_Updated'] == '' ? '' : date('M j, Y', strtotime($h['Last_Updated']))) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                    [
                        'name' => 'atdel[' . $h['idAttribute'] . ']',
                        'type' => 'checkbox'
                    ]
                )));
}

// new attribute
$aTbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd(HTMLInput::generateMarkup('',
    [
        'name' => 'atTitle[0]'
    ]
)) . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($attrTypes, ''),
                [
                    'name' => 'atType[0]'
                ]
            )) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                [
                    'name' => 'atCat[0]'
                ]
            )) . HTMLTable::makeTd('Create New',
            [
                'colspan' => '2'
            ]
        ));

$attrTable = $aTbl->generateMarkup();

// Constraints
$constraintTable = $constraints->createConstraintTable($dbh);

// Demographics Selection table
$tbl = ResourceBldr::getSelections($dbh, 'Demographics', 'm', $labels);
$demoSelections = $tbl->generateMarkup(["class" => "sortable"]);

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

$selDemos = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows, ''),
    [
        'name' => 'selDemoLookup',
        'data-type' => 'd',
        'class' => 'hhk-selLookup'
    ]
);

// Checklists Manager
$cblistSelections = '';  //Checklist::createChecklistSelectors($dbh, $labels);

$insuranceType = new InsuranceType();

// save insurance types
if (isset($_POST["insuranceTypes"])) {
    $insuranceType = new InsuranceType();
    $insuranceType->save($dbh, $_POST);
}

$insuranceType->loadInsuranceTypes($dbh);
$selInsTypes = $insuranceType->generateSelector();

if (isset($_POST["insurances"])) {
    $insurance = new Insurance();
    $return = $insurance->save($dbh, $_POST);
    if (is_array($return)) {
        echo json_encode($return);
        exit;
    }
}

$lookupErrMsg = '';

// General Lookup categories
$stmt2 = $dbh->query("select distinct `Type`, `Table_Name` from gen_lookups where `Type` in ('h','u', 'ha', 'm');");
$rows2 = $stmt2->fetchAll(\PDO::FETCH_NUM);

$lkups = [];
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

$selLookups = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($lkups, ''),
    [
        'name' => 'sellkLookup',
        'class' => 'hhk-selLookup'
    ]
);

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

$seldiscs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows3, ''),
    [
        'name' => 'seldiscs',
        'class' => 'hhk-selLookup'
    ]
);

// Misc Codes (cancel codes, etc
$rows4 = [
    [
        "ReservStatus",
        "Reservation Cancel Codes"
    ]
];

$selmisc = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows4, ''),
    [
        'name' => 'selmisc',
        'class' => 'hhk-selLookup'
    ]
);

// Items
$sitems = $dbh->query("Select  i.idItem, itm.Type_Id, i.Description, i.Gl_Code, i.Percentage, i.Last_Order_Id
    from item i left join item_type_map itm on itm.Item_Id = i.idItem");
$items = $sitems->fetchAll(\PDO::FETCH_ASSOC);

$itbl = new HTMLTable();

$ths = HTMLTable::makeTh('Description') . HTMLTable::makeTh('GL Code');
$colCounter = [];

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
        $trs .= HTMLTable::makeTd('(Additional Charges)') . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Gl_Code'],
            [
                'name' => 'txtGlCode[' . $d['idItem'] . ']'
            ]
        ));
    } else {
        $trs .= HTMLTable::makeTd(HTMLInput::generateMarkup($d['Description'],
            [
                'name' => 'txtItem[' . $d['idItem'] . ']'
            ]
        )) . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Gl_Code'],
                        [
                            'name' => 'txtGlCode[' . $d['idItem'] . ']'
                        ]
                    ));
    }

    foreach ($colCounter as $c) {

        $attrs = [
            'type' => 'checkbox',
            'name' => 'cbtax[' . $d['idItem'] . '][' . $c . ']'
        ];

        // Look for tax item connection
        foreach ($taxItemMap as $m) {
            if ($m['idItem'] == $d['idItem'] && $m['Item_Id'] == $c) {
                $attrs['checked'] = 'checked';
            }
        }

        $trs .= HTMLTable::makeTd(HTMLInput::generateMarkup($c, $attrs),
            [
                'style' => 'text-align:center;'
            ]
        );
    }

    $itbl->addBodyTr($trs);
}

$itemTable = $itbl->generateMarkup(
    [
        'style' => 'float:left;'
    ]
);

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
        $hotTaxes++;
    }

    if ($d['Last_Order_Id'] > 0) {
        $trArry['style'] = 'background-color:yellow;';
    }

    $lastId = $d['Last_Order_Id'];

    $tiTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($d['Description'],
        [
            'name' => 'txttItem[' . $d['idItem'] . ']',
            'size' => '18'
        ]
    )) . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Gl_Code'],
                    [
                        'name' => 'txttGlCode[' . $d['idItem'] . ']',
                        'size' => '18'
                    ]
                )) . HTMLTable::makeTd(HTMLInput::generateMarkup(number_format($d['Percentage'], 3),
                    [
                        'name' => 'txttPercentage[' . $d['idItem'] . ']',
                        'size' => '8'
                    ]
                )) . HTMLTable::makeTd(HTMLInput::generateMarkup($d['Timeout_Days'],
                    [
                        'name' => 'txttMaxDays[' . $d['idItem'] . ']',
                        'size' => '5'
                    ]
                )) . HTMLTable::makeTd($d['First_Order_Id']) . HTMLTable::makeTd($d['Last_Order_Id']), $trArry);
}

// New Tax item
$tiTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('',
    [
        'name' => 'txttItem[0]',
        'placeholder' => 'New Tax',
        'size' => '18'
    ]
)) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                [
                    'name' => 'txttGlCode[0]',
                    'size' => '18'
                ]
            )) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                [
                    'name' => 'txttPercentage[0]',
                    'size' => '8'
                ]
            )) . HTMLTable::makeTd(HTMLInput::generateMarkup('',
                [
                    'name' => 'txttMaxDays[0]',
                    'size' => '5'
                ]
            )));

$tiTbl->addHeaderTr(HTMLTable::makeTh($hotTaxes . ' Taxes' . (count($titems) > $hotTaxes ? ' and ' . (count($titems) - $hotTaxes) . ' Old taxes' : ''),
    [
        'colspan' => '6'
    ]
));
$tiTbl->addHeaderTr(HTMLTable::makeTh('Description') . HTMLTable::makeTh('GL Code') . HTMLTable::makeTh('Percentage') . HTMLTable::makeTh('Max Days') . HTMLTable::makeTh('First Visit') . HTMLTable::makeTh('Last Visit'));

$taxTable = $tiTbl->generateMarkup(
    [
        'style' => 'float:left;'
    ]
);

// Form Upload

$rteSelectForm = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups(readGenLookupsPDO($dbh, 'Form_Upload')), $formType, TRUE),
    [
        'name' => 'selFormUpload'
    ]
);

// Form Builder
$forms = FormTemplate::listTemplates($dbh);
$formTbl = new HTMLTable();
$formTbl->addHeaderTr(HTMLTable::makeTh('Referral Forms', ['colspan' => '4']));
$formTbl->addHeaderTr(HTMLTable::makeTh('Actions') . HTMLTable::makeTh('ID') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Status'));
if (count($forms) > 0) {
    foreach ($forms as $form) {
        $formTbl->addBodyTr(HTMLTable::makeTd('<button class="editForm hhk-btn" data-docId="' . $form['idDocument'] . '">Edit</button>') . HTMLTable::makeTd($form['idDocument']) . HTMLTable::makeTd($form['Title']) . HTMLTable::makeTd($form['Status']));
    }
}


$demogs = readGenLookupsPDO($dbh, 'Demographics');
foreach ($demogs as $key => $demog) {
    if ($demog["Substitute"] == "") { //remove disabled demogs
        unset($demogs[$key]);
    }
}

$formBuilderLabels = [
    "hospital" => $labels->getString('hospital', 'hospital', 'Hospital'),
    "guest" => $labels->getString('MemberType', 'guest', 'Guest'),
    "patient" => $labels->getString('MemberType', 'patient', 'Patient'),
    "diagnosis" => $labels->getString('hospital', 'diagnosis', 'Diagnosis'),
    "location" => $labels->getString('hospital', 'location', 'Unit'),
    "referralAgent" => $labels->getString('hospital', 'referralAgent', 'Referral Agent'),
    "treatmentStart" => $labels->getString('hospital', 'treatmentStart', 'Treatement Start'),
    "treatmentEnd" => $labels->getString('hospital', 'treatmentEnd', 'Treatment End'),
    "mrn" => $labels->getString('hospital', 'MRN', 'MRN'),
    "nickname" => $labels->getString('MemberType', 'nickname', 'Nickname'),
    "namePrefix" => $labels->getString('MemberType', 'namePrefix', 'Prefix')
];
$formBuilderOptions = [
    "county" => $uS->county,
    "doctor" => $uS->Doctor,
    "referralAgent" => $uS->ReferralAgent,
    "diagnosisDetails" => $uS->ShowDiagTB
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>
        <?php echo $wInit->pageTitle; ?>
    </title>
    <?php echo JQ_UI_CSS; ?>
    <?php echo JQ_DT_CSS; ?>
    <?php echo HOUSE_CSS; ?>
    <?php echo NOTY_CSS; ?>
    <?php echo GRID_CSS; ?>
    <?php echo FAVICON; ?>
    <?php echo NAVBAR_CSS; ?>
    <?php echo CSSVARS; ?>

    <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
    <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
    <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
    <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
    <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
    <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
    <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
    <script type="text/javascript" src="<?php echo HTMLENTITIES_JS; ?>"></script>
    <script type="text/javascript" src="../js/formBuilder/form-builder.min.js"></script>
    <script type="text/javascript" src="<?php echo FORMBUILDER_JS; ?>"></script>
    <script type="text/javascript" src="<?php echo SERIALIZEJSON; ?>"></script>
    <script type="text/javascript" src="<?php echo RESCBUILDER_JS; ?>"></script>
    <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
</head>

<body <?php if ($wInit->testVersion) {
    echo "class='testbody'";
} ?>>
    <?php echo $wInit->generatePageMenu(); ?>
    <div id="contentDiv">
        <div class="my-1 d-flex align-items-center" id="rescBuilderTitle">
			<h1 class="mr-3"><?php echo $wInit->pageHeading; ?></h1>
            <span class="p-1 ui-corner-all ui-state-highlight">Changes won't take effect until the next login</span>
        </div>

        <div id="mainTabs" style="font-size: .9em; display: none;">
            <ul>
                <li><a href="#rescTable">Resources</a></li>
                <li><a href="#roomTable">Rooms</a></li>
                <li><a href="#rateTable">
                        <?php echo $rateTableTabTitle; ?>
                    </a></li>
                <li><a href="#hospTable">
                        <?php echo $hospitalTabTitle; ?>
                    </a></li>
                <?php if ($uS->InsuranceChooser) { ?>
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
            <div id="rescTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                <form autocomplete="off">
                    <?php echo $rescTable; ?>
                </form>
            </div>
            <div id="roomTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                <form autocomplete="off">
                    <?php echo $roomTable; ?>
                </form>
            </div>
            <?php if ($uS->InsuranceChooser) { ?>
                <div id="insTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                    <div>
                        <?php echo $demoMessage; ?>
                    </div>
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
                                    <td>
                                        <?php echo $selInsTypes; ?>
                                    </td>
                                </tr>
                            </table>
                            <div id="divdemoCat"></div>
                        </form>
                    </div>
                </div>
            <?php } ?>
            <div id="demoTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                <div>
                    <?php echo $demoMessage; ?>
                </div>
                <div style="float: left;">
                    <h3>Demographic Categories</h3>
                    <form id="formdemo">
                        <div>
                            <?php echo $demoSelections; ?>
                        </div>
                        <span style="margin: 10px; float: right;"><input type="button" id='btndemoSave'
                                class="hhk-savedemoCat" data-type="h" value="Save" /></span>
                    </form>
                </div>

                <div style="float: left; margin-left: 30px;">
                    <h3>Demographics</h3>
                    <form id="formdemoCat">
                        <table>
                            <tr>
                                <th>Demographic</th>
                                <td>
                                    <?php echo $selDemos; ?>
                                </td>
                            </tr>
                        </table>
                        <div id="divdemoCat"></div>
                        <span style="margin: 10px; float: right;"><input type="button" id='btndemoSaveCat'
                                class="hhk-saveLookup" data-type="d" value="Save" /></span>
                    </form>
                </div>
<!-- 
                <div style="float: left; margin-left: 30px;">
                    <h3>Checklist Categories </h3>
                    <form id="formcblist">
                        <div>
                            <?php //echo $cblistSelections; ?>
                        </div>
                        <span style="margin: 10px; float: right;"><input type="button" id='btncblistSave'
                                class="hhk-savecblist" data-type="h" value="Save" /></span>
                    </form>
                </div>
                <div style="float: left; margin-left: 30px;">
                    <h3>Demographics</h3>
                    <form id="formcbCat">
                        <table>
                            <tr>
                                <th>Checklist</th>
                                <td>
                                    <?php echo $selChecklistItems; ?>
                                </td>
                            </tr>
                        </table>
                        <div id="divchecklistCat"></div>
                        <span style="margin: 10px; float: right;"><input type="button" id='btnChecklistSaveCat' class="hhk-saveLookup"
                                data-type="d" value="Save" /></span>
                    </form>
                </div>
 -->
            </div>
            <div id="lkTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide" style="font-size: .9em;">
                <div style="float: left;">
                    <h3>General Lookups</h3>
                    <form method="POST" action="ResourceBuilder.php" id="formlk">
                        <table>
                            <tr>
                                <th>Category</th>
                                <td>
                                    <?php echo $selLookups; ?>
                                </td>
                            </tr>
                        </table>
                        <div id="divlk" class="hhk-divLk"></div>
                        <span style="margin: 10px; float: right;">
                            <?php if (!$hasDiags) { ?>
                                <input type="submit" name='btnAddDiags' id="btnAddDiags" value="Add Diagnosis" />
                            <?php }
                            if (!$hasLocs) { ?>
                                <input type="submit" id='btnAddLocs' name="btnAddLocs" value="Add Location" />
                            <?php } ?>
                            <input type="button" id='btnlkSave' class="hhk-saveLookup" data-type="h" value="Save" />
                        </span>
                    </form>
                </div>
                <div style="float: left; margin-left: 30px;">
                    <h3>Discounts &amp; Additional Charges</h3>
                    <form method="POST" action="ResourceBuilder.php" id="formdisc">
                        <table>
                            <tr>
                                <th>Category</th>
                                <td>
                                    <?php echo $seldiscs; ?>
                                </td>
                            </tr>
                        </table>
                        <div id="divdisc" class="hhk-divLk"></div>
                        <span style="margin: 10px; float: right;">
                            <?php if (!$hasDiscounts) { ?>
                                <input type="submit" name='btnHouseDiscs' id="btnHouseDiscs" value="Add Discounts" />
                            <?php }
                            if (!$hasAddnl) { ?>
                                <input type="submit" id='btnAddnlCharge' name="btnAddnlCharge"
                                    value="Add Additional Charges" />
                            <?php } ?>
                            <input type="button" id='btndiscSave' class="hhk-saveLookup" data-type="ha" value="Save" />
                        </span>
                    </form>
                </div>
                <div style="float: left; margin-left: 30px;">
                    <h3>Miscelaneous Lookups</h3>
                    <form method="POST" action="ResourceBuilder.php" id="formmisc">
                        <table>
                            <tr>
                                <th>Category</th>
                                <td>
                                    <?php echo $selmisc; ?>
                                </td>
                            </tr>
                        </table>
                        <div id="divmisc" class="hhk-divLk"></div>
                        <span style="margin: 10px; float: right;"> <input type="button" id='btnmiscSave'
                                class="hhk-saveLookup" data-type="ha" value="Save" />
                        </span>
                    </form>
                </div>

            </div>
            <div id="rateTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                <p style="padding: 3px; background-color: #fff7db;">Make changes
                    directly into the text boxes below and press 'Save'.</p>
                <?php echo $rateTableErrorMessage; ?>
                <form method="POST" action="ResourceBuilder.php" name="form1"
                    onsubmit="return confirm('Are you sure you want to save?');">
                    <div style="clear: left; float: left;">
                        <?php echo $pricingModelTable; ?>
                    </div>
                    <?php echo $visitFeesTable . $keysTable . $payTypesTable . $feesTable . $faMarkup; ?>
                    <div style="clear: both"></div>
                    <span style="margin: 10px; float: right;"><input type="submit" id='btnkfSave' name="btnkfSave"
                            value="Save" /></span>
                </form>
            </div>
            <div id="hospTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                <form method="POST" action="ResourceBuilder.php" name="formh">
                    <?php echo $hospTable; ?>
                    <div style="clear: both"></div>
                    <span style="margin: 10px; float: right;"><input type="submit" id='btnhSave' name="btnhSave"
                            value="Save" /></span>
                </form>
            </div>
            <div id="formUpload" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                <p>Select the form to upload:
                    <?php echo $rteSelectForm; ?>
                    <span id="spnFrmLoading" style="font-style: italic; display: none;">Loading...</span>
                    <input type="button" id="btnNewForm" value="New Form" style="display:none;" />
                </p>
                <p id="rteMsg" style="float: left;" class="ui-state-highlight">
                    <?php echo $rteMsg; ?>
                </p>
                <div id="divUploadForm" style="margin-top: 1em;"></div>
            </div>
            <div id="formBuilder" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
            </div>
            <div id="itemTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                <form method="POST" action="ResourceBuilder.php" name="formitem">
                    <?php echo $itemTable; ?>
                    <div style="clear: both"></div>
                    <span style="margin: 10px; float: right;"><input type="submit" id='btnItemSave' name="btnItemSave"
                            value="Save" /></span>
                </form>
            </div>
            <div id="taxTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                <?php echo $itemMessage; ?>
                <form method="POST" action="ResourceBuilder.php" name="formtax">
                    <?php echo $taxTable; ?>
                    <div style="clear: both"></div>
                    <span style="margin: 10px; float: right;"><input type="submit" id='btnItemSave' name="btnTaxSave"
                            value="Save" /></span>
                </form>
            </div>
            <div id="attrTable" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                <form method="POST" action="ResourceBuilder.php" name="format">
                    <?php echo $attrTable; ?>
                    <div style="clear: both"></div>
                    <span style="margin: 10px; float: right;"><input type="submit" id='btnAttrSave' name="btnAttrSave"
                            value="Save" /></span>
                </form>
            </div>
            <div id="constr" class="hhk-tdbox hhk-visitdialog ui-tabs-hide">
                <form autocomplete="off">
                    <?php echo $constraintTable; ?>
                </form>
            </div>
        </div>
        <div id="divNewForm" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display: none;">
            <form method="POST" action="ResourceBuilder.php" id="formFormNew" name="formFormNew">
                <table>
                    <tr>
                        <th colspan="2"><span id="spanFrmTypeTitle"></span></th>
                    </tr>
                    <tr>
                        <th>Language or other title</th>
                        <td><input id="txtformLang" name="txtformLang" type="text" value='' /></td>
                    </tr>
                </table>
                <input type="hidden" id="hdnFormType" name="hdnFormType" />
            </form>
        </div>
        <div id="statEvents" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em;"></div>
        <input type="hidden" id='fixedRate' value="<?php echo (RoomRateCategories::Fixed_Rate_Category); ?>" />
        <input type="hidden" id='tabIndex' value="<?php echo ($tabIndex); ?>" />
        <input type="hidden" id='frmDemog' value='<?php echo json_encode($demogs); ?>' />
        <input type="hidden" id="labels" value='<?php echo json_encode($formBuilderLabels); ?>' />
        <input type="hidden" id="frmOptions" value='<?php echo json_encode($formBuilderOptions); ?>' />
    </div>
    <!-- div id="contentDiv"-->
</body>

</html>