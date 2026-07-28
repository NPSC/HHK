<?php
namespace HHK\CrmExport;

use HHK\CrmExport\Neon\NeonManager;
use HHK\CrmExport\Salesforce\SalesforceManager;
use PDOStatement;

interface ExportManagerInterface {

    const SearchViewName = '';

    public static function factory(\PDO $dbh, string $cmsName): ?ExportManagerInterface;

    public function exportMembers(\PDO $dbh, array $ids): array;

    public function upsertMembers(\PDO $dbh, array $sourceIds, bool $trace = false, bool $linkRelatives = true): array;

    public function exportPayments(\PDO $dbh, string $startDateString, string $endDateString): array;

    public function exportVisits(\PDO $dbh, mixed $idPsg, array $rels): array;

    public function getSearchFields(\PDO $dbh, string $tableName): array;

    public function setExcludeMembers(\PDO $dbh, array $psgIds): array;

    public function searchMembers(array $criteria): array;

    public function getMember(\PDO $dbh, array $parameters): string;

    public function retrieveRemoteAccount(string|int $accountId): array;

    public function updateRemoteMember(\PDO $dbh, array $accountData, int $idName, array $extraSourceCols = [], bool $updateAddr = false): string;

    public function showConfig(\PDO $dbh): mixed;

    public function saveConfig(\PDO $dbh): mixed;

    public function unwindResponse(mixed &$line, mixed $results, string $prefix = ''): void;

    public static function loadSearchDB(\PDO $dbh, string $view, array $sourceIds): bool|PDOStatement|null;

    public static function findPrimaryGuest(\PDO $dbh, mixed $idPrimaryGuest, mixed $idPsg, RelationshipMapper $rMapper): array;

    public function getMyCustomFields(\PDO $dbh): array;

    public function resetExternalId(\PDO $dbh, mixed $id): int;

    public function unencodeHTML(mixed $text): array|string|null;

    public function getTransferReport(\PDO $dbh, string $start, string $end): array|bool;
    
    public function getAccountId(): mixed;

    public function setAccountId(string|int $v): void;

    public function getServiceTitle(): mixed;

    public function getServiceName(): mixed;

    public function getUserId(): mixed;

    public function getPassword(): mixed;

    public function getClientId(): mixed;

    public function getClientSecret(): mixed;

    public function getEndpointUrl(): mixed;

    public function getSecurityToken(): mixed;

    public function getApiVersion(): mixed;

    public function getMaxPSGsPerBatch(): mixed;

    public function getLastUpdated(): mixed;

    public function getUpdatedBy(): mixed;

    public function getGatewayId(): mixed;

    public function getMemberReplies(): mixed;

    public function getReplies(): mixed;

    public function getProposedUpdates(): mixed;

    public function getLogServiceName(): string;

}
