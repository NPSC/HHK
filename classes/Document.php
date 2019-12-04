<?php
/**
 * Document.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Document
 *
 * @author Will
 */

class Document {

    // Document Type
    const FileType = 'file';
    const HtmlType = 'html';
    
    //LinkTypes
    const GuestLink = "guestId";
    const PsgLink = "psgId";

    // Document Status
    const ActiveStatus = 'a';
    const DeletedStatus = 'd';
    
    // MimeTypes
    
    protected $mimeTypes =
    	[
    		'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'application/pdf' => 'pdf',
			'application/msword' => 'doc',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];


    // Document record field vars
    protected $idDocument = 0;
    protected $title = '';
    protected $name = '';
    protected $category = '';
    protected $type = '';
    protected $mimeType = '';
    protected $folder = '';
    protected $language = '';
    protected $abstract = '';
    protected $doc = '';
    protected $status = '';
    protected $createdBy = '';
    protected $lastUpdated = null;
    protected $updatedBy = '';
    protected $cretedOn = '';

    private $documentRS;

    /**
     *
     * @param int $idDocument
     */
    public function __construct($idDocument = 0) {

        $id = intval($idDocument, 10);
        $this->idDocument = $id;

    }

    /**
     *
     * @param \PDO $dbh
     * @return boolean
     */
    public function loadDocument(\PDO $dbh) {

        $response = TRUE;

        if ($this->idDocument > 0) {

            $documentRS = new DocumentRS();
            $documentRS->idDocument->setStoredVal($this->idDocument);
            $rows = EditRS::select($dbh, $documentRS, array($documentRS->idDocument));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $documentRS);

                $this->createdOn = $documentRS->Timestamp->getStoredVal();
                $this->setStatus($documentRS->Status->getStoredVal());
                $this->setTitle($documentRS->Title->getStoredVal());
                $this->setCategory($documentRS->Category->getStoredVal());
                $this->setType($documentRS->Type->getStoredVal());
                $this->setMimeType($documentRS->Mime_Type->getStoredVal());
                $this->setFolder($documentRS->Folder->getStoredVal());
                $this->setLanguage($documentRS->Language->getStoredVal());
                $this->setAbstract($documentRS->Abstract->getStoredVal());
                $this->setDoc($documentRS->Doc->getStoredVal());
                $this->setCreatedBy($documentRS->Created_By->getStoredVal());
                $this->setUpdatedBy($documentRS->Updated_By->getstoredVal());
                $this->setLastUpdated($documentRS->Last_Updated->getStoredVal());

                $this->documentRS = $documentRS;

            } else {
                $response =  FALSE;
            }
        }

        return $response;
    }

    /**
     *
     * @param string $title
     * @param string $mimeType
     * @param string $doc
     * @param string $documentType
     * @param string $docStatus
     */
    public static function createNew($title, $mimeType, $doc, $username, $documentType = self::FileType, $docStatus = Document::ActiveStatus ) {

        if ($title != '' && $mimeType != '' && $doc != '') {

            $document = new Document();

            $document->setTitle($title);
            $document->setMimeType($mimeType);
            $document->setDoc($doc);
            $document->setType($documentType);
            $document->setStatus($docStatus);
            $document->setCreatedBy($username);
            $document->idDocument = 0;

        } else {
            throw new Hk_Exception_Runtime('Trying to create an invalid Document.  ');
        }

        return $document;
    }

    public function saveNew(\PDO $dbh) {

        if ($this->isValid() === FALSE) {
            return FALSE;
        }

        // Insert
        $documentRS = new DocumentRS();
        $documentRS->Title->setNewVal($this->getTitle());
        $documentRS->Mime_Type->setNewVal($this->getMimeType());
        $documentRS->Doc->setNewVal($this->getDoc());
        $documentRS->Type->setNewVal($this->getType());
        $documentRS->Status->setNewVal($this->getStatus());
        $documentRS->Created_By->setNewVal($this->getCreatedBy());
        $documentRS->Last_Updated->setNewVal($this->getLastUpdated());
        $documentRS->Updated_By->setNewVal($this->getUpdatedBy());

        $this->idDocument = EditRS::insert($dbh, $documentRS);
        $documentRS->idDocument->setNewVal($this->idDocument);
        EditRS::updateStoredVals($documentRS);

        $this->documentRS = $documentRS;

    }
    
    /**
     *
     * @param \PDO $dbh
     * @param string $guestId
     * @param string $psgId
     * @return int last insert id.
     */
     
    public function linkNew(\PDO $dbh, $guestId = null, $psgId = null){
	    if($this->idDocument && ($psgId || $guestId)){
		    $query = 'INSERT INTO `link_doc` (`idDocument`, `idGuest`, `idPSG`) VALUES("' . $this->idDocument . '", "' . $guestId . '", "' . $psgId . '");';
		    $stmt = $dbh->prepare($query);
		    $stmt->execute();
		    return $dbh->lastInsertId();
		    
	    }
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $title
     * @return int the number of records updated.
     */

    public function saveTitle(\PDO $dbh, $title) {

        $counter = 0;

        if ($this->getIdDocument() > 0 && $this->loadDocument($dbh)) {

            $this->documentRS->Title->setNewVal($title);

            $counter = EditRS::update($dbh, $this->documentRS, array($this->documentRS->idDocument));
            EditRS::updateStoredVals($this->documentRS);
        }

        return $counter;
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $username
     * @return int the number of rows affected
     */
    public function deleteDocument(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->getIdDocument() > 0 && $this->loadDocument($dbh)) {

            $this->documentRS->Status->setNewVal(self::DeletedStatus);
            $this->documentRS->Updated_By->setNewVal($username);
            $this->documentRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->documentRS, array($this->documentRS->idDocument));
            EditRS::updateStoredVals($this->documentRS);

        }

        return $counter;
    }


    /**
     *
     * @param \PDO $dbh
     * @param string $username
     * @return int the number of rows affected
     */
    public function undoDeleteDocument(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->getIdDocument() > 0 && $this->loadDocument($dbh)) {

            $this->documentRS->Status->setNewVal(self::ActiveStatus);
            $this->documentRS->Updated_By->setNewVal($username);
            $this->documentRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->documentRS, array($this->documentRS->idDocument));
            EditRS::updateStoredVals($this->documentRS);

        }

        return $counter;
    }

    protected function isValid() {

        return TRUE;
    }

    public function getIdDocument() {
        return $this->idDocument;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getName() {
        return $this->name;
    }

    public function getCategory() {
        return $this->category;
    }

    public function getType() {
        return $this->type;
    }
    
    public function getMimeType() {
        return $this->mimeType;
    }
    
    public function getFolder() {
        return $this->folder;
    }
    
    public function getLanguage() {
        return $this->language;
    }
    
    public function getAbstract() {
        return $this->abstrac;
    }
    
    public function getDoc() {
        return $this->doc;
    }
    
    public function getStatus() {
        return $this->status;
    }

    public function getLastUpdated() {
        return $this->lastUpdated;
    }
    
    public function getCreatedBy() {
        return $this->createdBy;
    }

    public function getUpdatedBy() {
        return $this->updatedBy;
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setCategory($category) {
        $this->category = $category;
    }

    public function setType($type) {
        $this->type = $type;
    }
    
    public function setMimeType($mimeType) {
        $this->mimeType = $mimeType;
    }
    
    public function setFolder($folder) {
        $this->folder = $folder;
    }
    
    public function setLanguage($language) {
        $this->language = $language;
    }
    
    public function setAbstract($abstract) {
        $this->abstract = $abstract;
    }
    
    public function setDoc($doc) {
        $this->doc = $doc;
    }
    
    public function setStatus($status) {
        $this->status = $status;
    }

    public function setLastUpdated($lastUpdated) {
        $this->lastUpdated = $lastUpdated;
    }
    
    public function setCreatedBy($createdBy) {
        $this->createdBy = $createdBy;
    }

    public function setUpdatedBy($updatedBy) {
        $this->updatedBy = $updatedBy;
    }
    
    public function getExtension(){
	    if($this->mimeType && $this->mimeTypes[$this->mimeType]){
		    return $this->mimeTypes[$this->mimeType];
	    }else{
		    return false;
	    }
    }
}