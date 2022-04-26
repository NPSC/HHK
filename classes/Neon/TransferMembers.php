<?php

namespace HHK\Neon;

use HHK\AuditLog\NameLog;
use HHK\Exception\{RuntimeException, UploadException};
use HHK\Member\MemberSearch;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameRS;
use HHK\SysConst\MemStatus;

/*
 * TransferMembers.php
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of TransferMembers
 *
 * @author Eric
 */
class TransferMembers {

    protected $webService;
    protected $userId;
    protected $password;
    protected $customFields;
    protected $errorMessage;
    protected $pageNumber;
    protected $replies;
    protected $memberReplies;
    protected $hhReplies;
    protected $relationshipMapper;
    protected $hhkToNeonRelationMap;

    // Maximum custom properties for a NEON account
    const MAX_CUSTOM_PROPERTYS = 30;

    const EXCLUDE_TERM = 'excld';

    public function __construct($userId, $password, array $customFields = array()) {

        $this->userId = $userId;
        $this->password = $password;
        $this->customFields = $customFields;
        $this->errorMessage = '';
        $this->pageNumber = 1;
        $this->txMethod = '';
        $this->txParams = '';
        $this->hhReplies = [];
        $this->memberReplies = [];
        $this->replies = [];
    }



    /** Last name search remote
     *
     * @param string $searchCriteria A set of left most letters to search
     * @return array Array of names as search result
     */
    public function searchAccount($searchCriteria) {

        $replys = array();

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $msearch = new MemberSearch($searchCriteria['letters']);
        $standardFields = array('Account ID', 'Account Type', 'Deceased', 'Prefix', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Preferred Name', 'Address Line 1', 'City', 'State', 'Zip Code');

        $search = array(
          'method' => 'account/listAccounts',
          'criteria' => array(
            //array( 'First Name', 'CONTAIN', str_replace('%', '', $msearch->getName_First())),
            array( 'Last Name', 'CONTAIN', str_replace('%', '', $msearch->getName_Last())),
          ),
          'columns' => array(
            'standardFields' => $standardFields,
            'customFields' => array(),
          ),
          'page' => array(
            'currentPage' => $this->pageNumber,
            'pageSize' => 20,
            'sortColumn' => 'Last Name',
            'sortDirection' => 'ASC',
          ),
        );

        $result = $this->webService->search($search);

        if ($this->checkError($result)) {

            $replys['error'] = $this->errorMessage;

        } else if (isset($result['searchResults'])) {

            foreach ($result['searchResults'] as $r) {

                $namArray['id'] = $r["Account ID"];
                $namArray['fullName'] = $r["First Name"] . ' ' . $r["Last Name"];
                $namArray['value'] = ($r['Prefix'] != '' ? $r['Prefix'] . ' ' : '' )
                    . $r["Last Name"] . ", " . $r["First Name"]
                    . ($r['Suffix'] != '' ? ', ' . $r['Suffix'] : '' )
                    . ($r['Preferred Name'] != '' ? ' (' . $r['Preferred Name'] . ')' : '' )
                    . ($r['Deceased'] !== 'No' ? ' [Deceased] ' : '')
                    . ($r['City'] != '' ? '; ' . $r['City'] : '')
                    . ($r['State'] != '' ? ', ' . $r['State'] : '')
                    . ($r['Zip Code'] != '' ? ', ' . $r['Zip Code'] : '');

                $replys[] = $namArray;

            }

            if (count($replys) === 0) {
                $replys[] = array("id" => 0, "value" => "No one found.");
            }
        } else {
            $replys[] = array("id" => 0, "value" => "No one found.");
        }

        return $replys;
    }

    /**
     *
     * @param mixed $accountId Remote account Id
     * @return mixed The Remote account object.
     * @throws RuntimeException
     */
    public function retrieveAccount($accountId) {

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $account = $this->webService->getIndividualAccount($accountId);

        if ($this->checkError($account)) {
            throw new RuntimeException($this->errorMessage);
        }

        return $account;
    }

    /** Update an existing Individual Account including name, phone, address and email
     *
     * @param \PDO $dbh
     * @param array $accountData
     * @param int $idName
     * @return string
     * @throws RuntimeException
     */
    public function updateNeonAccount(\PDO $dbh, $accountData, $idName, $extraSourceCols = [], $updateAddr = TRUE) {

        if ($idName < 1) {
            throw new RuntimeException('HHK Member Id not specified: ' . $idName);
        }


        // Get member data record
        $r = $this->loadSourceDB($dbh, $idName, $extraSourceCols);


        if (is_null($r)) {
            throw new RuntimeException('HHK Member Id not found: ' . $idName);
        }

        if (isset($accountData['accountId']) === FALSE) {
            throw new RuntimeException("Remote account id not found for " . $r['firstName'] . " " . $r['lastName'] . ": HHK Id = " . $idName . ", Account Id = " . $r['accountId']);
        }

        if ($r['accountId'] != $accountData['accountId']) {
            throw new RuntimeException("Account Id mismatch: local account Id = " . $r['accountId'] . ", remote account Id = " . $accountData['accountId'] . ", HHK Id = " . $idName);
        }

        $unwound = array();
        $this->unwindResponse($unwound, $accountData);

        $param['individualAccount.accountId'] = $unwound['accountId'];

        // Name, phone, email
        $this->fillPcName($r, $param, $unwound);

        // Address
        if (isset($r['addressLine1']) && $r['addressLine1'] != '') {

            if ($updateAddr) {
                $r['isPrimaryAddress'] = 'true';
                $this->fillPcAddr($r, $param, $unwound);
            } else {
                // dont update address from HHK.
                $this->fillPcAddr(array(), $param, $unwound);
            }

        }

        // Other crap
        $this->fillOther($r, $param, $unwound);

        $paramStr = $this->fillIndividualAccount($r);

        // Custom Parameters
        $paramStr .= $this->fillCustomFields($r, $unwound);

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $request = array(
           'method' => 'account/updateIndividualAccount',
           'parameters' => $param,
           'customParmeters' => $paramStr
        );

        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            $msg = $this->errorMessage;
        } else {
            $msg = 'Updated ' . $r['firstName'] . ' ' . $r['lastName'];
        }

        return $msg;

    }

    public function unwindResponse(&$line, $results, $prefix = '') {

        foreach ($results as $k => $v) {

            if (is_array($v)) {
                $newPrefix = $prefix . $k . '.';
                $this->unwindResponse($line, $v, $newPrefix);
            } else {
                if (is_bool($v)) {
                    if ($v) {
                        $v = 'true';
                    } else {
                        $v = 'false';
                    }
                }
                $line[$prefix . $k] = $v;
            }
        }

        return;
    }

    public function listCustomFields() {

    	$request = array(
    			'method' => 'common/listCustomFields',
    			'parameters' => array('searchCriteria.component' => 'Account')
    	);

    	// Log in with the web service
    	$this->openTarget($this->userId, $this->password);
    	$result = $this->webService->go($request);

    	if ($this->checkError($result)) {
    		throw new RuntimeException($this->errorMessage);
    	}

    	if (isset($result['customFields']['customField'])) {
    		return $result['customFields']['customField'];
    	}

    	return array();
    }

    public function listSources() {

    	$request = array(
    			'method' => 'account/listSources'
    	);

    	// Log in with the web service
    	$this->openTarget($this->userId, $this->password);
    	$result = $this->webService->go($request);

    	if ($this->checkError($result)) {
    		throw new RuntimeException($this->errorMessage);
    	}

    	if (isset($result['sources']['source'])) {
    		return $result['sources']['source'];
    	}

    	return array();
    }

    public function getCountryIds() {

        $countries = array();

        $request = array(
            'method' => 'account/listCountries',
        );

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);
        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            throw new RuntimeException($this->errorMessage);

        }

        if (isset($result['countries']['country'])) {

            foreach ($result['countries']['country'] as $c) {
                $countries[$c['id']] = $c['name'];
            }
        }

        return $countries;
    }

    public function listNeonType($method, $listName, $listItem) {

        $types = array();

        $request = array(
            'method' => $method,
        );

        // Cludge for relationship types
        if ($method == 'account/listRelationTypes') {
            $request['parameters'] = array('relationTypeCategory'=>'Individual-Individual');
        }

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);
        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            throw new RuntimeException('Method: ' . $method . ', List Name: ' . $listName . ', Error Message: ' .$this->errorMessage);
        }

        if (isset($result[$listName][$listItem])) {

            foreach ($result[$listName][$listItem] as $c) {
                if (isset($c['id'])) {
                    $types[$c['id']] = $c['name'];
                } else if (isset($c['code'])) {
                    $types[$c['code']] = $c['name'];
                }
            }
        }

        return $types;
    }

    public function sendDonations(\PDO $dbh, $username, $start = '', $end = '') {

        $replys = array();
        $this->memberReplies = array();
        $idMap = array();
        $mappedItems = array();
        $whereClause = '';

        if ($start != '') {
            $whereClause = " and DATE(`date`) >= DATE('$start') ";
        }

        if ($end != '') {
            $whereClause .= " and DATE(`date`) <= DATE('$end') ";
        }

        $stmt = $dbh->query("Select * from vguest_neon_payment where 1=1 $whereClause");

        if ($stmt->rowCount() < 1) {
            return array(array('Donation Result'=>'No new HHK payments found to transfer.  '));
        }

        // Load the time codes for output
        $stmtList = $dbh->query("Select * from neon_type_map");
        $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($items as $i) {
            $mappedItems[$i['Neon_Name']][$i['Neon_Type_Code']] = $i['Neon_Type_Name'];
        }

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Don't process empty fund.id's
            if ($r['fund.id'] == '') {
                continue;
            }

            // return data array.
            $f = array();

            // Prefill output array
            foreach ($r as $k => $v) {

                if ($k != '') {

                    if ($k == 'amount') {
                        $f[$k] = number_format($v, 2);
                    } else if ($k == 'fund.id' && isset($mappedItems['fund'][$v])) {
                        $f[$k] = $mappedItems['fund'][$v];
                    } else if ($k == 'tenderType.id' && isset($mappedItems['tender'][$v])) {
                        $f[$k] = $mappedItems['tender'][$v];
                    } else if ($k == 'cardType.name' && isset($mappedItems['creditCardType'][$v])) {
                        $f[$k] = $mappedItems['creditCardType'][$v];
                    } else if ($k != 'source.name') {
                        $f[$k] = $v;
                    }
                }
            }

            // Is the account defined?
            if ($r['accountId'] == '' && isset($idMap[$r['hhkId']])) {
                // Already made a new Neon account.
                $r['accountId'] = $idMap[$r['hhkId']];

            } else if ($r['accountId'] == '') {

                // Search and create a new account if needed.
                $acctReplys = $this->sendList($dbh, array($r['hhkId']), $username);

                if (isset($acctReplys[0]['Account ID']) && $acctReplys[0]['Account ID'] != '') {

                    // A new account is created.
                    $this->memberReplies[] = $acctReplys[0];
                    $r['accountId'] = $acctReplys[0]['Account ID'];
                    $f['accountId'] = $acctReplys[0]['Account ID'] . '*';
                    $idMap[$r['hhkId']] = $acctReplys[0]['Account ID'];

                } else if (isset($acctReplys[0]['Result'])) {

                    // Some kind of problem like multiple accounts found.
                    $f['Result'] = $acctReplys[0]['Result'];
                    $replys[] = $f;
                    continue;

                } else {
                    $f['Result'] = "Undefined problem adding HHK person to Neon.";
                    $replys[] = $f;
                    continue;

                }
            }


            // Make the donation with the HHK payment record.
            $this->createDonation($dbh, $r, $f);


            $replys[] = $f;
        }

        return $replys;
    }

    protected function createDonation(\PDO $dbh, $r, &$f) {

        $param = array();

        $this->fillDonation($r, $param);
        $this->fillPayment($r, $param);

        $request = array(
          'method' => 'donation/createDonation',
          'parameters' => $param,

          );


        $wsResult = $this->webService->go($request);


        if ($this->checkError($wsResult)) {

            $f['Result'] = $this->errorMessage;

        } else if (isset($wsResult['donationId']) === FALSE) {

            $f['Result'] = 'Huh?  The donation Id was not set';

        } else {

            try {

                $this->updateLocalPaymentRecord($dbh, $r['idPayment'], $wsResult['donationId']);
                $f['Result'] = 'Success';

            } catch (UploadException $uex) {

                $f['Result'] = $uex->getMessage();
            }

            $f['External Payment Id'] = $wsResult['donationId'];
        }

        return $wsResult;

    }

    /** Sends stay information, hospital and diagnosis, and households.
     *
     * @param \PDO $dbh
     * @param string $username
     * @param string $end
     * @return array
     */
    public function sendVisits(\PDO $dbh, $username, $idPsg, $rels) {

        $this->memberReplies = [];
        $this->replies = [];

        // dont allow if neon config file doesnt have the custom fileds
        if (isset($this->customFields['First_Visit']) === FALSE) {
            $rep = array();
            $rep[] = array('Update_Message'=>'Vist transfer is not configured.');
            return $rep;
        }

        $visits = [];
        $stayIds = [];
        $guestIds = [];
        $sendIds = [];
        $psgs = [];


        // Read stays from db
        $stmt = $dbh->query("SELECT
    s.idStays,
    s.idVisit,
    s.Visit_Span,
    s.idName AS `hhkId`,
    IFNULL(v.idPrimaryGuest, 0) as `idPG`,
    IFNULL(n.External_Id, '') AS `accountId`,
    IFNULL(n.Name_Last, '') AS `Last_Name`,
    IFNULL(n.Name_Full, '') AS `Full_Name`,
    IFNULL(hs.idHospital, 0) AS `idHospital`,
    IFNULL(hs.Diagnosis, '') AS `Diagnosis_Code`,
    IFNULL(hs.idPsg, 0) as `idPsg`,
    IFNULL(hs.idPatient, 0) as `idPatient`,
    IFNULL(ng.Relationship_Code, '') as `Relation_Code`,
    CONCAT_WS(' ', na.Address_1, na.Address_2) as `Address`,
    IFNULL(DATE_FORMAT(s.Span_Start_Date, '%Y-%m-%d'), '') AS `Start_Date`,
    IFNULL(DATE_FORMAT(s.Span_End_Date, '%Y-%m-%d'), '') AS `End_Date`,
    datediff(DATE(`s`.`Span_End_Date`), DATE(`s`.`Span_Start_Date`)) AS `Nite_Counter`
FROM
    stays s
        LEFT JOIN
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
        LEFT JOIN
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        LEFT JOIN
    `name` n ON s.idName = n.idName
		LEFT JOIN
	name_guest ng on s.idName = ng.idName and hs.idPsg = ng.idPsg
        LEFT JOIN
    name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
WHERE
    s.On_Leave = 0
    AND s.`Status` != 'a'
    AND s.Recorded = 0
    AND n.External_Id != '" . self::EXCLUDE_TERM . "'
    AND n.Member_Status = '" . MemStatus::Active ."'
    AND s.Span_End_Date is not NULL
    AND datediff(DATE(`s`.`Span_End_Date`), DATE(`s`.`Span_Start_Date`)) > 0
    AND hs.idPsg = $idPsg
ORDER BY s.idVisit , s.Visit_Span , s.idName , s.Span_Start_Date" );

        // Count up guest stay dates and nights.
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $stayIds[] = $r['idStays'];

            $visits[$r['idVisit']] = array(
                'idPG' => $r['idPG'],
                'idPatient' => $r['idPatient'],
                'idPsg' => $r['idPsg']
            );

            $psgs[$r['idPsg']] = array(
                'idHospital' => $r['idHospital'],
                'Diagnosis_Code' => $r['Diagnosis_Code']
            );

            if (isset($guestIds[ $r['hhkId'] ])) {

                $startDT = new \DateTime($r['Start_Date']);
                $endDT = new \DateTime($r['End_Date']);

                if ($guestIds[ $r['hhkId'] ]['Start_Date'] > $startDT ) {
                    $guestIds[ $r['hhkId'] ]['Start_Date'] = $startDT;
                }

                if ($guestIds[ $r['hhkId'] ]['End_Date'] < $endDT ) {

                    $guestIds[ $r['hhkId'] ]['End_Date'] = $endDT;

                    // Always use latest hospital stay
                    $psgs[$r['idPsg']] = array(
                        'idHospital' => $r['idHospital'],
                        'Diagnosis_Code' => $r['Diagnosis_Code']
                    );
                }

                $guestIds[ $r['hhkId'] ]['Nite_Counter'] += $r['Nite_Counter'];

            } else {

                // new guest
                $guestIds[ $r['hhkId'] ] = array(
                    'hhkId' => $r['hhkId'],
                    'accountId' => $r['accountId'],
                    'idPsg' => $r['idPsg'],
                    'Relation_Code' => $r['Relation_Code'],
                    'Start_Date' => new \DateTime($r['Start_Date']),
                    'End_Date' => new \DateTime($r['End_Date']),
                    'Nite_Counter' => $r['Nite_Counter'],
                    'Address' => $r['Address'],
                    'Last_Name' => $r['Last_Name'],
                    'Full_Name' => $r['Full_Name'],
                    'Neon_Rel_Code' => (isset($rels[$r['hhkId']]) ? $rels[$r['hhkId']] : ''),
                );

            }
        }

        // Adds any non visitors to the list of guests.
        $this->getNonVisitors($dbh, array_keys($visits), $guestIds, $rels);

        // Check for and combine any missing Neon account ids.
        foreach ($guestIds as $id => $r ) {

            // Is the account defined?
            if ($r['accountId'] == '') {
                $sendIds[] = $id;
            }
        }

        // Search and create a new Neon account if needed.
        if (count($sendIds) > 0) {

            // Write to Neon
            $this->memberReplies = $this->sendList($dbh, $sendIds, $username);

            // Capture new account Id's from any new members.
            foreach ($this->memberReplies as $f) {

                if (isset($f['Account ID']) && $f['Account ID'] !== '') {
                    $guestIds[$f['HHK_ID']]['accountId'] = $f['Account ID'];
                } else if (isset($f['Result']) && $f['Result'] == 'Account Deleted at Neon') {
                    // no further processing
                    unset($guestIds[$f['HHK_ID']]);
                }
            }
        }

        $badUpdateIds = [];

        // Fill the custom parameters for each visit.
        foreach ($guestIds as $r ) {

            // Write the visits to Neon
            try {
                $visitReplys[] = $this->updateVisitParms($dbh, $r, $psgs);
            } catch (\Exception $e) {
                $visitReplys[] = array(
                    'First_Visit'=>'',
                    'Last_Visit'=>'',
                    'Nite_Counter'=>'',
                    'Diagnosis'=>'',
                    'Hospital'=>'',
                    'PSG_Number'=>$r['idPsg'],
                    'Update_Message'=>'Neon account id not found for ' . $r['Full_Name'] . ': HHK Id = ' . $r['hhkId'] . ' Account Id = '. $r['accountId']
                );
                $badUpdateIds[$r['hhkId']] = $r['hhkId'];
            }

        }

        // Mark the stays record as "Recorded".
        $this->updateStayRecorded($dbh, $stayIds);

        // Remove bad updates
        foreach ($badUpdateIds as $b) {
            unset($guestIds[$b]);
        }

        // Relationship Mapper object.
        $this->relationshipMapper = new RelationshipMapper($dbh);

        // Create or update households.
        $this->sendHouseholds($dbh, $guestIds, $visits, $rels, $badUpdateIds);

        return $visitReplys;
    }

    protected function updateVisitParms(\PDO $dbh, $r, $psgs) {

        // Retrieve the Account
        $origValues = $this->retrieveAccount($r['accountId']);
        $codes = [];
        $f = array();

        // Check for earliest visit start
        if (isset($r['Start_Date']) && isset($this->customFields['First_Visit'])) {

            $startDT = $r['Start_Date'];
            $earliestStart = $this->findCustomField($origValues, $this->customFields['First_Visit']);

            if ($earliestStart !== FALSE && $earliestStart != '') {

                $earlyDT = new \DateTime($earliestStart);

                if ($earlyDT > $startDT) {
                    $codes['First_Visit'] = $startDT->format('m/d/Y');
                } else {
                    $codes['First_Visit'] = $earliestStart;
                }
            } else {
                $codes['First_Visit'] = $startDT->format('m/d/Y');
            }

            $f['First_Visit'] = $codes['First_Visit'];

        } else if (isset($r['Start_Date']) === FALSE) {
            $f['First_Visit'] = '';
        }

        // Check for latest visit end
        if (isset($r['End_Date']) && isset($this->customFields['Last_Visit'])) {

            $endDT = $r['End_Date'];
            $latestEnd = $this->findCustomField($origValues, $this->customFields['Last_Visit']);

            if ($latestEnd !== FALSE && $latestEnd != '') {

                $lateDT = new \DateTime($latestEnd);

                if ($lateDT < $endDT) {
                    $codes['Last_Visit'] = $endDT->format('m/d/Y');
                }else {
                    // No change
                    $codes['Last_Visit'] = $latestEnd;
                }
            } else {
                $codes['Last_Visit'] = $endDT->format('m/d/Y');
            }

            $f['Last_Visit'] = $codes['Last_Visit'];

        } else if (isset($r['End_Date']) === FALSE) {
            $f['Last_Visit'] = '';
        }

        // Check Nights counter
        if (isset($r['Nite_Counter']) && isset($this->customFields['Nite_Counter'])) {

            $nites = intval($r['Nite_Counter'], 10);
            $niteCounter = intval($this->findCustomField($origValues, $this->customFields['Nite_Counter']), 10);

            $codes['Nite_Counter'] = ($niteCounter + $nites);
            $f['Nite_Counter'] = $codes['Nite_Counter'];

        } else if (isset($r['Nite_Counter']) === FALSE) {
            $f['Nite_Counter'] = '';
        }

         // Check Diagnosis
         if (isset( $psgs[$r['idPsg']]['Diagnosis_Code']) && isset($this->customFields['Diagnosis'])) {

             $codes['Diagnosis'] = $psgs[$r['idPsg']]['Diagnosis_Code'];
             $f['Diagnosis'] = $codes['Diagnosis'];
         }

         // Check Hospital
         if (isset($psgs[$r['idPsg']]['idHospital']) && isset($this->customFields['Hospital'])) {

             $codes['Hospital'] = $psgs[$r['idPsg']]['idHospital'];
             $f['Hospital'] = $codes['Hospital'];
         }

         // Check PSG id
         if (isset($r['idPsg']) && isset($this->customFields['PSG_Number'])) {

             $codes['PSG_Number'] = $r['idPsg'];
             $f['PSG_Number'] = $codes['PSG_Number'];
         }


         // Update Neon with these customdata.
         $f['Update_Message'] = $this->updateNeonAccount($dbh, $origValues, $r['hhkId'], $codes, FALSE);

         return $f;
    }

    protected function updateStayRecorded(\PDO $dbh, $stayIds) {

        $idList = [];

        // clean up the stay ids
        foreach ($stayIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            $parm = "(" . implode(',', $idList) . ") ";
            return $dbh->exec("Update stays set Recorded = 1 where idStays in $parm");

        }

        return NULL;
    }

    public static function getNonVisitors(\PDO $dbh, $visits, &$guestIds, $rels) {

        $idList = [];
        $idNames = [];

        // clean up the visit ids
        foreach ($visits as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            $stmt = $dbh->query("Select	DISTINCT
    ng.idName AS `hhkId`,
    IFNULL(ng.Relationship_Code, '') as `Relation_Code`,
    IFNULL(n.External_Id, '') AS `accountId`,
    IFNULL(n.Name_Last, '') AS `Last_Name`,
    IFNULL(n.Name_Full, '') AS `Full_Name`,
    IFNULL(hs.idPsg, 0) as `idPsg`,
    CONCAT_WS(' ', na.Address_1, na.Address_2) as `Address`
from
	visit v
		join
	hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        join
	name_guest ng on hs.idPsg = ng.idPsg
		left join
	stays s on ng.idName = s.idName
        LEFT JOIN
    name n on n.idName = ng.idName
        LEFT JOIN
    name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
where
	s.idName is NULL
    AND n.External_Id != '" . self::EXCLUDE_TERM . "'
    AND n.Member_Status = '" . MemStatus::Active ."'
    AND v.idVisit in (" . implode(',', $idList) . ")");

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $idNames[ $r['hhkId'] ] = $r;

            }
        }

        if (count($idNames) > 0) {

            foreach ($idNames as $r) {
                // add them to the list of guests
                if (isset($guestIds[ $r['hhkId'] ]) === FALSE) {

                    $r['Neon_Rel_Code'] = (isset($rels[$r['hhkId']]) ? $rels[$r['hhkId']] : '');

                    $guestIds[ $r['hhkId'] ] = $r;
                }
            }
        }

    }

    protected function sendHouseholds(\PDO $dbh, $guests, $visits, $rels, $badUpdateIds) {

        foreach ($visits as $v) {

            // Primary guest defined?
            if (isset($guests[$v['idPG']]) === FALSE) {

                // Load Primary guest.
                $guests[$v['idPG']] = $this->findPrimaryGuest($dbh, $v['idPG'], $v['idPsg'], $this->relationshipMapper);

                if (count($guests[$v['idPG']]) == 0) {
                    continue;
                }
            }

            // Primary guest have an Neon Account Id?
            if ($guests[$v['idPG']]['accountId'] < 1) {
                continue;
            }

            // Bad account ID
            if (isset($badUpdateIds[$guests[$v['idPG']]['hhkId']])) {
                continue;
            }

            // Set Relationship mapper.
            $this->relationshipMapper->clear()->setPGtoPatient($guests[$v['idPG']]['Relation_Code']);

            // Does primary guest have a hh?
            $households = $this->searchHouseholds($guests[$v['idPG']]['accountId']);
            $householdId = 0;
            $countHouseholds = 0;

            // Find any households?
            if (isset($households['houseHolds']['houseHold'])) {
                $countHouseholds = count($households['houseHolds']['houseHold']);
            }

            // Check for NEON not finding the household Id
            if ($countHouseholds == 0) {

                // Create a new household for the primary guest
                $householdId = $this->createHousehold($guests[$v['idPG']]);

            } else {

                $hhs = $households['houseHolds']['houseHold'];

                // Find the household where primary guest is the primary contact.
                foreach ($hhs as $hh) {

                    // Get the primary household contact
                    $pcontact = $this->findHhPrimaryContact($hh);

                    // Found?
                    if (isset($pcontact['accountId']) && $guests[$v['idPG']]['accountId'] == $pcontact['accountId']) {
                        // primary guest household found.
                        $householdId = $hh['houseHoldId'];

                        break;
                    }
                }

                // Create a new household if none found.
                if ($householdId == 0) {

                    // Create a new household for the primary guest
                    $householdId = $this->createHousehold($guests[$v['idPG']]);
                }
            }


            // Should have an household id
            if ($householdId == 0) {
                continue;
            }

            // Add guest to household.
           $this->addToHousehold($householdId, $guests[$v['idPG']], $guests, $v['idPatient']);

        }  // next visit

    }

    public function searchHouseholds($accountId, $idHousehold = 0) {

        if ($idHousehold > 0) {
            $parms = array('householdId' => $idHousehold);
        } else if ($accountId > 0 ) {
            $parms = array('accountId' => $accountId);
        } else {
            return [];
        }

        $request = array(
            'method' => 'account/listHouseHolds',
            'parameters' => $parms,
        );

        $households = $this->webService->go($request);

        if ($this->checkError($households)) {
            $households['error'] = ($this->errorMessage);
        }

        return $households;
    }

    protected function findHhPrimaryContact(array $household) {

        $pContact = [];

        // Check the primary guest is the primary household contact
        foreach ($household['houseHoldContacts']['houseHoldContact'] as $hc) {

            if ($hc['isPrimaryHouseHoldContact'] == 'true') {

                $pContact = $hc;

                break;
            }
        }

        return $pContact;
    }

    /**
     *
     * @param int $primaryContactId
     * @param int $relationId
     * @param string $householdName
     * @param array $f
     * @return string|mixed
     */
    protected function createHousehold($primaryGuest) {

        $householdId = 0;
        $householdName = $this->unencodeHTML($primaryGuest['Last_Name']);
        $relationId = $primaryGuest['Neon_Rel_Code'];

        if ($householdName == '') {
            $this->setHhReplies(array(
                'Action'=>'Create',
                'Account Id'=>$primaryGuest['accountId'],
                'Result'=> 'Blank last name, Household not created.',
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId)));
            return $householdId;
        }

        if ($relationId == '') {
            $this->setHhReplies(array(
                'Action'=>'Create',
                'Account Id'=>$primaryGuest['accountId'],
                'Result'=> 'Relationship is undefined, Household not created.',
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId)));
            return $householdId;
        }

        // Primary Guest must have an address
        if ($primaryGuest['Address'] == '') {
            $this->setHhReplies(array(
                'Action'=>'Create',
                'Household'=>$householdName,
                'Account Id'=>$primaryGuest['accountId'],
                'Result'=> 'Blank address, Household not created.',
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId)));
            return $householdId;
        }


        // Finally, make a new household in Neon.
        $base = 'household.';
        $param[$base . 'name'] = $householdName;
        $param[$base . 'houseHoldContacts.houseHoldContact.accountId'] = $primaryGuest['accountId'];
        $param[$base . 'houseHoldContacts.houseHoldContact.relationType.id'] = $relationId;
        $param[$base . 'houseHoldContacts.houseHoldContact.isPrimaryHouseHoldContact'] = 'true';

        $request = array(
            'method' => 'account/createHouseHold',
            'parameters' => $param,
        );

        $wsResult = $this->webService->go($request);

        if ($this->checkError($wsResult)) {

            $this->setHhReplies(array(
                'Household'=>$householdName,
                'Account Id'=>$primaryGuest['accountId'],
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId),
                'Action'=>'Create',
                'Result'=> 'Failed: ' . $this->errorMessage));

        } else if (isset($wsResult['houseHoldId'])) {

            $householdId = $wsResult['houseHoldId'];
            $this->setHhReplies(array(
                'HH Id'=>$householdId,
                'Household'=>$householdName,
                'Account Id'=>$primaryGuest['accountId'],
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId),
                'Action'=>'Create',
                'Result'=> 'Success'));
        }

        return $householdId;
    }

    protected function addToHousehold($householdId, $pg, $guests, $idPatient) {

        $countHouseholds = 0;
        $newContacts = [];


        $households = $this->searchHouseholds(0, $householdId);

        if (isset($households['houseHolds']['houseHold'])) {
            $countHouseholds = count($households['houseHolds']['houseHold']);
        }

        if ($countHouseholds == 1) {

            $notJoined = 0;

            foreach ($guests as $g) {

                if ($g['idPsg'] == $pg['idPsg'] && $g['hhkId'] != $pg['hhkId']) {

                    // Valid guest
                    $foundId = FALSE;
                    $hhContacts = $households['houseHolds']['houseHold'][0]['houseHoldContacts']['houseHoldContact'];

                    // Search hh contacts
                    foreach ($hhContacts as $hc) {
                        if ($hc['accountId'] == $g['accountId']) {
                            $foundId = TRUE;
                            break;
                        }
                    }

                    if ($foundId === FALSE) {
                        // Update the household with new member

                        // Only if addresses match, or this is the patient and patient's address is blank.
                        if ((strtolower($pg['Address']) == strtolower($g['Address'])) || ($g['hhkId'] == $idPatient && $g['Address'] == '')) {
                            $newContacts[] = $g;
                        } else {
                            $this->setHhReplies(array(
                                'Household'=> $households['houseHolds']['houseHold'][0]['name'],
                                'HH Id'=>$households['houseHolds']['houseHold'][0]['houseHoldId'],
                                'Account Id'=>$g['accountId'],
                                'Name' => $g['Full_Name'],
                                'Action'=>'Join',
                                'Result' => 'Address Mismatch'
                            ));

                            $notJoined++;
                        }
                    }
                }
            }

            if (count($newContacts) > 0) {

                $this->updateHousehold($newContacts, $households['houseHolds']['houseHold'][0]);

            } else if ($notJoined > 0) {

                $this->setHhReplies(array(
                    'Household'=>$households['houseHolds']['houseHold'][0]['name'],
                    'HH Id'=>$households['houseHolds']['houseHold'][0]['houseHoldId'],
                    'Action'=>'Update',
                    'Account Id'=>'-',
                    'Name' => '-',
                    'Result'=>'Nobody joined this household.'));
            }
        }
    }

    protected function updateHousehold($newGuests, $household) {

        $base = 'household.';
        $customParamStr = '';

        $param[$base . 'householdId'] = $household['houseHoldId'];
        $param[$base . 'name'] = $household['name'];

        $pg = $this->findHhPrimaryContact($household);

        $param[$base . 'houseHoldContacts.houseHoldContact.accountId'] = $pg['accountId'];
        $param[$base . 'houseHoldContacts.houseHoldContact.relationType.id'] = $pg['relationType']['id'];
        $param[$base . 'houseHoldContacts.houseHoldContact.isPrimaryHouseHoldContact'] = 'true';

        foreach ($newGuests as $ng) {

            $ngRelationId = $ng['Neon_Rel_Code'];  //$this->relationshipMapper->relateGuest($ng['Relation_Code']);

            if ($ngRelationId != '') {

                $cparm = array(
                    $base . 'houseHoldContacts.houseHoldContact.accountId' => $ng['accountId'],
                    $base . 'houseHoldContacts.houseHoldContact.relationType.id' => $ngRelationId,
                    $base . 'houseHoldContacts.houseHoldContact.isPrimaryHouseHoldContact' => 'false',
                );

                $customParamStr .= '&' . http_build_query($cparm);

                $this->setHhReplies([
                    'HH Id'=>$household['houseHoldId'],
                    'Action'=>'Join',
                    'Household'=>$household['name'],
                    'Account Id'=>$ng['accountId'],
                    'Relationship' => $this->relationshipMapper->mapNeonTypeName($ngRelationId),
                    'Name'=>$ng['Full_Name']
                ]);

            } else {

                $this->setHhReplies(array(
                    'HH Id'=>$household['houseHoldId'],
                    'Household'=>$household['name'],
                    'Action'=>'Join',
                    'Account Id'=>$ng['accountId'],
                    'Relationship' => $ngRelationId,
                    'Name'=>$ng['Full_Name'],
                    'Result' => 'Failed: Relationship Missing.'
                ));
            }

        }

        $request = array(
            'method' => 'account/updateHouseHold',
            'parameters' => $param,
            'customParmeters' => $customParamStr,
        );


        $wsResult = $this->webService->go($request);

        if ($this->checkError($wsResult)) {

            $this->setHhReplies(array(
                'HH Id'=>$household['houseHoldId'],
                'Household'=>$household['name'],
                'Action'=>'Update',
                'Account Id'=>'-',
                'Name'=>'-',
                'Result' => 'Failed: '.$this->errorMessage
            ));

            // Check for special error codes.
            foreach ($wsResult['errors'] as $errors) {

                foreach ($errors as $e) {

                    // Check for "Given Household account is already a primary contact in another houseHold."
                    if ($e['errorCode'] == '10158') {
                        $this->deleteHousehold($household, $pg['accountId']);
                        break;
                    }
                }
            }

        } else if (isset($wsResult['houseHoldId']) === FALSE) {

            $this->setHhReplies(array(
                'Household'=>$household['name'],
                'Action'=>'Update',
                'Result'=>'Failed: Household Id not returned'));

        } else {
            $this->setHhReplies(array(
                'HH Id'=>$wsResult['houseHoldId'],
                'Household'=>$household['name'],
                'Action'=>'Update',
                'Result'=>'Success',
                'Name'=>'-',
                'Account Id'=>'-',
            ));
        }

    }

    protected function deleteHousehold($household, $accountId) {

        $request = array(
            'method' => 'account/deleteHouseHold',
            'parameters' => array('houseHoldId' => $household['houseHoldId'])
        );

        $wsResult = $this->webService->go($request);

        if ($this->checkError($wsResult)) {

            $this->setHhReplies(array(
                'HH Id'=>$household['houseHoldId'],
                'Household'=>$household['name'],
                'Account Id'=>$accountId,
                'Action'=>'Delete',
                'Result'=> 'Failed: ' . $this->errorMessage));

        } else {

            $this->setHhReplies(array(
                'HH Id'=>$household['houseHoldId'],
                'Household'=>$household['name'],
                'Account Id'=>$accountId,
                'Action'=>'Delete',
                'Result'=> 'Success: HouseHold deleted.'));
        }
    }


    public static function findPrimaryGuest(\PDO $dbh, $idPrimaryGuest, $idPsg, RelationshipMapper $rMapper) {

        $stmt = $dbh->query("Select
	n.idName as `hhkId`,
    IFNULL(n.External_Id, '') AS `accountId`,
    IFNULL(n.Name_Last, '') AS `Last_Name`,
    IFNULL(n.Name_Full, '') AS `Full_Name`,
    IFNULL(ng.Relationship_Code, '') as `Relation_Code`,
    CONCAT_WS(' ', na.Address_1, na.Address_2) as `Address`
FROM
	`name` n
		LEFT JOIN
    `name_guest` ng on n.idName = ng.idName and ng.idPsg = $idPsg
		LEFT JOIN
    name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
where n.External_Id != '" . self::EXCLUDE_TERM . "' AND n.Member_Status = '" . MemStatus::Active ."' AND n.idName = $idPrimaryGuest ");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $r = $rows[0];
        } else {
            return [];
        }

        $rMapper->clear()->setPGtoPatient($r['Relation_Code']);
        $relId = $rMapper->relateGuest($r['Relation_Code']);

        return array(
            'hhkId' => $r['hhkId'],
            'accountId' => $r['accountId'],
            'idPsg' => $idPsg,
            'Relation_Code' => $r['Relation_Code'],
            'Address' => $r['Address'],
            'Last_Name' => $r['Last_Name'],
            'Full_Name' => $r['Full_Name'],
            'Neon_Rel_Code' => $relId
        );
    }

    /**
     * @param \PDO $dbh
     * @param array $idPsgs
     * @param string $username
     * @return array
     */
    public static function sendExcludes(\PDO $dbh, $idPsgs, $username) {

        $idList = [];
        $idNames = [];
        $numUpdates = 0;

        // clean up the visit ids
        foreach ($idPsgs as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            // Remove Exclude status when an excluded member checks in.
            $stmt = $dbh->query("select DISTINCT n.idName AS `HHK Id`, n.Name_Full as `Full Name` from `name` n join name_guest ng on n.idName = ng.idName
                where ng.idPsg in (" . implode(',', $idList) . ");" );

            // Reset each external Id, and log it.
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $idNames[] = $r;

                $n = new NameRS();
                $n->idName->setStoredVal($r['HHK Id']);
                $names = EditRS::select($dbh, $n, array($n->idName));
                EditRS::loadRow($names[0], $n);

                $n->External_Id->setNewVal(self::EXCLUDE_TERM);
                $numRows = EditRS::update($dbh, $n, array($n->idName));

                if ($numRows > 0) {
                    // Log it.
                    NameLog::writeUpdate($dbh, $n, $n->idName->getStoredVal(), $username);
                    $numUpdates++;
                }
            }


//                 // Collect the names for return message
//             $stmt = $dbh->query("select DISTINCT n.idName AS `HHK Id`, n.Name_Full as `Full Name` from
//                 name_guest ng join `name` n on ng.idName = n.idName
//                 where ng.idPsg in (" . implode(',', $idList) . ");");

//             while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

//                 $idNames[] = $r;

//             }

//             // Update the External Id's
//             $rowcount = $dbh->exec("update name_guest ng join name n on ng.idName = n.idName set n.External_Id = 'excld'
//                 where ng.idPsg in (" . implode(',', $idList) . ");");

            $idNames[] = array('HHK Id'=>'', 'Full Name'=>$numUpdates . ' members updated.');
        }

        return $idNames;
    }

    /** Transfer the given source HHK ids to Neon.  Searches first, updates Neon if found.
     *
     * @param \PDO $dbh
     * @param array $sourceIds
     * @param string $username
     * @return array
     */
    public function sendList(\PDO $dbh, array $sourceIds, $username) {

        $replys = array();

        if (count($sourceIds) == 0) {
            $replys[0] = array('error'=>"The list of HHK Id's to send is empty.");
            return $replys;
        }

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        // Load Individual types
        $stmtList = $dbh->query("Select * from neon_type_map where List_Name = 'individualTypes'");
        $invTypes = array();

        while ($t = $stmtList->fetch(\PDO::FETCH_ASSOC)) {
            $invTypes[] = $t;
        }

        // Load search parameters for each source ID
        $stmt = $this->loadSearchDB($dbh, $sourceIds);

        if (is_null($stmt)) {
            $replys[0] = array('error'=>'No local records were found.');
            return $replys;
        }


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $f = array();   // output array

            // Prefill output array
            foreach ($r as $k => $v) {

                if ($k != '') {
                    $f[$k] = $v;
                }
            }

            // Search target system
            $result = $this->searchTarget($r);

            if ($this->checkError($result)) {
                $f['Result'] = $this->errorMessage;
                $replys[$r['HHK_ID']] = $f;
                continue;
            }


            // Check for NEON not finding the account Id
            if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] == 0 && $r['Account Id'] != '') {

                // Account was deleted from the Neon side.
                $f['Result'] = 'Account Deleted at Neon';   // procedure sendVisits() depends upon the exact wording of the quoted text. circa line 638
                $replys[$r['HHK_ID']] = $f;
                continue;

            }


            // Test results
            if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] == 1 ) {

                // We have a similar contact.

                // Make sure the external Id is defined locally
                if (isset($result['searchResults'][0]['Account ID']) && $result['searchResults'][0]['Account ID'] != '') {

                    $this->updateLocalNameRecord($dbh, $r['HHK_ID'], $result['searchResults'][0]['Account ID'], $username);
                    $f['Account ID'] = $result['searchResults'][0]['Account ID'];
                    $f['Result'] = 'Previously Transferred.';

                    // Check individual type
                    $typeFound = FALSE;

                    if (isset($result['searchResults'][0]['Individual Type'])) {

                        foreach ($invTypes as $t) {

                            if (stristr($result['searchResults'][0]['Individual Type'], $t['Neon_Type_Name']) !== FALSE) {
                                $typeFound = TRUE;
                                break;
                            }
                        }
                    }

                    if ($typeFound === FALSE) {
                        // Update the individual type
                        try{
                            $retrieveResult = $this->retrieveAccount($result['searchResults'][0]['Account ID']);
                            $f['Result'] .= $this->updateNeonAccount($dbh, $retrieveResult, $r['HHK_ID']);
                        } catch (RuntimeException $hex) {
                            $f['Result'] .= 'Update Individual Type Error: ' . $hex->getMessage();
                            continue;
                        }
                    }

                } else {

                    $f['Result'] = 'The search results Account Id is empty.';
                }

                $replys[$r['HHK_ID']] = $f;


            } else if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] > 1 ) {

                // We have more than one contact...
                $f['Result'] = 'Multiple Accounts.';
                $replys[$r['HHK_ID']] = $f;


            } else if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] == 0 ) {

                // Nothing found - create a new account at remote

                // Get member data record
                $row = $this->loadSourceDB($dbh, $r['HHK_ID']);

                if (is_null($row)) {
                    continue;
                }

                // Create new account
                $result = $this->createAccount($row);

                if ($this->checkError($result)) {
                    $f['Result'] = $this->errorMessage;
                    $replys[$r['HHK_ID']] = $f;
                    continue;
                }

                $accountId = filter_var($result['accountId'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalNameRecord($dbh, $r['HHK_ID'], $accountId, $username);

                if ($accountId != '') {
                    $f['Result'] = 'New NeonCRM Account';
                } else {
                    $f['Result'] = 'NeonCRM Account Missing';
                }
                $f['Account ID'] = $accountId;
                $replys[$r['HHK_ID']] = $f;

            } else {

                //huh?
                $f['Result'] = 'API ERROR: The Number of returned records is not defined.';
                $replys[$r['HHK_ID']] = $f;
            }

        }

        return $replys;

    }

    protected function updateLocalNameRecord(\PDO $dbh, $idName, $externalId, $username) {

        if ($externalId != '' && $idName > 0) {
            $nameRs = new NameRS();
            $nameRs->idName->setStoredVal($idName);
            $rows = EditRS::select($dbh, $nameRs, array($nameRs->idName));
            EditRS::loadRow($rows[0], $nameRs);

            $nameRs->External_Id->setNewVal($externalId);
            $upd = EditRS::update($dbh, $nameRs, array($nameRs->idName));

            if ($upd > 0) {
                NameLog::writeUpdate($dbh, $nameRs, $nameRs->idName->getStoredVal(), $username);
            }
        }
    }

    protected function updateLocalPaymentRecord(\PDO $dbh, $idPayment, $externalId) {

        $result = 0;

        if ($externalId != '' && $idPayment > 0) {

            $extId = filter_var($externalId, FILTER_SANITIZE_STRING);

            $stmt = $dbh->query("Select count(*) from payment where idPayment = $idPayment and External_Id = '$extId'");
            $extRows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($extRows[0]) == 1 && $extRows[0][0] > 0) {
                throw new UploadException("HHK Payment Record (idPayment = $idPayment) already has a Donation Id = " . $extId);
            }

            $result = $dbh->exec("Update `payment` set External_Id = '$extId' where idPayment = $idPayment;");

        }

        return $result;
    }

    protected function fillDonation($r, &$param) {

        $codes = array(
            'accountId',
            'amount',
            'date',
            'fund.id',
            'source.name',
        );

        $base = 'donation.';

        foreach ($codes as $c) {

            if (isset($r[$c]) && $r[$c] != '') {
                $param[$base . $c] = $r[$c];
            }
        }
    }

    protected function fillPayment($r, &$param) {

        $codes = array(
            'amount',
            'tenderType.id',
            'note',
        );

        $base = 'Payment.';

        foreach ($codes as $c) {

            if (isset($r[$c]) && $r[$c] != '') {
                $param[$base . $c] = $r[$c];
            }
        }

        switch ($r['tenderType.id']) {

            // Charge
            case '2':
                $param[$base . 'creditCardOfflinePayment.cardNumber'] = '444444444444' . $r['cardNumber'];
                $param[$base . 'creditCardOfflinePayment.cardHolder'] = $r['cardHolder'];
                $param[$base . 'creditCardOfflinePayment.cardType.name'] = $r['cardType.name'];
                break;

            // Check
            case '3':
                $param[$base . 'checkPayment.CheckNumber'] = $r['CheckNumber'];
                break;

        }

    }

    protected function fillPcName($r, &$param, $origValues = array()) {

        $codes = array(
            'contactId',
            'firstName',
            'lastName',
            'middleName',
            'preferredName',
            'prefix',
            'suffix',
            'salutation',
            'email1',
            'email2',
            'email3',
            'fax',
            'gender.name',
            'deceased',
            'title',
            'department',
        );

        $nonEmptyCodes = array(
            'dob',
            'phone1',
            'phone1Type',
            'phone2',
            'phone2Type',
            'phone3',
            'phone3Type',
        );

        $base = 'individualAccount.';
        $pc = 'primaryContact.';
        $basePc = $base . $pc;

        foreach ($codes as $c) {

            if (isset($r[$c])) {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . $c])) {
                $param[$basePc . $c] = $origValues[$pc . $c];
            }
        }

        foreach ($nonEmptyCodes as $c) {
        // these codes must be missing if not defined
            if (isset($r[$c]) && $r[$c] != '') {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . $c]) && $origValues[$pc . $c] != '') {
                $param[$basePc . $c] = $origValues[$pc . $c];
            }
        }
    }

    protected function fillIndividualAccount($r) {

        //$base = 'individualAccount.individualTypes.';
        $indBase = 'individualType.id';
        $str = '';

        if (isset($r[$indBase]) && $r[$indBase] > 0) {
            $str = '&individualAccount.individualTypes.individualType.id=' . $r[$indBase];
        }


        if (isset($r['individualType.id2']) && $r['individualType.id2'] > 0) {
            $str .= '&individualAccount.individualTypes.individualType.id=' . $r['individualType.id2'];
        }

        return $str;
    }

    protected function fillOther($r, &$param, $origValues = array()) {

        $codes = array(
            'noSolicitation',
            'url',
            'login.username',
            'login.password',
            'login.orgId',
            'source.name',
            'existingOrganizationId',
            'organizationName',
            'twitterPage',
            'facebookPage',
        );

        $base = 'individualAccount.';

        foreach ($codes as $c) {

            if (isset($r[$c])) {
                $param[$base . $c] = $r[$c];
            } else if (isset($origValues[$c])) {
                $param[$base . $c] = $origValues[$c];
            }
        }
    }

    protected function fillPcAddr($r, &$param, $origValues = array()) {

        $codes = array(
            'addressId',
            'isPrimaryAddress',
            'isShippingAddress',
            'addressType.name',
            'addressLine1',
            'addressLine2',
            'addressLine3',
            'addressLine4',
            'city',
            'state.code',
            'province',
            'country.id',
            'zipCode',
            'zipCodeSuffix',
            'startDate',
            'endDate',
        );


        $pc = 'primaryContact.addresses.address.';
        $basePc = 'individualAccount.' . $pc;

        foreach ($codes as $c) {

            if (isset($r[$c])) {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . '0.' . $c])) {
                $param[$basePc . $c] = $origValues[$pc . '0.' . $c];
            }
        }
    }

    protected function fillCustomFields($r, $origValues = array()) {

        $customParamStr = '';
        $base = 'individualAccount.customFieldDataList.customFieldData.';


        foreach ($this->customFields as $k => $v) {

            if (isset($r[$k]) && $r[$k] != '') {
                // We have this custom field.

                $cparam = array(
                    $base . 'fieldId' => $v,
                    $base . 'fieldOptionId' => '',
                    $base . 'fieldValue' => $r[$k]
                );

                $customParamStr .= '&' . http_build_query($cparam);

            } else {
                // We don't have the custom field, see if one exists in Neon and if so, copy it.

                $fieldValue = $this->findCustomField($origValues, $v);

                if ($fieldValue !== FALSE) {

                    $cparam = array(
                        $base . 'fieldId' => $v,
                        $base . 'fieldOptionId' => '',
                        $base . 'fieldValue' => $fieldValue
                    );

                    $customParamStr .= '&' . http_build_query($cparam);
                }
            }
        }

        // Search Neon custome fields that we don't control and copy them.
        $customParamStr .= $this->fillOtherCustomFields($origValues);

        return $customParamStr;

    }

    protected function fillOtherCustomFields($origValues) {

        $condition = TRUE;
        $index = 0;
        $customParamStr = '';
        $base = 'individualAccount.customFieldDataList.customFieldData.';

        if (isset($origValues['customFieldDataList']['customFieldData'])) {

            // Move Neon filedId's to key position
            $fieldCustom = array_flip($this->customFields);

            $cfValues = $origValues['customFieldDataList']['customFieldData'];

            while ($condition) {

                if (isset($cfValues[$index])) {

                    // Is this not one of my field Ids?
                    if (isset($cfValues[$index]["fieldId"]) && isset($fieldCustom[$cfValues[$index]["fieldId"]]) === FALSE) {
                        // Found other custom field

                        $cparam = array(
                            $base . 'fieldId' => $cfValues[$index]["fieldId"],
                            $base . 'fieldOptionId' => $cfValues[$index]["fieldOptionId"],
                            $base . 'fieldValue' => $cfValues[$index]["fieldValue"]
                        );

                        $customParamStr .= '&' . http_build_query($cparam);

                    }

                } else {
                    // end of custom fields
                    $condition = FALSE;
                }

                $index++;

                if ($index > self::MAX_CUSTOM_PROPERTYS) {
                    $condition = FALSE;
                }
            }
        }

        return $customParamStr;
    }

    /**
     *
     * @param array $origValues
     * @param string $base
     * @param mixed $fieldId
     * @return boolean|mixed
     */
    protected function findCustomField($origValues, $fieldId) {

        // find custom field index from neon
        $fieldValue = FALSE;
        $condition = TRUE;
        $index = 0;

        if (isset($origValues['customFieldDataList']['customFieldData'])) {

            $cfValues = $origValues['customFieldDataList']['customFieldData'];

            while ($condition) {

                if (isset($cfValues[$index])) {

                    // Is this my field Id?
                    if (isset($cfValues[$index]["fieldId"]) && $cfValues[$index]["fieldId"] == $fieldId) {
                        // Found the given custom field

                        $fieldValue = $cfValues[$index]["fieldValue"];
                        $condition = FALSE;
                    }

                } else {
                    // end of custom fields
                    $condition = FALSE;
                }

                $index++;

                if ($index > self::MAX_CUSTOM_PROPERTYS) {
                    $condition = FALSE;
                }
            }
        }

        return $fieldValue;
    }

    protected function createAccount(array $r) {

        $param = array(
            'originDetail' => 'Hospitality HouseKeeper Connector',
        );

        $this->fillPcName($r, $param);


        // Address
        if (isset($r['addressLine1']) && $r['addressLine1'] != '') {

            $r['isPrimaryAddress'] = 'true';
            $this->fillPcAddr($r, $param);

        }

        $paramStr = $this->fillIndividualAccount($r);

        $this->fillOther($r, $param);

        // Custom Parameters
        $paramStr .= $this->fillCustomFields($r);


        $request = array(
          'method' => 'account/createIndividualAccount',
          'parameters' => $param,
          'customParmeters' => $paramStr
          );

        return $this->webService->go($request);

    }

    protected function searchTarget(array $searchCriteria) {

        // Set up request
        $search = array(
            'method' => 'account/listAccounts',
            'columns' => array(
                'standardFields' => array('Account ID', 'Account Type', 'Individual Type'),
            ),
            'page' => array(
                'currentPage' => 1,
                'pageSize' => 200,
                'sortColumn' => 'Account ID',
                'sortDirection' => 'ASC',
            ),
        );

        // Apply search criteria
        foreach ($searchCriteria as $k => $v) {

            if (isset($this->customFields[$k]) == FALSE && $k != '' && $v != '') {
                $search['criteria'][] = array($k, 'EQUAL', $v);
            }
        }

        // Execute the search.
        return $this->webService->search($search);

    }

    protected function checkError($result) {

        $errorMsg = '';

        if (isset($result['operationResult']) && $result['operationResult'] == 'FAIL') {

            $errorMsg .= 'Result: "' . $result['operationResult'] . '", Date: ' . date('M j, Y H:i:s', strtotime($result['responseDateTime'])). "<br/>";

            foreach ($result['errors'] as $key => $errors) {

                $errorMsg .= $key . "<br/>";

                foreach ($errors as $e) {
                    $errorMsg .= $e['errorCode'] . ': ' . $e['errorMessage'] . "<br/>";
                }

            }

        } else if (isset($result['operationResult']) && $result['operationResult'] == 'ERROR') {
            $errorMsg .= 'Result: ' . $result['operationResult'] . ', Error Message: ' . $result['errorMessage'];

        } else if ( isset( $result['operationResult'] ) && $result['operationResult'] != 'SUCCESS' ) {

            $errorMsg .= 'Transaction failed';
        }

        $this->errorMessage = $errorMsg;


        if ($errorMsg != '') {
            return TRUE;
        } else {
            return FALSE;
        }

    }

    protected function loadSearchDB(\PDO $dbh, $sourceIds) {

        // clean up the ids
        foreach ($sourceIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            $parm = " in (" . implode(',', $idList) . ") ";
            return $dbh->query("Select * from vguest_search_neon where HHK_ID $parm");

        }

        return NULL;
    }

    public static function getSearchFields(\PDO $dbh, $tableName = 'vguest_search_neon') {

        $stmt = $dbh->query("SHOW COLUMNS FROM `$tableName`;");
        $cols = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cols[] = $r['Field'];
        }


        return $cols;

    }

    public function loadSourceDB(\PDO $dbh, $idName, $extraSourceCols = []) {

        $parm = intval($idName, 10);

        if ($parm > 0) {


            $stmt = $dbh->query("Select * from vguest_data_neon where HHK_ID = $parm");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) > 1) {
                $rows[0]['individualType.id2'] = $rows[1]['individualType.id'];
            } else if (count($rows) == 1) {
                $rows[0]['individualType.id2'] = '';
            }   else {
                $rows[0]['No Data'] = '';
            }

            if (count($extraSourceCols) > 0) {
                foreach ($extraSourceCols as $k => $v) {
                    $rows[0][$k] = $v;
                }
            }

            $rows[0]['firstName'] = $this->unencodeHTML($rows[0]['firstName']);
            $rows[0]['middleName'] = $this->unencodeHTML($rows[0]['middleName']);
            $rows[0]['lastName'] = $this->unencodeHTML($rows[0]['lastName']);
            $rows[0]['preferredName'] = $this->unencodeHTML($rows[0]['preferredName']);

            return $rows[0];

        }

        return NULL;

    }

    protected function openTarget($userId, $apiKey) {

        if (function_exists('curl_version') === FALSE) {
            throw new UploadException('PHP configuration error: cURL functions are missing.');
        }

        $keys = array('orgId'=>$userId, 'apiKey'=>$apiKey);

        $this->webService = new Neon();
        $loginResult = $this->webService->login($keys);


        if ( isset( $loginResult['operationResult'] ) && $loginResult['operationResult'] != 'SUCCESS' ) {
            throw new UploadException('API Login failed');
        }

        if ($loginResult['userSessionId'] == '') {
            throw new UploadException('API Session Id is missing');
        }

        return TRUE;
    }

    protected function closeTarget() {

        $this->webService->go( array( 'method' => 'common/logout' ) );
        $this->webService->setSession('');

    }

    public function unencodeHTML($text) {

        $txt = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $text
            );

        return $txt;
    }

    public function getTxMethod() {
        return $this->webService->txMethod;
    }

    public function getTxParams() {
        return $this->webService->txParams;
    }

    public function getReplies() {
        return $this->replies;
    }

    public function getHhReplies() {
        return $this->hhReplies;
    }

    public function setHhReplies(array $v) {

        $hhReply = [];

        if (isset($v['HH Id'])) {
            $hhReply['HH Id'] = $v['HH Id'];
        } else {
            $hhReply['HH Id'] = '';
        }

        if (isset($v['Household'])) {
            $hhReply['Household'] = $v['Household'];
        } else {
            $hhReply['Household'] = '';
        }

        if (isset($v['Account Id'])) {
            $hhReply['Account Id'] = $v['Account Id'];
        } else {
            $hhReply['Account Id'] = '';
        }

        if (isset($v['Name'])) {
            $hhReply['Name'] = $v['Name'];
        } else {
            $hhReply['Name'] = '';
        }

        if (isset($v['Relationship'])) {
            $hhReply['Relationship'] = $v['Relationship'];
        } else {
            $hhReply['Relationship'] = '';
        }

        if (isset($v['Action'])) {
            $hhReply['Action'] = $v['Action'];
        } else {
            $hhReply['Action'] = '';
        }

        if (isset($v['Result'])) {
            $hhReply['Result'] = $v['Result'];
        } else {
            $hhReply['Result'] = '';
        }


        $this->hhReplies[] = $hhReply;

    }

    public function getMemberReplies() {
        return $this->memberReplies;
    }
}
?>