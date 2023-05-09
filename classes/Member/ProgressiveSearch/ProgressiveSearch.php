<?php

namespace HHK\Member\ProgressiveSearch;

use HHK\Member\ProgressiveSearch\SearchNameData\SearchFor;
use HHK\Member\ProgressiveSearch\SearchNameData\SearchResults;
use HHK\Member\ProgressiveSearch\SearchNameData\SearchNameData;

class ProgressiveSearch {


	public static function doSearch(\PDO $dbh, SearchFor $searchFor) {

	    $stmt = $dbh->query(self::getSearchQuery($searchFor));

	    $results = [];

	    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

	        $searchResults = new SearchResults();

	        $searchResults->loadMeFrom($r, new SearchNameData());

	        $results[] = $searchResults;
	    }

	    return $results;
    }

    public static function getMemberQuery($idName) {

        $id = intval($idName);

        return "SELECT
    n.idName,
    n.Name_Last,
    n.Name_First,
    n.Name_Middle,
    n.Name_Suffix,
    n.Name_Nickname,
    n.Name_Prefix,
    n.Name_Suffix,
    IFNULL(n.BirthDate, '') as `Birthdate`,
    n.`Gender`,
    nd.`Ethnicity`,
    IFNULL(np.Phone_Num, '') AS `Phone_Num`,
    IFNULL(ne.Email, '') as `Email`,
    IFNULL(na.Address_1, '') as `Address1`,
    IFNULL(na.Address_2, '') as `Address2`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.County, '') AS `County`,
    IFNULL(na.State_Province, '') AS `State_Province`,
    IFNULL(na.Postal_Code, '') AS `Postal_Code`,
    IFNULL(na.Country_Code, '') AS `Country_Code`,
    IFNULL(gr.Description, '') AS `No_Return`
FROM
    name n
        LEFT JOIN
    name_phone np ON n.idName = np.idName
        AND n.Preferred_Phone = np.Phone_Code
        LEFT JOIN
    name_email ne ON n.idName = ne.idName
        AND n.Preferred_Email = ne.Purpose
        LEFT JOIN
    name_address na ON n.idName = na.idName
        AND n.Preferred_Mail_Address = na.Purpose
        LEFT JOIN
    name_demog nd ON n.idName = nd.idName
        LEFT JOIN
    gen_lookups gr ON gr.Table_Name = 'NoReturnReason'
        AND gr.Code = nd.No_Return
WHERE n.idName = $id ";

    }


    public static function getSearchQuery(SearchFor $searchFor) {

        $selRel = '';
        $joinRel = '';
        $where = $searchFor->getWhereClause();

        if ($searchFor->getPsgId() > 0) {

            $selRel = " IFNULL(ng.Relationship_Code, '') as Relationship, ";
            $joinRel = " LEFT JOIN name_guest ng on n.idName = ng.idName and ng.idPsg = " . $searchFor->getPsgId() . " ";
            $where .= " and ng.Relationship_Code != 'slf'"; // exclude patient when searching for guests
        }else{
            $selRel = " '' as Relationship, ";
        }

	    return "SELECT
    n.idName,
    n.Name_Prefix,
    n.Name_Last,
    n.Name_First,
    n.Name_Middle,
    n.Name_Suffix,
    n.Name_Nickname,
    IFNULL(n.BirthDate, '') as `Birthdate`,
    n.`Gender`,
    nd.`Ethnicity`,
    IFNULL(np.Phone_Num, '') AS `Phone_Num`,
    IFNULL(ne.Email, '') as `Email`,
    IFNULL(na.Address_1, '') as `Address1`,
    IFNULL(na.Address_2, '') as `Address2`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.County, '') AS `County`,
    IFNULL(na.State_Province, '') AS `State_Province`,
    IFNULL(na.Postal_Code, '') AS `Postal_Code`,
    IFNULL(na.Country_Code, '') AS `Country_Code`,
    " . $selRel . "
    IFNULL(gr.Description, '') AS `No_Return`
FROM
    name n
    " . $joinRel . "
        LEFT JOIN
    name_phone np ON n.idName = np.idName
        AND n.Preferred_Phone = np.Phone_Code
        LEFT JOIN
    name_email ne ON n.idName = ne.idName
        AND n.Preferred_Email = ne.Purpose
        LEFT JOIN
    name_address na ON n.idName = na.idName
        AND n.Preferred_Mail_Address = na.Purpose
        LEFT JOIN
    name_demog nd ON n.idName = nd.idName
        LEFT JOIN
    gen_lookups gr ON gr.Table_Name = 'NoReturnReason'
        AND gr.Code = nd.No_Return
WHERE n.idName > 0 and n.Record_Member = 1 and n.Member_Status ='a' and n.Name_Last LIKE '%" . $searchFor->getNameLast() . "%'
    AND (n.Name_First like '%" . $searchFor->getNameFirst() . "%' OR n.Name_Nickname = '%" . $searchFor->getNameFirst() . "%') "
    .  $where;

	}


}

