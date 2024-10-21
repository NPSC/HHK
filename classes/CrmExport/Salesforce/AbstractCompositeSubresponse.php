<?php
namespace HHK\CrmExport\Salesforce;


abstract class AbstractCompositeSubresponse {


    /**
     * Summary of idPsg
     * @var int
     */
    protected $idPsg;

    /**
     * Summary of searchNeedle
     * @var string
     */
    protected $searchNeedle;

    /**
     * Summary of subResponse
     * @var CompositeSubresponse
     */
    protected CompositeSubresponse $subResponse;


    const RELATION_NEEDLE = 'refRel_';
    const CONTACT_NEEDLE = 'refContact_';

    /**
     * Summary of factory
     * @param array $subResponse
     * @param mixed $idPsg
     * @return ContactCompositeSubresponse|RelationCompositeSubresponse|null
     */
    public static function factory(array $subResponse, $idPsg) {

        $compositeSubresponse = new CompositeSubresponse($subResponse);

        if (str_starts_with($compositeSubresponse->getReferenceId(), self::RELATION_NEEDLE)) {
            return new RelationCompositeSubresponse($compositeSubresponse, $idPsg);
        } else if (str_starts_with($compositeSubresponse->getReferenceId(), self::CONTACT_NEEDLE)) {
            return new ContactCompositeSubresponse($compositeSubresponse, $idPsg);
        }

        return null;
    }

    public function __construct(CompositeSubresponse $response, $idPsg)
    {
        $this->subresponse = $response;
        $this->idPsg = $idPsg;

    }

    /**
     * Summary of getIdName
     * @return int
     */
    public function getIdName() {
        return intval(str_replace($this->searchNeedle, '', $this->subResponse->getReferenceId()), 10);
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