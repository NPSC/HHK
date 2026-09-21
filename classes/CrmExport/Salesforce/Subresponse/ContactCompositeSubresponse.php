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
        $statusCode = $this->subresponse->getHttpStatusCode();

        if ($statusCode === 201) {
            // New record — upsert inserted; body contains the new SF id
            $this->updateLocal($dbh);
            return 'New Contact';
        }

        if ($statusCode === 200 || $statusCode === 204) {
            // Existing record — upsert updated; 200 body has success:true/created:false, 204 has no body
            return 'Contact Updated';
        }

        return 'Contact: '. $this->subresponse->getBody_errorCode() . ', ' . $this->subresponse->getBody_message() . ' (' . $statusCode . ')';
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