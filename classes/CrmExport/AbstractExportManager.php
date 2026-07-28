<?php
namespace HHK\CrmExport;

use HHK\CrmExport\Neon\NeonManager;
use HHK\CrmExport\Salesforce\SalesforceManager;
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameRS;
use HHK\AuditLog\NameLog;
use HHK\sec\Session;
use PDOStatement;

/**
 *
 * @author Eric
 *
 */
abstract class AbstractExportManager implements ExportManagerInterface{

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
    protected $maxPSGsPerBatch;

    protected bool $linkRelatives = true;

    protected $memberReplies;
    protected $replies;
    protected $proposedUpdates;
    protected \PDO $dbh;


    const CMS_NEON = 'neon';
    const CMS_SF = 'sf';
    const EXCLUDE_TERM = 'excld';

    const SearchViewName = '';


    public static function factory(\PDO $dbh, string $cmsName): ?ExportManagerInterface {

        switch (strtolower($cmsName)) {

            case self::CMS_NEON:

                return new NeonManager($dbh, $cmsName);

            case self::CMS_SF:

                return new SalesforceManager($dbh, $cmsName);

            default:

                return NULL;
        }
    }


    public function __construct(\PDO $dbh, string $cmsName) {

        $stmt = $dbh->query("SELECT `Description` FROM `gen_lookups` WHERE `Table_Name` = 'ExternalCRM' AND `Code` = '$cmsName';");
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

        $this->dbh = $dbh;

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
        $this->maxPSGsPerBatch = $cmsRs->retryCount->getStoredVal();
        $this->linkRelatives = ($cmsRs->userLoginUrl->getStoredVal() !== '0');

    }


    public function exportMembers(\PDO $dbh, array $ids): array {
        $replys[0] = array('error' => 'Transferring Members is not implemented');
        return $replys;
    }

    public function exportPayments(\PDO $dbh, string $startDateString, string $endDateString): array {
        $replys[0] = array('error' => 'Transferring Payments is not implemented');
        return $replys;
    }

    public function exportVisits(\PDO $dbh, $idPsg, array $rels): array {
        $replys[0] = array('error' => 'Transferring Visits is not implemented');
        return $replys;
    }

    public function getSearchFields(?\PDO $dbh, string $tableName): array {

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
    public function setExcludeMembers(\PDO $dbh, array $psgIds): array {
        $replys[0] = array('error' => 'Excluding Members is not implemented');
        return $replys;
    }

    public function searchMembers (array $criteria): array {
        $replys[0] = array('error' => 'Transferring Visits is not implemented');
        return $replys;
    }

    public function getMember(\PDO $dbh, array $parameters): string {
        return 'Getting Members is not implemented';
    }

    public function retrieveRemoteAccount(string|int $accountId): array {
        $replys[0] = array('error' => 'Retrieving remote accounts is not implemented');
        return $replys;
    }

    public function updateRemoteMember(\PDO $dbh, array $accountData, int $idName, array $extraSourceCols = [], bool $updateAddr = FALSE): string {
        return 'Updating Members is not implemented';
    }

    public abstract function showConfig(\PDO $dbh): mixed;
    public abstract function saveConfig(\PDO $dbh): mixed;

    public abstract function upsertMembers(\PDO $dbh, array $sourceIds, bool $trace = false, bool $linkRelatives = true): array;

    /**
     * Summary of unwindResponse
     * @param mixed $line
     * @param mixed $results
     * @param mixed $prefix
     * @return void
     */
    public function unwindResponse(&$line, $results, $prefix = ''): void {

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
                $results = ($results) ? 'true' : 'false';
            }
            $line[] = $results;
        }
        return;
    }

    /**
     * Summary of updateLocalExternalId
     * @param \PDO $dbh
     * @param mixed $idName
     * @param mixed $externalId
     * @return int
     */
    protected function updateLocalExternalId(\PDO $dbh, $idName, $externalId): int {

        $uS = Session::getInstance();
        $upd = 0;

        if ($idName > 0 && $externalId != '') {
            $nameRs = new NameRS();
            $nameRs->idName->setStoredVal($idName);
            $rows = EditRS::select($dbh, $nameRs, [$nameRs->idName]);
            EditRS::loadRow($rows[0], $nameRs);

            $nameRs->External_Id->setNewVal($externalId);
            $upd = EditRS::update($dbh, $nameRs, [$nameRs->idName]);

            if ($upd > 0) {
                NameLog::writeUpdate($dbh, $nameRs, $nameRs->idName->getStoredVal(), $uS->username);
            }
        }

        return $upd;
    }

    /**
     * Summary of resetExternalId
     * @param \PDO $dbh
     * @param mixed $id
     * @return int
     */
    public function resetExternalId(\PDO $dbh, $id): int {

        return $this->updateLocalExternalId($dbh, $id, '');

    }

    /**
     * Summary of unencodeHTML
     * @param mixed $text
     * @return array|string|null
     */
    public function unencodeHTML($text): array|string|null {

        $txt = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $text
            );

        return $txt;
    }


    /**
     * Summary of loadSearchDB
     * @param \PDO $dbh
     * @param string $view
     * @param array $sourceIds
     * @return PDOStatement|bool|null
     */
    public static function loadSearchDB(\PDO $dbh, string $view, array $sourceIds): bool|PDOStatement|null {

        if ($view == '') {
            return NULL;
        }

        // clean up the ids
        $idList = [];
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

    /**
     * Summary of findPrimaryGuest
     * @param \PDO $dbh
     * @param mixed $idPrimaryGuest
     * @param mixed $idPsg
     * @param \HHK\CrmExport\RelationshipMapper $rMapper
     * @return array
     */
    public static function findPrimaryGuest(\PDO $dbh, $idPrimaryGuest, $idPsg, RelationshipMapper $rMapper): array
    {
        return array();
    }

    public function getMyCustomFields(\PDO $dbh): array {
        return [];
    }

    public function getAccountId(): mixed {
        return $this->accountId;
    }

    public function setAccountId(string|int $v): void {
        $this->accountId = $v;
    }

    public function getServiceTitle(): mixed {
        return $this->serviceTitle;
    }

    public function getServiceName(): string {
        return $this->serviceName;
    }

    public function getUserId(): mixed {
        return $this->userId;
    }

    public function getPassword(): mixed {
        return $this->password;
    }

    public function getClientId(): mixed {
        return $this->clientId;
    }

    public function getClientSecret(): mixed {
        return $this->clientSecret;
    }

    public function getEndpointUrl(): mixed {
        return $this->endpointURL;
    }

    public function getSecurityToken(): mixed {
        return $this->securityToken;
    }

    public function getApiVersion(): mixed {
        return $this->apiVersion;
    }

    public function getMaxPSGsPerBatch(): mixed{
        return $this->maxPSGsPerBatch;
    }

    public function getLinkRelatives(): bool {
        return $this->linkRelatives;
    }

    public function getLastUpdated(): mixed {
        return $this->lastUpdated;
    }

    public function getUpdatedBy(): mixed {
        return $this->updatedBy;
    }

    public function getGatewayId(): mixed {
        return $this->gatewayId;
    }

    public function getMemberReplies(): array {
        return $this->memberReplies;
    }

    public function getReplies(): array
    {
        return $this->replies;
    }

    public function getProposedUpdates(): mixed
    {
        return $this->proposedUpdates;
    }

    public function getLogServiceName(): string {
        return '';
    }


}

