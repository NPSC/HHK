<?php

namespace HHK\GlStmt;

use HHK\HTMLControls\{HTMLTable};
use HHK\SysConst\ItemId;

class GlStmtTotals {
	
	protected $totalDebit;
	protected $totalCredit;
	protected $totals;
	private $pmCodes;
	private $itemCodes;
	
	
	public function __construct($pmCodes = array(), $itemCodes = array()) {
		
		$this->totalCredit = 0;
		$this->totalDebit = 0;
		$this->itemCodes = $itemCodes;
		$this->pmCodes = array_flip($pmCodes);
		
		foreach ($pmCodes as $p) {
			
			$this->totals[$p]['Credit'] = 0;
			$this->totals[$p]['Debit'] = 0;
		}
	}
	
	public function makeLine($glCode, $debitAmount, $creditAmount, $purchaseDate, $invoiceNumber) {
		
		if (isset($this->totals[$glCode]) === FALSE) {
			$this->totals[$glCode]['Credit'] = $creditAmount;
			$this->totals[$glCode]['Debit'] = $debitAmount;
		} else {
			
			$this->totals[$glCode]['Credit'] += $creditAmount;
			$this->totals[$glCode]['Debit'] += $debitAmount;
		}
		
		$this->totalCredit += $creditAmount;
		$this->totalDebit += $debitAmount;
		
		return array('glcode'=>$glCode, 'debit'=>$debitAmount, 'credit'=>$creditAmount, 'date'=>$purchaseDate->format('Y-m-d'), 'InvoiceNumber' => $invoiceNumber);
	}
	
	public function createMarkup($tableAttrs) {
		
		$tbl = new HTMLTable();
		
		// Item Payments
		$this->getItemDistribution($tbl);
		
		// Spacer
		$tbl->addBodyTr(HTMLTable::makeTd(''));

		$this->getPaymentDistribution($tbl);
		
		return $tbl->generateMarkup($tableAttrs);
	}
	
	public function getTotals() {
		return $this->totals;
	}
	
	protected function getItemDistribution(&$tbl) {
		
		$tbl->addBodyTr(HTMLTable::makeTh('Item Distribution', array('colspan'=>'3')));
		$itemCredit = 0;
		$lodgCredit = 0;

		//lodging
		if (isset($this->totals[$this->itemCodes[ItemId::Lodging]])) {
			
			// Reduce by waived amount.
			if (isset($this->totals[$this->itemCodes[ItemId::Waive]])) {
				$this->totals[$this->itemCodes[ItemId::Lodging]]['Credit'] += $this->totals[$this->itemCodes[ItemId::Waive]]['Credit'];
			}
			
			$this->makeItemLine(ItemId::Lodging, $tbl, $itemCredit);
			$lodgCredit = $this->totals[$this->itemCodes[ItemId::Lodging]]['Credit'];
			
		} else {
			
			$tbl->addBodyTr(
					HTMLTable::makeTd($this->itemCodes[ItemId::Lodging], array('class'=>'tdlabel'))
					. HTMLTable::makeTd('', array('style'=>'text-align:right;'))
					);
		}
		
		//lodging Reversal
		if (isset($this->totals[$this->itemCodes[ItemId::LodgingReversal]])) {
			
			$this->makeItemLine(ItemId::LodgingReversal, $tbl, $itemCredit);
			$lodgCredit += $this->totals[$this->itemCodes[ItemId::LodgingReversal]]['Credit'];
			
		} else {
			
			$tbl->addBodyTr(
					HTMLTable::makeTd($this->itemCodes[ItemId::LodgingReversal], array('class'=>'tdlabel'))
					. HTMLTable::makeTd('', array('style'=>'text-align:right;'))
					);
		}

		// Insert sub total for lodging
		$tbl->addBodyTr(
				HTMLTable::makeTd('Lodging Total', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($lodgCredit,2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-matchlgt'))
				);
		
		//Waive
// 		if (isset($this->totals[$this->itemCodes[11]])) {
// 			$this->makeItemLine(11, $tbl, $itemCredit);
// 		}
		
		//Discount
// 		if (isset($this->totals[$this->itemCodes[6]])) {
// 			$this->makeItemLine(6, $tbl, $itemCredit);
// 		}
		
		//Deposit
		if (isset($this->totals[$this->itemCodes[3]])) {
			$this->makeItemLine(3, $tbl, $itemCredit);
		}
		//Deposit Refund
		if (isset($this->totals[$this->itemCodes[4]])) {
			$this->makeItemLine(4, $tbl, $itemCredit);
		}
		
		//Cleaning fee
		if (isset($this->totals[$this->itemCodes[2]])) {
			$this->makeItemLine(2, $tbl, $itemCredit);
		}
		//Additional charge
		if (isset($this->totals[$this->itemCodes[9]])) {
			$this->makeItemLine(9, $tbl, $itemCredit);
		}
		
		//MOA
		if (isset($this->totals[$this->itemCodes[10]])) {
			$this->makeItemLine(10, $tbl, $itemCredit);
		}
		
		//Donation
		if (isset($this->totals[$this->itemCodes[8]])) {
			$this->makeItemLine(8, $tbl, $itemCredit);
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Item Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($itemCredit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-itempmt'))
				);
		
		return $itemCredit;
	}
	
	protected function getPaymentDistribution(&$tbl) {
		
		$totDebit = 0;
		
		$tbl->addBodyTr(HTMLTable::makeTh('Payment Distribution', array('colspan'=>'2')));
		
		foreach ($this->getTotals() as $k => $t) {
			
			if (isset($this->pmCodes[$k])) {
				
				$totDebit += $t['Debit'];
				
				$tbl->addBodyTr(
						HTMLTable::makeTd($k, array('class'=>'tdlabel'))
						. HTMLTable::makeTd(($t['Debit'] == 0 ? '' : number_format($t['Debit'], 2)), array('style'=>'text-align:right;'))
						);
			}
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payment Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($totDebit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-itempmt'))
				);
	}
	
	protected function makeItemLine($itemCode, &$tbl, &$itemCredit) {

		$itemCredit += $this->totals[$this->itemCodes[$itemCode]]['Credit'];
		
		$tbl->addBodyTr(
				HTMLTable::makeTd($this->itemCodes[$itemCode], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(($this->totals[$this->itemCodes[$itemCode]]['Credit'] == 0 ? '' : number_format($this->totals[$this->itemCodes[$itemCode]]['Credit'], 2)), array('style'=>'text-align:right;'))
				);
		
	}
	
	public function getTotalCredit() {
		return round($this->totalCredit, 2);
	}
	
	public function getTotalDebit() {
		return round($this->totalDebit,2);
	}
}

