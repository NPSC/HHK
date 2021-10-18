<?php
namespace HHK\GlStmt;

use HHK\HTMLControls\{HTMLTable};

class BaStmtTotals extends GlStmtTotals {
	
	public function __construct($pmCodes = array()) {
		
		$this->totalCredit = 0;
		$this->totalDebit = 0;
		$this->totals = array();
	}
	public function createMarkup($tableAttrs) {
		
		$tbl = new HTMLTable();
		$tbl->addHeaderTr(
				HTMLTable::makeTh('3rd Parties')
				.HTMLTable::makeTh('Paid')
				.HTMLTable::makeTh('Pending')
				
				);
		
		foreach ($this->getTotals() as $k => $t) {
			
			$tbl->addBodyTr(
					HTMLTable::makeTd($k, array('class'=>'tdlabel'))
					. HTMLTable::makeTd(($t['Debit'] == 0 ? '' : number_format($t['Debit'], 2)), array('style'=>'text-align:right;'))
					. HTMLTable::makeTd(($t['Credit'] == 0 ? '' : number_format($t['Credit'], 2)), array('style'=>'text-align:right;'))
					);
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($this->getTotalDebit(), 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals '))
				. HTMLTable::makeTd(number_format($this->getTotalCredit(), 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals '))
				);
		
		
		return $tbl->generateMarkup($tableAttrs);
	}
	
}