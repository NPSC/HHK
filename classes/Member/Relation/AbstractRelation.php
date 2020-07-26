<?php

namespace HHK\Member\Relation;

use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\Member\AbstractMember;
use HHK\SysConst\{MemStatus, RelLinkType};
use HHK\Tables\EditRS;
use HHK\Tables\Name\RelationsRS;

/**
 * AbstractRelation.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractRelation {
    
    /** @var array Holds an iTable for each relation of this type */
    protected $relNames = array();
    
    /** @var int */
    protected $id;
    
    /** @var integer control id attribute prefix - optional */
    protected $idPrefex;
    
    /** @var RelationCode  */
    protected $relCode;
    
    protected $trash;
    protected $careof;
    protected $addCareof;
    
    /**
     *
     * @param \PDO $dbh
     * @param AbstractMember $name
     * @param string $idPrefix
     */
    public function __construct(\PDO $dbh, $idName) {
        
        $this->id = $idName;
        $this->relCode = $this->loadRelCode();
        
        $this->relNames = $this->loadRecords($dbh);
        
        $this->trash = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash', 'title'=>'Delete Link', 'style'=>'float: left; margin-right:.3em;margin-top:.2em;'));
        $this->careof = HTMLContainer::generateMarkup('span', '', array('name'=>'delcareof', 'class'=>'ui-icon ui-icon-mail-closed', 'style'=>'float: left; margin-right:.3em;margin-top:.2em;'));
        $this->addCareof = HTMLContainer::generateMarkup('span', '', array('name'=>'addcareof', 'class'=>'ui-icon ui-icon-plus', 'style'=>'float: left; margin-right:.3em;margin-top:.2em;'));
        
    }
    
    public static function instantiateRelation(\PDO $dbh, $relCode, $idName) {
        
        switch ($relCode) {
            case RelLinkType::Child:
                return new Children($dbh, $idName);
                break;
                
            case RelLinkType::Parnt:
                return new Parents($dbh, $idName);
                break;
                
            case RelLinkType::Sibling:
                return new Siblings($dbh, $idName);
                break;
                
            case RelLinkType::Relative:
                return new Relatives($dbh, $idName);
                break;
                
            case RelLinkType::Spouse:
                return new Partner($dbh, $idName);
                break;
                
                // Individuals link to one organization.
            case RelLinkType::Company:
                return new Company($dbh, $idName);
                break;
                
                // Organizations link to employees.
            case RelLinkType::Employee:
                return new Employees($dbh, $idName);
                break;
                
            default:
                break;
        }
        
        return null;
    }
    
    public function getRelNames() {
        return $this->relNames;
    }
    
    protected abstract function loadRelCode();
    
    /**
     *
     * @param \PDO $dbh
     * @return relationsRS
     */
    protected function loadRecords(\PDO $dbh) {
        $rels = array();
        
        //$stmt = $dbh->prepare($this->getQuery(), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        //$stmt->execute(array(":id" => $this->getIdName(), ":idw" => $this->getIdName()));
        $stmt = $this->getPdoStmt($dbh);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $rs = new relationsRS();
                EditRS::loadRow($r, $rs);
                $rels[] = array('Id' => $r["Id"], 'Name' => $r["Name"], 'Status'=>$r['MemStatus'], 'rs' => $rs);
            }
        }
        return $rels;
    }
    
    protected abstract function getPdoStmt(\PDO $dbh);
    
    protected abstract function getHtmlId();
    
    public function savePost(\PDO $dbh, array $post, $user) {
        
        if (isset($post["sel" . $this->relCode->getCode()])) {
            
            $item = $post["sel" . $this->relCode->getCode()];
            
            // Add any relations
            if (substr($item, 0, 2) == "n_") {
                
                // caught a new ID
                $rId = intval(filter_var(substr($item, 2), FILTER_SANITIZE_NUMBER_INT));
                
                if ($rId > 0 && $rId <> $this->name->get_idName()) {
                    // Ok, record new relationship
                    $this->addRelationship($dbh, $this->name->get_idName(), $rId, $user);
                }
            }
            
            
            // Delete any relations?
            if (isset($post["x" . $this->relCode->getCode()])) {
                $dRel = filter_var($post["x" . $this->relCode->getCode()], FILTER_VALIDATE_BOOLEAN);
                if ($dRel) {
                    // One of the delete checkboxes are checked
                    $rId = intval(filter_var($item, FILTER_SANITIZE_NUMBER_INT));
                    
                    if ($rId > 0) {
                        // Delete a relationship
                        $this->removeRelationship($dbh, $this->name->get_idName(), $rId);
                    }
                }
            }
        }
    }
    
    public abstract function addRelationship(\PDO $dbh, $rId, $user);
    
    public abstract function removeRelationship(\PDO $dbh, $rId);
    
    public function getIdName() {
        return $this->id;
    }
    
    public function createMarkup($page = 'NameEdit.php') {
        
        $table = new HTMLTable();
        
        $table->addHeaderTr(HTMLTable::makeTh($this->relCode->getTitle(), array('colspan'=>'2')));
        
        if (count($this->relNames) > 0) {
            
            foreach ($this->relNames as $rName) {
                
                $deceasedClass = '';
                if ($rName['Status'] == MemStatus::Deceased) {
                    $deceasedClass = ' hhk-deceased';
                }
                
                $table->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $rName["Name"], array('href'=>$page.'?id='.$rName['Id'], 'class'=>$deceasedClass, 'title'=>'Click to Edit this Member')), array('class'=>'hhk-rel-td'))
                    .HTMLTable::makeTd($this->trash, array('name'=>$rName['Id'], 'class'=>'hhk-rel-td hhk-deletelink', 'title'=>'Delete ' . $this->relCode->getTitle() . ' Link to ' . $rName["Name"])));
            }
        }
        
        $table->addBody($this->createNewEntry());
        
        return HTMLContainer::generateMarkup('div', $table->generateMarkup(), array('id'=>'acm'.$this->relCode->getCode(), 'name'=>$this->relCode->getCode(), 'class'=>'hhk-relations'));
    }
    
    protected abstract function createNewEntry();
    
}
?>