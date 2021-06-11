<?php

namespace HHK\Member;

class ProgressiveSearch {
	
	protected $nameFirst;
	protected $nameMiddle;
	protected $nameLast;
	protected $birthDate;

	protected $email;
	protected $phone;
	protected $addressStreet;
	protected $addressCity;
	protected $addressState;
	protected $addressZip;
	protected $addressCountry;
	
	protected $whereClause;
	

	public function __construct() {
	    
	    $this->whereClause = '';
	}
	
	public function doSearch(\PDO $dbh) {
	    
	    $stmt = $dbh->query($this->getQuery());
	    
	    $nameArray = [];
	    
	    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
	                
	        $r["First"] = preg_replace_callback("/(&#[0-9]+;)/",
	            function($m) {
	                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
	            },
	            $r["First"]
	            );
	        $r["Last"] = preg_replace_callback("/(&#[0-9]+;)/",
	            function($m) {
	                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
	            },
	            $r["Last"]
	            );
	        $r["Nickname"] = preg_replace_callback("/(&#[0-9]+;)/",
	            function($m) {
	                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
	            },
	            $r["Nickname"]
	            );
	        
	        if ($r['Birth Date'] != '') {
	            $birthDate = new \DateTime($r['Birth Date']);
	            $r['Birth Date'] = $birthDate->format ('m/d/Y');
	        }
	        
	        $r['Phone'] = htmlspecialchars_decode($r['Phone']);
	        
	        $nameArray[] = $r;
	    }
	    
	    return $nameArray;
    }
	
	/**
	 * @param string $nameFirst
	 */
	public function setNameFirst($nameFirst) {
		$this->nameFirst = $nameFirst;
		return $this;
	}

	/**
	 * @param string $nameMiddle
	 */
	public function setNameMiddle($nameMiddle) {
		$this->nameMiddle = $nameMiddle;
		return $this;
	}

	/**
	 * @param string $nameLast
	 */
	public function setNameLast($nameLast) {
	    $this->nameLast = $nameLast;
	    return $this;
	}
	
		/**
	 * @param string $birthDate
	 */
	public function setBirthDate($birthDate) {
	    try {
	        
	        $birthDT = new \DateTime($birthDate);
	        $this->birthDate = $birthDT->format('Y-m-d');
	        $this->whereClause .= " AND DATE(n.BirthDate) = " . $this->birthDate;
	        
	    } catch (\Exception $ex) {
	        // don't set phone number
	    }
	    
		return $this;
	}

    /**
	 * @param string $email
	 */
	public function setEmail($email) {
		$this->email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
		$this->whereClause .= " OR ne.Email = '" . $this->email . "' ";
		return $this;
	}

	/**
	 * @param string $phone
	 */
	public function setPhone($phone) {
		$ary = array('+', '-');
		$this->phone = str_replace($ary, '', filter_var($phone, FILTER_SANITIZE_NUMBER_INT));
		$this->whereClause .= " OR np.Phone = '" . $this->phone . "' ";
		return $this;
	}

	/**
	 * @param string $addressStreet
	 */
	public function setAddressStreet($addressStreet) {
		$this->addressStreet = $addressStreet;
		return $this;
	}

	/**
	 * @param string $addressCity
	 */
	public function setAddressCity($addressCity) {
		$this->addressCity = $addressCity;
		return $this;
	}

	/**
	 * @param string $addressState
	 */
	public function setAddressState($addressState) {
		$this->addressState = $addressState;
		return $this;
	}

	/**
	 * @param string $addressZip
	 */
	public function setAddressZip($addressZip) {
		$this->addressZip = $addressZip;
		return $this;
	}

	/**
	 * @param string $addressCountry
	 */
	public function setAddressCountry($addressCountry) {
		$this->addressCountry = $addressCountry;
		return $this;
	}
	
	protected function getQuery() {
	    
	    return "SELECT DISTINCT
    n.idName as `Id`,
    n.Name_Last as `Last`,
    n.Name_First as `First`,
    IFNULL(g.Description, '') AS Suffix,
    n.Name_Nickname as `Nickname`,
    IFNULL(n.BirthDate, '') as `Birth Date`,
    n.Member_Status as `Member Status`,
    IFNULL(np.Phone_Num, '') AS `Phone`,
    IFNULL(case when na.Address_2 = '' then na.Address_1 else concat_ws(na.Address_1, na.Address_2) end, '') as `Street Address`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.State_Province, '') AS `State`,
    IFNULL(na.Postal_Code, '') AS `Zip Code`,
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
        AND n.Preferred_Email = na.Purpose
        LEFT JOIN
    name_demog nd ON n.idName = nd.idName
        LEFT JOIN
    gen_lookups g ON g.Table_Name = 'Name_Suffix'
        AND g.Code = n.Name_Suffix
        LEFT JOIN
    gen_lookups gr ON gr.Table_Name = 'NoReturnReason'
        AND gr.Code = nd.No_Return
WHERE n.idName > 0 and n.Name_Last = '" . $this->nameLast . "' AND n.Name_First = '" . $this->nameFirst . "' and n.Record_Member = 1 "
    .  $this->whereClause;
	    
	}

	
}

