<?php

namespace HHK\GlStmt;

use HHK\HTMLControls\{HTMLTable};


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
		
		$totCredit = 0;
		$totDebit = 0;
		
		// Item Payments
		$itemAmounts = $this->getItemDistribution($tbl);
		
		// Payment Methods only
		$tbl->addBodyTr(HTMLTable::makeTd(''));
		$tbl->addBodyTr(HTMLTable::makeTh('Payment Distribution', array('colspan'=>'2')));
// 		$tbl->addBodyTr(
// 				HTMLTable::makeTh('Type')
// 				//.HTMLTable::makeTh('Credit')
// 				.HTMLTable::makeTh('Debit')
// 				);
		
		foreach ($this->getTotals() as $k => $t) {
			
			if (isset($this->pmCodes[$k])) {
				
				$totCredit += $t['Credit'];
				$totDebit += $t['Debit'];
				
				$tbl->addBodyTr(
						HTMLTable::makeTd($k, array('class'=>'tdlabel'))
					//	. HTMLTable::makeTd(($t['Credit'] == 0 ? '' : number_format($t['Credit'], 2)), array('style'=>'text-align:right;'))
						. HTMLTable::makeTd(($t['Debit'] == 0 ? '' : number_format($t['Debit'], 2)), array('style'=>'text-align:right;'))
						);
			}
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payment Totals', array('class'=>'tdlabel'))
				//. HTMLTable::makeTd(($totCredit != 0 ? number_format($totCredit, 2) : ''), array('style'=>'text-align:right;', 'class'=>'hhk-tdTotals'))
				. HTMLTable::makeTd(number_format($totDebit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-itempmt'))
				);
		
		// Final total line (should balance)
// 		$tbl->addBodyTr(
// 				HTMLTable::makeTd('Totals', array('class'=>'tdlabel'))
// 				. HTMLTable::makeTd(number_format($totCredit + $itemAmounts['credit'], 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
// 				. HTMLTable::makeTd(number_format($totDebit + $itemAmounts['debit'], 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
// 				);
		
		return $tbl->generateMarkup($tableAttrs);
	}
	
	public function getTotals() {
		return $this->totals;
	}
	
	public function getItemDistribution(&$tbl) {
		
		$tbl->addBodyTr(HTMLTable::makeTh('Item Distribution', array('colspan'=>'3')));
// 		$tbl->addBodyTr(
// 				HTMLTable::makeTh('Item')
// 				.HTMLTable::makeTh('Credit')
// 			//	.HTMLTable::makeTh('Debit')
// 				);
		
		$itemCredit = 0;
		$itemDebit = 0;
		
		$lodgCredit = 0;
		$lodgDebit = 0;

		//lodging
		if (isset($this->totals[$this->itemCodes[1]])) {
			
			$this->makeItemLine(1, $tbl, $itemCredit, $itemDebit);
			$lodgCredit = $this->totals[$this->itemCodes[1]]['Credit'];
			$lodgDebit = $this->totals[$this->itemCodes[1]]['Debit'];
			
		} else {
			
			$tbl->addBodyTr(
					HTMLTable::makeTd($this->itemCodes[1], array('class'=>'tdlabel'))
					. HTMLTable::makeTd('', array('style'=>'text-align:right;'))
				//	. HTMLTable::makeTd('', array('style'=>'text-align:right;'))
					);
		}
		
		//lodging Reversal
		if (isset($this->totals[$this->itemCodes[7]])) {
			
			$this->makeItemLine(7, $tbl, $itemCredit, $itemDebit);
			$lodgCredit += $this->totals[$this->itemCodes[7]]['Credit'];
			$lodgDebit += $this->totals[$this->itemCodes[7]]['Debit'];
			
		} else {
			
			$tbl->addBodyTr(
					HTMLTable::makeTd($this->itemCodes[7], array('class'=>'tdlabel'))
					. HTMLTable::makeTd('', array('style'=>'text-align:right;'))
			//		. HTMLTable::makeTd('', array('style'=>'text-align:right;'))
					);
		}

		// Insert sub total for lodging
		$tbl->addBodyTr(
				HTMLTable::makeTd('Lodging Total', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($lodgCredit,2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-matchlgt'))
			//	. HTMLTable::makeTd(($lodgDebit > 0 ? number_format($lodgDebit, 2) : ''), array('style'=>'text-align:right;', 'class'=>'hhk-tdTotals'))
				);
		
		//Waive
		if (isset($this->totals[$this->itemCodes[11]])) {
			$this->makeItemLine(11, $tbl, $itemCredit, $itemDebit);
		}
		//Discount
		if (isset($this->totals[$this->itemCodes[6]])) {
			$this->makeItemLine(6, $tbl, $itemCredit, $itemDebit);
		}
		
		//Deposit
		if (isset($this->totals[$this->itemCodes[3]])) {
			$this->makeItemLine(3, $tbl, $itemCredit, $itemDebit);
		}
		//Deposit Refund
		if (isset($this->totals[$this->itemCodes[4]])) {
			$this->makeItemLine(4, $tbl, $itemCredit, $itemDebit);
		}
		
		//Cleaning fee
		if (isset($this->totals[$this->itemCodes[2]])) {
			$this->makeItemLine(2, $tbl, $itemCredit, $itemDebit);
		}
		//Additional charge
		if (isset($this->totals[$this->itemCodes[9]])) {
			$this->makeItemLine(9, $tbl, $itemCredit, $itemDebit);
		}
		
		//NOA
		if (isset($this->totals[$this->itemCodes[10]])) {
			$this->makeItemLine(10, $tbl, $itemCredit, $itemDebit);
		}
		
		//Donation
		if (isset($this->totals[$this->itemCodes[8]])) {
			$this->makeItemLine(8, $tbl, $itemCredit, $itemDebit);
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Item Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($itemCredit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-itempmt'))
//				. HTMLTable::makeTd(($itemDebit != 0 ?number_format($itemDebit, 2) : ''), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		
		return array('credit'=>$itemCredit, 'debit'=>$itemDebit);
	}
	
	protected function makeItemLine($itemCode, &$tbl, &$itemCredit, &$itemDebit) {

		$itemCredit += $this->totals[$this->itemCodes[$itemCode]]['Credit'];
		$itemDebit += $this->totals[$this->itemCodes[$itemCode]]['Debit'];
		
		$tbl->addBodyTr(
				HTMLTable::makeTd($this->itemCodes[$itemCode], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(($this->totals[$this->itemCodes[$itemCode]]['Credit'] == 0 ? '' : number_format($this->totals[$this->itemCodes[$itemCode]]['Credit'], 2)), array('style'=>'text-align:right;'))
		//		. HTMLTable::makeTd(($this->totals[$this->itemCodes[$itemCode]]['Debit'] == 0 ? '' : number_format($this->totals[$this->itemCodes[$itemCode]]['Debit'], 2)), array('style'=>'text-align:right;'))
				);
		
	}
	
	public function getTotalCredit() {
		return round($this->totalCredit, 2);
	}
	
	public function getTotalDebit() {
		return round($this->totalDebit,2);
	}
}

