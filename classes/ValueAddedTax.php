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
 * Description of ValueAddedTax
 *
 * @author Eric
 */
class ValueAddedTax {

    protected $taxedItemList;
    protected $taxedItems;

    public function __construct(\PDO $dbh) {
        $this->taxedItemList = $this->loadTaxedItemList($dbh);

        foreach ($this->getTaxedItemList() as $i) {
            $this->taxedItems[] = new TaxedItem($i['idItem'], $i['taxIdItem'], $i['Max_Days'], $i['Percentage'], $i['Description'], $i['Gl_Code']);
        }
    }

    protected function loadTaxedItemList(\PDO $dbh) {

        // Taxed items
        $tistmt = $dbh->query("select ii.idItem, ti.Percentage, ti.Description, ti.Internal_Number as `Max_Days`, ti.idItem as `taxIdItem`, ti.Gl_Code from item_item ii join item i on ii.idItem = i.idItem join item ti on ii.Item_Id = ti.idItem");
        return $tistmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    /** Get sum of tax item percents connected to each taxable item.
     *
     * @param type $numDays
     * @return array
     */
    public function getTaxedItems($numDays) {

        // Any taxes
        $taxedItems = array();

        foreach (getTaxedItemList() as $t) {

            $maxDays = intval($t['Max_Days'], 10);

            if ($maxDays == 0 || $numDays <= $maxDays) {

                if (isset($taxedItems[$t['idItem']])) {
                    $taxedItems[$t['idItem']] += $t['Percentage'];
                } else {
                    $taxedItems[$t['idItem']] = $t['Percentage'];
                }
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

        foreach ($this->getTaxedItemList() as $i) {

            if ($i['idItem'] == $taxedItemId && $i['Max_Days'] > 0 && $numDays > $i['Max_Days']) {
                $timedout[] = $i['taxIdItem'];
            }
        }

        return $timedout;
    }

    /**
     * Get the taxed items list who's taxing item.maxDays are optionally filtered by $numDays
     *
     * @param int $numDays
     * @return array
     */
    public function getTaxedItemList($numDays = 0) {

        $taxedItems = array();

        foreach ($this->taxedItemList as $i) {

        if ($i['Max_Days'] == 0 || $numDays <= $i['Max_Days']) {
                $taxedItems[] = $i['taxIdItem'];
            }
        }

        return $taxedItems;
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
        $this->maxDays = $maxDays;
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
