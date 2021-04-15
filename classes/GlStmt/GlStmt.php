<?php
namespace HHK\GlStmt;

use HHK\SysConst\{ItemId, PaymentStatusCode};

use HHK\SysConst\ResourceStatus;
use HHK\sec\Session;
use HHK\HTMLControls\{HTMLTable};
use HHK\House\Resource\ResourceTypes;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;

class GlStmt {

	protected $startDate;
	protected $endDate;
	protected $startDay;
	protected $records;
	public $lines;
	protected $payAmounts = [];
	protected $itemPayments = [];
	protected $orderIds = [];

	protected $errors;

	protected $glLineMapper;
	protected $baLineMapper;

	/**
	 * New Gl Statement object that assumes (Year, $month, $day) is the start date of this statement.
	 * This statement loads data for every day of the month indicated.
	 *
	 * @param \PDO $dbh
	 * @param mixed $year
	 * @param mixed $month
	 * @param string $day
	 */
	public function __construct(\PDO $dbh, $year, $month, $day = '01') {
		
		$this->startDay = $day;
		$this->startDate = new \DateTimeImmutable(intval($year) . '-' . intval($month) . '-' . $this->getStartDay());
		
		// End date is the beginning of the next month.
		$this->endDate = $this->startDate->add(new \DateInterval('P1M'));
		
		$this->errors = array();
		$this->stopAtInvoice = '';
		
		// Get payment gl codes
		$pmCodes = array();
		$stmt = $dbh->query("Select Gl_Code from payment_method where idPayment_method in (1, 2, 3, 5)");
		while ($p = $stmt->fetch(\PDO::FETCH_NUM)) {
			$pmCodes[] = $p[0];
		}
		
		// Get item glcodes
		$itemCodes = [];
		$stmti = $dbh->query("Select idItem, Gl_Code from item");
		while ($i = $stmti->fetch(\PDO::FETCH_NUM)) {
			$itemCodes[$i[0]] = $i[1];
			$this->itemPayments[$i[0]] = 0;
		}
		
		$this->glLineMapper = new GlStmtTotals($pmCodes, $itemCodes);
		$this->baLineMapper = new BaStmtTotals();
	}
	
	/**
	 *
	 */
	public function mapRecords(\PDO $dbh) {
		
		$this->lines = [];
		$idInvoice = 0;
		$serialId = '0';
		
		$query = "call gl_report('" . $this->startDate->format('Y-m-d') . "','" . $this->endDate->format('Y-m-d') . "')";
		$stmt = $dbh->query($query);
		
		while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			
			$serial = $r['idInvoice'] . 'p' . $r['idPayment'];
			
			if ($serialId != $serial) {
				// Payment Change

				if ($r['idInvoice'] != $idInvoice && $idInvoice != 0) {
					//Invoice Change
					
					$idInvoice = $r['idInvoice'];
					$this->orderIds[] = $r['idInvoice'];
				}
				
				// Record new payment lines
				$this->recordPayment($r);
			
			}
			
			$serialId = $serial;

			if ($r['pStatus'] == PaymentStatusCode::Reverse || $r['pStatus'] == PaymentStatusCode::VoidSale || $r['pStatus'] == PaymentStatusCode::Declined) {
				continue;
			}
			
			// Multiple invoice lines for one payment...
			if (isset($this->payAmounts[$r['idPayment']]) === FALSE) {
				$this->payAmounts[$r['idPayment']] = $r['pAmount'];
			}
			
			$this->recordInvLine($r);
			
		}
		
		$stmt->nextRowset();
		
		
		
		if ($this->glLineMapper->getTotalCredit() != $this->glLineMapper->getTotalDebit()) {
			$this->recordError('Credits not equal debits: ' .$this->glLineMapper->getTotalCredit() .'  '.$this->glLineMapper->getTotalDebit());
		}
		
		return $this;
	}
	
	protected function recordPayment($r) {
				
			
		if ($r['pStatus'] == PaymentStatusCode::Reverse || $r['pStatus'] == PaymentStatusCode::VoidSale || $r['pStatus'] == PaymentStatusCode::Declined) {
			return;
		}
		
		// Payment dates
		if ($r['pTimestamp'] != '') {
			$paymentDate = new \DateTime($r['pTimestamp']);
		} else {
			$this->recordError("Missing Payment Date. Payment Id = ". $r['idPayment']);
			return;
		}
		
		if ($r['pUpdated'] != '') {
			$pUpDate = new \DateTime($r['pUpdated']);
		} else {
			$pUpDate = NULL;
		}
		
			if ($r['pStatus'] == PaymentStatusCode::Retrn) {
				//Return earlier sale
				
				if (is_null($pUpDate)) {
					$this->recordError("Retrn missing its Last Updated. Payment Id = ". $r['idPayment']);
					return;
				}
				
				// Returned during this period?
				if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
					// It is a return in this period.
					
					// 3rd party payments
					if ($r['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($r['ba_Gl_Debit'], (0 - abs($r['pAmount'])), 0, $pUpDate, $r['iNumber']);
					}
					
					$this->lines[] = $this->glLineMapper->makeLine($r['PayMethod_Gl_Code'], (0 - abs($r['pAmount'])), 0, $pUpDate, $r['iNumber']);
					
				}
				
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					// It is still a payment in this period.

					// 3rd party payments
					if ($r['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($r['ba_Gl_Debit'], $r['pAmount'], 0, $paymentDate, $r['iNumber']);
					}
					
					$this->lines[] = $this->glLineMapper->makeLine($r['PayMethod_Gl_Code'], $r['pAmount'], 0, $paymentDate, $r['iNumber']);
					
				}

			} else if (($r['pStatus'] == PaymentStatusCode::Paid || $r['pStatus'] == PaymentStatusCode::VoidReturn)  && $r['Is_Refund'] == 0) {
				// Status = Sale

				// un-returned payments are dated on the update.
				if (is_null($pUpDate) === FALSE) {
					$paymentDate = $pUpDate;
				}

				// Payment is in this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {

					// 3rd party payments
					if ($r['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($r['ba_Gl_Debit'], $r['pAmount'], 0, $paymentDate, $r['iNumber']);
					}

					$this->lines[] = $this->glLineMapper->makeLine($r['PayMethod_Gl_Code'], $r['pAmount'], 0, $paymentDate, $r['iNumber']);

				}

			} else if ($r['pStatus'] == PaymentStatusCode::Paid && $r['Is_Refund'] > 0){
				// Status = refund amount

				// Payment is in this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					// 3rd party payments
					if ($r['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($r['ba_Gl_Debit'], (0 - abs($r['pAmount'])), 0, $paymentDate, $r['iNumber']);
					}

					$this->lines[] = $this->glLineMapper->makeLine($r['PayMethod_Gl_Code'], (0 - abs($r['pAmount'])), 0, $paymentDate, $r['iNumber']);

				}

			} else {
				$this->recordError("Unanticipated Payment Status: ". $r['pStatus'] . '  Payment Id = '.$r['idPayment']);
			}

	}
	
	protected function recordInvLine($r) {
				
		// Payment dates
		if ($r['pTimestamp'] != '') {
			$paymentDate = new \DateTime($r['pTimestamp']);
		} else {
			$paymentDate = NULL;
		}
		
		if ($r['pUpdated'] != '') {
			$pUpDate = new \DateTime($r['pUpdated']);
		} else {
			$pUpDate = NULL;
		}
		
		// Normalize amount
		$ilAmt = round($r['il_Amount'], 2);
		
		// Sale
		if (($r['pStatus'] == PaymentStatusCode::Paid || $r['pStatus'] == PaymentStatusCode::VoidReturn) && $r['Is_Refund'] == 0) {
			
			// un-returned payments are dated on the update.
			if (is_null($pUpDate) === FALSE) {
				$paymentDate = $pUpDate;
			}
			
			$this->payAmounts[$r['idPayment']] -= $ilAmt;
			
			// Payment is in this period?
			if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
				
				$this->lines[] = $this->glLineMapper->makeLine($r['Item_Gl_Code'], 0, $ilAmt, $paymentDate, $r['iNumber']);
				
				$this->itemPayments[$r['il_Item_Id']] += $ilAmt;
								
			}
			
		// Refunds
		} else if ($r['pStatus'] == PaymentStatusCode::Paid && $r['Is_Refund'] == 1) {
			
			// payment is positive in this case.
			$this->payAmounts[$r['idPayment']] += $ilAmt;
			
			// Payment must be within the .
			if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
				
				$this->lines[] = $this->glLineMapper->makeLine($r['Item_Gl_Code'], 0, $ilAmt, $paymentDate, $r['iNumber']);
				$this->itemPayments[$r['il_Item_Id']] += $ilAmt;
			}
			
		//Returns
		} else if ($r['pStatus'] == PaymentStatusCode::Retrn) {
			// The invoice line amount (ilAmt) is positive.
			
			if (is_null($pUpDate)) {
				$this->recordError("Missing Last Updated Date. Payment Id = ". $r['idPayment']);
				return;
			}
			
			$this->payAmounts[$r['idPayment']] -= $ilAmt;
			
			// Returned during this period?
			if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
				// It is a return in this period.
				
				$this->lines[] = $this->glLineMapper->makeLine($r['Item_Gl_Code'], 0, (0 - $ilAmt), $paymentDate, $r['iNumber']);
				
				$this->itemPayments[$r['il_Item_Id']] += (0 - $ilAmt);
								
			}
			
			// Paid during this period?
			if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
				
				$this->lines[] = $this->glLineMapper->makeLine($r['Item_Gl_Code'], 0, $ilAmt, $paymentDate, $r['iNumber']);
				
				$this->itemPayments[$r['il_Item_Id']] += $ilAmt;
			}
		}
	}
	
	
	public function doReport (\PDO $dbh, $monthArray, $tableAttrs) {
		
		$uS = Session::getInstance();

		$priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
		
		$finInterval = new FinancialInterval($this->startDate, $this->endDate);
		
		$stmtCalc = $finInterval->collectData($dbh, $priceModel, $this->getOrderNumbers());
		
		$tbl = new HTMLTable();
		
		$tbl->addHeaderTr(HTMLTable::makeTh('Lodging Payment Distribution', array('colspan'=>'2')));
		$tbl->addBodyTr(
				HTMLTable::makeTd('Back Payments to earlier months', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPaymentToPast(), 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPaymentToNow(), 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Prepayments to future months', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPaymentToFuture(), 2), array('style'=>'text-align:right;'))
				);
		
		$vids = $stmtCalc->getOverpaidVisitIds();
		$vid = json_encode($vids);

		$tbl->addBodyTr(
				HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'unallocVisits', 'data-vids'=>$vid, 'class'=>'ui-icon ui-icon-info', 'title'=>'List visits with unallocated payments.', 'style'=>'margin-right:1em;')).'Unallocated Payments' , array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getUnallocatedPayments(), 2), array('style'=>'text-align:right;'))
				);
		
		$lodg = $stmtCalc->getPaymentToPast() + $stmtCalc->getPaymentToNow() + $stmtCalc->getPaymentToFuture() + $stmtCalc->getUnallocatedPayments();
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Total', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($lodg, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-matchlgt'))
				);
		
		
		$tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'2')));
		$tbl->addBodyTr(HTMLTable::makeTh('Payment Reconciliation', array('colspan'=>'2')));
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Total Prepayments from earlier months', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPaymentFromPast(), 2), array('style'=>'text-align:right; border: 2px solid teal;'
						, 'title'=>'May include payments slated for future months. '))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Prepayments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPastPaymentsToNow(), 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd($monthArray[$this->startDate->format('n')][1] . ' Payments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPaymentToNow(), 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Total Payments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPaymentToNow() + $stmtCalc->getPastPaymentsToNow(), 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		
		
		$tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'2')));
		$tbl->addBodyTr(HTMLTable::makeTh('Lodging Charge Distribution', array('colspan'=>'2')));
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Paid Charges for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getPaymentToNow() + $stmtCalc->getPastPaymentsToNow(), 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Unpaid Charges from ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getUnpaidCharges(), 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Itemized Discounts ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format(abs($stmtCalc->getDiscount()), 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Waived Charges for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format(abs($stmtCalc->getWaiveAmt()), 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Rate Subsidy for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($stmtCalc->getSubsidyCharge(), 2), array('style'=>'text-align:right;'))
				);
		
		$income = $stmtCalc->getPaymentToNow() + $stmtCalc->getPastPaymentsToNow() + $stmtCalc->getUnpaidCharges() + abs($stmtCalc->getDiscount())
		+ abs($stmtCalc->getWaiveAmt()) + $stmtCalc->getSubsidyCharge();
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Income for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($income, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals hhk-matchinc'))
				);
		
		return $tbl->generateMarkup($tableAttrs)
		. $this->statsPanel($dbh, $finInterval->getTotalCatNites(), $this->startDate->format('Y-m-d'), $this->endDate->format('Y-m-d'), $finInterval->getRoomCategories(), 'Report_Category', $monthArray, $stmtCalc->getFullIntervalCharge())
		. $this->createBAMarkup($finInterval->getBaArray(), $tableAttrs)
		. HTMLContainer::generateMarkup('div', $this->showPaymentAmounts($finInterval->getPayAmounts()));
		
	}
	
	protected function showPaymentAmounts($p) {
		$tbl = new HTMLTable();
		foreach ($p as $k=>$v) {
			if (round($v, 2) != 0) {
			 	$tbl->addBodyTr(HTMLTable::makeTd($k).HTMLTable::makeTd($v));
			}
		}
		return $tbl->generateMarkup();
	}
	
	protected function createBAMarkup($baTotals, $tableAttrs) {
		
		$totals = array('paid'=>0, 'pend'=>0);
		$tbl = new HTMLTable();
		$tbl->addHeaderTr(
				HTMLTable::makeTh('3rd Parties')
				.HTMLTable::makeTh('Paid')
				.HTMLTable::makeTh('Pending')
				
				);
		
		foreach ($baTotals as $k => $t) {
			
			if ($k == '') {
				Continue;
			}
			
			if (isset($t['paid']) === FALSE) {
				$t['paid'] = 0;
			}
			
			if (isset($t['pend']) === FALSE) {
				$t['pend'] = 0;
			}
			
			$tbl->addBodyTr(
					HTMLTable::makeTd($k, array('class'=>'tdlabel'))
					. HTMLTable::makeTd(($t['paid'] == 0 ? '' : number_format($t['paid'], 2)), array('style'=>'text-align:right;'))
					. HTMLTable::makeTd(($t['pend'] == 0 ? '' : number_format($t['pend'], 2)), array('style'=>'text-align:right;'))
					);
			
			$totals['paid'] += $t['paid'];
			$totals['pend'] += $t['pend'];
			
		}
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($totals['paid'], 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals '))
				. HTMLTable::makeTd(number_format($totals['pend'], 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals '))
				);
		
		$tableAttrs['style'] = "margin-top:9px;";
		return $tbl->generateMarkup($tableAttrs);
	}
	
	protected function statsPanel(\PDO $dbh, $totalCatNites, $start, $end, $categories, $rescGroup, $monthArray, $fullIntervalCharge) {
		
		$totalOOSNites = 0;
		
		$stDT = new \DateTime($start . ' 00:00:00');
		$enDT = new \DateTime($end . ' 00:00:00');
		$numNights = $enDT->diff($stDT, TRUE)->days;
		
		$qu = "select r.idResource, rm.Category, rm.Type, rm.Report_Category, rm.Rate_Code, IFNULL(rate.Reduced_Rate_1, 0.0) as 'Flat_Rate', ifnull(g.Substitute, '0') as `Static_Rate`, ifnull(ru.Start_Date,'') as `Start_Date`, ifnull(ru.End_Date, '') as `End_Date`, ifnull(ru.Status, 'a') as `RU_Status`
        from resource r left join
resource_use ru on r.idResource = ru.idResource and DATE(ru.Start_Date) < DATE('" . $enDT->format('Y-m-d') . "') and DATE(ru.End_Date) > DATE('" . $stDT->format('Y-m-d') . "')
left join resource_room rr on r.idResource = rr.idResource
left join room rm on rr.idRoom = rm.idRoom
left join room_rate rate on rate.FA_Category = 'e' and rate.Status = 'a'
left join gen_lookups g on g.`Table_Name` = 'Static_Room_Rate' and g.`Code` = rm.Rate_Code
where r.`Type` in ('" . ResourceTypes::Room . "','" . ResourceTypes::RmtRoom . "')
order by r.idResource;";
		
		$rstmt = $dbh->query($qu);
		
		$rooms = array();
		$rates = array();
		
		// Get rooms and oos days
		while ($r = $rstmt->fetch(\PDO::FETCH_ASSOC)) {
			
			$nites = 0;
			
			if ($r['Start_Date'] != '' && $r['End_Date'] != '') {
				$arriveDT = new \DateTime($r['Start_Date']);
				$arriveDT->setTime(0, 0, 0);
				$departDT = new \DateTime($r['End_Date']);
				$departDT->setTime(0,0,0);
				
				// Only collect days within the time period.
				if ($arriveDT < $stDT) {
					$arriveDT = new \DateTime($stDT->format('Y-m-d 00:00:00'));
				}
				
				if ($departDT > $enDT) {
					$departDT = new \DateTime($enDT->format('Y-m-d 00:00:00'));
				}
				
				// Collect 0-day events as one day
				if ($arriveDT == $departDT) {
					$nites = 0;
				} else {
					$nites = $departDT->diff($arriveDT, TRUE)->days;
				}
			}
			
			if (isset($rooms[$r['idResource']][$r[$rescGroup]][$r['RU_Status']]) === FALSE) {
				$rooms[$r['idResource']][$r[$rescGroup]][$r['RU_Status']] = $nites;
			} else {
				$rooms[$r['idResource']][$r[$rescGroup]][$r['RU_Status']] += $nites;
			}
			
			$rates[$r['idResource']] = $r['Flat_Rate'];
			
		}
		
		// Filter out unavailalbe rooms and add up the nights
		$availableRooms = 0;
		$unavailableRooms = 0;
		$rateDayTotal = 0;
		
		foreach($rooms as $id => $r) {
			
			foreach ($r as $c) {
				
				if (isset($c[ResourceStatus::Unavailable]) && $c[ResourceStatus::Unavailable] >= $numNights) {
					$unavailableRooms++;
					continue;
				}
				
				$availableRooms++;
				$rateDayTotal += $rates[$id];
				
				foreach ($c as $k => $v) {
					
					if ($k != ResourceStatus::Available) {
						
						$totalOOSNites += $v;
					}
				}
			}
		}
		
		
		$numUsefulNights = ($availableRooms * $numNights) - $totalOOSNites;
		
		$sTbl = new HTMLTable();
		$sTbl->addHeaderTr(HTMLTable::makeTh('Parameter') . HTMLTable::makeTh('All ' . $availableRooms . ' Rooms') . HTMLTable::makeTh('Flat Rate'));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Room-Nights in ' . $monthArray[$stDT->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd($numUsefulNights, array('style'=>'text-align:center;'))
				. HTMLTable::makeTd('$'.number_format($rateDayTotal * $numNights, 2), array('style'=>'text-align:right;')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Visit Nights in ' . $monthArray[$stDT->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd($totalCatNites['All'], array('style'=>'text-align:center;'))
				. HTMLTable::makeTd('$'.number_format($fullIntervalCharge, 2), array('style'=>'text-align:right;','class'=>'hhk-matchinc')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Room Utilization', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(($numUsefulNights <= 0 ? '0' : number_format($totalCatNites['All'] * 100 / $numUsefulNights, 1)) . '%', array('style'=>'text-align:center;')));
		
		
		return $sTbl->generateMarkup();
		
	}
	
	protected function getOrderNumbers() {

		$orderNumbers = '';

		if (count($this->orderIds) > 0) {

			foreach ($this->orderIds as $k) {

				if ($k < 1) {
					continue;
				}

				if ($orderNumbers == '') {
					$orderNumbers = $k;
				} else {
					$orderNumbers .= ','. $k;
				}
			}

			$orderNumbers = " or v.idVisit in ($orderNumbers) ";
		}

		return $orderNumbers;

	}

	
	protected function recordError($error) {
		$this->errors[] = $error;
	}
	
	public function getInvoices() {
		return $this->records;
	}
	
	public function getErrors() {
		return $this->errors;
	}
	
	public function getGlStmeTotalsObj() {
		return $this->glLineMapper;
	}
	
	public function getGlMarkup($tableAttrs) {
		return $this->glLineMapper->createMarkup($tableAttrs);
	}
	
	protected function getStartDay() {
		$iDay = intval($this->startDay, 10);
		$sDay = '';
		
		if ($iDay < 1 || $iDay > 28) {
			throw new RuntimeException('The Start-Day is not viable: ' . $iDay);
		}
		
		// Format with leading 0's
		if ($iDay < 10) {
			$sDay = '0' . $iDay;
		} else {
			$sDay = (string)$iDay;
		}
		return $sDay;
	}
	
	public static function invoiceHeader() {
		
		return array('Inv #', 'Delegated', 'Status', 'Amt', 'Deleted', 'Pledged', 'Rate', 'Balance', 'Order Number', 'Sub Order');
	}
	public static function lineHeader() {
		
		return array(' ', ' ', 'id', 'Amt', 'Item', 'Type', 'Gl Code');
	}
	public static function paymentHeader() {
		
		return array(' ', 'id', 'Status', 'Amt', 'Method', 'Updated', 'Timestamp', 'Refund', 'Payor', 'Pm Gl', 'Ba Debit', 'Ba Cred');
	}
	
}

