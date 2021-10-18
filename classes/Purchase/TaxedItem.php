<?php

namespace HHK\Purchase;

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
 
class TaxedItem {
    
    protected $idTaxedItem;
    protected $idTaxingItem;
    protected $maxDays;
    protected $percentTax;
    protected $decimalTax;
    protected $taxingItemDesc;
    protected $taxingItemGlCode;
    protected $firstOrderId;
    protected $lastOrderId;
    
    /**
     *
     * @param int $idTaxedItem  Item Id of the item being taxed.
     * @param int $idTaxingItem  Item Id of the taxing item.
     * @param int $maxDays  The maximum number of days to apply the tax.
     * @param float $percentTax  The percent tax - divide by 100 to get the decimal tax mulitplier.
     * @param string $taxingItemDesc
     * @param string $taxingItemGlCode
     * @param int $firstOrderId
     * @param int $lastOrderId
     */
    public function __construct($idTaxedItem, $idTaxingItem, $maxDays, $percentTax, $taxingItemDesc, $taxingItemGlCode, $firstOrderId, $lastOrderId) {
        $this->idTaxedItem = $idTaxedItem;
        $this->idTaxingItem = $idTaxingItem;
        $this->maxDays = intval($maxDays, 10);
        $this->percentTax = $percentTax;
        $this->taxingItemDesc = $taxingItemDesc;
        $this->taxingItemGlCode = $taxingItemGlCode;
        $this->firstOrderId = $firstOrderId;
        $this->lastOrderId = $lastOrderId;
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
    
    public function getTextPercentTax() {
        $strTax = (string)$this->getPercentTax();
        
        return $this->suppressTrailingZeros($strTax);
    }
    
    public function getDecimalTax() {
        return $this->getPercentTax() / 100;
    }
    
    public function getTaxAmount($amt) {
        return round($amt * $this->getDecimalTax(), 2);
    }
    
    public function getTaxingItemDesc() {
        return $this->taxingItemDesc;
    }
    
    public function getTaxingItemGlCode() {
        return $this->taxingItemGlCode;
    }
    
    public function getFirstOrderId() {
        return $this->firstOrderId;
    }
    
    public function getLastOrderId() {
        return $this->lastOrderId;
    }
    
    
    public static function suppressTrailingZeros($strTax) {
        
        $taxPrettyStr = '';
        
        $taxArray = str_split($strTax, 1);
        $cntr = count($taxArray);
        
        if ($cntr <= 1) {
            $taxPrettyStr = $strTax;
        } else {
            
            for ($n = ($cntr - 1); $n>=1; $n--) {
                if ($taxArray[$n] == '0' || $taxArray[$n] == '.') {
                    unset($taxArray[$n]);
                } else {
                    break;
                }
            }
            
            $taxPrettyStr = implode('', $taxArray);
        }
        
        return $taxPrettyStr . '%';
    }
    
}
?>