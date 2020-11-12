<?php

namespace HHK\GlStmt;

use HHK\HTMLControls\{HTMLTable};


class GlStmtTotals {
	
	protected $totalDebit;
	protected $totalCredit;
	protected $totals;
	private $pmCodes;
	
	
	public function __construct($pmCodes = array()) {
		
		$this->totalCredit = 0;
		$this->totalDebit = 0;
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
		$tbl->addHeaderTr(HTMLTable::makeTh('Payment Distribution', array('colspan'=>'3')));
		$tbl->addBodyTr(
				HTMLTable::makeTh('Type')
				.HTMLTable::makeTh('Credit')
				.HTMLTable::makeTh('Debit')
				);
		
		$totCredit = 0;
		$totDebit = 0;
		
		// Payment Methods only
		foreach ($this->getTotals() as $k => $t) {
			
			if (isset($this->pmCodes[$k])) {
				
				$totCredit += $t['Credit'];
				$totDebit += $t['Debit'];
				
				$tbl->addBodyTr(
						HTMLTable::makeTd($k, array('class'=>'tdlabel'))
						. HTMLTable::makeTd(($t['Credit'] == 0 ? '' : number_format($t['Credit'], 2)), array('style'=>'text-align:right;'))
						. HTMLTable::makeTd(($t['Debit'] == 0 ? '' : number_format($t['Debit'], 2)), array('style'=>'text-align:right;'))
						);
			}
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payment Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($totCredit, 2), array('style'=>'text-align:right;', 'class'=>'hhk-tdTotals'))
				. HTMLTable::makeTd(number_format($totDebit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		
		// Items
		$tbl->addBodyTr(HTMLTable::makeTd(''));
		$tbl->addBodyTr(HTMLTable::makeTh('Item Distribution', array('colspan'=>'3')));
		$tbl->addBodyTr(
				HTMLTable::makeTh('Item')
				.HTMLTable::makeTh('Credit')
				.HTMLTable::makeTh('Debit')
				);
		
		$itemCredit = 0;
		$itemDebit = 0;
		
		foreach ($this->getTotals() as $k => $t) {
			
			if (isset($this->pmCodes[$k]) === FALSE) {
				
				$itemCredit += $t['Credit'];
				$itemDebit += $t['Debit'];
				
				$tbl->addBodyTr(
						HTMLTable::makeTd($k, array('class'=>'tdlabel'))
						. HTMLTable::makeTd(($t['Credit'] == 0 ? '' : number_format($t['Credit'], 2)), array('style'=>'text-align:right;'))
						. HTMLTable::makeTd(($t['Debit'] == 0 ? '' : number_format($t['Debit'], 2)), array('style'=>'text-align:right;'))
						);
			}
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Item Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($itemCredit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				. HTMLTable::makeTd(number_format($itemDebit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payment Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(($totCredit == 0 ? '' : number_format($totCredit, 2)), array('style'=>'text-align:right;'))
				. HTMLTable::makeTd(($totDebit == 0 ? '' : number_format($totDebit, 2)), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($totCredit + $itemCredit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				. HTMLTable::makeTd(number_format($totDebit + $itemDebit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		
		return $tbl->generateMarkup($tableAttrs);
	}
	
	public function getTotals() {
		return $this->totals;
	}
	
	public function getTotalCredit() {
		return $this->totalCredit;
	}
	
	public function getTotalDebit() {
		return $this->totalDebit;
	}
}

