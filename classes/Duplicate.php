<?php

namespace HHK;

use HHK\SysConst\{VolMemberType, RelLinkType, MemStatus};
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};

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

    protected static function getNameDuplicates(\PDO $dbh, $mType) {

        $rows = array();

        if ($mType == VolMemberType::ReferralAgent || $mType == VolMemberType::Doctor) {

            // get duplicate names
            $stmt = $dbh->query("select
    Name_Full, count(n.idName) as `dups`
from
    `name` n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '$mType'
where
    n.Member_Status = 'a' and n.Record_Member = 1
group by n.Name_Full having count(n.idName) > 1;");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } else if ($mType == VolMemberType::Patient) {

            $stmt = $dbh->query("select
    n.Name_Full, count(n.idName) as `dups`
from
    `name` n join name_guest ng on n.idName = ng.idName and ng.Relationship_Code = 'slf'
where
    n.Member_Status in ('a','d') and n.Record_Member = 1
group by LOWER(n.Name_Full)
having count(n.idName) > 1
order by count(n.idName) DESC, LOWER(n.Name_Last), LOWER(n.Name_First);");

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                //$name = $r['Name_Full'];

//                $stmt2 = $dbh->query("select
//        n.idName as `Id`,
//        n.Name_Full as `Name`,
//        concat(na.City, na.State_Province) as `adr`
//    from
//        `name` n
//            left join
//        name_address na ON n.idName = na.idName
//            and n.Preferred_Mail_Address = na.Purpose
//            left join
//        name_guest ng ON n.idName = ng.idName
//    where
//        ng.Status = 'a' and LOWER(n.Name_Full) = '" . strtolower($name) . "' and ng.idName is not null and n.Member_Status = 'a'
//    group by LOWER(adr)
//    having count(n.idName) > 1");
//
//                $pats = $stmt2->fetchAll(PDO::FETCH_ASSOC);
//
//                if (count($pats) > 0) {
                    $rows[] = $r;
//                }

            }

        } else if ($mType == VolMemberType::Guest) {

            $stmt = $dbh->query("select
    n.Name_Full, count(n.idName) as `dups`
from
    `name` n join name_guest ng on n.idName = ng.idName
where
    n.Member_Status in ('a','d') and n.Record_Member = 1
group by LOWER(n.Name_Full), ng.idPsg
having count(n.idName) > 1
order by count(n.idName) DESC, LOWER(n.Name_Last), LOWER(n.Name_First);");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }


        return $rows;

    }


    public static function expand(\PDO $dbh, $fullName, $post, $relLinkTypes) {

        $markup = '';

        if ($post['mType'] == VolMemberType::ReferralAgent) {

            $markup = Duplicate::expandOther($dbh, $fullName, VolMemberType::ReferralAgent);

        } else if ($post['mType'] == VolMemberType::Doctor) {

            $markup = Duplicate::expandOther($dbh, $fullName, VolMemberType::Doctor);

        } else if ($post['mType'] == VolMemberType::Patient) {

            // Expand this selection
            $expansion = Duplicate::expandPatient($dbh, $fullName);
            $data = array();
            $idPsgs = array();

            foreach ($expansion as $d) {

                $id = $d['Id'];

                $idPsgs[$d['idPsg']] = $d['idPsg'];

                $d['Id'] = HTMLContainer::generateMarkup('a', $d['Id'], array('href'=>'NameEdit.php?id=' . $d['Id']));
                $d['P id'] = HTMLContainer::generateMarkup('a', $d['P id'], array('href'=>'NameEdit.php?id=' . $d['P id']));
                $d['Good'] = HTMLInput::generateMarkup($d['idPsg'], array('type'=>'radio', 'name'=>'rbgood', 'id'=>'g'.$d['idPsg']));
                $d['Bad'] = HTMLInput::generateMarkup($d['idPsg'], array('type'=>'radio', 'name'=>'rbbad', 'id'=>'b'.$d['idPsg']));
                $d['Rel'] = $relLinkTypes[$d['Rel']][1];

                $data[] = $d;
            }



            $markup = CreateMarkupFromDB::generateHTML_Table($data, 'pickId');

            $markup .= HTMLInput::generateMarkup('Combine PSG\'s', array('id'=>'btnCombPSG', 'type'=>'button', 'style'=>'margin-left:300px;'));
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

        }else {

            // Expand this selection
            $expansion = Duplicate::expandGuest($dbh, $fullName);
            $data = array();
            $ids = array();
            $idPsgs = array();

            foreach ($expansion as $d) {

                $ids[$d['Id']] = $d['Id'];
                $id = $d['Id'];

                $idPsgs[$d['idPsg']] = $d['idPsg'];

                $d['Id'] = HTMLContainer::generateMarkup('a', $d['Id'], array('href'=>'NameEdit.php?id=' . $d['Id']));
                $d['P id'] = HTMLContainer::generateMarkup('a', $d['P id'], array('href'=>'NameEdit.php?id=' . $d['P id']));

                $d['Save'] = HTMLInput::generateMarkup($id, array('type'=>'radio', 'name'=>'rbsave', 'id'=>'s'.$id));

                if ($d['Rel'] == RelLinkType::Self) {
                    $d['Remove'] = '-';
                } else {
                    $d['Remove'] = HTMLInput::generateMarkup($id, array('type'=>'radio', 'name'=>'rbremove', 'id'=>'r'.$id));
                }

                $d['Rel'] = $relLinkTypes[$d['Rel']][1];

                $data[] = $d;
            }



            $markup = CreateMarkupFromDB::generateHTML_Table($data, 'pickId');
            $markup .= HTMLInput::generateMarkup('Combine Id\'s', array('id'=>'btnCombId', 'type'=>'button'));

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
            registration rg
                    left join
            visit v on rg.idRegistration = v.idRegistration
            left join
        stays s ON v.idVisit = s.idVisit and v.Span = s.Visit_Span
                    left join
            `name` n on s.idName = n.idName
    where
        rg.idPsg = $idPsg
    order by idStays;");

                $markup .= HTMLContainer::generateMarkup('div',
                            CreateMarkupFromDB::generateHTML_Table($stmt->fetchAll(\PDO::FETCH_ASSOC), 'idPsg')
                            , array('style'=>'margin:5px;'));
            }

        }

        return $markup;
    }

    protected static function expandPatient(\PDO $dbh, $name) {

        $stmt = $dbh->prepare("select
    n.idName as `Id`,
    n.Name_Full as `Name`,
    concat(na.Address_1, na.Address_2) as `Address`,
    na.City,
    na.State_Province as `St`,
    np.Phone_Num as Phone,
    ng.idPsg,
    'g' as Good,
    'b' as Bad,
    ng.Relationship_Code as `Rel`,
    hs.idHospital_stay as `Hs id`,
    n2.idName as `P id`,
    n2.Name_Full as `Patient`,
    (select count(*) from visit where idRegistration = r.idregistration) as `visits`,
    (select count(*) from stays where idName = n.idName) as `stays`,
    (select count(*) from reservation_guest where idGuest = n.idName) as `Resvs`
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
    hospital_stay hs ON ng.idPsg = hs.idPsg
        left join
    name n2 ON hs.idPatient = n2.idName
        left join
    registration r ON ng.idPsg = r.idPsg
where
    ng.Status = 'a' and LOWER(n.Name_Full) = :name and ng.idName is not null and n.Member_Status = 'a'");

        $stmt->execute(array(':name'=>  strtolower($name)));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function expandGuest(\PDO $dbh, $name) {

        $stmt = $dbh->prepare("select
    n.idName as `Id`,
    's' as `Save`,
    'r' as `Remove`,
    n.Name_Full as `Name`,
    concat(na.Address_1, na.Address_2) as `Address`,
    na.City,
    na.State_Province as `St`,
    np.Phone_Num as Phone,
    ng.idPsg,
    ng.Relationship_Code as `Rel`,
    hs.idHospital_stay as `Hs id`,
    n2.idName as `P id`,
    n2.Name_Full as `Patient`,
    (select count(*) from visit where idRegistration = r.idregistration) as `visits`,
    (select count(*) from stays where idName = n.idName) as `stays`,
    (select count(*) from reservation_guest where idGuest = n.idName) as `Resvs`
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
    hospital_stay hs ON ng.idPsg = hs.idPsg
        left join
    name n2 ON hs.idPatient = n2.idName
        left join
    registration r ON ng.idPsg = r.idPsg
where
    ng.Status = 'a' and LOWER(n.Name_Full) = :name and ng.idName is not null and n.Member_Status = 'a'");

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

        $tbl->addHeaderTr(HTMLTable::makeTh('') . HTMLTable::makeTh('Id'). HTMLTable::makeTh('Name'). HTMLTable::makeTh('Phone'). HTMLTable::makeTh('Email'));

        return $tbl->generateMarkup() . HTMLInput::generateMarkup('Combine', array('id'=>'btnCombine', 'type'=>'button', 'data-type'=> $mType));
    }

    public static function listNames(\PDO $dbh, $mType) {

        $msg = self::getNameDuplicates($dbh, $mType);

        if (count($msg) > 0) {

            $data = array();

            foreach ($msg as $d) {

                $data[] = array('Name' => HTMLInput::generateMarkup($d['Name_Full'], array('type' => 'button', 'data-type' => $mType, 'data-fn' => $d['Name_Full'], 'class' => 'hhk-expand', 'title' => 'Click to expand')), 'Count' => $d['dups']);
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
            return 'One or the other PSG id is 0.  No action.';
        }

        if ($sPsgId == $dPsgId) {
            return 'Good and Bad are the same.  No action.';
        }

        $affRows = $dbh->exec("call combinePSG($sPsgId, $dPsgId);");

        return 'Affected rows: ' . $affRows;

    }

    public static function combineId(\PDO $dbh, $saveId, $deleteId) {

        $sPsgId = intval($saveId, 10);
        $dPsgId = intval($deleteId, 10);

        if ($sPsgId == 0 || $dPsgId == 0) {
            return 'One or the other Id id is 0.  No action.';
        }

        if ($sPsgId == $dPsgId) {
            return 'Save and Remove are the same.  No action.';
        }

        $affRows = $dbh->exec("call remove_dup_guest($sPsgId, $dPsgId);");

        return 'Affected rows: ' . $affRows;

    }

}
