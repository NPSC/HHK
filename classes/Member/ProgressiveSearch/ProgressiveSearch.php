<?php

namespace HHK\Member\ProgressiveSearch;

use HHK\Member\ProgressiveSearch\SearchNameData\SearchFor;
use HHK\Member\ProgressiveSearch\SearchNameData\SearchResults;

class ProgressiveSearch {
	
		
	public function doSearch(\PDO $dbh, SearchFor $searchFor) {
	    
	    $stmt = $dbh->query($this->getQuery($searchFor));
	    
	    $results = [];
	    
	    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
	        
	        $searchResults = new SearchResults();
	                
	        $searchResults->setId($r['Id'])
	           ->setPsgId($r['idPsg'])
	           ->setNameFirst($r["First"])
	           ->setNameLast($r["Last"])
	           ->setNickname($r["Nickname"])
	           ->setNameMiddle($r["Middle"])
	           ->setBirthDate($r['Birthdate'])
	           ->setPhone($r['Phone'])
	           ->setEmail($r['Email'])
	           ->setAddressStreet($r['Street Address'])
	           ->setAddressCity($r['City'])
	           ->setAddressState($r['State'])
	           ->setAddressZip($r['Zip'])
	           ->setAddressCountry($r['Country'])
	           ->setNoReturn($r['No_Return']);
	        
	        $results[] = $searchResults;
	    }
	    
	    return $results;
    }
	
	
	
	
	
    protected function getQuery(SearchFor $searchFor) {
	    
	    return "SELECT DISTINCT
    n.idName as `Id`,
    IFNULL(ng.idPsg, 0) as `idPsg`,
    n.Name_Last as `Last`,
    n.Name_First as `First`,
    n.Name_Middle as `Middle`,
    IFNULL(g.Description, '') AS `Suffix`,
    n.Name_Nickname as `Nickname`,
    IFNULL(n.BirthDate, '') as `Birthdate`,
    n.Member_Status as `Member Status`,
    IFNULL(np.Phone_Num, '') AS `Phone`,
    IFNULL(ne.Email, '') as `Email`,
    IFNULL(case when na.Address_2 = '' then na.Address_1 else concat_ws(na.Address_1, na.Address_2) end, '') as `Street Address`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.State_Province, '') AS `State`,
    IFNULL(na.Postal_Code, '') AS `Zip`,
    IFNULL(na.Country_Code, '') AS `Country`,
    IFNULL(gr.Description, '') AS `No_Return`
FROM
    name n
        LEFT JOIN
    name_guest ng on n.idName = ng.idName and ng.Relationship_Code = 'slf'
        LEFT JOIN
    name_phone np ON n.idName = np.idName
        AND n.Preferred_Phone = np.Phone_Code
        LEFT JOIN
    name_email ne ON n.idName = ne.idName
        AND n.Preferred_Email = ne.Purpose
        LEFT JOIN
    name_address na ON n.idName = na.idName
        AND n.Preferred_Email = na.Purpose
        LEFT JOIN
    name_demog nd ON n.idName = nd.idName
        LEFT JOIN
    gen_lookups g ON g.Table_Name = 'Name_Suffix'
        AND g.Code = n.Name_Suffix
        LEFT JOIN
    gen_lookups gr ON gr.Table_Name = 'NoReturnReason'
        AND gr.Code = nd.No_Return
WHERE n.idName > 0 and n.Record_Member = 1 and n.Name_Last = '" . $searchFor->getNameLast() . "' AND n.Name_First = '" . $searchFor->getNameFirst() . "' "
    .  $searchFor->getWhereClause();
	    
	}

	
}

