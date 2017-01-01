<?php
/**
 * Item.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Item
 *
 * @author Eric
 */
class Item {

    protected $idItem;
    protected $typeId;
    protected $unitPrice;
    protected $itemRs;

    public function __construct(\PDO $dbh, $idItem, $unitPrice = 0) {

        $this->idItem = $idItem;

        $this->loadRecord($dbh);

        $this->unitPrice = $unitPrice;

    }

    protected function loadRecord(\PDO $dbh) {

        $stmt = $dbh->query("select * from item where idItem = " . $this->idItem);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($items) == 0) {
            throw new Hk_Exception_Runtime('Item not found. Item Id = '. $this->idItem);
        }

        $this->itemRs = new ItemRS();
        EditRS::loadRow($items[0], $this->itemRs);


        $this->typeId = 1;

        if (isset($items[0]['Type_Id'])) {
            $this->typeId = $items[0]['Type_Id'];
        }

    }


    public static function loadItems(\PDO $dbh) {

        $stmt = $dbh->query("select idItem, Description from item where Deleted = 0");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }



    public function getIdItem() {
        return $this->idItem;
    }

    public function getTypeId() {
        return $this->typeId;
    }

    public function getUnitPrice() {
        return $this->unitPrice;
    }

    public function getDescription() {
        return $this->itemRs->Description->getStoredVal();
    }

}

