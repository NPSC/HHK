<?php
namespace HHK\CrmExport\Neon;

use GuzzleHttp\Exception\RequestException;
use HHK\Common;
use HHK\CrmExport\AbstractExportManager;
use HHK\Crypto;
use HHK\House\ResourceBldr;
use HHK\HTMLControls\{HTMLTable, HTMLSelector, HTMLInput};
use HHK\sec\Session;
use HHK\HTMLControls\HTMLContainer;
use HHK\Exception\{RuntimeException, UploadException};
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameRS;
use HHK\Member\MemberSearch;
use HHK\AuditLog\NameLog;
use HHK\SysConst\MemStatus;
use HHK\CrmExport\RelationshipMapper;

/**
 *
 * @author Eric
 *
 */
class NeonManager extends AbstractExportManager {

    protected ?array $customFields = null;
    protected array $typeMap = [];

    protected array $hhReplies = [];

    protected string $configMessage = '';

    protected int $pageNumber;
    protected RelationshipMapper $relationshipMapper;
    protected array $hhkToNeonRelationMap;

    const SearchViewName = 'vguest_search_neon';

    const LOG_SERVICE_NAME = "neonCRM";
    const HHK_CUSTOM_FIELD_GROUP = 'Hospitality Housekeeper';
    private const ORIGIN = "Hospitality Housekeeper Connector";
    private const SOURCE = "HHK";

    protected Neon $neonWebService;
    protected NeonWebService $neonWebServiceV2;

    public function __construct(\PDO $dbh, string $cmsName) {
        parent::__construct($dbh, $cmsName);
        $this->neonWebServiceV2 = new NeonWebService($dbh, $this->getUserId(), Crypto::decryptMessage($this->getPassword()));
        $this->neonWebService = new Neon();
    }

    public function searchMembers ($searchCriteria) {
        $replys = [];

        $msearch = new MemberSearch($searchCriteria['letters']);
        $standardFields = array('Account ID', 'Account Type', 'Deceased', 'Prefix', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Preferred Name', 'Email 1');

        $search = [
            'searchFields' => [
                ['field' => 'Last Name', 'operator' => 'CONTAIN', 'value' => str_replace('%', '', $msearch->getName_Last())]
            ],
            'outputFields' => $standardFields,
            'pagination' => [
                'currentPage' => $this->pageNumber,
                'pageSize' => 20,
                'sortColumn' => 'Last Name',
                'sortDirection' => 'ASC',
            ]
        ];

        $result = $this->neonWebServiceV2->searchAccounts($search);

        // TODO: Handle errors
        /*

        if ($this->checkError($result)) {

            $replys['error'] = $this->errorMessage;

        } else */
        if (isset($result['searchResults'])) {

            foreach ($result['searchResults'] as $r) {

                $namArray['id'] = $r["Account ID"];
                $namArray['fullName'] = $r['Prefix'] . ' ' . $r["First Name"] . ' ' . $r["Last Name"] . ' ' . $r["Suffix"];
                $namArray['value'] = $r["Last Name"] . ", " . $r["First Name"] . (isset($r['Email 1']) ? ', ' . $r['Email 1'] : '');
                //$namArray['Phone'] = isset($r['Phone']) ? $r['Phone'] : '';
                $namArray['Email'] = isset($r['Email 1']) ? $r['Email 1'] : '';

                $replys[] = $namArray;

            }

            if (count($replys) === 0) {
                $replys[] = ["id" => 0, "value" => "No one found."];
            }
        } else {
            $replys[] = ["id" => 0, "value" => "No one found."];
        }

        return $replys;

    }

    /**
     *
     */
    public function exportMembers(\PDO $dbh, array $sourceIds) {

        $replys = [];

        if (count($sourceIds) == 0) {
            $replys[0] = ['error' => "The list of HHK Id's to send is empty."];
            return $replys;
        }

        $this->customFields = $this->getMyCustomFields($dbh);

        // Log in with the web service
        $this->openTarget();

        // Load Individual types
        $stmtList = $dbh->query("Select * from neon_type_map where List_Name in ('individualTypes', 'genders')");

        while ($t = $stmtList->fetch(\PDO::FETCH_ASSOC)) {
            $this->typeMap[$t['List_Name']][$t['HHK_Type_Code']] = $t;
        }

        // Load search parameters for each source ID
        $stmt = $this->loadSearchDB($dbh, 'vguest_search_neon', $sourceIds);

        if (is_null($stmt)) {
            $replys[0] = ['error' => 'No local records were found.'];
            return $replys;
        }


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $f = [];   // output array

            // Prefill output array
            foreach ($r as $k => $v) {

                if ($k != '') {
                    $f[$k] = $v;
                }
            }

            // Search target system
            try{
                $result = $this->searchTarget($r);
            } catch (RequestException $e){
                $f['Result'] = $this->formatError($e);
                $replys[$r['HHK_ID']] = $f;
                continue;

            }


            // Check for NEON not finding the account Id
            if ( isset($result['pagination']['totalResults'] ) && $result['pagination']['totalResults'] == 0 && $r['Account Id'] != '') {

                // Account is missing from the Neon side.
                $f['Result'] = 'Account Deleted at Neon';   // procedure sendVisits() depends upon the exact wording of the quoted text. circa line 638
                $replys[$r['HHK_ID']] = $f;
                continue;

            }


            // Test results
            if ( isset($result['pagination']['totalResults'] ) && $result['pagination']['totalResults'] == 1 ) {

                // We have a similar contact.

                // Make sure the external Id is defined locally
                if (isset($result['searchResults'][0]['Account ID']) && $result['searchResults'][0]['Account ID'] != '') {

                    $this->updateLocalExternalId($dbh, $r['HHK_ID'], $result['searchResults'][0]['Account ID']);
                    $f['Account ID'] = $result['searchResults'][0]['Account ID'];
                    $f['Result'] = 'Up to date.';

                    // Check individual type
                    $typeFound = FALSE;

                    if (isset($result['searchResults'][0]['Individual Type'])) {

                        foreach ($this->typeMap['individualTypes'] as $t) {

                            if (stristr($result['searchResults'][0]['Individual Type'], $t['Neon_Type_Name']) !== FALSE) {
                                $typeFound = TRUE;
                                break;
                            }
                        }
                    }

                    if ($typeFound === FALSE) {
                        // Update the individual type
                        try{
                            $retrieveResult = $this->retrieveRemoteAccount($result['searchResults'][0]['Account ID']);
                            $f['Result'] .= $this->updateRemoteMember($dbh, $retrieveResult, $r['HHK_ID']);
                        } catch (RuntimeException $hex) {
                            $f['Result'] .= 'Update Individual Type Error: ' . $hex->getMessage();
                            continue;
                        }
                    }

                } else {

                    $f['Result'] = 'The search results Account Id is empty.';
                }

                $replys[$r['HHK_ID']] = $f;


            } else if ( isset($result['pagination']['totalResults'] ) && $result['pagination']['totalResults'] > 1 ) {

                // We have more than one contact...
                $f['Result'] = 'Multiple Accounts.';
                $replys[$r['HHK_ID']] = $f;


            } else if ( isset($result['pagination']['totalResults'] ) && $result['pagination']['totalResults'] == 0 ) {

                // Nothing found - create a new account at remote

                // Get member data record
                $row = $this->loadSourceDB($dbh, $r['HHK_ID'], 'vguest_data_neon');

                if (is_null($row)) {
                    continue;
                }

                // Create new account
                try{
                    $result = $this->createRemoteAccount($row);
                } catch (RequestException $e){
                    $f['Result'] = $this->formatError($e);
                    $replys[$r['HHK_ID']] = $f;
                    continue;
                }

                $accountId = filter_var($result['id'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalExternalId($dbh, $r['HHK_ID'], $accountId);

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

    public function updateRemoteMember(\PDO $dbh, array $accountData, $idName, $extraSourceCols = [], $updateAddr = TRUE) {

        if ($idName < 1) {
            throw new RuntimeException('HHK Member Id not specified: ' . $idName);
        }


        // Get member data record
        $r = $this->loadSourceDB($dbh, $idName, 'vguest_data_neon', $extraSourceCols);


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

        $param['individualAccount']['accountId'] = $unwound['accountId'];

        // Name, phone, email
        NeonHelper::fillPcName($r, $param, $unwound);

        // Address
        if (isset($r['addressLine1']) && $r['addressLine1'] != '') {

            if ($updateAddr) {
                $r['isPrimaryAddress'] = 'true';
                NeonHelper::fillPcAddr($r, $param, $unwound);
            } else {
                // dont update address from HHK.
                NeonHelper::fillPcAddr(array(), $param, $unwound);
            }

        }

        // Other crap
        NeonHelper::fillOther($r, $param, $unwound);

        NeonHelper::fillIndividualAccount($r, $param);

        // Custom Parameters
        NeonHelper::fillCustomFields($this->customFields, $r, $param, $unwound);

        // Log in with the web service
        $this->openTarget();

        $request = [
            'method' => 'account/updateIndividualAccount',
            'parameters' => $param,
        ];

        $result = $this->neonWebService->go($request);

        if ($this->checkError($result)) {
            $msg = $this->errorMessage;
        } else {
            $msg = 'Updated ' . $r['firstName'] . ' ' . $r['lastName'];
        }

        return $msg;

    }


    public function getMember(\PDO $dbh, $parameters) {

        $source = (isset($parameters['src']) ? $parameters['src'] : '');
        $id = (isset($parameters['accountId']) ? $parameters['accountId'] : '');
        $resultStr = new HTMLTable();
        $reply = '';

        if ($source === 'hhk') {

            $row = $this->loadSourceDB($dbh, $id, 'vguest_data_neon');

            if (is_null($row)) {
                $reply .= 'Error - HHK Id not found';

            } else {

                foreach ($row as $k => $v) {
                     $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd($v));
                }

                $reply = $resultStr->generateMarkup();
                $this->setAccountId((isset($row['accountId']) ? $row['accountId'] : ''));
            }

        } else if ($source === 'remote') {

            // Neon accounts
            // $result = $this->retrieveRemoteAccount($id);
            $result = $this->neonWebServiceV2->getAccount($id);

            $parms = array();
            $this->unwindResponse($parms, $result);

            $resultStr->addBodyTr(HTMLTable::makeTh('Individual', ['colspan' => '2']));

            foreach ($parms as $k => $v) {
                $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd($v));
            }

            $this->setAccountId((isset($parms['accountId']) ? $parms['accountId'] : ''));

            // Neon Househods
            //$result = $this->searchHouseholds($id);
            $result = $this->neonWebServiceV2->listHouseholds(accountId:$id);

            $parms = array();
            $this->unwindResponse($parms, $result);

            $resultStr->addBodyTr(HTMLTable::makeTh('Households', ['colspan' => '2']));

            foreach ($parms as $k => $v) {
                $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd($v));
            }

            $reply = $resultStr->generateMarkup();

        } else {
            $reply .= "Source for search not found: " . $source;
        }

        return $reply;
    }

    protected function createRemoteAccount(array $r) {

        $param = [];

        NeonHelper::fillPcName($r, $param);


        // Address
        if (isset($r['addressLine1']) && $r['addressLine1'] != '') {

            $r['isPrimaryAddress'] = 'true';
            NeonHelper::fillPcAddr($r, $param);

        }

        NeonHelper::fillIndividualAccount($r, $param);

        NeonHelper::fillOther($r, $param);

        // Custom Parameters
        NeonHelper::fillCustomFields([], $r, $param);

        $param['individualAccount']['origin'] = ['originDetail' => self::ORIGIN];

        return $this->neonWebServiceV2->createAccount($param);

    }

    /**
     *
     * @param mixed $accountId Remote account Id
     * @return mixed The Remote account object.
     * @throws RuntimeException
     */
    public function retrieveRemoteAccount($accountId) {

        //TODO: Handle errors

        $account = $this->neonWebServiceV2->getAccount($accountId);

        return $account;
    }

    public function getCountryIds() {

        $countries = [];

        $request = [
            'method' => 'account/listCountries',
        ];

        // Log in with the web service
        $this->openTarget();

        $result = $this->neonWebService->go($request);

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

    public function exportPayments(\PDO $dbh, $startStr, $endStr) {

        $replys = [];
        $this->memberReplies = array();
        $idMap = [];
        $mappedItems = [];
        $whereClause = '';


        $endDT = new \DateTimeImmutable($endStr);

        try {
            if ($startStr != '') {
                $startDT = new \DateTimeImmutable($startStr);
                $whereClause = " and DATE(`date`) >= DATE('" . $startDT->format('Y-m-d') . "') ";
            }

            if ($endStr != '') {
                $endDT = new \DateTimeImmutable($endStr);
                $whereClause .= " and DATE(`date`) <= DATE('" . $endDT->format('Y-m-d') . "') ";
            }
        } catch(\Exception $e) {
            return [['Donation Result' => 'Start or End date is malformed.  ' . $e->getMessage()]];
        }

        // Load the time codes for output
        $stmtList = $dbh->query("Select * from neon_type_map");
        $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($items as $i) {
            $mappedItems[$i['Neon_Name']][$i['Neon_Type_Code']] = $i['Neon_Type_Name'];
        }

        // Log in with the web service
        $this->openTarget();

        $stmt = $dbh->query("Select * from vguest_neon_payment where 1=1 $whereClause");

        if ($stmt->rowCount() < 1) {
            return [['Donation Result' => 'No new HHK payments found to transfer.  ']];
        }


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Don't process empty fund.id's
            if ($r['fund.id'] == '') {
                continue;
            }

            // return data array.
            $f = [];

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
                $acctReplys = $this->exportMembers($dbh, [$r['hhkId']]);

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

        NeonHelper::fillDonation($r, $param);
        NeonHelper::fillPayment($r, $param);

        $request = array(
            'method' => 'donation/createDonation',
            'parameters' => $param,

        );


        $wsResult = $this->neonWebService->go($request);


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

    protected function updateLocalPaymentRecord(\PDO $dbh, $idPayment, $externalId) {

        $result = 0;

        if ($externalId != '' && $idPayment > 0) {

            $extId = filter_var($externalId, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $stmt = $dbh->query("Select count(*) from payment where idPayment = $idPayment and External_Id = '$extId'");
            $extRows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($extRows[0]) == 1 && $extRows[0][0] > 0) {
                throw new UploadException("HHK Payment Record (idPayment = $idPayment) already has a Donation Id = " . $extId);
            }

            $result = $dbh->exec("Update `payment` set External_Id = '$extId' where idPayment = $idPayment;");

        }

        return $result;
    }

    protected function searchTarget(array $searchCriteria) {
/*
        // Set up request
        $search = array(
            'method' => 'account/listAccounts',
            'columns' => [
                'standardFields' => array('Account ID', 'Account Type', 'Individual Type'),  // lastModifiedDateTime
            ],
            'page' => [
                'currentPage' => 1,
                'pageSize' => 200,
                'sortColumn' => 'Account ID',
                'sortDirection' => 'ASC',
            ],
        );

        // Apply search criteria
        foreach ($searchCriteria as $k => $v) {

            if (isset($this->customFields[$k]) == FALSE && $k != '' && $v != '') {
                $search['criteria'][] = [$k, 'EQUAL', $v];
            }
        }
*/

        $search = array(
            'outputFields' => [
                'Account ID', 'Account Type', 'Individual Type'  // lastModifiedDateTime
            ],
            'pagination' => [
                'currentPage' => 1,
                'pageSize' => 200,
                'sortColumn' => 'Account ID',
                'sortDirection' => 'ASC',
            ],
        );

        // Apply search criteria
        foreach ($searchCriteria as $k => $v) {

            if (isset($this->customFields[$k]) == FALSE && $k != '' && $v != '') {
                $search['searchFields'][] = ['field' => $k, 'operator' => 'EQUAL', 'value' => $v];
            }
        }

        // Execute the search.
        //return $this->neonWebService->search($search);
        return $this->neonWebServiceV2->searchAccounts($search);

    }


    /** Sends individuals, stay information, hospital and diagnosis, and households.
     *
     * @param \PDO $dbh
     * @param int $idPsg
     * @param array $rels
     * @return array
     */
    public function exportVisits(\PDO $dbh, $idPsg, array $rels) {

        $this->memberReplies = [];
        $this->replies = [];
        $this->customFields = $this->getMyCustomFields($dbh);

        // dont allow if neon config file doesnt have the custom fileds
        if (isset($this->customFields['First_Visit']) === FALSE) {
            $rep = [];
            $rep[] = ['Update_Message' => 'Vist transfer is not configured.'];
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
            $this->memberReplies = $this->exportMembers($dbh, $sendIds);

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
                $visitReplys[] = [
                    'First_Visit' => '',
                    'Last_Visit' => '',
                    'Nite_Counter' => '',
                    'Diagnosis' => '',
                    'Hospital' => '',
                    'PSG_Number' => $r['idPsg'],
                    'Update_Message' => 'Neon account id not found for ' . $r['Full_Name'] . ': HHK Id = ' . $r['hhkId'] . ' Account Id = ' . $r['accountId']
                ];
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
        $origValues = $this->retrieveRemoteAccount($r['accountId']);
        $codes = [];
        $f = [];

        // Check for earliest visit start
        if (isset($r['Start_Date']) && isset($this->customFields['First_Visit'])) {

            $startDT = $r['Start_Date'];
            $earliestStart = NeonHelper::findCustomField($origValues, $this->customFields['First_Visit']);

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
            $latestEnd = NeonHelper::findCustomField($origValues, $this->customFields['Last_Visit']);

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
            $niteCounter = intval(NeonHelper::findCustomField($origValues, $this->customFields['Nite_Counter']), 10);

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
        $f['Update_Message'] = $this->updateRemoteMember($dbh, $origValues, $r['hhkId'], $codes, FALSE);

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
            $parms = ['householdId' => $idHousehold];
        } else if ($accountId > 0 ) {
            $parms = ['accountId' => $accountId];
        } else {
            return [];
        }

        $request = [
            'method' => 'account/listHouseHolds',
            'parameters' => $parms,
        ];

        $households = $this->neonWebService->go($request);

        if ($this->checkError($households)) {
            $households['error'] = $this->errorMessage;
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
     * @param array $primaryGuest
     * @return string|mixed
     */
    protected function createHousehold(array $primaryGuest) {

        $householdId = 0;
        $householdName = $this->unencodeHTML($primaryGuest['Last_Name']);
        $relationId = $primaryGuest['Neon_Rel_Code'];

        if ($householdName == '') {
            $this->setHhReplies([
                'Action' => 'Create',
                'Account Id' => $primaryGuest['accountId'],
                'Result' => 'Blank last name, Household not created.',
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId)
            ]);
            return $householdId;
        }

        if ($relationId == '') {
            $this->setHhReplies([
                'Action' => 'Create',
                'Account Id' => $primaryGuest['accountId'],
                'Result' => 'Relationship is undefined, Household not created.',
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId)
            ]);
            return $householdId;
        }

        // Primary Guest must have an address
        if ($primaryGuest['Address'] == '') {
            $this->setHhReplies([
                'Action' => 'Create',
                'Household' => $householdName,
                'Account Id' => $primaryGuest['accountId'],
                'Result' => 'Blank address, Household not created.',
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId)
            ]);
            return $householdId;
        }


        // Finally, make a new household in Neon.
        $base = 'household.';
        $param[$base . 'name'] = $householdName;
        $param[$base . 'houseHoldContacts.houseHoldContact.accountId'] = $primaryGuest['accountId'];
        $param[$base . 'houseHoldContacts.houseHoldContact.relationType.id'] = $relationId;
        $param[$base . 'houseHoldContacts.houseHoldContact.isPrimaryHouseHoldContact'] = 'true';

        $request = [
            'method' => 'account/createHouseHold',
            'parameters' => $param,
        ];

        $wsResult = $this->neonWebService->go($request);

        if ($this->checkError($wsResult)) {

            $this->setHhReplies([
                'Household' => $householdName,
                'Account Id' => $primaryGuest['accountId'],
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId),
                'Action' => 'Create',
                'Result' => 'Failed: ' . $this->errorMessage
            ]);

        } else if (isset($wsResult['houseHoldId'])) {

            $householdId = $wsResult['houseHoldId'];
            $this->setHhReplies([
                'HH Id' => $householdId,
                'Household' => $householdName,
                'Account Id' => $primaryGuest['accountId'],
                'Name' => $primaryGuest['Full_Name'],
                'Relationship' => $this->relationshipMapper->mapNeonTypeName($relationId),
                'Action' => 'Create',
                'Result' => 'Success'
            ]);
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
                            $this->setHhReplies([
                                'Household' => $households['houseHolds']['houseHold'][0]['name'],
                                'HH Id' => $households['houseHolds']['houseHold'][0]['houseHoldId'],
                                'Account Id' => $g['accountId'],
                                'Name' => $g['Full_Name'],
                                'Action' => 'Join',
                                'Result' => 'Address Mismatch'
                            ]);

                            $notJoined++;
                        }
                    }
                }
            }

            if (count($newContacts) > 0) {

                $this->updateHousehold($newContacts, $households['houseHolds']['houseHold'][0]);

            } else if ($notJoined > 0) {

                $this->setHhReplies([
                    'Household' => $households['houseHolds']['houseHold'][0]['name'],
                    'HH Id' => $households['houseHolds']['houseHold'][0]['houseHoldId'],
                    'Action' => 'Update',
                    'Account Id' => '-',
                    'Name' => '-',
                    'Result' => 'Nobody joined this household.'
                ]);
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

        // 2023/11/13 EKC RelationType can be null, it seems.
        if (isset($pg['relationType']['id']) && is_null($pg['relationType']['id']) === FALSE) {
            $param[$base . 'houseHoldContacts.houseHoldContact.relationType.id'] = $pg['relationType']['id'];
        }

        $param[$base . 'houseHoldContacts.houseHoldContact.isPrimaryHouseHoldContact'] = 'true';

        foreach ($newGuests as $ng) {

            $ngRelationId = $ng['Neon_Rel_Code'];  //$this->relationshipMapper->relateGuest($ng['Relation_Code']);

            if ($ngRelationId != '') {

                $cparm = [
                    $base . 'houseHoldContacts.houseHoldContact.accountId' => $ng['accountId'],
                    $base . 'houseHoldContacts.houseHoldContact.relationType.id' => $ngRelationId,
                    $base . 'houseHoldContacts.houseHoldContact.isPrimaryHouseHoldContact' => 'false',
                ];

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

                $this->setHhReplies([
                    'HH Id' => $household['houseHoldId'],
                    'Household' => $household['name'],
                    'Action' => 'Join',
                    'Account Id' => $ng['accountId'],
                    'Relationship' => $ngRelationId,
                    'Name' => $ng['Full_Name'],
                    'Result' => 'Failed: Relationship Missing.'
                ]);
            }

        }

        $request = array(
            'method' => 'account/updateHouseHold',
            'parameters' => $param,
            'customParmeters' => $customParamStr,
        );


        $wsResult = $this->neonWebService->go($request);

        if ($this->checkError($wsResult)) {

            $this->setHhReplies([
                'HH Id' => $household['houseHoldId'],
                'Household' => $household['name'],
                'Action' => 'Update',
                'Account Id' => '-',
                'Name' => '-',
                'Result' => 'Failed: ' . $this->errorMessage
            ]);

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

            $this->setHhReplies([
                'Household' => $household['name'],
                'Action' => 'Update',
                'Result' => 'Failed: Household Id not returned'
            ]);

        } else {
            $this->setHhReplies([
                'HH Id' => $wsResult['houseHoldId'],
                'Household' => $household['name'],
                'Action' => 'Update',
                'Result' => 'Success',
                'Name' => '-',
                'Account Id' => '-',
            ]);
        }

    }

    protected function deleteHousehold($household, $accountId) {

        $request = array(
            'method' => 'account/deleteHouseHold',
            'parameters' => array('houseHoldId' => $household['houseHoldId'])
        );

        $wsResult = $this->neonWebService->go($request);

        if ($this->checkError($wsResult)) {

            $this->setHhReplies([
                'HH Id' => $household['houseHoldId'],
                'Household' => $household['name'],
                'Account Id' => $accountId,
                'Action' => 'Delete',
                'Result' => 'Failed: ' . $this->errorMessage
            ]);

        } else {

            $this->setHhReplies([
                'HH Id' => $household['houseHoldId'],
                'Household' => $household['name'],
                'Account Id' => $accountId,
                'Action' => 'Delete',
                'Result' => 'Success: HouseHold deleted.'
            ]);
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

        return [
            'hhkId' => $r['hhkId'],
            'accountId' => $r['accountId'],
            'idPsg' => $idPsg,
            'Relation_Code' => $r['Relation_Code'],
            'Address' => $r['Address'],
            'Last_Name' => $r['Last_Name'],
            'Full_Name' => $r['Full_Name'],
            'Neon_Rel_Code' => $relId
        ];
    }

    protected function loadSourceDB(\PDO $dbh, $idName, $view, $extraSourceCols = []) {

         $parm = intval($idName, 10);

         if ($parm > 0) {

             $stmt = $dbh->query("Select * from `$view` where HHK_ID = $parm");
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

    public function setExcludeMembers(\PDO $dbh, $psgIds) {

        $uS = Session::getInstance();

        $idList = [];
        $idNames = [];
        $numUpdates = 0;

        // clean up the PSG ids
        foreach ($psgIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            // Remove Exclude status when an excluded member checks in.
            $stmt = $dbh->query("select DISTINCT n.idName from `name` n join name_guest ng on n.idName = ng.idName
                where ng.idPsg in (" . implode(',', $idList) . ");" );

            // Reset each external Id, and log it.
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $idNames[] = $r;

                $n = new NameRS();
                $n->idName->setStoredVal($r['idName']);
                $names = EditRS::select($dbh, $n, array($n->idName));
                EditRS::loadRow($names[0], $n);

                $n->External_Id->setNewVal(self::EXCLUDE_TERM);
                $numRows = EditRS::update($dbh, $n, array($n->idName));

                if ($numRows > 0) {
                    // Log it.
                    NameLog::writeUpdate($dbh, $n, $n->idName->getStoredVal(), $uS->username);
                    $numUpdates++;
                }
            }

            $idNames[] = array('HHK Id'=>'', 'Full Name'=>$numUpdates . ' members updated.');
        }

        return $idNames;

    }

    public function listNeonType(string $method, string $listName, string $listItem) {

        $types = [];
        $result = null;

        try {
            switch ($method) {
                case 'account/listRelationTypes':
                    $result = $this->neonWebServiceV2->getRelationTypes('INDIVIDUAL_INDIVIDUAL');
                    break;
                case 'account/listIndividualTypes':
                    $result = $this->neonWebServiceV2->getIndividualTypes();
                    break;
                case 'account/listGenders':
                    $result = $this->neonWebServiceV2->getGenders();
                    break;
                default:
                    $types = [];
            }
        } catch (RequestException $e){
           throw new RuntimeException('Unable to retrieve ' . $listName . ' from Neon: ' . $this->formatError($e)); 
        }

        if (is_array($result)) {

            foreach ($result as $c) {
                if (isset($c['id'], $c['name'])) {
                    $types[$c['id']] = $c['name'];
                } else if (isset($c['code'], $c['name'])) {
                    $types[$c['code']] = $c['name'];
                } else if (isset($c['code'], $c['description'])) {
                    $types[$c['code']] = $c['description'];
                }
            }
        }

        return $types;
    }

    protected function listCustomFields() {

        $customFields = [];

        $request = [
            'method' => 'common/listCustomFields',
            'parameters' => array('searchCriteria.component' => 'Account')
        ];

        // Log in with the web service
        $this->openTarget();

        $result = $this->neonWebService->go($request);

        if ($this->checkError($result)) {
            throw new RuntimeException($this->errorMessage);
        }

        if (isset($result['customFields']['customField'])) {
            $customFields = $result['customFields']['customField'];
        }

        return $customFields;
    }

    /**
     * Summary of getMyCustomFields
     * @param \PDO $dbh
     * @return array
     */
    public function getMyCustomFields(\PDO $dbh) {

        if (is_null($this->customFields)) {
            $cf = Common::readGenLookupsPDO($dbh, 'Cm_Custom_Fields', "Order");

            foreach($cf as $k => $v) {
                $this->customFields[$k] = $v['Description'];
            }
        }

        return $this->customFields;
    }

    protected function showGatewayCredentials() {

        $tbl = new HTMLTable();


            $tbl->addBodyTr(
                HTMLTable::makeTh('CRM Name', ['style' => 'border-top:2px solid black;'])
                . HTMLTable::makeTd($this->getServiceTitle(), ['style' => 'border-top:2px solid black;'])
                );

            $tbl->addBodyTr(
                HTMLTable::makeTh('CRM Gateway Id', array())
                . HTMLTable::makeTd($this->getGatewayId())
                );
            $tbl->addBodyTr(
                HTMLTable::makeTh('Organization ID', array())
                . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getUserId(), array('name' => '_txtuserId', 'size' => '90')))
                );
            $tbl->addBodyTr(
                HTMLTable::makeTh('API Key', array())
                . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getPassword(), array('type'=>'password', 'name' => '_txtpwd', 'size' => '90')))
                );
            $tbl->addBodyTr(
                HTMLTable::makeTh('API Version', array())
                . HTMLTable::makeTd($this->neonWebServiceV2::API_VERSION)
                );


        return $tbl->generateMarkup();

    }

    public function showConfig(\PDO $dbh) {

        $markup = '';
        $crmTitle = ucfirst($this->serviceName);

        $customFields = $this->getMyCustomFields($dbh);

        try {

            $stmt = $dbh->query("select * from neon_lists;");

            while ($list = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                if (isset($list['HHK_Lookup']) === FALSE) {
                    continue;
                }

                // Remove funds if not being used.
                if ($list['HHK_Lookup'] == 'Fund' || $list['HHK_Lookup'] == 'Pay_Type' || $list['HHK_Lookup'] == 'Charge_Cards') {
                    if (isset($customFields['Fund']) === false || $customFields['Fund'] == '') {
                        continue;
                    }
                }

                $neonItems = $this->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

                switch ($list['HHK_Lookup']) {
                    case 'Fund':
                        $stFund = $dbh->query("select idItem as Code, Description, '' as `Substitute` from item where Deleted = 0;");
                        $hhkLookup = [];

                        while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                            $hhkLookup[$row["Code"]] = $row;
                        }

                        $hhkLookup['p'] = ['Code' => 'p', 0 => 'p', 'Description' => 'Payment', 1 => 'Payment', 'Substitute' => '', 2 => ''];
                        break;
                    case 'Pay_Type':
                        $stFund = $dbh->query("select `idPayment_method` as `Code`, `Method_Name` as `Description`, '' as `Substitute` from payment_method;");
                        $hhkLookup = [];

                        while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                            $hhkLookup[$row['Code']] = $row;
                        }
                        break;
                    default:
                        $hhkLookup = HTMLSelector::removeOptionGroups(Common::readGenLookupsPDO($dbh, $list['HHK_Lookup']));
                        break;
                }

                $stmtList = $dbh->query("Select * from neon_type_map where List_Name = '" . $list['List_Name'] . "'");
                $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

                $mappedItems = array();
                foreach ($items as $i) {
                    $mappedItems[$i['Neon_Type_Code']] = $i;
                }

                $nTbl = new HTMLTable();
                $nTbl->addHeaderTr(HTMLTable::makeTh('HHK Lookup') . HTMLTable::makeTh("$crmTitle Name") . HTMLTable::makeTh("$crmTitle ID"));

                foreach ($neonItems as $n => $k) {

                    $hhkTypeCode = '';
                    if (isset($mappedItems[$n])) {
                        $hhkTypeCode = $mappedItems[$n]['HHK_Type_Code'];
                    }

                    $nTbl->addBodyTr(
                        HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hhkLookup, $hhkTypeCode), ['name' => 'sel' . $list['List_Name'] . '[' . $n . ']']))
                        . HTMLTable::makeTd($k)
                        . HTMLTable::makeTd($n, ['style' => 'text-align:center;'])
                        );
                }

                $markup .= HTMLContainer::generateMarkup('div', $nTbl->generateMarkup([], $list['List_Name']), ['class'=>'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']);
            }

            // Custom fields
            $results = $this->neonWebServiceV2->getCustomFields('Account');
            $cfTbl = new HTMLTable();

            $cfTbl->addHeaderTr(HTMLTable::makeTh('HHK Field') . HTMLTable::makeTh("$crmTitle Name"));

            foreach ($customFields as $k => $v) {
                $found = false;
                foreach ($results as $r) {
                    if ($r['name'] == $k) {
                        $cfTbl->addBodyTr(HTMLTable::makeTd($k) . HTMLTable::makeTd($r['displayName']));
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $cfTbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', ['class' =>'ui-icon ui-icon-alert']) . $k, ['title'=>"Field not found in $crmTitle"]) . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['type' => 'checkbox', 'name' => "chkCreateCustomField[{$k}]", 'id' => "chkCreate_{$k}", 'value' => '1']) . HTMLContainer::generateMarkup('label', 'Create', ['for' => "chkCreate_{$k}"])), ['class' =>'ui-state-error']);
                }
            }

            $markup .= HTMLContainer::generateMarkup('div', $cfTbl->generateMarkup([], 'Custom Fields'), ['class'=>'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']);

            // Sources
            $results = $this->neonWebServiceV2->getSources();
            $sTbl = new HTMLTable();
            $sTbl->addHeaderTr(HTMLTable::makeTh('Source') . HTMLTable::makeTh("$crmTitle ID"));

            $foundHHKSource = false;
            foreach ($results as $v) {
                if($v['name'] == self::SOURCE) {
                    $foundHHKSource = true;
                }

                $sTbl->addBodyTr(HTMLTable::makeTd($v['name']) . HTMLTable::makeTd($v['id']));

            }

            if($foundHHKSource === false) {
                $sTbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', ['class' =>'ui-icon ui-icon-alert']) . self::SOURCE, ['title'=>"Source not found in $crmTitle"]) . HTMLTable::makeTd('Not Found'), ['class' =>'ui-state-error']);
            }

            $markup .= HTMLContainer::generateMarkup('div', $sTbl->generateMarkup([], 'Sources'), ['class'=>'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']);

        } catch (\Exception $pe) {
            $markup .= HTMLContainer::generateMarkup('h4', "Transfer Error: " .$pe->getMessage(), array('style'=>'margin-left:200px;color:red;'));
        }

        return $this->showGatewayCredentials() . HTMLContainer::generateMarkup('div', $markup, ['class'=>'hhk-flex mt-3']);
    }

    protected function saveCredentials(\PDO $dbh, string $username) {

        $result = '';
        $crmRs = new CmsGatewayRS();

        $rags = [
            '_txtuserId'   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtpwd'   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtEPurl'   => FILTER_SANITIZE_URL,
        ];
        $post = filter_input_array(INPUT_POST, $rags);

        // User Id
        if (isset($post['_txtuserId'])) {
            $crmRs->username->setNewVal(htmlspecialchars($post['_txtuserId']));
        }

        // Password
        if (isset($post['_txtpwd'])) {

            $pw = htmlspecialchars($post['_txtpwd']);

            if ($pw != '' && $this->getPassword() != $pw) {
                $pw = Crypto::encryptMessage($pw);
            }

            $crmRs->password->setnewVal($pw);
        }

        // Endpoint URL
        if (isset($post['_txtEPurl'])) {
            $crmRs->endpointUrl->setNewVal($post['_txtEPurl']);
        }

        $crmRs->Updated_By->setNewVal($username);
        $crmRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));


        if ($this->getGatewayId() < 1) {
            // Insert

            $crmRs->Gateway_Name->setNewVal($this->getServiceName());
            $idGateway = EditRS::insert($dbh, $crmRs);

            if ($idGateway > 0) {
                EditRS::updateStoredVals($crmRs);
                $this->gatewayId = $idGateway;
                $result = 'New CMS gateway created.  Id = '.$idGateway;
            }

        } else {
            // Update

            $crmRs->Gateway_Name->setStoredVal($this->getServiceName());
            $rc = EditRS::update($dbh, $crmRs, [$crmRs->Gateway_Name]);

            if ($rc > 0) {
                // something updated
                EditRS::updateStoredVals($crmRs);
                $result = 'New CMS gateway Updated.  ';
            }
        }

        $this->loadCredentials($crmRs);
        return $result;
    }

    public function saveConfig(\PDO $dbh) {

        $uS = Session::getInstance();
        $count = 0;
        $idTypeMap = 0;

        // credentials
        $this->saveCredentials($dbh, $uS->username);

        // Handle checkboxes for creating missing custom fields
        $createControl = filter_input_array(INPUT_POST, ['chkCreateCustomField' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY]]);
        if (!empty($createControl['chkCreateCustomField'])) {
            $messages = [];
            $groupId = $this->findOrCreateCustomFieldGroup();
            foreach ($createControl['chkCreateCustomField'] as $fieldName => $v) {
                $fieldName = filter_var($fieldName, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                if ($fieldName !== '' && $v === '1') {

                    $messages[] = $this->createCustomFieldInNeon($dbh, $fieldName, $groupId);
                }
            }
            if (!empty($messages)) {
                $this->configMessage = implode('<br/>', $messages);
                return $this->configMessage;
            }
        }

        // Custom fields
        $results = $this->neonWebServiceV2->getCustomFields('Account');
        $custom_fields = [];
        $myCustomFields = Common::readGenLookupsPDO($dbh, 'Cm_Custom_Fields');

        foreach ($results as $v) {
            if (isset($myCustomFields[ $v['name']])) {
                $custom_fields[$v['name']] = $v['id'];
            }
        }

        // Write Custom Field Ids to the config file.
        ResourceBldr::saveGenLk($dbh, 'Cm_Custom_Fields', $custom_fields, [], []);


        // Properties
        $stmt = $dbh->query("Select * from neon_lists;");

        while ($list = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $neonItems = $this->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

            if ($list['HHK_Lookup'] == 'Fund') {

                // Use Items for the Fund
                $stFund = $dbh->query("select `idItem` as `Code`, `Description`, '' as `Substitute` from item where Deleted = 0;");
                $hhkLookup = [];

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row['Code']] = $row;
                }

                $hhkLookup['p'] = array('Code'=>'p', 0=>'p', 'Description' => 'Payment', 1=>'Payment', 'Substitute'=>'', 2=>'');

            } else if ($list['HHK_Lookup'] == 'Pay_Type') {

                // Use payment_method
                $stFund = $dbh->query("select `idPayment_method` as `Code`, `Method_Name` as `Description`, '' as `Substitute` from payment_method;");
                $hhkLookup = [];

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row['Code']] = $row;
                }

            } else {
                $hhkLookup = HTMLSelector::removeOptionGroups(Common::readGenLookupsPDO($dbh, $list['HHK_Lookup']));
            }

            $stmtList = $dbh->query("Select * from neon_type_map where List_Name = '" . $list['List_Name'] . "'");
            $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);
            $mappedItems = [];
            foreach ($items as $i) {
                $mappedItems[$i['HHK_Type_Code']] = $i;
            }

            $nTbl = new HTMLTable();
            $nTbl->addHeaderTr(HTMLTable::makeTh('HHK Lookup') . HTMLTable::makeTh('NeonCRM Name') . HTMLTable::makeTh('NeonCRM Id'));

            $controls = filter_input_array(INPUT_POST, ['sel' . $list['List_Name'] => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY]]);
            $listNames = array_pop($controls);

            foreach ($neonItems as $n => $k) {

                if (isset($listNames[$n])) {

                    $hhkTypeCode = $listNames[$n];

                    if ($hhkTypeCode == '') {
                        // delete if previously set
                        foreach ($mappedItems as $i) {
                            if ($i['Neon_Type_Code'] == $n && $i['HHK_Type_Code'] != '') {
                                $dbh->exec("delete from neon_type_map  where idNeon_type_map = " .$i['idNeon_type_map']);
                                break;
                            }
                        }

                        continue;

                    } else if (isset($hhkLookup[$hhkTypeCode]) === FALSE) {
                        continue;
                    }

                    if (isset($mappedItems[$hhkTypeCode])) {
                        // Update
                        $count = $dbh->exec("update neon_type_map set Neon_Type_Code = '$n', Neon_Type_name = '$k' where HHK_Type_Code = '$hhkTypeCode' and List_Name = '" . $list['List_Name'] . "'");
                    } else {
                        // Insert
                        $idTypeMap = $dbh->exec("Insert into neon_type_map (List_Name, Neon_Name, Neon_Type_Code, Neon_Type_Name, HHK_Type_Code, Updated_By, Last_Updated) "
                            . "values ('" . $list['List_Name'] . "', '" . $list['List_Item'] . "', '" . $n . "', '" . $k . "', '" . $hhkTypeCode . "', '" . $uS->username . "', now() );");
                    }
                }
            }
        }

        return $count + $idTypeMap;
    }

    public function getConfigMessage(): string {
        return $this->configMessage;
    }

    public function createCustomFieldInNeon(\PDO $dbh, string $fieldName, string $groupId): string {
        $cf = Common::readGenLookupsPDO($dbh, 'Cm_Custom_Fields');

        if (!isset($cf[$fieldName])) {
            return "Field '$fieldName' not found in local configuration.";
        }

        $dataType    = !empty($cf[$fieldName]['Substitute']) ? $cf[$fieldName]['Substitute'] : 'Text';
        $displayName = str_replace('_', ' ', $fieldName);

        try {
            $created = $this->neonWebServiceV2->createCustomField([
                'name'        => $fieldName,
                'status'      => 'ACTIVE',
                'displayName' => $displayName,
                'groupId'     => $groupId,
                'displayType' => 'OneLineText',
                'dataType'    => $dataType,
                'component'   => 'Account',
                'accountSettings' => [
                    'accountType' => 'Any'
                ]
            ]);

            if (empty($created['id'])) {
                return "Failed to create custom field '$fieldName' in NeonCRM.";
            }

            return "Custom field '$fieldName' created and added to '" . self::HHK_CUSTOM_FIELD_GROUP . "' group.";

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $detail = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return "NeonCRM API error creating '$fieldName': " . $detail;
        }
    }

    protected function findOrCreateCustomFieldGroup(): ?string {
        $groups = $this->neonWebServiceV2->getCustomFieldGroups('Account');

        if (\is_array($groups)) {
            foreach ($groups as $group) {
                if (isset($group['displayName']) && $group['displayName'] === self::HHK_CUSTOM_FIELD_GROUP) {
                    return (string) $group['id'];
                }
            }
        }

        $result = $this->neonWebServiceV2->createCustomFieldGroup([
            'displayName' => self::HHK_CUSTOM_FIELD_GROUP,
            'component'   => 'Account',
        ]);

        return isset($result['id']) ? (string) $result['id'] : '';
    }

    protected function openTarget() {

        if (function_exists('curl_version') === FALSE) {
            throw new UploadException('PHP configuration error: cURL functions are missing.');
        }

        if ($this->getUserId() == '' || $this->getPassword() == '') {
            throw new UploadException('User Name or Password are missing.');
        }

        $keys = array('orgId'=>$this->getUserId(), 'apiKey'=>Crypto::decryptMessage($this->getPassword()));

        $this->neonWebService = new Neon();
        $loginResult = $this->neonWebService->login($keys);


        if ( isset( $loginResult['operationResult'] ) && $loginResult['operationResult'] != 'SUCCESS' ) {
            throw new UploadException('API Login failed');
        }

        if ($loginResult['userSessionId'] == '') {
            throw new UploadException('API Session Id is missing');
        }

        return TRUE;
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

    protected function formatError(RequestException $e): string {

        if (!$e->hasResponse()) {
            return 'Request Error: ' . $e->getMessage();
        }

        $response = $e->getResponse();
        $msg = "HTTP {$response->getStatusCode()} {$response->getReasonPhrase()}<br/>";

        $body = (string) $response->getBody();

        if ($body === '') {
            return $msg;
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            return $msg;
        }

        foreach ($decoded as $apiMessage) {
            $code    = $apiMessage['code']    ?? '';
            $message = $apiMessage['message'] ?? '';

            if ($code !== '' || $message !== '') {
                $msg .= $code . ': ' . $message . '<br/>';
            }
        }

        return $msg;
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

    public function getLogServiceName(){
        return self::LOG_SERVICE_NAME;
    }
}

