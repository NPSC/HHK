<?php

namespace HHK;

use HHK\House\PSG;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\MemStatus;
use HHK\SysConst\RelLinkType;
use HHK\SysConst\VolMemberType;

/**
 * Duplicate.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Duplicate
 *
 * @author Eric
 */

class Duplicate {

    protected static function getNameDuplicates(\PDO $dbh, $mType, array $filters) {

        $rows = array();

        $groupByStr = self::buildGroupBy($filters);
        $whereStr = self::buildWhere($filters);

        if ($mType == VolMemberType::ReferralAgent || $mType == VolMemberType::Doctor) {

            // get duplicate names
            $stmt = $dbh->prepare("SELECT
    `Name_Full`, COUNT(`n`.`idName`) AS `dups`, GROUP_CONCAT(`n`.`idName`) AS `idNames`
FROM
    `name` `n` JOIN `name_volunteer2` `nv` ON `n`.`idName` = `nv`.`idName` AND `nv`.`Vol_Category` = 'Vol_Type' AND `nv`.`Vol_Code` = :mType
WHERE
    `n`.`Member_Status` = 'a' AND `n`.`Record_Member` = 1
GROUP BY `n`.`Name_Full` HAVING COUNT(`n`.`idName`) > 1;");
            $stmt->execute([':mType' => $mType]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } else if ($mType == VolMemberType::Patient) {

            $stmt = $dbh->prepare("SELECT
    `n`.`Name_Full`, COUNT(`n`.`idName`) AS `dups`, GROUP_CONCAT(`n`.`idName`) AS `idNames`
FROM
    `name` `n` JOIN `name_guest` `ng` ON `n`.`idName` = `ng`.`idName` AND `ng`.`Relationship_Code` = 'slf'
    LEFT JOIN `name_phone` `np` ON `n`.`idName` = `np`.`idName` AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
    LEFT JOIN `name_email` `ne` ON `n`.`idName` = `ne`.`idName` AND `n`.`Preferred_Email` = `ne`.`Purpose`
    LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName` AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
WHERE
    `n`.`Member_Status` IN ('a','d') AND `n`.`Record_Member` = 1 " . $whereStr . "
GROUP BY LOWER(`n`.`Name_Full`)" . $groupByStr . "
HAVING COUNT(`n`.`idName`) > 1
ORDER BY COUNT(`n`.`idName`) DESC, LOWER(`n`.`Name_Last`), LOWER(`n`.`Name_First`);");
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } else if ($mType == VolMemberType::Guest) {

            $stmt = $dbh->prepare("SELECT
    `n`.`Name_Full`, COUNT(`n`.`idName`) AS `dups`, GROUP_CONCAT(`n`.`idName`) AS `idNames`
FROM
    `name` `n` JOIN `name_guest` `ng` ON `n`.`idName` = `ng`.`idName`
    LEFT JOIN `name_phone` `np` ON `n`.`idName` = `np`.`idName` AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
    LEFT JOIN `name_email` `ne` ON `n`.`idName` = `ne`.`idName` AND `n`.`Preferred_Email` = `ne`.`Purpose`
    LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName` AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
WHERE
    `n`.`Member_Status` IN ('a','d') AND `n`.`Record_Member` = 1 " . $whereStr . "
GROUP BY LOWER(`n`.`Name_Full`), `ng`.`idPsg`" . $groupByStr . "
HAVING COUNT(`n`.`idName`) > 1
ORDER BY COUNT(`n`.`idName`) DESC, LOWER(`n`.`Name_Last`), LOWER(`n`.`Name_First`);");
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }else if($mType == VolMemberType::Patient.VolMemberType::Guest){

            $stmt = $dbh->prepare("SELECT
    `n`.`Name_Full`, COUNT(DISTINCT `n`.`idName`) AS `dups`, GROUP_CONCAT(DISTINCT `n`.`idName`) AS `idNames`
FROM
    `name` `n` JOIN `name_guest` `ng` ON `n`.`idName` = `ng`.`idName`
    LEFT JOIN `name_phone` `np` ON `n`.`idName` = `np`.`idName` AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
    LEFT JOIN `name_email` `ne` ON `n`.`idName` = `ne`.`idName` AND `n`.`Preferred_Email` = `ne`.`Purpose`
    LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName` AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
WHERE
    `n`.`Member_Status` IN ('a','d') AND `n`.`Record_Member` = 1 " . $whereStr . "
GROUP BY LOWER(`n`.`Name_Full`)" . $groupByStr . "
HAVING COUNT(DISTINCT `n`.`idName`) > 1
ORDER BY COUNT(DISTINCT `n`.`idName`) DESC, LOWER(`n`.`Name_Last`), LOWER(`n`.`Name_First`);");
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }

        return $rows;

    }


    public static function expand(\PDO $dbh, $fullName, $post, $relLinkTypes) {

        $markup = '';

        $idNamesStr = (isset($post['idnames']) ? filter_var($post['idnames'], FILTER_SANITIZE_SPECIAL_CHARS) : "");
        $idNamesAr = explode(",", $idNamesStr);
        $sanitizedIdNames = (is_array($idNamesAr) && count($idNamesAr) > 0 ? filter_var_array($idNamesAr, FILTER_SANITIZE_NUMBER_INT) : []);
        $sanitizedIdNameStr = implode(",", $sanitizedIdNames);

        if ($post['mType'] == VolMemberType::ReferralAgent) {

            $markup = Duplicate::expandOther($dbh, $fullName, VolMemberType::ReferralAgent);

        } else if ($post['mType'] == VolMemberType::Doctor) {

            $markup = Duplicate::expandOther($dbh, $fullName, VolMemberType::Doctor);

        } else if ($post['mType'] == VolMemberType::Patient) {

            // Expand this selection
            $expansion = Duplicate::expandPatient($dbh, $fullName, $sanitizedIdNameStr);
            $data = array();
            $idPsgs = array();

            foreach ($expansion as $d) {

                $id = $d['Id'];

                $idPsgs[$d['idPsg']] = $d['idPsg'];

                $d['Id'] = HTMLContainer::generateMarkup('a', $d['Id'], array('href'=>'NameEdit.php?id=' . $d['Id']));
                $d['P id'] = HTMLContainer::generateMarkup('a', $d['P id'], array('href'=>'NameEdit.php?id=' . $d['P id']));
                $d['Save'] = HTMLInput::generateMarkup($d['idPsg'], array('type'=>'radio', 'name'=>'rbgood', 'id'=>'g'.$d['idPsg']));
                $d['Remove'] = HTMLInput::generateMarkup($d['idPsg'], array('type'=>'radio', 'name'=>'rbbad', 'id'=>'b'.$d['idPsg']));
                $d['Rel'] = $relLinkTypes[$d['Rel']][1];

                $data[] = $d;
            }



            $markup = CreateMarkupFromDB::generateHTML_Table($data, 'pickId');

            $markup .= HTMLInput::generateMarkup('Combine PSG\'s', array('id'=>'btnCombPSG', 'type'=>'button', 'style'=>'margin: 10px 0 5px 0;'));
            $markup .= HTMLContainer::generateMarkup('div', '', array('id'=>'spnAlert', 'style'=>'color:red; margin-left:10px;'));

            foreach ($idPsgs as $p) {

                $idPsg = intval($p, 10);

                if ($idPsg < 1) {
                    continue;
                }

                $stmt = $dbh->prepare("SELECT
        `rg`.`idPsg`,
        `n`.`idName`,
        `n`.`Name_Full`,
        DATE(IFNULL(`s`.`Span_Start_Date`, '')) AS `start`,
        DATE(IFNULL(`s`.`Span_End_Date`, '')) AS `end`,
        IFNULL(`s`.`idStays`, 0) AS `idStays`,
        IFNULL(`s`.`idVisit`, 0) AS `idVisit`,
        IFNULL(`s`.`Visit_Span`, 0) AS `Visit_Span`,
        IFNULL(`s`.`idRoom`, 0) AS `idRoom`,
        IFNULL(`s`.`Status`, '') AS `Status`,
        IFNULL(`v`.`idReservation`, 0) AS `idReservation`
    FROM
        `registration` `rg` LEFT JOIN
        `visit` `v` ON `rg`.`idRegistration` = `v`.`idRegistration`
        LEFT JOIN `stays` `s` ON `v`.`idVisit` = `s`.`idVisit` AND `v`.`Span` = `s`.`Visit_Span`
        LEFT JOIN `name` `n` ON `s`.`idName` = `n`.`idName`
    WHERE `rg`.`idPsg` = :idPsg
    ORDER BY `idStays`;");
                $stmt->execute([':idPsg' => $idPsg]);

                $markup .= HTMLContainer::generateMarkup('div',
                            CreateMarkupFromDB::generateHTML_Table($stmt->fetchAll(\PDO::FETCH_ASSOC), 'idPsg')
                            , array('style'=>'margin:5px;'));
            }

        }else if($post['mType'] == VolMemberType::Guest || $post['mType'] == VolMemberType::Patient.VolMemberType::Guest) {

            // Expand this selection
            $expansion = Duplicate::expandGuest($dbh, $fullName, $sanitizedIdNameStr);
            $data = array();

            $idPsgs = array();

            foreach ($expansion as $d) {


                $id = $d['Id'];

                $idPsgs[$d['idPsg']] = $d['idPsg'];

                $d['Id'] = HTMLContainer::generateMarkup('a', $d['Id'], array('href'=>'NameEdit.php?id=' . $d['Id']));
                $d['Patient ID'] = HTMLContainer::generateMarkup('a', $d['Patient ID'], array('href'=>'NameEdit.php?id=' . $d['Patient ID']));

                $d['Save'] = HTMLInput::generateMarkup($id, array('type'=>'radio', 'name'=>'rbsave', 'id'=>'s'.$id));

                if ($d['Patient Relation'] == RelLinkType::Self) {
                    $d['Remove'] = '-';
                } else {
                    $d['Remove'] = HTMLInput::generateMarkup($id, array('type'=>'radio', 'name'=>'rbremove', 'id'=>'r'.$id));
                }

                $d['Patient Relation'] = (isset($relLinkTypes[$d['Patient Relation']][1]) ? $relLinkTypes[$d['Patient Relation']][1] : '');

                $data[] = $d;
            }



            $markup = CreateMarkupFromDB::generateHTML_Table($data, 'pickId');
            $markup .= HTMLInput::generateMarkup('Combine Id\'s', array('id'=>'btnCombId', 'type'=>'button', 'style'=>'margin: 10px 0 5px 0;'));

            $markup .= HTMLContainer::generateMarkup('div', '', array('id'=>'spnAlert', 'style'=>'color:red; margin-left:10px;'));

            foreach ($idPsgs as $p) {

                $idPsg = intval($p, 10);

                if ($idPsg < 1) {
                    continue;
                }

                $psg = new PSG($dbh, $idPsg);

                $psgStmt = $dbh->prepare("SELECT `ng`.`idName` AS `ID`, `n`.`Name_Full` AS `Name`, `rc`.`Description` AS `Patient Relationship`
                FROM `name_guest` `ng`
                    LEFT JOIN `name` `n` ON `ng`.`idName` = `n`.`idName`
                    LEFT JOIN `gen_lookups` `rc` ON `ng`.`Relationship_Code` = `rc`.`Code` AND `rc`.`Table_Name` = 'Patient_Rel_Type'
                WHERE `ng`.`idPsg` = :idPsg");
                $psgStmt->execute([':idPsg' => $idPsg]);

            $psgMembers = $psgStmt->fetchAll(\PDO::FETCH_ASSOC);

                $stmt = $dbh->prepare("SELECT
            `rg`.`idPsg`,
            CONCAT(IFNULL(`s`.`idVisit`, ''), '-', IFNULL(`s`.`Visit_Span`, '')) AS `Visit ID`,
            IFNULL(`v`.`idReservation`, '') AS `Reservation ID`,
            `n`.`idName`,
            `n`.`Name_Full` AS `Name`,
            IFNULL(DATE_FORMAT(DATE(`s`.`Span_Start_Date`), '%b %e, %Y'), '') AS `Span Start`,
            IFNULL(DATE_FORMAT(DATE(`s`.`Span_End_Date`), '%b %e, %Y'), '') AS `Span End`,
        IFNULL(`resc`.`Title`, IFNULL(`s`.`idRoom`, '')) AS `Room`,
        IFNULL(`vstat`.`Description`, IFNULL(`s`.`Status`, '')) AS `Status`
    FROM
            `registration` `rg`
                    LEFT JOIN
            `visit` `v` ON `rg`.`idRegistration` = `v`.`idRegistration`
            LEFT JOIN
        `stays` `s` ON `v`.`idVisit` = `s`.`idVisit` AND `v`.`Span` = `s`.`Visit_Span`
                    LEFT JOIN
            `name` `n` ON `s`.`idName` = `n`.`idName`
            LEFT JOIN
                `resource` `resc` ON `s`.`idRoom` = `resc`.`idResource`
            LEFT JOIN
                `gen_lookups` `vstat` ON `vstat`.`Table_Name` = 'Visit_Status' AND `vstat`.`Code` = `s`.`Status`
    WHERE
        `rg`.`idPsg` = :idPsg
    ORDER BY `idStays`;");
                $stmt->execute([':idPsg' => $idPsg]);

                $markup .= HTMLContainer::generateMarkup('h3', 'Patient ID: ' . $psg->getIdPatient() . ' ' . $psg->getPatientName($dbh), array("class"=>' ui-widget-header ui-corner-top ui-state-default mt-3', 'style'=>"text-align: left;")) . 
                        HTMLContainer::generateMarkup('div',
                            HTMLContainer::generateMarkup("div",
                                HTMLContainer::generateMarkup("h4", "PSG Members", array('class'=>'pb-2')).
                                CreateMarkupFromDB::generateHTML_Table($psgMembers, 'psgMembers')
                            , array('class'=>'ui-widget ui-widget-content ui-corner-all p-2 me-3')) .
                            HTMLContainer::generateMarkup("div",
                                HTMLContainer::generateMarkup("h4", "Stays", array('class'=>'pb-2')).
                                CreateMarkupFromDB::generateHTML_Table($stmt->fetchAll(\PDO::FETCH_ASSOC), 'idPsg')
                            , array('class'=>'ui-widget ui-widget-content ui-corner-all p-2'))
                        , array('class'=>'ui-widget ui-widget-content ui-corner-bottom p-2 hhk-flex'));
            }

        }
        
        return $markup;
    }

    protected static function expandPatient(\PDO $dbh, $name, string $idNamesStr = "") {

        $idNameParams = [];
        $idNameClause = "";

        if ($idNamesStr != "") {
            $idNamePlaceholders = [];
            foreach (explode(",", $idNamesStr) as $idx => $idNameVal) {
                $ph = ":idn" . $idx;
                $idNamePlaceholders[] = $ph;
                $idNameParams[$ph] = $idNameVal;
            }
            $idNameClause = " AND `n`.`idName` IN (" . implode(",", $idNamePlaceholders) . ")";
        }

        $stmt = $dbh->prepare("SELECT
    `n`.`idName` AS `Id`,
    `ng`.`idPsg`,
    's' AS `Save`,
    'r' AS `Remove`,
    `n`.`Name_Full` AS `Name`,
    CONCAT(`na`.`Address_1`, `na`.`Address_2`) AS `Address`,
    `na`.`City`,
    `na`.`State_Province` AS `St`,
    CASE WHEN `n`.`Preferred_Phone` = 'no' THEN 'No Phone' ELSE IFNULL(`np`.`Phone_Num`, '') END AS `Phone`,
    `ms`.`Description` AS `Status`,
    `ng`.`Relationship_Code` AS `Rel`,
    `n2`.`idName` AS `P id`,
    `n2`.`Name_Full` AS `Patient`,
    (SELECT COUNT(*) FROM `visit` WHERE `idRegistration` = `r`.`idregistration`) AS `visits`,
    (SELECT COUNT(*) FROM `stays` WHERE `idName` = `n`.`idName`) AS `stays`,
    (SELECT COUNT(*) FROM `reservation_guest` WHERE `idGuest` = `n`.`idName`) AS `Resvs`,
    (SELECT COUNT(*) FROM `link_doc` WHERE `idGuest` = `n`.`idName` OR `idPSG` = `ng`.`idPsg`) AS `Docs`,
    (SELECT COUNT(*) FROM `incident_report` WHERE `Guest_Id` = `n`.`idName` OR `Psg_Id` = `ng`.`idPsg`) AS `Incidents`
FROM
    `name` `n`
        LEFT JOIN
    `name_address` `na` ON `n`.`idName` = `na`.`idName`
        AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
        LEFT JOIN
    `name_phone` `np` ON `n`.`idName` = `np`.`idName`
        AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
        LEFT JOIN
    `name_guest` `ng` ON `n`.`idName` = `ng`.`idName`
        LEFT JOIN
    `psg` ON `ng`.`idPsg` = `psg`.`idPsg`
        LEFT JOIN
    `name` `n2` ON `psg`.`idPatient` = `n2`.`idName`
        LEFT JOIN
    `registration` `r` ON `ng`.`idPsg` = `r`.`idPsg`
        LEFT JOIN
    `gen_lookups` `ms` ON `n`.`Member_Status` = `ms`.`Code` AND `ms`.`Table_Name` = 'mem_status'
WHERE
    `ng`.`Status` = 'a' AND LOWER(`n`.`Name_Full`) = :name AND `ng`.`idName` IS NOT NULL AND `n`.`Member_Status` IN ('a', 'd') AND `ng`.`Relationship_Code` = :relCode" . $idNameClause);

        $stmt->execute(array(':name' => strtolower($name), ':relCode' => RelLinkType::Self) + $idNameParams);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function expandGuest(\PDO $dbh, $name, string $idNamesStr = "") {

        $idNameParams = [];
        $idNameClause = "";

        if ($idNamesStr != "") {
            $idNamePlaceholders = [];
            foreach (explode(",", $idNamesStr) as $idx => $idNameVal) {
                $ph = ":idn" . $idx;
                $idNamePlaceholders[] = $ph;
                $idNameParams[$ph] = $idNameVal;
            }
            $idNameClause = " AND `n`.`idName` IN (" . implode(",", $idNamePlaceholders) . ")";
        }

        $stmt = $dbh->prepare("SELECT
    `n`.`idName` AS `Id`,
    's' AS `Save`,
    'r' AS `Remove`,
    `n`.`Name_Full` AS `Name`,
    CONCAT(`na`.`Address_1`, `na`.`Address_2`) AS `Address`,
    `na`.`City`,
    `na`.`State_Province` AS `St`,
    DATE_FORMAT(`n`.`BirthDate`, '%b %e, %Y') AS `Birth Date`,
    CASE WHEN `n`.`Preferred_Phone` = 'no' THEN 'No Phone' ELSE IFNULL(`np`.`Phone_Num`, '') END AS `Phone`,
    CASE WHEN `n`.`Preferred_Email` = 'no' THEN 'No Email' ELSE IFNULL(`ne`.`Email`, '') END AS `Email`,
    `ms`.`Description` AS `Status`,
    `ng`.`idPsg`,
    `ng`.`Relationship_Code` AS `Patient Relation`,
    `n2`.`idName` AS `Patient ID`,
    `n2`.`Name_Full` AS `Patient`,
    (SELECT COUNT(*) FROM `visit` WHERE `idRegistration` = `r`.`idregistration`) AS `visits`,
    (SELECT COUNT(*) FROM `stays` WHERE `idName` = `n`.`idName`) AS `stays`,
    (SELECT COUNT(*) FROM `reservation_guest` WHERE `idGuest` = `n`.`idName`) AS `Resvs`,
    (SELECT COUNT(*) FROM `link_doc` WHERE `idGuest` = `n`.`idName`) AS `Docs`,
    (SELECT COUNT(*) FROM `incident_report` WHERE `Guest_Id` = `n`.`idName` OR `Psg_Id` = `ng`.`idPsg`) AS `Incidents`
FROM
    `name` `n`
        LEFT JOIN
    `name_address` `na` ON `n`.`idName` = `na`.`idName`
        AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
        LEFT JOIN
    `name_phone` `np` ON `n`.`idName` = `np`.`idName`
        AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
        LEFT JOIN
    `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
        AND `n`.`Preferred_Email` = `ne`.`Purpose`
        LEFT JOIN
    `name_guest` `ng` ON `n`.`idName` = `ng`.`idName`
        LEFT JOIN
    `psg` ON `ng`.`idPsg` = `psg`.`idPsg`
        LEFT JOIN
    `name` `n2` ON `psg`.`idPatient` = `n2`.`idName`
        LEFT JOIN
    `registration` `r` ON `ng`.`idPsg` = `r`.`idPsg`
        LEFT JOIN
    `gen_lookups` `ms` ON `n`.`Member_Status` = `ms`.`Code` AND `ms`.`Table_Name` = 'mem_status'
WHERE
    `ng`.`Status` = 'a' AND LOWER(`n`.`Name_Full`) = :name AND `ng`.`idName` IS NOT NULL AND `n`.`Member_Status` IN ('a', 'd')" . $idNameClause
);

        $stmt->execute(array(':name' => strtolower($name)) + $idNameParams);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    public static function expandOther(\PDO $dbh, $nameLastFirst, $mType) {

        $stmt = $dbh->prepare("SELECT
    `n`.`idName`, `n`.`Name_Full`,
    CASE WHEN `n`.`Preferred_Phone` = 'no' THEN 'No Phone' ELSE IFNULL(`np`.`Phone_Num`, '') END AS `Phone_Num`,
    CASE WHEN `n`.`Preferred_Email` = 'no' THEN 'No Email' ELSE IFNULL(`ne`.`Email`, '') END AS `Email`
FROM
    `name` `n`
        LEFT JOIN
    `name_phone` `np` ON `n`.`idName` = `np`.`idName`
        LEFT JOIN
    `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
        JOIN
    `name_volunteer2` `nv` ON `n`.`idName` = `nv`.`idName`
        AND `nv`.`Vol_Category` = 'Vol_Type'
        AND `nv`.`Vol_Code` = :mType
WHERE
    `n`.`Member_Status`='a' AND `n`.`Name_Full` = :nameLastFirst");
        $stmt->execute([':mType' => $mType, ':nameLastFirst' => $nameLastFirst]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup($r['idName'], array('type'=>'radio', 'id'=>'rb'. $r['idName'], 'name'=>'rbchoose')))
                .HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('a', $r['idName'], array('href'=>'NameEdit.php?id='.$r['idName']))
                    . HTMLTable::makeTd($r['Name_Full']). HTMLTable::makeTd($r['Phone_Num']) . HTMLTable::makeTd($r['Email'])));
        }

        $tbl->addHeaderTr(HTMLTable::makeTh('Keep') . HTMLTable::makeTh('Id'). HTMLTable::makeTh('Name'). HTMLTable::makeTh('Phone'). HTMLTable::makeTh('Email'));

        return $tbl->generateMarkup() . HTMLInput::generateMarkup('Combine', array('id'=>'btnCombine', 'type'=>'button', 'data-type'=> $mType, 'style'=>'margin-top: 10px'));
    }

    public static function listNames(\PDO $dbh, $mType, array $filters) {

        $msg = self::getNameDuplicates($dbh, $mType, $filters);

        if (count($msg) > 0) {

            $data = array();

            foreach ($msg as $d) {

                $data[] = array('Name' => HTMLInput::generateMarkup($d['Name_Full'], array('type' => 'button', 'data-type' => $mType, 'data-fn' => $d['Name_Full'], 'data-idnames'=> $d['idNames'], 'class' => 'hhk-expand', 'title' => 'Click to expand')), 'Count' => $d['dups']);
            }

            $markup = HTMLContainer::generateMarkup('p', count($msg) . ' records');
            $markup .= CreateMarkupFromDB::generateHTML_Table($data, 'dupNames');

        } else {

            $markup = HTMLContainer::generateMarkup('h3', 'No records found.');
        }

        return $markup;
    }

    public static function combine(\PDO $dbh, $mType, $id) {

        $reply = '';

        if ($mType == VolMemberType::ReferralAgent && $id > 0) {
            // combine referral agents into this agent.

            $stmt = $dbh->prepare("SELECT `Name_Last_First` FROM `name` WHERE `idName` = :id");
            $stmt->execute([':id' => $id]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {

                $nameLastFirst = $rows[0]['Name_Last_First'];

                if ($nameLastFirst != '') {

                    $stmt = $dbh->prepare("SELECT `n`.`idName` FROM `name` `n` JOIN `name_volunteer2` `nv` ON `n`.`idName` = `nv`.`idName`
WHERE `nv`.`Vol_Category` = 'Vol_Type' AND `nv`.`Vol_Code` = :volCode AND `n`.`Name_Last_First` = :nameLastFirst");
                    $stmt->execute([':volCode' => VolMemberType::ReferralAgent, ':nameLastFirst' => $nameLastFirst]);

                    $updHospStmt = $dbh->prepare("UPDATE `hospital_stay` SET `idReferralAgent` = :id WHERE `idReferralAgent` = :oldId");
                    $updNameStmt = $dbh->prepare("UPDATE `name` SET `Member_Status` = :status WHERE `idName` = :oldId");

                    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

                        if ($r[0] == $id) {
                            continue;
                        }

                        $updHospStmt->execute([':id' => $id, ':oldId' => $r[0]]);

                        $updNameStmt->execute([':status' => MemStatus::ToBeDeleted, ':oldId' => $r[0]]);

                        $reply = 'Okay';
                    }
                }
            }

        } else if ($mType == VolMemberType::Doctor && $id > 0) {
            // combine referral agents into this agent.

            $stmt = $dbh->prepare("SELECT `Name_Last_First` FROM `name` WHERE `idName` = :id");
            $stmt->execute([':id' => $id]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {

                $nameLastFirst = $rows[0]['Name_Last_First'];

                if ($nameLastFirst != '') {

                    $stmt = $dbh->prepare("SELECT `n`.`idName` FROM `name` `n` JOIN `name_volunteer2` `nv` ON `n`.`idName` = `nv`.`idName`
WHERE `nv`.`Vol_Category` = 'Vol_Type' AND `nv`.`Vol_Code` = :volCode AND `n`.`Name_Last_First` = :nameLastFirst");
                    $stmt->execute([':volCode' => VolMemberType::Doctor, ':nameLastFirst' => $nameLastFirst]);

                    $updHospStmt = $dbh->prepare("UPDATE `hospital_stay` SET `idDoctor` = :id WHERE `idDoctor` = :oldId");
                    $updNameStmt = $dbh->prepare("UPDATE `name` SET `Member_Status` = :status WHERE `idName` = :oldId");

                    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

                        if ($r[0] == $id) {
                            continue;
                        }

                        $updHospStmt->execute([':id' => $id, ':oldId' => $r[0]]);

                        $updNameStmt->execute([':status' => MemStatus::ToBeDeleted, ':oldId' => $r[0]]);

                        $reply = 'Okay';
                    }
                }
            }
        }

        return $reply;
    }

    public static function combinePsg(\PDO $dbh, $savePsgId, $deletePsgId) {

        $sPsgId = intval($savePsgId, 10);
        $dPsgId = intval($deletePsgId, 10);

        if ($sPsgId == 0 || $dPsgId == 0) {
            return array('error'=>'One or the other PSG id is 0.  No action.');
        }

        if ($sPsgId == $dPsgId) {
            return array('error'=>'Good and Bad are the same.  No action.');
        }

        $stmt = $dbh->prepare("CALL `combinePSG`(:sPsgId, :dPsgId);");
        $stmt->execute([':sPsgId' => $sPsgId, ':dPsgId' => $dPsgId]);
        $rtn = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return (isset($rtn[0])? $rtn[0]: array('error'=>'Query failed'));

    }

    public static function combineId(\PDO $dbh, $saveId, $deleteId) {

        $sPsgId = intval($saveId, 10);
        $dPsgId = intval($deleteId, 10);

        if ($sPsgId == 0 || $dPsgId == 0) {
            return array('error'=>'One or the other Id id is 0.  No action.');
        }

        if ($sPsgId == $dPsgId) {
            return array('error'=>'Save and Remove are the same.  No action.');
        }

        //check if deleteId is patient
        $query = "SELECT `idName` FROM `name_guest` WHERE `idName` = :idName AND `Relationship_Code` = :relCode;";
        $stmt = $dbh->prepare($query);
        $stmt->execute([":idName" => $dPsgId, ":relCode" => RelLinkType::Self]);
        if($stmt->rowCount() > 0){
            return array('error'=>'Cannot remove ID ' . $dPsgId . " because they are a patient. Try removing the duplicate guest instead, or search for duplicate patients first");
        }

        $stmt = $dbh->prepare("CALL `remove_dup_guest`(:sPsgId, :dPsgId);");
        $stmt->execute([':sPsgId' => $sPsgId, ':dPsgId' => $dPsgId]);
        $rtn = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return (isset($rtn[0])? $rtn[0]: array('error'=>'Query failed'));

    }

    /**
     * Create a query string of group by's given an array of filter checkboxes
     * 
     * @param array $filters
     * @return string
     */
    protected static function buildGroupBy(array $filters){
        $groupBy = [];
        foreach($filters as $filter){
            switch($filter){
                case "birthdate":
                    $groupBy[] = "`n`.`BirthDate`";
                    break;
                case "phone":
                    $groupBy[] = "`np`.`Phone_Search`";
                    break;
                case "email":
                    $groupBy[] = "LOWER(`ne`.`Email`)";
                    break;
                case "address":
                    $groupBy[] = "LOWER(CONCAT(`na`.`Address_1`, `na`.`Address_2`, `na`.`City`, `na`.`State_province`, `na`.`Postal_Code`))";
                    break;
                default:
            }
        }

        return (count($groupBy) > 0 ? "," . implode(",", $groupBy) : "");
    }

    protected static function buildWhere(array $filters){
        $where = [];
        foreach($filters as $filter){
            switch($filter){
                case "birthdate":
                    $where[] = "`n`.`BirthDate` != ''";
                    break;
                case "phone":
                    $where[] = "`np`.`Phone_Search` != ''";
                    break;
                case "email":
                    $where[] = "LOWER(`ne`.`Email`) != ''";
                    break;
                case "address":
                    $where[] = "LOWER(CONCAT(`na`.`Address_1`, `na`.`Address_2`, `na`.`City`, `na`.`State_province`, `na`.`Postal_Code`)) != ''";
                    break;
                default:
            }
        }

        return (count($where) > 0 ? " AND " . implode(" AND ", $where) : "");
    }

}
