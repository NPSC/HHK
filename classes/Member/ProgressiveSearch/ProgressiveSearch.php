<?php

namespace HHK\Member\ProgressiveSearch;

use HHK\Member\ProgressiveSearch\SearchNameData\SearchFor;
use HHK\Member\ProgressiveSearch\SearchNameData\SearchResults;
use HHK\Member\ProgressiveSearch\SearchNameData\SearchNameData;

class ProgressiveSearch {
 

	public static function doSearch(\PDO $dbh, SearchFor $searchFor): array {

	    $stmt = $dbh->prepare(self::getSearchQuery($searchFor));
	    $stmt->execute(self::getSearchParams($searchFor));

	    $results = [];

	    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

	        $searchResults = new SearchResults();

	        $searchResults->loadMeFrom($r, new SearchNameData());

	        $results[] = $searchResults;
	    }

	    return $results;
    }

    public static function getMemberQuery(int $idName): string {

        return "SELECT
            `n`.`idName`,
            `n`.`Name_Last`,
            `n`.`Name_First`,
            `n`.`Name_Middle`,
            `n`.`Name_Suffix`,
            `n`.`Name_Nickname`,
            `n`.`Name_Prefix`,
            `n`.`Name_Suffix`,
            IFNULL(`n`.`BirthDate`, '') AS `Birthdate`,
            `n`.`Gender`,
            `nd`.`Ethnicity`,
            IFNULL(`np`.`Phone_Num`, '') AS `Phone_Num`,
            IFNULL(`np`.`SMS_Status`, '') AS `SMS_Status`,
            IFNULL(`ne`.`Email`, '') AS `Email`,
            IFNULL(`na`.`Address_1`, '') AS `Address1`,
            IFNULL(`na`.`Address_2`, '') AS `Address2`,
            IFNULL(`na`.`City`, '') AS `City`,
            IFNULL(`na`.`County`, '') AS `County`,
            IFNULL(`na`.`State_Province`, '') AS `State_Province`,
            IFNULL(`na`.`Postal_Code`, '') AS `Postal_Code`,
            IFNULL(`na`.`Country_Code`, '') AS `Country_Code`,
            IFNULL(`gr`.`Description`, '') AS `No_Return`,
            IFNULL(`ec`.`Name_First`, '') AS `ec_First`,
            IFNULL(`ec`.`Name_Last`, '') AS `ec_Last`,
            IFNULL(`ec`.`Phone_Home`, '') AS `ec_Phone`,
            IFNULL(`ec`.`Phone_Alternate`, '') AS `ec_Alternate`,
            IFNULL(`ec`.`Relationship`, '') AS `ec_Relationship`
        FROM
            `name` `n`
                LEFT JOIN
            `name_phone` `np` ON `n`.`idName` = `np`.`idName`
                AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
                LEFT JOIN
            `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
                AND `n`.`Preferred_Email` = `ne`.`Purpose`
                LEFT JOIN
            `name_address` `na` ON `n`.`idName` = `na`.`idName`
                AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
                LEFT JOIN
            `emergency_contact` `ec` ON `n`.`idName` = `ec`.`idName`
                LEFT JOIN
            `name_demog` `nd` ON `n`.`idName` = `nd`.`idName`
                LEFT JOIN
            `gen_lookups` `gr` ON `gr`.`Table_Name` = 'NoReturnReason'
                AND `gr`.`Code` = `nd`.`No_Return`
        WHERE `n`.`idName` = :idName ";

    }

    public static function getMemberParams($idName) {
        return [':idName' => intval($idName)];
    }


    public static function getSearchQuery(SearchFor $searchFor): string {

        $selRel = '';
        $joinRel = '';
        $where = $searchFor->getWhereClause();

        if ($searchFor->getPsgId() > 0) {

            $selRel = " IFNULL(`ng`.`Relationship_Code`, '') AS `Relationship`, ";
            $joinRel = " LEFT JOIN `name_guest` `ng` ON `n`.`idName` = `ng`.`idName` AND `ng`.`idPsg` = :psgId ";
            //$where .= " and not ng.Relationship_Code <=> 'slf'"; // exclude patient when searching for guests
        }else{
            $selRel = " '' AS `Relationship`, ";
        }

	    return "SELECT
        `n`.`idName`,
        `n`.`Name_Prefix`,
        `n`.`Name_Last`,
        `n`.`Name_First`,
        `n`.`Name_Middle`,
        `n`.`Name_Suffix`,
        `n`.`Name_Nickname`,
        IFNULL(`n`.`BirthDate`, '') AS `Birthdate`,
        `n`.`Gender`,
        `nd`.`Ethnicity`,
        IFNULL(`np`.`Phone_Num`, '') AS `Phone_Num`,
        IFNULL(`np`.`SMS_Status`, '') AS `SMS_Status`,
        IFNULL(`ne`.`Email`, '') AS `Email`,
        IFNULL(`na`.`Address_1`, '') AS `Address1`,
        IFNULL(`na`.`Address_2`, '') AS `Address2`,
        IFNULL(`na`.`City`, '') AS `City`,
        IFNULL(`na`.`County`, '') AS `County`,
        IFNULL(`na`.`State_Province`, '') AS `State_Province`,
        IFNULL(`na`.`Postal_Code`, '') AS `Postal_Code`,
        IFNULL(`na`.`Country_Code`, '') AS `Country_Code`,
        " . $selRel . "
        IFNULL(`gr`.`Description`, '') AS `No_Return`
    FROM
        `name` `n`
        " . $joinRel . "
            LEFT JOIN
        `name_phone` `np` ON `n`.`idName` = `np`.`idName`
            AND `n`.`Preferred_Phone` = `np`.`Phone_Code`
            LEFT JOIN
        `name_email` `ne` ON `n`.`idName` = `ne`.`idName`
            AND `n`.`Preferred_Email` = `ne`.`Purpose`
            LEFT JOIN
        `name_address` `na` ON `n`.`idName` = `na`.`idName`
            AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
            LEFT JOIN
        `name_demog` `nd` ON `n`.`idName` = `nd`.`idName`
            LEFT JOIN
        `gen_lookups` `gr` ON `gr`.`Table_Name` = 'NoReturnReason'
            AND `gr`.`Code` = `nd`.`No_Return`
    WHERE `n`.`idName` > 0 AND `n`.`Record_Member` = 1 AND `n`.`Member_Status` = 'a' AND `n`.`Name_Last` LIKE :nameLast
        AND (`n`.`Name_First` LIKE :nameFirst1 OR `n`.`Name_Nickname` = :nameFirst2) "
        .  $where;

	}

    public static function getSearchParams(SearchFor $searchFor) {

        $params = [
            ':nameLast' => '%' . $searchFor->getNameLast() . '%',
            ':nameFirst1' => '%' . $searchFor->getNameFirst() . '%',
            ':nameFirst2' => '%' . $searchFor->getNameFirst() . '%',
        ];

        if ($searchFor->getPsgId() > 0) {
            $params[':psgId'] = $searchFor->getPsgId();
        }

        return array_merge($params, $searchFor->getWhereParams());
    }


}

