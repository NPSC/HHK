<?php
namespace HHK\CrmExport\Salesforce\Subresponse;

use HHK\Tables\EditRS;
use HHK\sec\Session;
use HHK\Tables\Name\NameRS;
use HHK\AuditLog\NameLog;

class ContactCompositeSubresponse extends AbstractCompositeSubresponse
{

    /**
     * Summary of __construct
     * @param CompositeSubresponse $response
     * @param mixed $idPsg
     */
    public function __construct(CompositeSubresponse $response, $idPsg, $isSuccessful)
    {
        $this->searchNeedle = self::CONTACT_NEEDLE;
        parent::__construct($response, $idPsg, $isSuccessful);

    }

    /**
     * Summary of processResult
     * @return string
     */
    public function processResult(\PDO $dbh): string
    {
        $result = '';

        if ($this->subresponse->getBody_success()) {
            // 201 — new contact created by upsert; save the returned SF id locally
            $this->updateLocal($dbh);
            return 'New Contact';
        }

        if ($this->subresponse->getHttpStatusCode() === 204) {
            // 204 — existing contact updated by upsert; no body returned
            return 'Contact Updated';
        }

        return 'Contact: '. $this->subresponse->getBody_errorCode() . ', ' . $this->subresponse->getBody_message() . ' (' . $this->subresponse->getHttpStatusCode() . ')';
    }

    /**
     * Summary of getContactId
     * @return string
     */
    public function getContactId(): string
    {
        $id = $this->subresponse->getBody_id();

        return $id;
    }

    /**
     * Summary of updateLocal
     * @param \PDO $dbh
     * @return int
     */
    public function updateLocal(\PDO $dbh): int
    {

        $uS = Session::getInstance();
        $upd = 0;
        $idName = $this->getIdName();
        $externalId = $this->subresponse->getBody_id();

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

}