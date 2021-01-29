<?php
namespace HHK\GlStmt;

use HHK\SysConst\{InvoiceStatus, ItemId, PaymentStatusCode, RoomRateCategories};
use HHK\SysConst\ItemType;
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
		
		$this->loadDbRecords($dbh);
		
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
		}
		
		$this->glLineMapper = new GlStmtTotals($pmCodes, $itemCodes);
		$this->baLineMapper = new BaStmtTotals();
	}
	
	/**
	 *
	 */
	public function mapRecords() {
		
		if (count($this->records) < 1) {
			$this->recordError('No Payments Found. ');
		}
		
		$this->lines = [];
		
		//
		foreach ($this->records as $r) {
			
			// Any payments?
			if (count($r['p']) < 1) {
				
				// Don't flag carried invoice.
				if ($r['i']['iStatus'] != InvoiceStatus::Carried) {
					$this->recordError('No payment for Invoice ' . $r['i']['iNumber']);
				}

				continue;
			}

			$payments = [];

			foreach ($r['p'] as $p) {

				if ($p['pStatus'] == PaymentStatusCode::Reverse || $p['pStatus'] == PaymentStatusCode::VoidSale || $p['pStatus'] == PaymentStatusCode::Declined) {
					continue;
				}

				$payments[$p['idPayment']] = $p;

			}

			// any payments left?
			if (count($payments) == 0) {
				continue;
			}
			
			$cpay = $this->combinePayments($payments, $r['i']['iNumber']);
			
			$this->mapInvLines($r['l'], $cpay, $r['i']['iNumber']);
		}
		
		if ($this->glLineMapper->getTotalCredit() != $this->glLineMapper->getTotalDebit()) {
			$this->recordError('Credits not equal debits.');
		}
		
		return $this;
	}
	
	/**
	 * returns a new CombinedPayment loaded with all payment amounts for an invoice.
	 *
	 * @param array $payments
	 * @param int $iNumber
	 * @return \HHK\GlStmt\CombinedPayment
	 */
	protected function combinePayments($payments, $iNumber) {
		
		$cpayment = new CombinedPayment();
		
		foreach ($payments as $p) {
			
			$pUpDate = NULL;
			
			// Check dates
			if ($p['pTimestamp'] != '') {
				$cpayment->setPaymentDate(new \DateTime($p['pTimestamp']));
			} else {
				$this->recordError("Missing Payment Date. Payment Id = ". $p['idPayment']);
				continue;
			}
			
			if ($p['pUpdated'] != '') {
				$pUpDate = new \DateTime($p['pUpdated']);
				$cpayment->setUpdatedDate($pUpDate);
			}
			
			
			if ($p['pStatus'] == PaymentStatusCode::Retrn) {
				//Return earlier sale
				
				if (is_null($pUpDate)) {
					$this->recordError("Retrn missing its Last Updated. Payment Id = ". $p['idPayment']);
					continue;
				}
				
				// Returned during this period?
				if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
					// It is a return in this period.
					
					// 3rd party payments
					if ($p['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($p['ba_Gl_Debit'], (0 - abs($p['pAmount'])), 0, $cpayment->getUpdatedDate(), $iNumber);
					}
					
					$this->lines[] = $this->glLineMapper->makeLine($p['pm_Gl_Code'], (0 - abs($p['pAmount'])), 0, $cpayment->getUpdatedDate(), $iNumber);
					
					$cpayment->returnAmount($p['pAmount']);

				}
				
				if ($cpayment->getPaymentDate() >= $this->startDate && $cpayment->getPaymentDate() < $this->endDate) {
					// It is still a payment in this period.

					// 3rd party payments
					if ($p['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($p['ba_Gl_Debit'], $p['pAmount'], 0, $cpayment->getPaymentDate(), $iNumber);
					}
					
					$this->lines[] = $this->glLineMapper->makeLine($p['pm_Gl_Code'], $p['pAmount'], 0, $cpayment->getPaymentDate(), $iNumber);
					
					$cpayment->payAmount($p['pAmount']);

				}

			} else if (($p['pStatus'] == PaymentStatusCode::Paid || $p['pStatus'] == PaymentStatusCode::VoidReturn)  && $p['Is_Refund'] == 0) {
				// Status = Sale

				// un-returned payments are dated on the update.
				if (is_null($pUpDate) === FALSE) {
					$cpayment->setPaymentDate($pUpDate);
				}

				// Payment is in this period?
				if ($cpayment->getPaymentDate() >= $this->startDate && $cpayment->getPaymentDate() < $this->endDate) {

					// 3rd party payments
					if ($p['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($p['ba_Gl_Debit'], $p['pAmount'], 0, $cpayment->getPaymentDate(), $iNumber);
					}

					$this->lines[] = $this->glLineMapper->makeLine($p['pm_Gl_Code'], $p['pAmount'], 0, $cpayment->getPaymentDate(), $iNumber);

					$cpayment->payAmount($p['pAmount']);

				}

			} else if ($p['pStatus'] == PaymentStatusCode::Paid && $p['Is_Refund'] > 0){
				// Status = refund amount

				// Payment is in this period?
				if ($cpayment->getPaymentDate() >= $this->startDate && $cpayment->getPaymentDate() < $this->endDate) {
					// 3rd party payments
					if ($p['ba_Gl_Debit'] != '') {
						$this->baLineMapper->makeLine($p['ba_Gl_Debit'], (0 - abs($p['pAmount'])), 0, $cpayment->getPaymentDate(), $iNumber);
					}

					$this->lines[] = $this->glLineMapper->makeLine($p['pm_Gl_Code'], (0 - abs($p['pAmount'])), 0, $cpayment->getPaymentDate(), $iNumber);

					$cpayment->refundAmount($p['pAmount']);
				}

			} else {
				$this->recordError("Unanticipated Payment Status: ". $p['pStatus'] . '  Payment Id = '.$p['idPayment']);
			}
		}
		
		return $cpayment;
	}
	
	protected function mapInvLines(array $iLines, CombinedPayment $cpay, $iNumber) {
		
		$waiveAmt = 0;
		$invLines = array();
		
		// Copy invoice lines and Look for waived.
		foreach ($iLines as $l) {
			
			if ($l['il_Item_Id'] == ItemId::Waive) {
				$waiveAmt += abs($l['il_Amount']);
			}
			
			$invLines[] = $l;
		}

		// Special handling for waived lines.
		if ($waiveAmt > 0) {
			$invLines = $this->mapWaiveLines($waiveAmt, $iLines);
		}
		

		if ($cpay->getPayAmount() > 0) {
			// sale
			$pAmount = $cpay->getPayAmount();
			
			foreach($invLines as $l) {
				
				$ilAmt = abs($l['il_Amount']);
				
				if ($pAmount >= $ilAmt) {
					$pAmount -= $ilAmt;
				} else {
					$ilAmt = $pAmount;
					$pAmount = 0;
				}
				
				// map gl code
				$this->lines[] = $this->glLineMapper->makeLine($l['Item_Gl_Code'], 0, abs($ilAmt), $cpay->getPaymentDate(), $iNumber);
			}
		}
		
		if ($cpay->getReturnAmount() > 0) {
			// return
			$pAmount = $cpay->getReturnAmount();
			
			foreach($invLines as $l) {
				
				$ilAmt = abs($l['il_Amount']);
				
				if ($pAmount >= $ilAmt) {
					$pAmount -= $ilAmt;
				} else {
					$ilAmt = $pAmount;
					$pAmount = 0;
				}
				
				// map gl code
				$this->lines[] = $this->glLineMapper->makeLine($l['Item_Gl_Code'], 0, (0 - abs($ilAmt)), $cpay->getUpdatedDate(), $iNumber);
			}
		}
		
		if ($cpay->getRefundAmount() > 0) {
			// refund
			$pAmount = $cpay->getRefundAmount();
			
			foreach($invLines as $l) {
				
				$ilAmt = abs($l['il_Amount']);
				
				if ($pAmount >= $ilAmt) {
					$pAmount -= $ilAmt;
				} else {
					$ilAmt = $pAmount;
					$pAmount = 0;
				}
				
				// map gl code
				$this->lines[] = $this->glLineMapper->makeLine($l['Item_Gl_Code'], 0, (0 - abs($ilAmt)), $cpay->getPaymentDate(), $iNumber);
			}
		}
						
	}
	
	protected function mapWaiveLines($waiveAmt, array $invLines) {
		
		$remainingItems = array();
		
		foreach ($invLines as $l) {
			
			// Don't return the waiving item
			if ($l['il_Item_Id'] == ItemId::Waive) {
				continue;
			}
			
			// Adjust the amounts after the waive.
			if ($l['il_Item_Id'] == ItemId::Lodging || $l['il_Item_Id'] == ItemId::AddnlCharge || $l['il_Type_Id'] == ItemType::Tax || $l['il_Item_Id'] == ItemId::VisitFee) {
				
				if ($l['il_Amount'] >= $waiveAmt) {
					
					$l['il_Amount'] -= $waiveAmt;
					$waiveAmt = 0;
					
				} else  if ($l['il_Amount'] > 0) {
					
					$waiveAmt -= $l['il_Amount'];
					$l['il_Amount'] = 0;
				}
			}
			
			$remainingItems[] = $l;
		}
		
		if ($waiveAmt != 0) {
			$this->recordError("Waive amount (" .$waiveAmt . ") not retired.");
		}
		
		return $remainingItems;
	}
	
	protected function loadDbRecords(\PDO $dbh) {
		
		$idInvoice = 0;
		$idPayment = 0;
		$idInvoiceLine = 0;
		
		$invoices = array();
		$invoice = array();
		$payments = array();
		$invoiceLines = array();
		$delegatedInvoiceLines = array();
		$delegatedPayments = array();
		
		$query = "call gl_report('" . $this->startDate->format('Y-m-d') . "','" . $this->endDate->format('Y-m-d') . "')";
		
		$stmt = $dbh->query($query);
		
		while ($p = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			
			if ($p['idInvoice'] != $idInvoice) {
				// Next Invoice
				
				if ($idInvoice > 0) {
					// close last invoice
					$invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'l'=>$invoiceLines);
				}
				
				$idInvoice = $p['idInvoice'];
				
				// new invoice
				$invoice = array(
						'iNumber'=>$p['iNumber'],
						'Delegated_Id'=>$p['Delegated_Id'],
						'iStatus'=>$p['iStatus'],
						'iAmount'=>$p['iAmount'],
						'iDeleted'=>$p['iDeleted'],
						'Pledged'=>$p['Pledged_Rate'],
						'Rate'=>$p['Rate'],
						'iBalance'=>$p['iBalance'],
						'Order_Number' => $p['Order_Number'],
						'Suborder_Number' => $p['Suborder_Number'],
				);
				
				$idPayment = 0;
				$idInvoiceLine = 0;
				$payments = array();
				$invoiceLines = array();
			}
			
			if ($p['idPayment'] != 0) {
				// Payment exists
				
				if ($idPayment != $p['idPayment']) {
					// Next Payment
					
					$idPayment = $p['idPayment'];
					
					$payment = array(
							'idPayment'=>$p['idPayment'],
							'pStatus'=>$p['pStatus'],
							'pAmount'=>$p['pAmount'],
							'pMethod'=>$p['pMethod'],
							'pUpdated'=>($p['pUpdated'] == '' ? '' : $p['pUpdated']),
							'pTimestamp'=>$p['pTimestamp'],
							'Is_Refund'=>$p['Is_Refund'],
							'idPayor'=>$p['idPayor'],
							'pm_Gl_Code'=>$p['PayMethod_Gl_Code'],
							'ba_Gl_Debit'=>$p['ba_Gl_Debit'],
							'ba_Gl_Credit'=>$p['ba_Gl_Credit'],
					);
					
					// Delegated invoice and there are actual payments to register.
					 if ($p['Delegated_Id'] == 0) {
						$payments[$idPayment] = $payment;
					 } else if ($p['iAmount'] != $p['iBalance']) {
					 	$delegatedPayments[$p['Delegated_Id']][$idPayment] = $payment;
					 }
					 	
				}
			}
			
			if ($p['il_Id'] != 0) {
				// Invoice line exists
				
				if ($idInvoiceLine != $p['il_Id']) {
					// Next Line
					
					$idInvoiceLine = $p['il_Id'];
					
					$line = array(
							'il_Id'=>$p['il_Id'],
							'il_Amount'=>$p['il_Amount'],
							'il_Item_Id'=>$p['il_Item_Id'],
							'il_Type_Id'=>$p['il_Type_Id'],
							'Item_Gl_Code'=>$p['Item_Gl_Code'],
					);
					
					if ($p['Delegated_Id'] > 0) {
						$delegatedInvoiceLines[$p['Delegated_Id']][$idInvoiceLine] = $line;
					} else if ($p['il_Item_Id'] != ItemId::InvoiceDue) {
						$invoiceLines[$idInvoiceLine] = $line;
					}
				}
			}
		}

		$stmt->nextRowset();

		if ($idInvoice > 0) {
			// close last invoice
			$invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'l'=>$invoiceLines);
		}

		// Add the delegated items to their carried-by invoice.
		foreach ($delegatedInvoiceLines as $k => $l) {

			foreach ($l as $line) {

				$invoices[$k]['l'][$line['il_Id']] = $line;
			}
		}

		// Add the delegated payments to their carried-by invoice.
		foreach ($delegatedPayments as $k => $l) {

			foreach ($l as $line) {

				$invoices[$k]['p'][$line['idPayment']] = $line;
			}
		}

		$this->records =  $invoices;
	}

	public function doReport (\PDO $dbh, $monthArray, $tableAttrs) {
		
		$uS = Session::getInstance();
		
		$start = $this->startDate->format('Y-m-d');
		$end = $this->endDate->format('Y-m-d');
		
		$categories = readGenLookupsPDO($dbh, 'Room_Category');
		$categories[] = array(0=>'', 1=>'(default)');
		
		$totalCatNites[] = array();
		foreach ($categories as $c) {
			$totalCatNites[$c[0]] = 0;
		}
		$totalCatNites['All'] = 0;
		
		$baArray = array();
		$baArray['']['paid'] = 0;
		$baArray['']['pend'] = 0;
		
		$priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
		
		$overPay = 0;
		$preIntervalPay = 0;
		$intervalPay = 0;
		$totalPayment = array();
		$forwardPay = 0;  // Payment from last month that pay stays in this month.
		$unpaidCharges = 0;  // Unpaid charges for this month only.
		$paymentsCarriedForward = 0;  // Payments from last month that are not used up in this month.
		
		
		$intervalCharge = 0;
		$fullInvervalCharge = 0;
		$subsidyCharge = 0;
		
		$vIntervalCharge = 0;
		$vPreIntervalCharge = 0;
		$vFullIntervalCharge = 0;
		$vSubsidyCharge = 0;
		$vIntervalPay = 0;
		$vForwardPay = 0;
		
		$paymentAmounts = array();
		$serialId = 0;
		$visitId = 0;
		$record = NULL;
		
		$istmt = $dbh->query("select idItem from item");
		while( $i = $istmt->fetch(\PDO::FETCH_NUM)) {
			$totalPayment[$i[0]] = 0;
		}
		
		$guestNightsSql = "0 as `Actual_Guest_Nights`, 0 as `PI_Guest_Nights`,";
		
		$stmt = $dbh->query($this->makeQuery($start, $end, $guestNightsSql));
		
		while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			
			$serial = ($r['idVisit'] * 100) + $r['Span'];
			
			if ($serialId != $serial) {
				// Span Change
				
				If ($visitId != $r['idVisit'] && $visitId != 0) {
					// Visit Change
					
					// leftover Payments from past (C23)
					$pfp = 0;
					if ($vForwardPay - $vPreIntervalCharge > 0) {
						$pfp = $vForwardPay - $vPreIntervalCharge;
					}
					
					// previous months leftover charge after previous payments (C22)
					$cfp = 0;
					if ($vPreIntervalCharge - $vForwardPay > 0) {
						$cfp = $vPreIntervalCharge - $vForwardPay;
					}
					
					
					// Payments to the past
					$ptp = 0;
					if ($cfp > 0) {
						if($vIntervalPay >= $cfp) {
							$ptp = $cfp;
						} else {
							$ptp = $vIntervalPay;
						}
					}
					
					// Payments to now
					$ptn = 0;
					if ($cfp <= $vIntervalPay) {
						if ($vIntervalPay - $cfp > $vIntervalCharge) {
							$ptn = $vIntervalCharge;
						} else {
							$ptn = $vIntervalPay - $cfp;
						}
					}
					
					// Payments to Future
					$ptf = 0;
					if ($ptp + $ptn < $vIntervalPay) {
						$ptf = $vIntervalPay - $ptp - $ptn;
					}
					
					// Unpaid Charges
					if ($vIntervalCharge > $ptn) {
						$unpaidCharges += ($vIntervalCharge - $ptn);
					}
					
					// Payments Carried Forward
					if (($vForwardPay + $vIntervalPay) - $vPreIntervalCharge - $vIntervalCharge - $ptf > 0) {
						$paymentsCarriedForward += ($vForwardPay + $vIntervalPay) - $vPreIntervalCharge - $vIntervalCharge - $ptf;
					}
					
					
					$forwardPay += $pfp;
					$preIntervalPay += $ptp;
					$intervalPay += $ptn;
					$overPay += $ptf;
					
					$intervalCharge += $vIntervalCharge;
					$fullInvervalCharge += $vFullIntervalCharge;
					$subsidyCharge += $vSubsidyCharge;
					
					// Reset for next visit
					$vIntervalCharge = 0;
					$vPreIntervalCharge = 0;
					$vFullIntervalCharge = 0;
					$vSubsidyCharge = 0;
					$vIntervalPay = 0;
					$vForwardPay = 0;
										
					$visitId = $r['idVisit'];
				}
				
				$adjRatio = (1 + $r['Expected_Rate']/100);
				
				$totalCatNites[$r['Room_Category']] += $r['Actual_Interval_Nights'];
				$totalCatNites['All'] += $r['Actual_Interval_Nights'];
				
				//  Add up any pre-interval charges
				if ($r['Pre_Interval_Nights'] > 0) {
					
					// collect all pre-charges
					$priceModel->setCreditDays(0);
					$vPreIntervalCharge += $priceModel->amountCalculator($r['Pre_Interval_Nights'], $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $r['PI_Guest_Nights']) * $adjRatio;
					
				}
				
				// Add up interval charges
				if ($r['Actual_Interval_Nights'] > 0) {
					
					// Guest paying
					$priceModel->setCreditDays($r['Pre_Interval_Nights']);
					$vIntervalCharge += $priceModel->amountCalculator($r['Actual_Interval_Nights'], $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $r['Actual_Guest_Nights']) * $adjRatio;
					
					// Full charge
					$priceModel->setCreditDays($r['Pre_Interval_Nights']);
					$vFullIntervalCharge += $priceModel->amountCalculator($r['Actual_Interval_Nights'], 0, RoomRateCategories::FullRateCategory, $uS->guestLookups['Static_Room_Rate'][$r['Rate_Code']][2], $r['Actual_Guest_Nights']);
					
					if ($adjRatio > 0) {
						// Only adjust when the charge will be more.
						$vFullIntervalCharge = $vFullIntervalCharge * $adjRatio;
					}
					
					// discount charges are only for discounted rates.
					if ($r['Rate_Category'] != RoomRateCategories::FlatRateCategory) {
						$vSubsidyCharge += ($vFullIntervalCharge - $vIntervalCharge);
					}
					
				}
			}
			
			$serialId = $serial;
			$visitId = $r['idVisit'];
			$record = $r;
			
			// Unpaid invoices
			if ($r['Invoice_Status'] == InvoiceStatus::Unpaid) {
				$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['pend'], $r['il_Amount']);
			}
			
			if ($r['pStatus'] == PaymentStatusCode::Reverse || $r['pStatus'] == PaymentStatusCode::VoidSale || $r['pStatus'] == PaymentStatusCode::Declined) {
				continue;
			}
			
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
			
			// Multiple invoice lines for one payment...
			if (isset($paymentAmounts[$r['idPayment']]) === FALSE) {
				$paymentAmounts[$r['idPayment']] = $r['pAmount'];
			}
			
			
			// Payments
			if (($r['pStatus'] == PaymentStatusCode::Paid || $r['pStatus'] == PaymentStatusCode::VoidReturn) && $r['Is_Refund'] == 0) {
				// Sale
				// un-returned payments are dated on the update.
				if (is_null($pUpDate) === FALSE) {
					$paymentDate = $pUpDate;
				}

 				$paymentAmounts[$r['idPayment']] -= $ilAmt;

				// Payment is in this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vIntervalPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::Discount && $ilAmt < 0) {
						// Discounts lower lodging charges
						$vIntervalCharge += $ilAmt;
					}
					
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], $ilAmt);
					$totalPayment[$r['Item_Id']] += $ilAmt;
					
				} else if ($paymentDate < $this->startDate) {
					// Pre payment from before
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vForwardPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vForwardPay += $ilAmt;
					}
					
				}
				
			// Refunds
			} else if ($r['pStatus'] == PaymentStatusCode::Paid && $r['Is_Refund'] == 1) {
				
				// payment is positive in this case.
 				$paymentAmounts[$r['idPayment']] += $ilAmt;
				
				// Payment must be within the .
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vIntervalPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay += $ilAmt;
					}
					
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], $ilAmt);
					$totalPayment[$r['Item_Id']] += $ilAmt;
					
				} else if ($paymentDate < $this->startDate) {
					// Pre payment from before
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vForwardPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vForwardPay += $ilAmt;
					}
					
				}
				
			//Returns
			} else if ($r['pStatus'] == PaymentStatusCode::Retrn) {
				
				if (is_null($pUpDate)) {
					$this->recordError("Missing Last Updated. Payment Id = ". $r['idPayment']);
					continue;
				}
				
 				$paymentAmounts[$r['idPayment']] -= $ilAmt;
				
				// Returned during this period?
				if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
					// It is a return in this period.
					
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vIntervalPay -= $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay -= $ilAmt;
					}
					
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], (0 - $ilAmt));
					$totalPayment[$r['Item_Id']] -= $ilAmt;
					
				} else if ($paymentDate < $this->startDate) {
					// Pre payment from before
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vForwardPay -= $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vForwardPay -= $ilAmt;
					}
				}
				
				// Paid during this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vIntervalPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::Discount && $ilAmt < 0) {
						// Discounts lower lodging charges
						$vIntervalCharge += $ilAmt;
					}
					
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], $ilAmt);
					$totalPayment[$r['Item_Id']] += $ilAmt;
					
				} else if ($paymentDate < $this->startDate) {
					// Pre payment from before
					
					if ($r['Item_Id'] == ItemId::Lodging) {
						$vForwardPay += $ilAmt;
					} else if ($r['Item_Id'] == ItemId::LodgingReversal) {
						$vForwardPay += $ilAmt;
					}
				}

			} else if ($r['idPayment'] == 0 && $r['Invoice_Status'] == InvoiceStatus::Paid) {

				$paymentDate = new \DateTime($r['Invoice_Date']);

				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					// Discounts
					if ($r['Item_Id'] = ItemId::Discount) {
						$totalPayment[$r['Item_Id']] += abs($ilAmt);
						
						// Reduces the charges.
						$vIntervalCharge += $ilAmt;
					}
				}
			}
		}
		
		if ($record != NULL) {
			
			// leftover Payments from past (C23)
			$pfp = 0;
			if ($vForwardPay - $vPreIntervalCharge > 0) {
				$pfp = $vForwardPay - $vPreIntervalCharge;
			}
			
			// previous months leftover charge after previous payments (C22)
			$cfp = 0;
			if ($vPreIntervalCharge - $vForwardPay > 0) {
				$cfp = $vPreIntervalCharge - $vForwardPay;
			}
			
			
			// Payments to the past
			$ptp = 0;
			if ($cfp > 0) {
				if($vIntervalPay >= $cfp) {
					$ptp = $cfp;
				} else {
					$ptp = $vIntervalPay;
				}
			}
			
			// Payments to now
			$ptn = 0;
			if ($cfp <= $vIntervalPay) {
				if ($vIntervalPay - $cfp > $vIntervalCharge) {
					$ptn = $vIntervalCharge;
				} else {
					$ptn = $vIntervalPay - $cfp;
				}
			}
			
			// Payments to Future
			$ptf = 0;
			if ($ptp + $ptn < $vIntervalPay) {
				$ptf = $vIntervalPay - $ptp - $ptn;
			}
			
			// Unpaid Charges
			if ($vIntervalCharge > $ptn) {
				$unpaidCharges += ($vIntervalCharge - $ptn);
			}
			
			// Payments Carried Forward
			if (($vForwardPay + $vIntervalPay) - $vPreIntervalCharge - $vIntervalCharge - $ptf > 0) {
				$paymentsCarriedForward += ($vForwardPay + $vIntervalPay) - $vPreIntervalCharge - $vIntervalCharge - $ptf;
			}
			
			
			$forwardPay += $pfp;
			$preIntervalPay += $ptp;
			$intervalPay += $ptn;
			$overPay += $ptf;
			
			$intervalCharge += $vIntervalCharge;
			$fullInvervalCharge += $vFullIntervalCharge;
			$subsidyCharge += $vSubsidyCharge;

		}


		// Remove waived payments from intervalPay
		$intervalPay += $totalPayment[ItemId::Waive];
		$intervalCharge += $totalPayment[ItemId::Waive];
		
		$tbl = new HTMLTable();
		
		$tbl->addHeaderTr(HTMLTable::makeTh('Lodging Payment Distribution', array('colspan'=>'2')));
		$tbl->addBodyTr(
				HTMLTable::makeTd('Back Payments to earlier months', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($preIntervalPay, 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($intervalPay, 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Prepayments to future months', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($overPay, 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Total', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format(($totalPayment[ItemId::Lodging] + $totalPayment[ItemId::LodgingReversal] + $totalPayment[ItemId::Waive]), 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		
		$tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'2')));
		$tbl->addBodyTr(HTMLTable::makeTh('Payment Reconciliation', array('colspan'=>'2')));
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Prepayments from earlier months', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($forwardPay, 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payments from ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($intervalPay, 2), array('style'=>'text-align:right;'))
				);
 		$tbl->addBodyTr(
 				HTMLTable::makeTd('Total Payments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
 				. HTMLTable::makeTd(number_format($intervalPay + $forwardPay, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
 				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Payments Carried Forward', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($paymentsCarriedForward, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		

		$tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'2')));
		$tbl->addBodyTr(HTMLTable::makeTh('Lodging Charge Distribution', array('colspan'=>'2')));
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Paid Charges for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format(abs($intervalCharge), 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Unpaid Charges from ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($unpaidCharges, 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Itemized Discounts ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format(abs($totalPayment[ItemId::Discount]), 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Waived Charges for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format(abs($totalPayment[ItemId::Waive]), 2), array('style'=>'text-align:right;'))
				);
		
		$tbl->addBodyTr(
				HTMLTable::makeTd('Rate Subsidy for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($subsidyCharge, 2), array('style'=>'text-align:right;'))
				);
		$tbl->addBodyTr(
				HTMLTable::makeTd('Income for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($fullInvervalCharge, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				);
		
		return $tbl->generateMarkup($tableAttrs)
		. $this->statsPanel($dbh, $totalCatNites, $start, $end, $categories, 'Report_Category', $monthArray, $fullInvervalCharge)
		. $this->createBAMarkup($baArray, $tableAttrs)
		. HTMLContainer::generateMarkup('div', $this->showPaymentAmounts($paymentAmounts));
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
	
	protected function makeQuery($start, $end, $guestNightsSql) {
		
		$query = "select
	v.idVisit,
	v.Span,
	v.Arrival_Date,
	v.Expected_Departure,
	ifnull(v.Actual_Departure, '') as Actual_Departure,
	v.Span_Start,
	ifnull(v.Span_End, '') as Span_End,
	v.Pledged_Rate,
	v.Expected_Rate,
	v.Rate_Category,
	v.idRoom_Rate,
	v.`Status`,
    v.Rate_Glide_Credit,
	CASE
		WHEN
			DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start')
		THEN 0
		WHEN
			DATE(v.Span_Start) >= DATE('$end')
		THEN 0
		ELSE
			DATEDIFF(
			CASE
				WHEN
					DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) > DATE('$end')
				THEN
					DATE('$end')
				ELSE DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure)))
				END,
			CASE
				WHEN DATE(v.Span_Start) < DATE('$start') THEN DATE('$start')
				ELSE DATE(v.Span_Start)
				END
			)
	END AS `Actual_Interval_Nights`,
	CASE
		WHEN
			DATE(v.Span_Start) >= DATE('$start')
		THEN 0
		WHEN
			DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start')
		THEN
			DATEDIFF(DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))), DATE(v.Span_Start))
		ELSE DATEDIFF(
			CASE
			WHEN
			DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) > DATE('$start')
			THEN
			DATE('$start')
			ELSE
				DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure)))
			END,
			DATE(v.Span_Start)
			)
	END AS `Pre_Interval_Nights`,
	$guestNightsSql
	ifnull(rv.Visit_Fee, 0) as `Visit_Fee_Amount`,
	ifnull(rm.idRoom, '') as idRoom,
	ifnull(rm.Category, '') as Room_Category,
	ifnull(rm.`Type`, '') as Room_Type,
	ifnull(rm.Report_Category, '') as Report_Category,
	ifnull(rm.Rate_Code, '') as Rate_Code,
	ifnull(il.Amount, 0) as il_Amount,
	ifnull(il.Item_Id, 0) as Item_Id,
	ifnull(il.Type_Id, 0) as Type_Id,
	ifnull(il.Source_Item_Id, 0) as Source_Item_Id,
	ifnull(i.idInvoice, 0) as idInvoice,
	ifnull(i.Status, '') as `Invoice_Status`,
	ifnull(i.Sold_To_Id, 0) as Sold_To_Id,
	ifnull(i.Invoice_Date, '') as `Invoice_Date`,
	IFNULL(`p`.`idPayment`, 0) AS `idPayment`,
	IFNULL(`p`.`Amount`, 0) AS `pAmount`,
	IFNULL(`p`.`idPayment_Method`, 0) AS `pMethod`,
	IFNULL(`p`.`Status_Code`, '') AS `pStatus`,
	IFNULL(`p`.`Is_Refund`, 0) AS `Is_Refund`,
	IFNULL(`p`.`Last_Updated`, '') AS `pUpdated`,
	IFNULL(`p`.`idPayor`, 0) AS `idPayor`,
	IFNULL(`p`.`Payment_Date`, '') as `pTimestamp`,
	IFNULL(`nd`.`Gl_Code_Debit`, 'Guest') as `ba_Gl_Debit`
from
	visit v
		left join
	reservation rv ON v.idReservation = rv.idReservation
		left join
	resource_room rr ON v.idResource = rr.idResource
		left join
	room rm ON rr.idRoom = rm.idRoom
		left join
	invoice i on i.Order_Number = v.idVisit and i.Suborder_Number = v.Span and i.Deleted = 0
		left join
	invoice_line il on il.Invoice_Id = i.idInvoice  and il.Deleted = 0
		LEFT JOIN
	`payment_invoice` `pi` ON `pi`.`Invoice_Id` = `i`.`idInvoice`
		LEFT JOIN
	`payment` `p` ON `p`.`idPayment` = `pi`.`Payment_Id`
		LEFT JOIN
	name_demog nd on i.Sold_To_Id = nd.idName

where
	v.idVisit in
		(select idVisit from visit
        	where
            	`Status` <> 'p'
				and DATE(Arrival_Date) < DATE('$end')
				and DATE(ifnull(Span_End,
					case
					when now() > Expected_Departure then now()
					else Expected_Departure
                	end)) >= DATE('$start')
		) " .
		$this->getOrderNumbers() .
		" order by v.idVisit, v.Span";
		
		return $query;
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
		
		$qu = "select r.idResource, rm.Category, rm.Type, rm.Report_Category, rm.Rate_Code, g.Substitute as `Static_Rate`, ifnull(ru.Start_Date,'') as `Start_Date`, ifnull(ru.End_Date, '') as `End_Date`, ifnull(ru.Status, 'a') as `RU_Status`
        from resource r left join
resource_use ru on r.idResource = ru.idResource and DATE(ru.Start_Date) < DATE('" . $enDT->format('Y-m-d') . "') and DATE(ru.End_Date) > DATE('" . $stDT->format('Y-m-d') . "')
left join resource_room rr on r.idResource = rr.idResource
left join room rm on rr.idRoom = rm.idRoom
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
					$arriveDT = new \DateTime($stDT->format('Y-m-d H:i:s'));
				}
				
				if ($departDT > $enDT) {
					$departDT = new \DateTime($enDT->format('Y-m-d H:i:s'));
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
			
			$rates[$r['idResource']] = $r['Static_Rate'];
			
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
		
		
		$numRoomNights = $availableRooms * $numNights;
		$numUsefulNights = $numRoomNights - $totalOOSNites;
		
		$sTbl = new HTMLTable();
		$sTbl->addHeaderTr(HTMLTable::makeTh('Parameter') . HTMLTable::makeTh('All ' . $availableRooms . ' Rooms') . HTMLTable::makeTh('Flat Rate'));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Room-Nights in ' . $monthArray[$stDT->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd($numUsefulNights, array('style'=>'text-align:center;'))
				. HTMLTable::makeTd('$'.number_format($rateDayTotal * $numNights, 2), array('style'=>'text-align:right;')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Visit Nights in ' . $monthArray[$stDT->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd($totalCatNites['All'], array('style'=>'text-align:center;'))
				. HTMLTable::makeTd('$'.number_format($fullIntervalCharge, 2), array('style'=>'text-align:right;')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Room Utilization', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(($numUsefulNights <= 0 ? '0' : number_format($totalCatNites['All'] * 100 / $numUsefulNights, 1)) . '%', array('style'=>'text-align:center;')));
		
		
		return $sTbl->generateMarkup();
		
	}
	
	protected function getOrderNumbers() {
		
		$ordersArray = array();
		foreach ($this->getInvoices() as $r) {
			
			if ($r['i']['Order_Number'] != '') {
				$ordersArray[$r['i']['Order_Number']] = 'y';
			}
		}
		
		$orderNumbers = '';
		
		if (count($ordersArray) > 0) {
			
			foreach ($ordersArray as $k => $i) {
				
				if ($k == '' || $k == 0) {
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
	
	protected function arrayAdd(&$arrayMember, $amount) {
		
		if (isset($arrayMember)) {
			$arrayMember += $amount;
		} else {
			$arrayMember = $amount;
		}
		
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
	
	public function getBaMarkup($tableAttrs) {
		return $this->baLineMapper->createMarkup($tableAttrs);
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

