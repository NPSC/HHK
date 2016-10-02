<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ItemRS
 *
 * @author Eric
 */
class ItemRS extends TableRS {

    public $idItem;  // INTEGER NOT NULL,
    public $Internal_Number;  // VARCHAR(50) NOT NULL default '',
    public $Entity_Id;  // INTEGER NOT NULL DEFAULT 0,
    public $Percentage;  // DECIMAL(22,10) NOT NULL DEFAULT '0.00',
    public $Deleted;  // SMALLINT default 0 NOT NULL DEFAULT '0',
    public $Has_Decimals;  // SMALLINT default 0 NOT NULL DEFAULT '0',
    public $Gl_Code;  // VARCHAR(50) NOT NULL default '',
    public $Description;  // VARCHAR(1000), NOT NULL default ''.

    function __construct($TableName = 'item') {
        $this->idItem = new DB_Field('idItem', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Internal_Number = new DB_Field('Internal_Number', '', new DbStrSanitizer(50), TRUE, TRUE);
        $this->Entity_Id = new DB_Field('Entity_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Percentage = new DB_Field('Percentage', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Deleted = new DB_Field('Deleted', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Has_Decimals = new DB_Field('Has_Decimals', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Gl_Code = new DB_Field('Gl_Code', '', new DbStrSanitizer(50), TRUE, TRUE);
        $this->Description = new DB_Field('Description', '', new DbStrSanitizer(1000), TRUE, TRUE);
        parent::__construct($TableName);
    }

}

class ItemTypeRS extends TableRS {

    public $idItem_Type;  // INTEGER NOT NULL,
    public $Category_Type;  // INTEGER NOT NULL,
    public $Description;  // VARCHAR(100),
    public $Order_Line_Type_Id;  // INTEGER NOT NULL,

    function __construct($TableName = 'item_type') {
        $this->idItem_Type = new DB_Field('idItem_Type', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Category_Type = new DB_Field('Category_Type', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type_Description = new DB_Field('Type_Description', '', new DbStrSanitizer(100), TRUE, TRUE);
        $this->Order_Line_Type_Id = new DB_Field('Order_Line_Type_Id', 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}


class ItemTypeMapRS extends TableRS {

    public $Item_Id;  // INTEGER,
    public $Type_Id;  // INTEGER);

    function __construct($TableName = 'item_type_map') {
        $this->Item_Id = new DB_Field('Item_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type_Id = new DB_Field('Type_Id', 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}

class ItemPriceRS extends TableRS {

    public $idItem_price;  // INTEGER NOT NULL,
    public $Item_Id;  // INTEGER NOT NULL,
    public $Price;  // decimal
    public $Currency_Id;  // INTEGER NOT NULL,
    public $ModelCode;  // VARCHAR(5) NOT NULL DEFAULT ''

    function __construct($TableName = 'item_price') {
        $this->idItem_price = new DB_Field('idItem_price', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Item_Id = new DB_Field('Item_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Price = new DB_Field('Price', '', new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Currency_Id = new DB_Field('Currency_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->ModelCode = new DB_Field('ModelCode', '', new DbStrSanitizer(4), TRUE, TRUE);

        parent::__construct($TableName);
    }

}

