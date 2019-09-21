<?php

/*
 * The MIT License
 *
 * Copyright 2019 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Loads all items that are taxed along with details about each tax.
 *
 * @author Eric
 */
class ValueAddedTax {

    protected $allTaxedItems;

    public function __construct(\PDO $dbh) {

        foreach ($this->loadTaxedItemList($dbh) as $i) {
            $this->allTaxedItems[] = new TaxedItem($i['idItem'], $i['taxIdItem'], $i['Max_Days'], $i['Percentage'], $i['Description'], $i['Gl_Code']);
        }
    }

    protected function loadTaxedItemList(\PDO $dbh) {

        // Taxed items
        $tistmt = $dbh->query("select ii.idItem, ti.Percentage, ti.Description, ti.Internal_Number as `Max_Days`, ti.idItem as `taxIdItem`, ti.Gl_Code "
                . " from item_item ii join item i on ii.idItem = i.idItem join item ti on ii.Item_Id = ti.idItem");
        return $tistmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    /** Get sum of tax items connected to each taxable item.
     *
     * @param int $numDays
     * @return array of each taxed item containing the sum (float) of all connected taxes filtered by days.
     */
    public function getTaxedItemSums($numDays) {

        // Any taxes
        $taxedItems = array();

        foreach ($this->getCurrentTaxedItems($numDays) as $t) {

            if (isset($taxedItems[$t->getIdTaxedItem()])) {
                $taxedItems[$t->getIdTaxedItem()] += $t->getDecimalTax();
            } else {
                $taxedItems[$t->getIdTaxedItem()] = $t->getDecimalTax();
            }
        }

        return $taxedItems;
    }

    /** return any 'timed-out' tax items connected to the given item
     *  compared against $numDays.
     *
     * @param int $taxedItemId  the taxable item
     * @param int $numDays  the number of days under the tax
     * @return array tax item id's that have timed out.
     */
    public function getTimedoutTaxItems($taxedItemId, $numDays) {

        $timedout = array();

        if ($numDays <= 0) {
            throw new Hk_Exception_Runtime('The number of days to test must be > 0');
        }

        foreach ($this->getAllTaxedItems() as $t) {

            if ($t->getIdTaxedItem() == $taxedItemId && $t->getMaxDays() > 0 && $numDays > $t->getMaxDays()) {
                $timedout[] = $t;
            }
        }

        return $timedout;
    }

    public function getCurrentTaxedItems($numDays = 0) {

        $current = array();

        foreach ($this->getAllTaxedItems() as $t) {

            if ($t->getMaxDays() == 0 || $numDays <= $t->getMaxDays()) {
                $current[] = $t;
            }
        }

        return $current;
    }

    /**
     *
     * @return array of all the TaxedItems
     */
    public function getAllTaxedItems() {
        return $this->allTaxedItems;
    }

}


class TaxedItem {

    protected $idTaxedItem;
    protected $idTaxingItem;
    protected $maxDays;
    protected $percentTax;
    protected $decimalTax;
    protected $taxingItemDesc;
    protected $taxingItemGlCode;

    public function __construct($idTaxedItem, $idTaxingItem, $maxDays, $percentTax, $taxingItemDesc, $taxingItemGlCode) {
        $this->idTaxedItem = $idTaxedItem;
        $this->idTaxingItem = $idTaxingItem;
        $this->maxDays = intval($maxDays, 10);
        $this->percentTax = $percentTax;
        $this->taxingItemDesc = $taxingItemDesc;
        $this->taxingItemGlCode = $taxingItemGlCode;
    }

    public function getIdTaxedItem() {
        return $this->idTaxedItem;
    }

    public function getIdTaxingItem() {
        return $this->idTaxingItem;
    }

    public function getMaxDays() {
        return $this->maxDays;
    }

    public function getPercentTax() {
        return $this->percentTax;
    }

    public function getDecimalTax() {
        return $this->getPercentTax() / 100;
    }

    public function getTaxingItemDesc() {
        return $this->taxingItemDesc;
    }

    public function getTaxingItemGlCode() {
        return $this->taxingItemGlCode;
    }


}
