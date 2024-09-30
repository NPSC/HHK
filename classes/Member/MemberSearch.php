<?php

namespace HHK\Member;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\SysConst\MemDesignation;
use HHK\SysConst\PhonePurpose;
use HHK\SysConst\RelLinkType;
use HHK\SysConst\VolMemberType;
use HHK\sec\Labels;
use HHK\Exception\RuntimeException;

/**
 * memberSearch.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of MemberSearch
 * @package name
 * @author Eric
 */
class MemberSearch {

    /**
     * Summary of Name_First
     * @var
     */
    protected $Name_First;
    /**
     * Summary of Name_Last
     * @var
     */
    protected $Name_Last;
    /**
     * Summary of Phone
     * @var
     */
    protected $Phone;
    /**
     * Summary of Company
     * @var
     */
    protected $Company;
    /**
     * Summary of MRN
     * @var
     */
    protected $MRN;
    /**
     * Summary of diag
     * @var
     */
    protected $diag;
    /**
     * Summary of twoParts
     * @var
     */
    protected $twoParts;
    /**
     * Summary of letters
     * @var
     */
    protected $letters;

    /**
     * Summary of __construct
     * @param mixed $letters
     */
    public function __construct($letters) {

        $this->letters = $letters;
    	$this->prepareLetters($letters);
    }

    /**
     * Summary of prepareLetters
     * @param mixed $letters
     * @return void
     */
    public function prepareLetters($letters) {

    	$parts = explode(' ', strtolower(trim($letters)));

    	if (count($parts) > 1) {

    		// first or last name?
    		if (stristr($parts[0], ',') === FALSE) {
    			//first name first
    			$this->Name_First = $parts[0] . '%';
    			$this->Name_Last = $parts[1] . '%';
    		} else {
    			// last name first
    			$this->Name_First = $parts[1] . '%';
    			$this->Name_Last = str_replace(',', '', $parts[0]) . '%';
    		}

    		$this->twoParts = TRUE;
    		$this->Company = strtolower(trim($letters)) . '%';

    	} else {

    		$this->Name_First = $parts[0] . '%';
    		$this->Name_Last = $parts[0] . '%';
    		$this->Company = $parts[0] . '%';
//    		$this->MRN = $parts[0] . '%';
    		$this->twoParts = FALSE;
    	}

    }

    /**
     * Summary of volunteerCmteFilter
     * @param \PDO $dbh
     * @param mixed $basis
     * @param mixed $fltr
     * @param mixed $additional
     * @param mixed $psg
     * @return array
     */
    public function volunteerCmteFilter(\PDO $dbh, $basis, $fltr, $additional = '', $psg = '') {
        $events = array();

        $operation = 'OR';
        if ($this->twoParts) {
            $operation = 'AND';
        }


        if ($basis == "m") {

            $prts = explode("|", $fltr);
            if (count($prts) >= 2) {

                $query2 = "SELECT n.idName, n.Name_Last, n.Name_First, n.Name_Nickname
    FROM name_volunteer2 v left join name n on v.idName = n.idName
    where v.Vol_Status = 'a' and Vol_Category = :vcat and Vol_Code = :vcode
    and n.idName>0 and n.Member_Status='a' and (LOWER(n.Name_Last) like :ltrln
    $operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk))
    order by n.Name_Last, n.Name_First;";

                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':vcat' => $prts[0], ':vcode' => $prts[1], ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));

                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $row2) {

                    $namArray = array();

                    $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                            function($m) {
                                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                            },
                            $row2["Name_First"]
                    );
                    $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                            function($m) {
                                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                            },
                            $row2["Name_Last"]
                    );
                    $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                            function($m) {
                                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                            },
                            $row2["Name_Nickname"]
                    );

                    $namArray['id'] = $row2["idName"];
                    $namArray['value'] = $lastName . ", " . $firstName . ($nickName != '' ? ' (' . $nickName . ')' : '' );
                    $namArray['last'] = $lastName;
                    $namArray['first'] = $nickName != '' ? $nickName : $firstName;

                    $events[] = $namArray;
                }
                if (count($events) == 0) {
                    $events[] = array("id" => 0, "value" => "Nothing Returned");
                }

            } else {
                $events[] = array("error" => "Bad filter: " . $fltr);
            }



        // Referral Agent & Doctor
        } else if ($basis == VolMemberType::ReferralAgent || $basis == VolMemberType::Doctor) {

                $query2 = "SELECT distinct n.idName, n.Name_Last, n.Name_First, n.Name_Nickname, ifnull(nw.Phone_Num, '') as `WorkPhone`, ifnull(nw.Phone_Extension, '') as `WorkExt`, ifnull(nc.Phone_Num, '') as `CellPhone`, ifnull(ne.Email, '') as `Email`
FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '$basis'
left join name_phone nw on n.idName = nw.idName and nw.Phone_Code = '" . PhonePurpose::Work . "'
left join name_phone nc on n.idName = nc.idName and nc.Phone_Code = '" . PhonePurpose::Cell . "'
left join name_email ne on n.idName = ne.idName and n.Preferred_Email = ne.Purpose
where n.idName>0 and n.Member_Status='a' and n.Record_Member = 1  and (LOWER(n.Name_Last) like :ltrln
$operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) order by n.Name_Last, n.Name_First;";

            $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            $stmt->execute(array(':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $r) {

                $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_First"]
                );
                $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_Last"]
                );
                $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_Nickname"]
                );

                $events[] = [
                    'id' => $r["idName"],
                    'value' => $lastName . ", " . $firstName . ($nickName != '' ? ' (' . $nickName . ')' : ''),
                    'first' => ($nickName != '' ? $nickName : $firstName),
                    'last' => $lastName,
                    'wphone' => $r["WorkPhone"],
                    'wext' => $r['WorkExt'],
                    'cphone' => $r["CellPhone"],
                    'email' => $r['Email']
                ];
            }

            $labels = Labels::getLabels();


            // Add new entry option.
            $events[] = ['id' => 0, 'value' => ($basis == VolMemberType::Doctor ? 'New Doctor' : 'New ' . $labels->getString('hospital', 'referralAgent', 'Referral Agent'))];




        // Third party billing agent?
        } else if ($basis == VolMemberType::BillingAgent) {

            $baId = intval($this->Name_Last, 10);

            if ($baId > 0) {
                // search on id
                $stmt = $dbh->query("SELECT n.idName, n.Name_Last, n.Name_First, n.Name_Nickname, n.Company, nd.tax_exempt  " .
                        " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '$basis'" .
                        " join name_demog nd on n.idName = nd.idName" .
                        " where n.Member_Status='a' and n.Record_Member = 1 and n.idName = $baId");

            } else {

                $query2 = "SELECT distinct n.idName, n.Name_Last, n.Name_First, n.Name_Nickname, n.Company, nd.tax_exempt
FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '$basis'
 join name_demog nd on n.idName = nd.idName
where n.idName>0 and n.Member_Status='a' and n.Record_Member = 1  and ((LOWER(n.Name_Last) like :ltrln or LOWER(n.Company) like :ltrco)
$operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) order by n.Company, n.Name_Last, n.Name_First;";

                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First, ':ltrco'=>$this->Company));
            }


            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $r) {

                $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_First"]
                );
                $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_Last"]
                );
                $company = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Company"]
                );
                $events[] = array(
                    'id' => $r["idName"],
                    'taxExempt' => $r["tax_exempt"],
                    'value' => ($lastName != '' ? $lastName . ", " . $firstName . " - " . $company : $company) . ($r["tax_exempt"] ? " - Tax Exempt" : '')
                );
            }

            if (count($events) == 0) {
                $events[] = array("id" => 0, "value" => "Not Found");
            }




       // Guest or Patient as Guest
        } else if ($basis == VolMemberType::Guest || $basis == 'g,p') {

            $andVc = " and nv.Vol_Code = '" . VolMemberType::Guest . "' ";
            if ($basis == 'g,p') {
                $andVc = " and nv.Vol_Code in ('g','p') ";
            }


            $query2 = "SELECT distinct n.idName, n.Name_Last, n.Name_First, n.Name_Nickname, ifnull(np.Phone_Num, '') as `Phone`
FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type' $andVc
left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
where n.idName>0 and n.Member_Status='a' and n.Record_Member = 1  and (LOWER(n.Name_Last) like :ltrln
$operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) order by n.Name_Last, n.Name_First;";

            $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            $stmt->execute(array(':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $r) {

                $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_First"]
                );
                $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_Last"]
                );
                $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_Nickname"]
                );

                $events[] = array(
                    'id' => $r["idName"],
                    'value' => $lastName . ", " . $firstName . ($nickName != '' ? ' (' . $nickName . ')' : '' ) . '  ' . $r['Phone'],
                    'first' => ($nickName != '' ? $nickName : $firstName ),
                    'last' => $lastName,
                    'phone' => $r["Phone"],
                );
            }

            if ($additional != 'phone') {
                $events[] = array("id" => 0, "value" => "New Guest");
            } else if (count($events) == 0) {
                $events[] = array("id" => 0, "value" => "Nothing Found");
            }



        //search guests/patients based on PSG
        } else if ($basis == 'psg') {

            $andVc = " and nv.Vol_Code = '" . VolMemberType::Guest . "' ";


            $query2 = "SELECT distinct n.idName, n.Name_Last, n.Name_First, n.Name_Nickname, ifnull(np.Phone_Num, '') as `Phone`
            FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type' $andVc
            left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
            left join name_guest ng on n.idName = ng.idName
            where n.idName>0 and n.Member_Status='a' and n.Record_Member = 1  and idPsg = :idPsg and (LOWER(n.Name_Last) like :ltrln
            $operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) order by n.Name_Last, n.Name_First;";

            $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            $stmt->execute(array(':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First, ':idPsg'=>$psg));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $r) {

                $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                    function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    },
                    $r["Name_First"]
                    );
                $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                    function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    },
                    $r["Name_Last"]
                    );
                $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                    function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    },
                    $r["Name_Nickname"]
                    );

                $events[] = array(
                    'id' => $r["idName"],
                    'value' => $lastName . ", " . $firstName . ($nickName != '' ? ' (' . $nickName . ')' : '' ) . '  ' . $r['Phone'],
                    'first' => ($nickName != '' ? $nickName : $firstName ),
                    'last' => $lastName,
                    'phone' => $r["Phone"],
                );
            }

            if (count($events) == 0) {
                $events[] = array("id" => 0, "value" => "Nothing Found");
            }





        }else if ($basis == VolMemberType::Patient) {
            // Search patient

            $query2 = "SELECT n.idName, n.Name_Last, n.Name_First, n.Name_Nickname
FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '" . VolMemberType::Patient . "'
where n.idName>0 and n.Member_Status='a' and n.Record_Member = 1  and (LOWER(n.Name_Last) like :ltrln
$operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) order by n.Name_Last, n.Name_First;";
            $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            $stmt->execute(array(':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $r) {

                $namArray = array();

                $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_First"]
                );
                $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_Last"]
                );
                $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        },
                        $r["Name_Nickname"]
                );

                $namArray['id'] = $r["idName"];
                $namArray['value'] = $lastName . ", " . $firstName . ($nickName != '' ? ' (' . $nickName . ')' : '' );

                $events[] = $namArray;
            }

            $events[] = array("id" => 0, "value" => "New Patient");


        } else {
            $events[] = array("error" => "Bad Basis Code: " . $basis);
        }


        return $events;
    }

    /**
     * Summary of searchLinks
     * @param \PDO $dbh
     * @param mixed $basis
     * @param mixed $id
     * @param mixed $namesOnly
     * @return array
     */
    public function searchLinks(\PDO $dbh, $basis, $id, $namesOnly = FALSE) {
        $events = array();

        $operation = 'OR';
        if ($this->twoParts) {
            $operation = 'AND';
        }


        switch ($basis) {

            case "m":
                //                  0          1             2             3            4                 5                         6
                $query2 = "SELECT n.idName, n.Name_Last, n.Name_First, n.Company, case when n.Record_Company = 1 then 't' else '' end, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
                FROM name n left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
                WHERE n.idName>0 and n.Member_Status not in ('u','TBD','p') and
                (LOWER(n.Name_Last) like '" . $this->Name_Last . "' $operation
                (LOWER(n.Name_NickName) like '" . $this->Name_First . "' OR LOWER(n.Name_First) like '" . $this->Name_First . "') OR LOWER(n.Company) like '" . $this->Company . "') order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->query($query2);
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];

                    if ($row2[4] == '') {
                        $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                            }, $row2[1] . ", " . $row2[2]);
                    } else {
                        $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        }, $row2[3]);
                    }

                    $namArray['stat'] = $row2[6];
                    $namArray['scode'] = $row2[5];
                    $namArray['company'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        }, $row2[3]);

                    $events[] = $namArray;
                }

                break;

            case "ind":
                //                  0          1             2             3            4                 5             6
                $query2 = "SELECT n.idName, n.Name_Last, n.Name_First, n.Company, case when n.Record_Company = 1 then 't' else '' end, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!') as `Descrip`
                FROM name n left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
                WHERE n.idName>0 and n.idName <> :id and n.Member_Status<>'u' and n.Member_Status<>'TBD' and (LOWER(n.Name_Last) like :ltrln
                $operation (LOWER(n.Name_First) like :ltrfn  OR LOWER(n.Name_NickName) like :ltrnk)) order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {

                    $namArray = array();

                    $namArray['id'] = $row2[0];

                    if ($row2[4] == '') {
                        $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        }, $row2[1] . ", " . $row2[2]);
                    } else {
                        $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        }, $row2[3]);
                    }

                    $namArray['stat'] = $row2[6];
                    $namArray['scode'] = $row2[5];

                    $events[] = $namArray;
                }
                break;

            case "e":
                $query2 = "select e.idName, e.Email, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
                from name_email e join name n on e.idName = n.idName
                left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
                where n.idName>0 and n.Member_Status<>'u' and Member_Status<>'TBD' and  LOWER(e.Email) like :ltr order by n.Member_Status, e.Email";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':ltr' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = $row2[1];
                    $namArray['scode'] = $row2[2];
                    $namArray["stat"] = $row2[3];
                    $events[] = $namArray;
                }
                break;

            case RelLinkType::Parnt:
                //  parents                    0               1           2               3                               4
                $query2 = "SELECT n.idName as Id, n.Name_Last, n.Name_First, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
            FROM name n left join relationship r on n.idName = r.Target_Id and :id = r.idName and r.Relation_Type = 'par'
            left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
            WHERE (LOWER(n.Name_Last) like :ltrln $operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) and n.Record_Member = 1
                and n.Member_Status in ('a','d','in') and n.idName <> :id2 and r.idRelationship is null order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':id2' => $id, ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    }, $row2[1] . ", " . $row2[2]);
                    $namArray['scode'] = $row2[3];
                    $namArray["stat"] = $row2[4];
                    $events[] = $namArray;
                }
                break;

            case RelLinkType::Child:
                // chekdren                   0               1           2                   3                           4
                $query2 = "SELECT n.idName as Id, n.Name_Last, n.Name_First, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
                FROM name n left join relationship r on n.idName = r.idName and :id = r.Target_Id and r.Relation_Type = 'par'
                left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
                WHERE (LOWER(n.Name_Last) like :ltrln $operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) and n.Record_Member = 1
                and n.Member_Status in ('a','d','in') and n.idName <> :id2 and r.idRelationship is null order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':id2' => $id, ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    }, $row2[1] . ", " . $row2[2]);
                    $namArray['scode'] = $row2[3];
                    $namArray["stat"] = $row2[4];
                    $events[] = $namArray;
                }
                break;

            case RelLinkType::Sibling:
                //                      0               1              2            3                               4
                $query2 = "SELECT n.idName as Id, n.Name_Last, n.Name_First, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
            FROM name n left join relationship r on n.idName = r.idName and r.Relation_Type = 'sib'
            left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
                WHERE (LOWER(n.Name_Last) like :ltrln $operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) and n.Record_Member = 1 and ifnull(r.Group_Code,'0') not in
                (Select Group_Code from relationship where idname = :id)
                and n.Member_Status in ('a','d','in') and n.idName <> :id2 order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':id2' => $id, ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    }, $row2[1] . ", " . $row2[2]);

                    $namArray['scode'] = $row2[3];
                    $namArray["stat"] = $row2[4];
                    $events[] = $namArray;
                }
                break;

            case RelLinkType::Company:
                $query2 = "select idName as Id, Company from name where Record_Company=1 and Member_Status ='a' and idName>0 and idName <> :id and LOWER(Company) like :ltr order by Member_Status, Company;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':ltr' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/",
                            function($m) {
                                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                            },
                            $row2[1]
                    );

                    $events[] = $namArray;
                }
                break;

            case RelLinkType::Spouse:
                $query2 = "SELECT n.idName as Id, n.Name_Last, n.Name_First, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
                FROM name n left join relationship r on (n.idName = r.idName or n.idName = r.Target_Id) and r.Relation_Type = 'sp'
                left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
                WHERE (LOWER(n.Name_Last) like :ltrln $operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) and n.Record_Member = 1
                and n.Member_Status in ('a','d','in') and n.idName>0 and n.idName <> :id and r.idRelationship is null order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    }, $row2[1] . ", " . $row2[2]);
                    $namArray['scode'] = $row2[3];
                    $namArray["stat"] = $row2[4];
                    $events[] = $namArray;
                }
                break;

            case RelLinkType::Employee:
                $query2 = "SELECT n.idName, n.Name_Last, n.Name_First, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
            FROM name n left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
            WHERE n.Company_Id = 0 and n.Member_Status in ('a','in') and n.Record_Member = 1 and n.idName <> :id and (LOWER(n.Name_Last) like :ltrln
                $operation (LOWER(n.Name_First) like :ltrfn  OR LOWER(n.Name_NickName) like :ltrnk)) order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    }, $row2[1] . ", " . $row2[2]);
                    $namArray['scode'] = $row2[3];
                    $namArray["stat"] = $row2[4];
                    $events[] = $namArray;
                }
                break;

            case RelLinkType::Relative:
                //                  0                   1           2                   3                       4
                $query2 = "SELECT n.idName as Id, n.Name_Last, n.Name_First, ifnull(n.Member_Status,'x'), ifnull(g.Description,'Undefined!')
            FROM name n left join relationship r on n.idName = r.idName and r.Relation_Type = 'rltv'
            left join gen_lookups g on g.Table_Name='mem_status' and g.Code = n.Member_Status
                WHERE (LOWER(n.Name_Last) like :ltrln $operation (LOWER(n.Name_First) like :ltrfn OR LOWER(n.Name_NickName) like :ltrnk)) and n.Record_Member = 1 and ifnull(r.Group_Code,'0') not in
                (Select Group_Code from relationship where idname = :id)
                and n.Member_Status in ('a','d','in') and n.idName <> :id2 order by n.Member_Status, n.Name_Last, n.Name_First;";
                $stmt = $dbh->prepare($query2, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':id' => $id, ':id2' => $id, ':ltrln' => $this->Name_Last, ':ltrfn' => $this->Name_First, ':ltrnk' => $this->Name_First));
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                foreach ($rows as $row2) {
                    $namArray = array();

                    $namArray['id'] = $row2[0];
                    $namArray['value'] = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    }, $row2[1] . ", " . $row2[2]);
                    $namArray['scode'] = $row2[3];
                    $namArray["stat"] = $row2[4];
                    $events[] = $namArray;
                }
                break;


            default:
                $events = array("error" => "Bad Basis Code: " . $basis);
        }

        if ($namesOnly === FALSE) {
            $events[] = array("id" => MemDesignation::Individual, "value" => "New Individual");
            $events[] = array("id" => MemDesignation::Organization, "value" => "New Organization");
        }

        if (count($events) == 0) {
            $events[] = array("id" => 'x', 'value' => 'Nothing Returned');
        }

        return $events;
    }

    /**
     * Summary of MRNSearch
     * @param \PDO $dbh
     * @return array
     */
    public function MRNSearch(\PDO $dbh) {

        $this->MRN = $this->letters . '%';

        $query = "Select distinct n.idName,  n.Name_Last, n.Name_First, ifnull(gp.Description, '') as Name_Prefix, ifnull(g.Description, '') as Name_Suffix, n.Name_Nickname, n.BirthDate, "
            . " n.Member_Status, ifnull(gs.Description, '') as `Status`, ifnull(np.Phone_Num, '') as `Phone`, ifnull(na.City,'') as `City`, ifnull(na.State_Province,'') as `State`, "
            . " ifnull(gr.Description, '') as `No_Return` " . ", SUBSTR(MAX(CONCAT(LPAD(hs.idHospital_stay,50),hs.MRN)),51)as `MRN` "
            . " from `name` n "
            . " left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code"
            . " left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose"
            . " left join name_demog nd on n.idName = nd.idName"
            . " left join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'"
            . " left join gen_lookups g on g.Table_Name = 'Name_Suffix' and g.Code = n.Name_Suffix"
            . " left join gen_lookups gp on gp.Table_Name = 'Name_Prefix' and gp.Code = n.Name_Prefix"
            . " left join gen_lookups gs on gs.Table_Name = 'mem_status' and gs.Code = n.Member_Status"
            . " left join gen_lookups gr on gr.Table_Name = 'NoReturnReason' and gr.Code = nd.No_Return"
            . " left join hospital_stay hs on n.idName = hs.idPatient"
            . " where n.idName>0 and n.Member_Status in ('a','d') and n.Record_Member = 1 "
            . " and nv.Vol_Code in ('" . VolMemberType::Guest . "', '" . VolMemberType::Patient . "') "
            . " and hs.MRN like '" . $this->MRN . "' "
            . " group by n.idName order by hs.MRN";

        $stmt = $dbh->query($query);

        $events = array();

        while ($row2 = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $namArray = array();

            $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_First"]
                );
            $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_Last"]
                );
            $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_Nickname"]
                );

            $strBirthDate = '';
            if ($row2['BirthDate'] != '') {
                $birthDate = new \DateTime($row2['BirthDate']);
                $strBirthDate = $birthDate->format ('m/d/Y');
            }

            $phone = htmlspecialchars_decode($row2['Phone']);

            $namArray = [
                'id' => $row2["idName"],
                'fullName' => ($row2['Name_Prefix'] != '' ? $row2['Name_Prefix'] . ' ' : '' ) . $firstName . ' ' . ($nickName != '' ? '(' . $nickName . ') ' : '' ) . $lastName . ($row2['Name_Suffix'] != '' ? ', ' . $row2['Name_Suffix'] : '' ),
                'noReturn' => $row2['No_Return'],
                'value' => $row2['MRN'],
                'mrn' => $row2['MRN'],
                'phone' => $phone,
                'birthDate' => $strBirthDate,
                'memberStatus' => ($row2['Member_Status'] == 'd' ? $row2['Status'] : ''),
                'city' => $row2['City'],
                'state' => $row2['State'],
            ];

            $events[] = $namArray;
        }

        return $events;
    }

    /**
     * Summary of phoneSearch
     * @param \PDO $dbh
     * @param mixed $guestPatient
     * @return array
     */
    public function phoneSearch(\PDO $dbh, $guestPatient = TRUE) {

        $filterGP = '';
        if ($guestPatient) {
            $filterGP = " and nv.Vol_Code in ('" . VolMemberType::Guest . "', '" . VolMemberType::Patient . "') ";
        }


        $query = "Select distinct n.idName,  n.Name_Last, n.Name_First, ifnull(gp.Description, '') as Name_Prefix, ifnull(g.Description, '') as Name_Suffix, n.Name_Nickname, n.BirthDate, "
            . " n.Member_Status, ifnull(gs.Description, '') as `Status`, ifnull(np.Phone_Num, '') as `Phone`, ifnull(na.City,'') as `City`, ifnull(na.State_Province,'') as `State`, "
            . " ifnull(gr.Description, '') as `No_Return` " . ", SUBSTR(MAX(CONCAT(LPAD(hs.idHospital_stay,50),hs.MRN)),51) as `MRN`, np.Phone_Search "
            . " from `name` n "
            . " left join name_phone np on n.idName = np.idName AND n.Preferred_Phone = np.Phone_Code"
            . " left join name_address na on n.idName = na.idName AND n.Preferred_Mail_Address = na.Purpose"
            . " left join name_demog nd on n.idName = nd.idName"
            . " left join name_volunteer2 nv on n.idName = nv.idName AND nv.Vol_Category = 'Vol_Type'"
            . " left join gen_lookups g on g.Table_Name = 'Name_Suffix' AND g.Code = n.Name_Suffix"
            . " left join gen_lookups gp on gp.Table_Name = 'Name_Prefix' AND gp.Code = n.Name_Prefix"
            . " left join gen_lookups gs on gs.Table_Name = 'mem_status' AND gs.Code = n.Member_Status"
            . " left join gen_lookups gr on gr.Table_Name = 'NoReturnReason' AND gr.Code = nd.No_Return"
            . " left join hospital_stay hs on n.idName = hs.idPatient"
            . " where n.idName>0 AND n.Member_Status in ('a','d') AND n.Record_Member = 1 "
            . $filterGP
            . " AND np.Phone_Search LIKE '" . $this->Name_First . "' "
            . " group by n.idName order by np.Phone_Search";

        $stmt = $dbh->query($query);

        $events = array();

        while ($row2 = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $namArray = array();

            $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_First"]
                );
            $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_Last"]
                );
            $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_Nickname"]
                );

            $strBirthDate = '';
            if ($row2['BirthDate'] != '') {
                $birthDate = new \DateTime($row2['BirthDate']);
                $strBirthDate = $birthDate->format ('m/d/Y');
            }

            $namArray = [
                'id' => $row2["idName"],
                'fullName' => ($row2['Name_Prefix'] != '' ? $row2['Name_Prefix'] . ' ' : '' ) . $firstName . ' ' . ($nickName != '' ? '(' . $nickName . ') ' : '' ) . $lastName . ($row2['Name_Suffix'] != '' ? ', ' . $row2['Name_Suffix'] : '' ),
                'noReturn' => $row2['No_Return'],
                'value' => $row2['Phone_Search'],
                'phone' => htmlspecialchars_decode($row2['Phone']),
                'birthDate' => $strBirthDate,
                'memberStatus' => ($row2['Member_Status'] == 'd' ? $row2['Status']: ''),
                'city' => $row2['City'],
                'state' => $row2['State'],
            ];

            $events[] = $namArray;
        }

        return $events;
    }

    /**
     * Summary of diagnosisSearch
     * @param \PDO $dbh
     * @return array
     */
    public function diagnosisSearch(\PDO $dbh){

        $this->diag = '%' . $this->letters . '%';

        $query = "select d.Code as 'DiagCode', ifnull(cat.Description, '') as 'Category', d.Description as 'Diagnosis', concat(if(cat.Description is not null, concat(cat.Description, ': '), ''), d.Description) as `Title` from gen_lookups d
	               left join gen_lookups cat on d.`Substitute` = cat.`Code` and cat.`Table_Name` = 'Diagnosis_Category'
                where d.`Table_Name` = 'Diagnosis'
                having `Title` like :search;";

        $stmt = $dbh->prepare($query);

        $stmt->execute([":search"=>$this->diag]);

        $events = array();

        while ($row2 = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $diagAr = array();

            $diagAr = [
                'id' => $row2["DiagCode"],
                'value' => htmlspecialchars_decode($row2['Title'], ENT_QUOTES),
            ];

            $events[] = $diagAr;
        }

        return $events;

    }

    /**
     * Summary of guestSearch
     * @param \PDO $dbh
     * @return array
     */
    public function guestSearch(\PDO $dbh) {

        $operation = 'OR';
        if ($this->twoParts) {
            $operation = 'AND';
        }

        $query = "Select distinct n.idName,  n.Name_Last, n.Name_First, ifnull(gp.Description, '') as Name_Prefix, ifnull(g.Description, '') as Name_Suffix, n.Name_Nickname, n.BirthDate, "
            . " n.Member_Status, ifnull(gs.Description, '') as `Status`, ifnull(np.Phone_Num, '') as `Phone`, ifnull(na.City,'') as `City`, ifnull(na.State_Province,'') as `State`, "
            . " ifnull(gr.Description, '') as `No_Return` " . ", SUBSTR(MAX(CONCAT(LPAD(hs.idHospital_stay,50),hs.MRN)),51)as `MRN` "
            . " from `name` n "
            . " left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code"
            . " left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose"
            . " left join name_demog nd on n.idName = nd.idName"
            . " left join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'"
            . " left join gen_lookups g on g.Table_Name = 'Name_Suffix' and g.Code = n.Name_Suffix"
            . " left join gen_lookups gp on gp.Table_Name = 'Name_Prefix' and gp.Code = n.Name_Prefix"
            . " left join gen_lookups gs on gs.Table_Name = 'mem_status' and gs.Code = n.Member_Status"
            . " left join gen_lookups gr on gr.Table_Name = 'NoReturnReason' and gr.Code = nd.No_Return"
            . " left join hospital_stay hs on n.idName = hs.idPatient"
            . " where n.idName>0 and n.Member_Status in ('a','d') and n.Record_Member = 1 "
            . " and nv.Vol_Code in ('" . VolMemberType::Guest . "', '" . VolMemberType::Patient . "') "
            . " and (LOWER(n.Name_Last) like :nameLast "
            . " $operation (LOWER(n.Name_First) like :nameFirst OR LOWER(n.Name_NickName) like :nameFirst2)) "
            . " group by n.idName order by n.Name_Last, n.Name_First";

        $stmt = $dbh->prepare($query);
        $stmt->execute([
            ":nameFirst"=>$this->Name_First,
            ":nameFirst2"=>$this->Name_First,
            ":nameLast"=>$this->Name_Last
        ]);

        $events = array();

        while ($row2 = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $namArray = array();

            $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_First"]
                );
            $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_Last"]
                );
            $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                function($m) {
                    return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                },
                $row2["Name_Nickname"]
                );

            $strBirthDate = '';
            if ($row2['BirthDate'] != '') {
                $birthDate = new \DateTime($row2['BirthDate']);
                $strBirthDate = $birthDate->format ('m/d/Y');
            }

            $namArray = [
                'id' => $row2["idName"],
                'fullName' => ($row2['Name_Prefix'] != '' ? $row2['Name_Prefix'] . ' ' : '' ) . $firstName . ' ' . ($nickName != '' ? '(' . $nickName . ') ' : '' ) . $lastName . ($row2['Name_Suffix'] != '' ? ', ' . $row2['Name_Suffix'] : '' ),
                'noReturn' => $row2['No_Return'],
                'value' => $firstName . ' ' . $lastName . ' ' . $nickName,
                'phone' => htmlspecialchars_decode($row2['Phone']),
                'birthDate' => $strBirthDate,
                'memberStatus' => ($row2['Member_Status'] == 'd' ? $row2['Status'] : ''),
                'city' => $row2['City'],
                'state' => $row2['State'],
            ];

            $events[] = $namArray;
        }

        return $events;
    }


    /**
     * Summary of roleSearch
     * @param \PDO $dbh
     * @param mixed $mode
     * @param mixed $guestPatient
     * @param mixed $MRN
     * @return array
     */
    public function roleSearch(\PDO $dbh, $mode = '', $guestPatient = FALSE, $MRN = FALSE) {

        $operation = 'OR';
        if ($this->twoParts) {
            $operation = 'AND';
        }

        $filterGP = '';
        if ($guestPatient) {
            $filterGP = " and nv.Vol_Code in ('" . VolMemberType::Guest . "', '" . VolMemberType::Patient . "') ";
        }

        $query = "Select distinct n.idName,  n.Name_Last, n.Name_First, ifnull(gp.Description, '') as Name_Prefix, ifnull(g.Description, '') as Name_Suffix, n.Name_Nickname, n.BirthDate, "
                . " n.Member_Status, ifnull(gs.Description, '') as `Status`, ifnull(np.Phone_Num, '') as `Phone`, ifnull(na.City,'') as `City`, ifnull(na.State_Province,'') as `State`, "
                . " ifnull(gr.Description, '') as `No_Return` " . ", SUBSTR(MAX(CONCAT(LPAD(hs.idHospital_stay,50),hs.MRN)),51)as `MRN` "
            . " from `name` n "
                . " left join name_phone np on n.idName = np.idName and n.Preferred_Phone = np.Phone_Code"
                . " left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose"
                . " left join name_demog nd on n.idName = nd.idName"
                . " left join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'"
                . " left join gen_lookups g on g.Table_Name = 'Name_Suffix' and g.Code = n.Name_Suffix"
                . " left join gen_lookups gp on gp.Table_Name = 'Name_Prefix' and gp.Code = n.Name_Prefix"
                . " left join gen_lookups gs on gs.Table_Name = 'mem_status' and gs.Code = n.Member_Status"
                . " left join gen_lookups gr on gr.Table_Name = 'NoReturnReason' and gr.Code = nd.No_Return"
                . " left join hospital_stay hs on n.idName = hs.idPatient"
            . " where n.idName>0 and n.Member_Status in ('a','d') and n.Record_Member = 1 $filterGP "
                . ($MRN ? "" : " and (LOWER(n.Name_Last) like '" . $this->Name_Last . "' "
                . " $operation (LOWER(n.Name_First) like '" . $this->Name_First . "' OR LOWER(n.Name_NickName) like '" . $this->Name_First . "')) "
                . " OR np.Phone_Search like '" . $this->Name_First . "' ")
                . ($MRN ? " and hs.MRN like '" . $this->MRN . "' " : "")
            . " group by n.idName order by n.Name_Last, n.Name_First";

        $stmt = $dbh->query($query);

        $events = array();

        while ($row2 = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $namArray = array();

            $firstName = preg_replace_callback("/(&#[0-9]+;)/",
                    function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    },
                    $row2["Name_First"]
            );
            $lastName = preg_replace_callback("/(&#[0-9]+;)/",
                    function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    },
                    $row2["Name_Last"]
            );
            $nickName = preg_replace_callback("/(&#[0-9]+;)/",
                    function($m) {
                        return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                    },
                    $row2["Name_Nickname"]
            );

            $strBirthDate = '';
            if ($row2['BirthDate'] != '') {
            	$birthDate = new \DateTime($row2['BirthDate']);
            	$strBirthDate = $birthDate->format ('m/d/Y');
            }

            $phone = htmlspecialchars_decode($row2['Phone']);

            $namArray['id'] = $row2["idName"];
            $namArray['fullName'] = $firstName . ' ' . $lastName;
            $namArray['No_Return'] = $row2['No_Return'];
            $namArray['value'] =
                ($row2['No_Return'] != '' ? $row2['No_Return'] . '; ' : '')
                . ($row2['Name_Prefix'] != '' ? $row2['Name_Prefix'] . ' ' : '' )
                . $lastName . ", " . $firstName
                . ($row2['Name_Suffix'] != '' ? ', ' . $row2['Name_Suffix'] : '' )
                . ($nickName != '' ? ' (' . $nickName . ')' : '' )
                . ($phone != '' ? ' (' . $phone . ')' : '' )
                . ($strBirthDate != '' ? ' (' . $strBirthDate . ')' : '' )
                . ($row2['Member_Status'] == 'd' ? ' [' . $row2['Status'] . ']' : '')
                . ($row2['City'] != '' ? '; ' . $row2['City'] : '')
                . ($row2['State'] != '' ? ', ' . $row2['State'] : '')
                . ($row2['MRN'] != '' ? " MRN: " . $row2['MRN'] : '');

            $events[] = $namArray;
        }

        if ($mode == 'mo') {
            if (count($events) === 0) {
                $events[] = array("id" => 0, "value" => "No one found.");
            }
        } else {
            $events[] = array("id" => 0, "value" => "New Person");
        }

        return $events;
    }

    /**
     * Searches for a previous occurance of the supplied name.
     * Duplicate prevention.
     *
     * @param \PDO $dbh
     * @param array $post
     * @throws RuntimeException::
     */
    public static function searchName(\PDO $dbh, $memDesignation, $nameLast, $nameFirst = '', $email = '', $phone = '') {

        $email = strtolower($email);
        $phone = strtolower($phone);
        $nl = strtolower($nameLast);

        // Check for individual
        if ($memDesignation == MemDesignation::Individual) {

            $nf = strtolower($nameFirst);

            $query = "SELECT n.idName, concat(n.Name_Last, ', ', n.Name_First, case when n.Name_Nickname is null
                then '' else concat('(' , n.Name_nickname, ')') end) as `Name`, n.Company, n.Member_Status, ne.Email, np.Phone_Num,
                concat(na.Address_1, case when na.Address_2 = '' then '' else concat(' ', na.Address_2) end, ', ', na.City, ', ', na.State_Province, ', ', na.Postal_Code) as `Address`
            FROM name n left join name_email ne on n.idName = ne.idName
                left join name_phone np on n.idName = np.idName
                left join name_address na on n.idName = na.idName
            WHERE n.idName>0 and n.Member_Status not in ('u','TBD','p') and n.Record_Member = 1
            AND ((LOWER(n.Name_Last) = :nl AND (LOWER(n.Name_NickName) = :nf OR LOWER(n.Name_First) = :nf2)) ";

            $parms = array(':nl' => $nl, ':nf' => $nf, ':nf2' => $nf);

            if ($email != '') {
                $query .= " or LOWER(ne.Email) = :em ";
                $parms[':em'] = $email;
            }

            if ($phone != '') {
                $query .= " or LOWER(np.Phone_Num) = :ph ";
                $parms[':ph'] = $phone;
            }

            $query .= ") order by n.Member_Status, n.Name_Last;";

            // Check for an organization
        } else if ($memDesignation == MemDesignation::Organization) {

            $query = "SELECT n.idName, n.Company as `Name`, n.Member_Status, ne.Email, np.Phone_Num,
                concat(na.Address_1, case when na.Address_2 = '' then '' else concat(' ', na.Address_2) end, ', ', na.City, ', ', na.State_Province, ', ', na.Postal_Code) as `Address`
            FROM name n left join name_email ne on n.idName = ne.idName
                left join name_phone np on n.idName = np.idName
                left join name_address na on n.idName = na.idName
            WHERE n.idName>0 and n.Member_Status not in ('u','TBD','p') and n.Record_Member = 0
            AND (LOWER(n.Company) = :nc ";


            $parms = array(':nc' => $nl);

            if ($email != '') {
                $query .= " or LOWER(ne.Email) = :em ";
                $parms[':em'] = $email;
            }

            if ($phone != '') {
                $query .= " or LOWER(np.Phone_Num) = :ph ";
                $parms[':ph'] = $phone;
            }

            $query .= ") order by n.Member_Status, n.Company;";
        } else {
            throw new RuntimeException('Bad member designation: ' . $memDesignation);
        }

        $stmt = $dbh->prepare($query);
        $stmt->execute($parms);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Summary of createDuplicatesDiv
     * @param array $dups
     * @return string
     */
    public static function createDuplicatesDiv(array $dups) {

        if (count($dups) !== 0) {

            $tbl = new HTMLTable();

            foreach ($dups as $d) {
                $tbl->addBodyTr(
                        HTMLTable::makeTd(HTMLInput::generateMarkup($d['idName'], array('type' => 'radio', 'name' => 'hhk-dup-alternate', 'class' => 'hhk-replaceDupWith')))
                        . HTMLTable::makeTd($d['idName'])
                        . HTMLTable::makeTd($d['Name'])
                        . HTMLTable::makeTd($d['Address'])
                );
            }

            $tbl->addHeaderTr(HTMLTable::makeTh('Use') . HTMLTable::makeTh('Id') . HTMLTable::makeTh('Name') . HTMLTable::makeTh('Address'));

            return HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('id' => 'hhkPossibleDups'));
        }

        return '';
    }

    /**
     * Summary of getName_First
     * @return string
     */
    public function getName_First() {
        return $this->Name_First;
    }

    /**
     * Summary of getName_Last
     * @return string
     */
    public function getName_Last() {
        return $this->Name_Last;
    }

    /**
     * Summary of getPhone
     * @return mixed
     */
    public function getPhone() {
        return $this->Phone;
    }

    /**
     * Summary of getCompany
     * @return string
     */
    public function getCompany() {
        return $this->Company;
    }


}
