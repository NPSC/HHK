<?php
/**
 * Document.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Note
 *
 * @author Eric
 */

class Document {

    // Document statuses
    const DOC_ACTIVE = 'a';
    const DOC_UPDATED = 'u';
    const DOC_DELETED = 'd';


    // Document record field vars
    protected $idDocument = 0;
    protected $doc = NULL;
    protected $title = '';
    protected $abstract = '';
    protected $type = '';
    protected $category = '';
    protected $createdBy = '';
    protected $status = '';
    protected $createdOn = null;
    protected $lastUpdated = null;
    protected $updatedBy = '';

    protected $docTypes;
    protected $docCategories;

    protected $hasDocument;

    /**
     *
     * @var DocumentRS
     */
    private $docRS;

    /**
     *
     * @param int $idDocument
     */
    public function __construct(\PDO $dbh, $idDocument = 0) {

        $id = intval($idDocument, 10);
        $this->idDocument = $id;

        $this->loadDoc($dbh);

        $this->docTypes = readGenLookupsPDO($dbh, 'Document_Type');
        $this->docCategories = readGenLookupsPDO($dbh, 'Document_Category');

    }
    
    public static function findDocument(\PDO $dbh, $title, $category, $type) {
        
        $idDoc = 0;
                
        $stmt = $dbh->query("select idDocument from document where `Title` = '$title' and `Category` = '$category' and `Type` = '$type' and `Status` = 'a'");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        
        if ($stmt->rowCount() > 0) {
            $idDoc = $rows[0][0];
        }

        return $idDoc;

    }

    protected function loadDoc(\PDO $dbh) {

        $docRS = new DocumentRS();
        $this->hasDocument = FALSE;

        if ($this->idDocument > 0) {

            $docRS->idDocument->setStoredVal($this->idDocument);
            $rows = EditRS::select($dbh, $docRS, array($docRS->idDocument));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $docRS);

                $this->setStatus($docRS->Status->getStoredVal())
                        ->setDoc($docRS->Doc->getStoredVal())
                        ->setTitle($docRS->Title->getStoredVal())
                        ->setAbstract($docRS->Abstract->getStoredVal())
                        ->setType($docRS->Type->getStoredVal())
                        ->setUpdatedBy($docRS->Updated_By->getstoredVal())
                        ->setLastUpdated($docRS->Last_Updated->getStoredVal());

                $this->createdBy = $docRS->Created_By->getStoredVal();
                $this->category = $docRS->Category->getStoredVal();
                $this->createdOn = $docRS->Timestamp->getStoredVal();

                $this->hasDocument = TRUE;

            }
        }

        $this->docRS = $docRS;
    }

    /**
     *
     * @param blob $doc
     * @param string $title
     * @param string $type
     * @param string $category
     * @param string $abstract
     */
    public function createNew($doc, $title, $type, $category, $abstract = '') {

        if (isset($this->docTypes[$type]) && isset($this->docCategories[$category])) {

            $this->setDoc($doc)
                ->setType($type)
                ->setTitle($title)
                ->setAbstract($abstract)
                ->setStatus(Document::DOC_ACTIVE);

                $document->category = $category;
                $document->idDocument = 0;

        } else {
            throw new Hk_Exception_Runtime('Document Type or Category is invalid.  ');
        }

    }

    public function save(\PDO $dbh, $userName) {

        $docRS = new DocumentRS();

        if ($this->idDocument == 0) {
            // Insert
            $docRS->Created_By->setNewVal($this->getCreatedBy());
            $docRS->Doc->setNewVal($this->getDoc());
            $docRS->Type->setNewVal($this->getType());
            $docRS->Category->setNewVal($this->getCategory());
            $docRS->Abstract->setNewVal($this->getAbstract());
            $docRS->Title->setNewVal($this->getTitle());
            $docRS->Status->setNewVal($this->getStatus());
            $docRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $this->idDocument = EditRS::insert($dbh, $docRS);

        } else {
            // Update

            // Set original status to updated
            $this->docRS->Status->setNewVal(Document::DOC_UPDATED);
            EditRS::update($dbh, $this->docRS, array($this->docRS->idDocument));

            // Create a new Copy
            $docRS->Created_By->setNewVal($this->getCreatedBy());
            $docRS->Doc->setNewVal($this->getDoc());
            $docRS->Type->setNewVal($this->getType());
            $docRS->Category->setNewVal($this->getCategory());
            $docRS->Abstract->setNewVal($this->getAbstract());
            $docRS->Title->setNewVal($this->getTitle());
            $docRS->Status->setNewVal(Document::DOC_ACTIVE);
            $docRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $docRS->Updated_By->setNewVal($userName);
            $docRS->Timestamp->setNewVal($this->getCreatedOn());

            $this->idDocument = EditRS::insert($dbh, $docRS);

        }

        $this->loadDoc($dbh);

        return $this->getIdDocument();
    }



    /**
     *
     * @param \PDO $dbh
     * @param string $username
     * @return int the number of rows affected
     */
    public function delete(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->getIdDocument() > 0) {

            $this->docRS->Status->setNewVal(Document::DOC_DELETED);

            $this->docRS->Updated_By->setNewVal($username);
            $this->docRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->docRS, array($this->docRS->idDocument));

        }

        return $counter;
    }


    public function getIdDocument() {
        return $this->idDocument;
    }

    public function getDoc() {
        return $this->doc;
    }

    public function isValid() {
        return $this->hasDocument;
    }

    public function getType() {
        return $this->type;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getAbstract() {
        return $this->abstract;
    }

    public function getCategory() {
        return $this->category;
    }

    public function getCreatedBy() {
        return $this->createdBy;
    }

    public function getLastUpdated() {
        return $this->lastUpdated;
    }

    public function getUpdatedBy() {
        return $this->updatedBy;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getCreatedOn() {
        return $this->createdOn;
    }


    public function setDoc($doc) {
        $this->doc = $doc;
        return $this;
    }

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function setAbstract($type) {
        $this->abstract = $type;
        return $this;
    }

    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    public function setLastUpdated($lastUpdated) {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function setUpdatedBy($updatedBy) {
        $this->updatedBy = $updatedBy;
        return $this;
    }


}


class ListDocuments {

//    protected $type;
//    protected $category;

    public static function listHouseForms(\PDO $dbh) {

        $docs = array();

        $stmt = $dbh->query("SELECT `idDocument`, `Title`, Last_Updated from `document` where `Category` = 'form' and `Type` = 'md' and `Status` = 'a';");


        If ($stmt->rowCount() > 0) {
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $docs;
    }

    public static function asLookups($docs) {

        $lookups = array();

        foreach ($docs as $d) {
            $lookups[$d['idDocument']] = array('Code'=>$d['idDocument'], 'Description'=>$d['Title'],
                                                0=>$d['idDocument'], 1=>$d['Title']);
        }

        return $lookups;
    }
}