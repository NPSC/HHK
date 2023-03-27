<?php
namespace HHK\House\Report;

use HHK\sec\{Session};
use HHK\Tables\{EditRS, GenLookupsRS};
use HHK\TableLog\HouseLog;
use HHK\SysConst\{ReservationStatusType, GLTypeCodes};
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\{HTMLTable,HTMLInput, HTMLSelector};
use HHK\House\Insurance\Insurance;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\GLTableNames;


/**
 *
 * @author Eric
 *
 */
class ResourceBldr
{

    public static function saveArchive(\PDO $dbh, $desc, $subt, $tblName) {
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

    public static function getSelections(\PDO $dbh, $tableName, $type, $labels) {

        $uS = Session::getInstance();
        $diags = array();
        $diagCats = array();

        if ($tableName == $labels->getString('hospital', 'diagnosis', DIAGNOSIS_TABLE_NAME)) {
            $tableName = DIAGNOSIS_TABLE_NAME;
            $diagCats = readGenLookupsPDO($dbh, "Diagnosis_Category", "Description");
        } else if ($tableName == $labels->getString('hospital', 'location', LOCATION_TABLE_NAME)) {
            $tableName = LOCATION_TABLE_NAME;
        }

        // Generate selectors.
        if ($tableName == RESERV_STATUS_TABLE_NAME) {

            $lookups = readLookups($dbh, $type, 'Code', true);

            // get Cancel Codes
            foreach ($lookups as $lookup) {
                if ( isset ($lookup['Code']) && $lookup['Type'] == ReservationStatusType::Cancelled) {
                    $diags[] = $lookup;
                }
            }

        } else if($tableName == "insurance_type") {

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
        ($tableName != RESERV_STATUS_TABLE_NAME ? HTMLTable::makeTh('') : '') . HTMLTable::makeTh(count($diags) . ' Entries') . ($tableName == DIAGNOSIS_TABLE_NAME ? HTMLTable::makeTh('Category') : '') . ($type == GlTypeCodes::CA ? HTMLTable::makeTh('Amount') : '') . ($type == GlTypeCodes::HA ? HTMLTable::makeTh('Days') : '') . ($type == GlTypeCodes::Demographics && ($uS->RibbonColor == $tableName || $uS->RibbonBottomColor == $tableName) ? HTMLTable::makeTh('Colors (font, bkgrnd)') : '') . ($type == GlTypeCodes::U ? '' : ($type == GlTypeCodes::m || $tableName == RESERV_STATUS_TABLE_NAME ? HTMLTable::makeTh('Use') : HTMLTable::makeTh('Delete') . HTMLTable::makeTh('Replace With')));

        $tbl->addHeaderTr($hdrTr);

        foreach ($diags as $d) {

            $cbDelMU = '';

            if ($type == GlTypeCodes::m || ($tableName == RESERV_STATUS_TABLE_NAME && ($d['Type'] == ReservationStatusType::Cancelled))) {

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

            $tbl->addBodyTr(
                ($tableName != RESERV_STATUS_TABLE_NAME ?
                    HTMLTable::makeTd(
                        HTMLContainer::generateMarkup("span", "", array("class"=>"ui-icon ui-icon-arrowthick-2-n-s")) .
                        HTMLInput::generateMarkup($d[4], array("name"=>'txtDOrder[' . $d[0] . ']', "type"=>"hidden"))
                        , array("class"=>"sort-handle", "title"=>"Drag to sort")) : '') .
                HTMLTable::makeTd(
                    HTMLInput::generateMarkup($d[1], array('name' => 'txtDiag[' . $d[0] . ']'))
                ) .
                ($tableName == DIAGNOSIS_TABLE_NAME ?
                    HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($diagCats, $d['Substitute']), array('name' => 'selDiagCat[' . $d[0] . ']'))
                    ) : ''
                ) .

                ($type == GlTypeCodes::HA || $type == GlTypeCodes::CA || ($type == GlTypeCodes::Demographics && ($uS->RibbonColor == $tableName || $uS->RibbonBottomColor == $tableName)) ? HTMLTable::makeTd(HTMLInput::generateMarkup($d[2], array(
                'size' => '10',
                'style' => 'text-align:right;',
                'name' => 'txtDiagAmt[' . $d[0] . ']'
            ))) : '') . $cbDelMU . ($type != GlTypeCodes::m && $type != GlTypeCodes::U && $tableName != RESERV_STATUS_TABLE_NAME ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($diags, ''), array(
                'name' => 'selDiagDel[' . $d[0] . ']'
            ))) : ''));
        }

        // New Entry Markup?
        if ($type != GlTypeCodes::U && $type != GlTypeCodes::m && $tableName != RESERV_STATUS_TABLE_NAME) {
            // new entry row

            $tbl->addBodyTr(
                ($tableName != RESERV_STATUS_TABLE_NAME ?
                    HTMLTable::makeTd(
                        HTMLContainer::generateMarkup("span", "", array("class"=>"ui-icon ui-icon-arrowthick-2-n-s")) .
                        HTMLInput::generateMarkup($d[4], array("name"=>'txtDOrder[0]', "type"=>"hidden"))
                        , array("class"=>"sort-handle", "title"=>"Drag to sort")) : '') .

                HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
                'name' => 'txtDiag[0]'
            ))) . ($tableName == DIAGNOSIS_TABLE_NAME ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($diagCats, $d['Substitute']), array(
                'name' => 'selDiagCat[0]'
            ))) : '') . ($type == GlTypeCodes::HA || $type == GlTypeCodes::CA ? HTMLTable::makeTd(HTMLInput::generateMarkup('', array(
                'size' => '10',
                'style' => 'text-align:right;',
                'name' => 'txtDiagAmt[0]'
            ))) : '') . HTMLTable::makeTd('New', array(
                'colspan' => 2
            )));
        }

        return $tbl;
    }

    public static function checkLookups(\PDO $dbh, $post, $labels) {

        $uS = Session::getInstance();
        $tableName = filter_var($post['table'], FILTER_SANITIZE_STRING);

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

        if (isset($post['cmd'])) {
            $cmd = filter_var($post['cmd'], FILTER_SANITIZE_STRING);
        }

        if (isset($post['tp'])) {
            $type = filter_var($post['tp'], FILTER_SANITIZE_STRING);
        }

        // Save
        if ($cmd == 'save' && isset($post['txtDiag'])) {

            // Check for a new entry
            if (isset($_POST['txtDiag'][0]) && $_POST['txtDiag'][0] != '') {

                // new entry
                $dText = filter_var($post['txtDiag'][0], FILTER_SANITIZE_STRING);
                $aText = '';

                if(isset($post['selDiagCat'][0])){
                    $aText = filter_var($post['selDiagCat'][0], FILTER_SANITIZE_STRING);
                }

                if ($tableName == 'Patient_Rel_Type') {
                    $aText = $labels->getString('MemberType', 'visitor', 'Guest').'s';
                }

                if (isset($_POST['txtDiagAmt'][0])) {
                    $aText = filter_var($post['txtDiagAmt'][0], FILTER_SANITIZE_STRING);
                }

                $orderNumber = 0;
                if (isset($_POST['txtDOrder'][0])) {
                    $orderNumber = intval(filter_var($post['txtDOrder'][0], FILTER_SANITIZE_NUMBER_INT), 10);
                }

                // Check for an entry with the same description
                $stmt = $dbh->query("Select count(*) from gen_lookups where `Table_Name` = '$tableName' and LOWER(`Description`) = '" . strtolower($dText) . "';");
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

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

                unset($post['txtDiag'][0]);
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
            if (isset($post['txtDiagAmt'])) {

                foreach ($post['txtDiagAmt'] as $k => $a) {
                    if (is_numeric($a)) {
                        $a = floatval($a);
                    }

                    $amounts[$k] = $a;
                }
            }elseif(isset($post['selDiagCat'])){
                $amounts = filter_var_array($post['selDiagCat'], FILTER_SANITIZE_STRING);
            }

            $codeArray = filter_var_array($post['txtDiag'], FILTER_SANITIZE_STRING);
            $orderNums = (isset($post['txtDOrder']) ? filter_var_array($post['txtDOrder'], FILTER_SANITIZE_NUMBER_INT) : array());

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

                        $desc = '';
                        if (isset($post['txtDiag'][$c])) {
                            $desc = filter_var($post['txtDiag'][$c], FILTER_SANITIZE_STRING);
                        }

                        $orderNumber = 0;
                        if (isset($post['txtDOrder'][$c])) {
                            $orderNumber = intval(filter_var($post['txtDOrder'][$c], FILTER_SANITIZE_NUMBER_INT), 10);
                        }

                        $use = '';
                        if (isset($post['cbDiagDel'][$c])) {
                            $use = 'y';
                            $on = $orderNumber + 100;
                            $dbh->exec("Insert Ignore into `gen_lookups` (`Table_Name`, `Code`, `Description`, `Order`) values ('RibbonColors', '$c', '$desc', '$on');");
                        } else {
                            $dbh->exec("DELETE FROM `gen_lookups` where `Table_Name` = 'Ribbon_Colors' and `Code` = '$c';");
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
            } else if (isset($post['selmisc'])) {
                replaceLookups($dbh, $post['selmisc'], $codeArray, (isset($post['cbDiagDel']) ? $post['cbDiagDel'] : array()));
            } else {
                replaceGenLk($dbh, $tableName, $codeArray, $amounts, $orderNums, (isset($post['cbDiagDel']) ? $post['cbDiagDel'] : NULL), $rep, (isset($post['cbDiagDel']) ? $post['selDiagDel'] : array()));
            }
        }

        if($cmd == "load" && $tableName == "insurance"){
            $insurance = new Insurance();
            $insurance->loadInsurances($dbh, $type);
            echo $insurance->generateTblMarkup();
            exit();
        }

        // Generate selectors.
        if (isset($post['selmisc'])) {
            $tbl = self::getSelections($dbh, RESERV_STATUS_TABLE_NAME, $post['selmisc'], $labels);
        } else {
            $tbl = self::getSelections($dbh, $tableName, $type, $labels);
        }

        echo ($tbl->generateMarkup(array("class"=>"sortable")));
        exit();

    }
}

