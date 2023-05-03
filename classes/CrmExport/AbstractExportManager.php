<?php
namespace HHK\CrmExport;

use HHK\CrmExport\Neon\NeonManager;
use HHK\CrmExport\Salesforce\SalesforceManager;
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameRS;
use HHK\AuditLog\NameLog;
use HHK\sec\Session;

/**
 *
 * @author Eric
 *
 */
abstract class AbstractExportManager {

    protected $webService;
    protected $gatewayId;
    protected $userId;
    protected $password;
    protected $endpointURL;

    protected $serviceName;
    protected $serviceTitle;
    protected $clientId;
    protected $clientSecret;
    protected $securityToken;
    protected $userLoginUrl;
    protected $retryCount;
    protected $updatedBy;
    protected $lastUpdated;

    protected $errorMessage;
    protected $accountId;
    protected $apiVersion;

    protected $memberReplies;
    protected $replies;
    protected $proposedUpdates;


    const CMS_NEON = 'neon';
    const CMS_SF = 'sf';
    const EXCLUDE_TERM = 'excld';


    public static function factory(\PDO $dbh, $cmsName) {

        switch (strtolower($cmsName)) {

            case self::CMS_NEON:

                return new NeonManager($dbh, $cmsName);

            case self::CMS_SF:

                return new SalesforceManager($dbh, $cmsName);

            default:

                return NULL;
        }
    }


    public function __construct(\PDO $dbh, $cmsName) {

        $stmt = $dbh->query("SELECT `Description` FROM gen_lookups WHERE Table_Name = 'ExternalCRM' AND Code = '$cmsName';");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (isset($rows[0]) && isset($rows[0][0])) {
            $this->serviceTitle = $rows[0][0];
        }

        $this->serviceName = $cmsName;
        $this->errorMessage = '';

        $cmsRs = new CmsGatewayRS();
        $cmsRs->Gateway_Name->setStoredVal($this->serviceName);
        $gws = EditRS::select($dbh, $cmsRs, array($cmsRs->Gateway_Name));

        if (count($gws) == 1) {
            $cmsRs = new CmsGatewayRS();
            EditRS::loadRow($gws[0], $cmsRs);
        }

        $this->loadCredentials($cmsRs);

    }

    protected function loadCredentials(CmsGatewayRS $cmsRs) {

        $this->password = $cmsRs->password->getStoredVal();
        $this->userId = $cmsRs->username->getStoredVal();
        $this->endpointURL = $cmsRs->endpointUrl->getStoredVal();
        $this->clientId = $cmsRs->clientId->getStoredVal();
        $this->clientSecret = $cmsRs->clientSecret->getStoredVal();
        $this->securityToken = $cmsRs->securityToken->getStoredVal();
        $this->userLoginUrl = $cmsRs->userLoginUrl->getStoredVal();
        $this->gatewayId = $cmsRs->idcms_gateway->getStoredVal();
        $this->updatedBy = $cmsRs->Updated_By->getStoredVal();
        $this->lastUpdated = $cmsRs->Last_Updated->getStoredVal();
        $this->apiVersion = $cmsRs->apiVersion->getStoredVal();

    }


    public function exportMembers(\PDO $dbh, array $ids) {
        $replys[0] = array('error' => 'Transferring Members is not implemented');
        return $replys;
    }

    public function exportPayments(\PDO $dbh, $startDateString, $endDateString) {
        $replys[0] = array('error' => 'Transferring Payments is not implemented');
        return $replys;
    }

    public function exportVisits(\PDO $dbh, $idPsg, array $rels) {
        $replys[0] = array('error' => 'Transferring Visits is not implemented');
        return $replys;
    }

    public static function getSearchFields($dbh, $tableName) {

        $stmt = $dbh->query("SHOW COLUMNS FROM `$tableName`;");
        $cols = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cols[] = $r['Field'];
        }

        return $cols;
    }

    /**
     * Locally exclude members from external CRM.
     *
     * @param array $psgIds  PSG Id's to exclude.
     */
    public function setExcludeMembers(\PDO $dbh, array $psgIds) {
        $replys[0] = array('error' => 'Excluding Members is not implemented');
        return $replys;
    }

    public function searchMembers ($criteria) {
        $replys[0] = array('error' => 'Transferring Visits is not implemented');
        return $replys;
    }

    public function getMember(\PDO $dbh, $parameters) {
        $replys[0] = array('error' => 'Getting Members is not implemented');
        return $replys;
    }

    public function retrieveRemoteAccount($accountId) {
        $replys[0] = array('error' => 'Retrieving remote accounts is not implemented');
        return $replys;
    }

    public function updateRemoteMember(\PDO $dbh, array $accountData, $idName, $extraSourceCols = [], $updateAddr = FALSE) {
        $replys[0] = array('error' => 'Updating Members is not implemented');
        return $replys;
    }

    public abstract function showConfig(\PDO $dbh);
    public abstract function saveConfig(\PDO $dbh);

    public function unwindResponse(&$line, $results, $prefix = '') {

        if (is_array($results)) {

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
        } else {
            if (is_bool($results)) {
                if ($results) {
                    $results = 'true';
                } else {
                    $results = 'false';
                }
            }
            $line[] = $results;
        }
        return;
    }

    protected function updateLocalExternalId(\PDO $dbh, $idName, $externalId) {

        $uS = Session::getInstance();
        $upd = 0;

        if ($idName > 0) {
            $nameRs = new NameRS();
            $nameRs->idName->setStoredVal($idName);
            $rows = EditRS::select($dbh, $nameRs, array($nameRs->idName));
            EditRS::loadRow($rows[0], $nameRs);

            $nameRs->External_Id->setNewVal($externalId);
            $upd = EditRS::update($dbh, $nameRs, array($nameRs->idName));

            if ($upd > 0) {
                NameLog::writeUpdate($dbh, $nameRs, $nameRs->idName->getStoredVal(), $uS->username);
            }
        }

        return $upd;
    }

    public function resetExternalId(\PDO $dbh, $id) {

        return $this->updateLocalExternalId($dbh, $id, '');

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


    public static function loadSearchDB(\PDO $dbh, $view, $sourceIds) {

        if ($view == '') {
            return NULL;
        }

        // clean up the ids
        foreach ($sourceIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            $parm = " in (" . implode(',', $idList) . ") ";
            return $dbh->query("Select * from $view where HHK_ID $parm");

        }

        return NULL;
    }

    public static function findPrimaryGuest(\PDO $dbh, $idPrimaryGuest, $idPsg, RelationshipMapper $rMapper)
    {
        return array();
    }

    public function getMyCustomFields(\PDO $dbh) {
        return [];
    }

    public function getAccountId() {
        return $this->accountId;
    }

    public function setAccountId($v) {
        $this->accountId = $v;
    }

    public function getServiceTitle() {
        return $this->serviceTitle;
    }

    public function getServiceName() {
        return $this->serviceName;
    }

    public function getUserId() {
        return $this->userId;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getClientId() {
        return $this->clientId;
    }

    public function getClientSecret() {
        return $this->clientSecret;
    }

    public function getEndpointUrl() {
        return $this->endpointURL;
    }

    public function getSecurityToken() {
        return $this->securityToken;
    }

    public function getApiVersion() {
        return $this->apiVersion;
    }

    public function getLastUpdated() {
        return $this->lastUpdated;
    }

    public function getUpdatedBy() {
        return $this->updatedBy;
    }

    public function getGatewayId() {
        return $this->gatewayId;
    }

    public function getMemberReplies() {
        return $this->memberReplies;
    }

    public function getReplies()
    {
        return $this->replies;
    }

    public function getProposedUpdates()
    {
        return $this->proposedUpdates;
    }


}

