<?php

namespace HHK;

use HHK\House\PSG;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
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

        if ($mType == VolMemberType::ReferralAgent || $mType == VolMemberType::Doctor) {

            // get duplicate names
            $stmt = $dbh->query("select
    Name_Full, count(n.idName) as `dups`, group_concat(n.idName) as `idNames`
from
    `name` n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '$mType'
where
    n.Member_Status = 'a' and n.Record_Member = 1
group by n.Name_Full having count(n.idName) > 1;");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } else if ($mType == VolMemberType::Patient) {

            $stmt = $dbh->query("select
    n.Name_Full, count(n.idName) as `dups`, group_concat(n.idName) as `idNames`
from
    `name` n join name_guest ng on n.idName = ng.idName and ng.Relationship_Code = 'slf'
    left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
    left join name_email ne on n.idName = ne.idName and n.Preferred_Email = ne.Purpose
    left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
where
    n.Member_Status in ('a','d') and n.Record_Member = 1
group by LOWER(n.Name_Full)" . $groupByStr . "
having count(n.idName) > 1
order by count(n.idName) DESC, LOWER(n.Name_Last), LOWER(n.Name_First);");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } else if ($mType == VolMemberType::Guest) {

            $stmt = $dbh->query("select
    n.Name_Full, count(n.idName) as `dups`, group_concat(n.idName) as `idNames`
from
    `name` n join name_guest ng on n.idName = ng.idName
    left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
    left join name_email ne on n.idName = ne.idName and n.Preferred_Email = ne.Purpose
    left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
where
    n.Member_Status in ('a','d') and n.Record_Member = 1
group by LOWER(n.Name_Full), ng.idPsg" . $groupByStr . "
having count(n.idName) > 1
order by count(n.idName) DESC, LOWER(n.Name_Last), LOWER(n.Name_First);");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }else if($mType == VolMemberType::Patient.VolMemberType::Guest){

            $stmt = $dbh->query("select
    n.Name_Full, count(distinct n.idName) as `dups`, group_concat(distinct n.idName) as `idNames`
from
    `name` n join name_guest ng on n.idName = ng.idName
    left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
    left join name_email ne on n.idName = ne.idName and n.Preferred_Email = ne.Purpose
    left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
where
    n.Member_Status in ('a','d') and n.Record_Member = 1
group by LOWER(n.Name_Full)" . $groupByStr . "
having count(distinct n.idName) > 1
order by count(distinct n.idName) DESC, LOWER(n.Name_Last), LOWER(n.Name_First);");

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

                $stmt = $dbh->query("select
        rg.idPsg,
        n.idName,
        n.Name_Full,
        DATE(ifnull(s.Span_Start_Date, '')) as `start`,
        DATE(ifnull(s.Span_End_Date, '')) as `end`,
        ifnull(s.idStays, 0) as idStays,
        ifnull(s.idVisit, 0) as idVisit,
        ifnull(s.Visit_Span, 0) as Visit_Span,
        ifnull(s.idRoom, 0) as idRoom,
        ifnull(s.`Status`, '') as `Status`,
        ifnull(v.idReservation, 0) as idReservation
    from
        registration rg left join
        visit v on rg.idRegistration = v.idRegistration
        left join stays s ON v.idVisit = s.idVisit and v.Span = s.Visit_Span
        left join `name` n on s.idName = n.idName
    where rg.idPsg = $idPsg
    order by idStays;");

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
            //if(SecurityComponent::is_TheAdmin() || $post['mType'] != VolMemberType::Patient.VolMemberType::Guest ){
                $markup .= HTMLInput::generateMarkup('Combine Id\'s', array('id'=>'btnCombId', 'type'=>'button', 'style'=>'margin: 10px 0 5px 0;'));
            //}else{
            //    $uS = Session::getInstance();
            //    $markup .= HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("div", "Merging Patients and Guests is not currently available, please contact NPSC at " . $uS->Error_Report_Email . " if you would like to merge Patients and Guests", array("class"=>'ui-widget ui-widget-content ui-corner-all mt-3 p-2 d-inline-block', "style"=>"max-width: 400px;")));
            //}

            $markup .= HTMLContainer::generateMarkup('div', '', array('id'=>'spnAlert', 'style'=>'color:red; margin-left:10px;'));

            foreach ($idPsgs as $p) {

                $idPsg = intval($p, 10);

                if ($idPsg < 1) {
                    continue;
                }

                $psg = new PSG($dbh, $idPsg);

                $psgStmt = $dbh->query("select ng.idName as `ID`, n.Name_Full as `Name`, rc.Description as `Patient Relationship`
                from name_guest ng
                    left join name n on ng.idName = n.idName
                    left join gen_lookups rc on ng.Relationship_Code = rc.Code and rc.Table_Name = 'Patient_Rel_Type'
                where ng.idPsg = " . $idPsg);

            $psgMembers = $psgStmt->fetchAll(\PDO::FETCH_ASSOC);

                $stmt = $dbh->query("select
            rg.idPsg,
            concat(ifnull(s.idVisit, ''), '-', ifnull(s.Visit_Span, '')) as `Visit ID`,
            ifnull(v.idReservation, '') as `Reservation ID`,
            n.idName,
            n.Name_Full as `Name`,
            ifnull(date_format(DATE(s.Span_Start_Date), '%b %e, %Y'), '') as `Span Start`,
            ifnull(date_format(DATE(s.Span_End_Date), '%b %e, %Y'), '') as `Span End`,
        ifnull(resc.Title, ifnull(s.idRoom, '')) as Room,
        ifnull(vstat.Description, ifnull(s.`Status`, '')) as `Status`
    from
            registration rg
                    left join
            visit v on rg.idRegistration = v.idRegistration
            left join
        stays s ON v.idVisit = s.idVisit and v.Span = s.Visit_Span
                    left join
            `name` n on s.idName = n.idName
            left join
                resource resc on s.idRoom = resc.idResource
            left join
                gen_lookups vstat on vstat.Table_Name = 'Visit_Status' and vstat.Code = s.Status
    where
        rg.idPsg = $idPsg
    order by idStays;");

                $markup .= HTMLContainer::generateMarkup('h3', 'Patient ID: ' . $psg->getIdPatient() . ' ' . $psg->getPatientName($dbh), array("class"=>' ui-widget-header ui-corner-top ui-state-default mt-3', 'style'=>"text-align: left;")) . 
                        HTMLContainer::generateMarkup('div',
                            HTMLContainer::generateMarkup("div",
                                HTMLContainer::generateMarkup("h4", "PSG Members", array('class'=>'pb-2')).
                                CreateMarkupFromDB::generateHTML_Table($psgMembers, 'psgMembers')
                            , array('class'=>'ui-widget ui-widget-content ui-corner-all p-2 mr-3')) .
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


        $stmt = $dbh->prepare("select
    n.idName as `Id`,
    ng.idPsg,
    's' as `Save`,
    'r' as `Remove`,
    n.Name_Full as `Name`,
    concat(na.Address_1, na.Address_2) as `Address`,
    na.City,
    na.State_Province as `St`,
    np.Phone_Num as Phone,
    ms.Description as `Status`,
    ng.Relationship_Code as `Rel`,
    n2.idName as `P id`,
    n2.Name_Full as `Patient`,
    (select count(*) from visit where idRegistration = r.idregistration) as `visits`,
    (select count(*) from stays where idName = n.idName) as `stays`,
    (select count(*) from reservation_guest where idGuest = n.idName) as `Resvs`,
    (select count(*) from link_doc where idGuest = n.idName or idPSG = ng.idPsg) as `Docs`,
    (select count(*) from report where Guest_Id = n.idName or Psg_Id = ng.idPsg) as `Incidents`
from
    `name` n
        left join
    name_address na ON n.idName = na.idName
        and n.Preferred_Mail_Address = na.Purpose
        left join
    name_phone np ON n.idName = np.idName
        and n.Preferred_Phone = np.Phone_Code
        left join
    name_guest ng ON n.idName = ng.idName
        left join
    psg ON ng.idPsg = psg.idPsg
        left join
    name n2 ON psg.idPatient = n2.idName
        left join
    registration r ON ng.idPsg = r.idPsg
        left join
    gen_lookups ms ON n.Member_Status = ms.Code and ms.Table_Name = 'mem_status'
where
    ng.Status = 'a' and LOWER(n.Name_Full) = :name and ng.idName is not null and n.Member_Status in('a', 'd') and ng.Relationship_Code = '" . RelLinkType::Self . "' " . ($idNamesStr != "" ? " and n.idName IN (" . $idNamesStr . ")" : ""));

        $stmt->execute(array(':name'=>  strtolower($name)));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function expandGuest(\PDO $dbh, $name, string $idNamesStr = "") {

        $stmt = $dbh->prepare("select
    n.idName as `Id`,
    's' as `Save`,
    'r' as `Remove`,
    n.Name_Full as `Name`,
    concat(na.Address_1, na.Address_2) as `Address`,
    na.City,
    na.State_Province as `St`,
    date_format(n.BirthDate, '%b %e, %Y') as `Birth Date`,
    np.Phone_Num as Phone,
    ne.Email as Email,
    ms.Description as `Status`,
    ng.idPsg,
    ng.Relationship_Code as `Patient Relation`,
    n2.idName as `Patient ID`,
    n2.Name_Full as `Patient`,
    (select count(*) from visit where idRegistration = r.idregistration) as `visits`,
    (select count(*) from stays where idName = n.idName) as `stays`,
    (select count(*) from reservation_guest where idGuest = n.idName) as `Resvs`,
    (select count(*) from link_doc where idGuest = n.idName) as `Docs`,
    (select count(*) from report where Guest_Id = n.idName or Psg_Id = ng.idPsg) as `Incidents`
from
    `name` n
        left join
    name_address na ON n.idName = na.idName
        and n.Preferred_Mail_Address = na.Purpose
        left join
    name_phone np ON n.idName = np.idName
        and n.Preferred_Phone = np.Phone_Code
        left join
    name_email ne ON n.idName = ne.idName
        and n.Preferred_Email = ne.Purpose
        left join
    name_guest ng ON n.idName = ng.idName
        left join
    psg ON ng.idPsg = psg.idPsg
        left join
    name n2 ON psg.idPatient = n2.idName
        left join
    registration r ON ng.idPsg = r.idPsg
        left join
    gen_lookups ms ON n.Member_Status = ms.Code and ms.Table_Name = 'mem_status'
where
    ng.Status = 'a' and LOWER(n.Name_Full) = :name and ng.idName is not null and n.Member_Status in ('a', 'd')" . ($idNamesStr != "" ? " and n.idName IN (" . $idNamesStr . ")" : "")
);

        $stmt->execute(array(':name'=>  strtolower($name)));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    public static function expandOther(\PDO $dbh, $nameLastFirst, $mType) {

        $stmt = $dbh->query("SELECT
    n.idName, n.Name_Full, np.Phone_Num, ne.Email
FROM
    name n
        LEFT JOIN
    name_phone np ON n.idName = np.idName
        LEFT JOIN
    name_email ne ON n.idName = ne.idName
        JOIN
    name_volunteer2 nv ON n.idName = nv.idName
        AND nv.Vol_Category = 'Vol_Type'
        AND nv.Vol_Code = '$mType'
WHERE
    n.Member_Status='a' and n.Name_Full = '$nameLastFirst'");

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

            $stmt = $dbh->query("SELECT Name_Last_First FROM name WHERE idName = $id");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {

                $nameLastFirst = $rows[0]['Name_Last_First'];

                if ($nameLastFirst != '') {

                    $stmt = $dbh->query("select n.idName from name n join name_volunteer2 nv on n.idName = nv.idName
where nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '" . VolMemberType::ReferralAgent . "' and n.Name_Last_First = '$nameLastFirst'");

                    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

                        if ($r[0] == $id) {
                            continue;
                        }

                        $dbh->exec("update hospital_stay set idReferralAgent = $id "
                            . "where idReferralAgent = " . $r[0]);

                        $dbh->exec("update name set Member_Status = '" . MemStatus::ToBeDeleted . "' "
                            . " where idName = " . $r[0]);

                        $reply = 'Okay';
                    }
                }
            }

        } else if ($mType == VolMemberType::Doctor && $id > 0) {
            // combine referral agents into this agent.

            $stmt = $dbh->query("SELECT Name_Last_First FROM name WHERE idName = $id");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {

                $nameLastFirst = $rows[0]['Name_Last_First'];

                if ($nameLastFirst != '') {

                    $stmt = $dbh->query("select n.idName from name n join name_volunteer2 nv on n.idName = nv.idName
where nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '" . VolMemberType::Doctor . "' and n.Name_Last_First = '$nameLastFirst'");

                    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

                        if ($r[0] == $id) {
                            continue;
                        }

                        $dbh->exec("update hospital_stay set idDoctor = $id "
                            . "where idDoctor = " . $r[0]);

                        $dbh->exec("update name set Member_Status = '" . MemStatus::ToBeDeleted . "' "
                            . " where idName = " . $r[0]);

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

        //$affRows = $dbh->exec("call combinePSG($sPsgId, $dPsgId);");
        $stmt = $dbh->query("call combinePSG($sPsgId, $dPsgId);");
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
        $query = "select idName from name_guest where idName = :idName and Relationship_Code = '" . RelLinkType::Self . "';";
        $stmt = $dbh->prepare($query);
        $stmt->execute([":idName"=>$dPsgId]);
        if($stmt->rowCount() > 0){
            return array('error'=>'Cannot remove ID ' . $dPsgId . " because they are a patient. Try removing the duplicate guest instead, or search for duplicate patients first");
        }

        $stmt = $dbh->query("call remove_dup_guest($sPsgId, $dPsgId);");
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
                    $groupBy[] = "n.BirthDate";
                    break;
                case "phone":
                    $groupBy[] = "np.Phone_Search";
                    break;
                case "email":
                    $groupBy[] = "LOWER(ne.Email)";
                    break;
                case "address":
                    $groupBy[] = "LOWER(concat(na.Address_1, na.Address_2, na.City, na.State_province, na.Postal_Code))";
                    break;
                default:
            }
        }

        return (count($groupBy) > 0 ? "," . implode(",", $groupBy) : "");
    }

}
