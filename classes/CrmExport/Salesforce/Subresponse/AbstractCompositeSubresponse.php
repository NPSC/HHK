<?php
namespace HHK\CrmExport\Salesforce\Subresponse;


abstract class AbstractCompositeSubresponse {


    /**
     * Summary of idPsg
     * @var int
     */
    public $idPsg;

    /**
     * Summary of searchNeedle
     * @var string
     */
    protected $searchNeedle;

    protected $isSuccessful;

    /**
     * Summary of subResponse
     * @var CompositeSubresponse
     */
    protected CompositeSubresponse $subresponse;


    const RELATION_NEEDLE = 'refRel_';
    const CONTACT_NEEDLE = 'refContact_';

    /**
     * Summary of factory
     * @param array $subResponse
     * @param mixed $idPsg
     * @return ContactCompositeSubresponse|RelationCompositeSubresponse|null
     */
    public static function factory(array $subResponse, $idPsg, $isSuccessful) {

        $compositeSubresponse = new CompositeSubresponse($subResponse, $isSuccessful);

        if (str_starts_with($compositeSubresponse->getReferenceId(), self::RELATION_NEEDLE)) {
            return new RelationCompositeSubresponse($compositeSubresponse, $idPsg, $isSuccessful);
        } else if (str_starts_with($compositeSubresponse->getReferenceId(), self::CONTACT_NEEDLE)) {
            return new ContactCompositeSubresponse($compositeSubresponse, $idPsg, $isSuccessful);
        }

        return null;
    }

    public function __construct(CompositeSubresponse $response, $idPsg, $isSuccessful)
    {
        $this->subresponse = $response;
        $this->idPsg = $idPsg;
        $this->isSuccessful = $isSuccessful;

    }

    /**
     * Summary of getIdName
     * @return int
     */
    public function getIdName() {

        $parts = explode('_', $this->subresponse->getReferenceId());
        if (isset($parts[1])) {
            return intval($parts[1], 10);
        }
        return 0;
    }

    /**
     * Summary of processResult
     * @param \PDO $dbh
     * @return string
     */
    public abstract function processResult(\PDO $dbh);

    /**
     * Summary of getContactId
     * @return string
     */
    public abstract function getContactId();

    /**
     * Summary of updateLocal
     * @param \PDO $dbh
     * @return int
     */
    public abstract function updateLocal(\PDO $dbh);

}