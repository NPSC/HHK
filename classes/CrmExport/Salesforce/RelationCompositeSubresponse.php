<?php
namespace HHK\CrmExport\Salesforce;

use HHK\Tables\EditRS;
use HHK\sec\Session;
use HHK\Tables\Name\Name_GuestRS;
use HHK\AuditLog\NameLog;

class RelationCompositeSubresponse extends AbstractCompositeSubresponse {


    /**
     * Summary of __construct
     * @param \HHK\CrmExport\Salesforce\CompositeSubresponse $response
     * @param mixed $idPsg
     */
    public function __construct(CompositeSubresponse $response, $idPsg, $isSuccessful)
    {
        $this->searchNeedle = self::RELATION_NEEDLE;
        parent::__construct($response, $idPsg, $isSuccessful);

    }

    /**
     * Summary of processResult
     * @return string
     */
    public function processResult(\PDO $dbh): string {
        $result = '';

        if ($this->subresponse->getBody_success()) {
            $result = 'New Relationship';
            $this->updateLocal($dbh);
        } else {
            $result = $result = 'Relat: ' . $this->subresponse->getBody_errorCode() . $this->subresponse->getBody_message() . '(' . $this->subresponse->getHttpStatusCode() . ')';
        }
        return $result;
    }

    /**
     * Summary of getContactId
     * @return string
     */
    public function getContactId(): string
    {
        $id = '';

        return $id;
    }

    /**
     * Summary of updateLocal
     * @param \PDO $dbh
     * @return int
     */
    public function updateLocal(\PDO $dbh)
    {

        $uS = Session::getInstance();
        $upd = 0;
        $idName = $this->getIdName();
        $externalId = $this->subresponse->getBody_id();

        if ($idName > 0 && $externalId != '') {
            $nameRs = new Name_GuestRS();
            $nameRs->idName->setStoredVal($idName);
            $nameRs->idPsg->setStoredVal($this->idPsg);
            $rows = EditRS::select($dbh, $nameRs, [$nameRs->idName, $nameRs->idPsg]);
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