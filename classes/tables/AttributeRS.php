<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Attribute
 *
 * @author Eric
 */
class AttributeRS extends TableRS {

    public $idAttribute;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Type;  // varchar(4) NOT NULL,
    public $Title;  // varchar(145) NOT NULL DEFAULT '',
    public $Category;  // varchar(45) NOT NULL DEFAULT '',
    public $Status;  // varchar(4) NOT NULL DEFAULT '',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,

    function __construct($TableName = 'attribute') {

        $this->idAttribute = new DB_Field('idAttribute', 0, new DbIntSanitizer());
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(145), TRUE, TRUE);
        $this->Category = new DB_Field('Category', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(4), TRUE, TRUE);

        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field('Last_Updated', null, new DbDateSanitizer('Y-m-d H:i:s'), FALSE);
        //$this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}


class AttributeEntityRS extends TableRS {


    public $idEntity;  // int(11) NOT NULL,
    public $idAttribute;
    public $Type;  // varchar(4) NOT NULL,


    function __construct($TableName = "attribute_entity") {

        $this->idEntity = new DB_Field("idEntity", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idAttribute = new DB_Field("idAttribute", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        parent::__construct($TableName);
    }
}


class ConstraintRS extends TableRS {

    public $idConstraint;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Type;  // varchar(4) NOT NULL,
    public $Title;  // varchar(145) NOT NULL DEFAULT '',
    public $Category;  // varchar(45) NOT NULL DEFAULT '',
    public $Status;  // varchar(4) NOT NULL DEFAULT '',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,

    function __construct($TableName = 'constraints') {

        $this->idConstraint = new DB_Field('idConstraint', 0, new DbIntSanitizer());
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(145), TRUE, TRUE);
        $this->Category = new DB_Field('Category', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(4), TRUE, TRUE);

        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field('Last_Updated', null, new DbDateSanitizer('Y-m-d H:i:s'), FALSE);
        //$this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class ConstraintAttributeRS extends TableRS {

    public $idConstraint;  // int(11) NOT NULL,
    public $idAttribute;
    public $Type;  // varchar(4) NOT NULL,
    public $Operation;  // varchar(4) NOT NULL,


    function __construct($TableName = "constraint_attribute") {

        $this->idConstraint = new DB_Field("idConstraint", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idAttribute = new DB_Field("idAttribute", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Operation = new DB_Field('Operation', '', new DbStrSanitizer(4), TRUE, TRUE);
        parent::__construct($TableName);
    }
}


class ConstraintEntityRS extends TableRS {


    public $idEntity;  // int(11) NOT NULL,
    public $idConstraint;
    public $Type;  // varchar(4) NOT NULL,


    function __construct($TableName = "constraint_entity") {

        $this->idEntity = new DB_Field("idEntity", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idConstraint = new DB_Field("idConstraint", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        parent::__construct($TableName);
    }
}

