<?php



class GlStmt {

	protected $startDate;
	protected $endDate;
	protected $startDay;
	protected $records;

	protected $errors;
	protected $paymentDate;
	protected $glLineMapper;
	protected $baLineMapper;


	public function __construct(\PDO $dbh, $month, $year, $day = '01') {

		$this->startDay = $day;
		$this->startDate = new \DateTimeImmutable(intval($year) . '-' . intval($month) . '-' . $this->getStartDay());

		// End date is the beginning of the next month.
		$this->endDate = $this->startDate->add(new DateInterval('P1M'));
		
		$this->errors = array();
		$this->stopAtInvoice = '';

		$this->loadDbRecords($dbh);

		$pmCodes = array();
		$stmt = $dbh->query("Select Gl_Code from payment_method where idPayment_method in (1, 2, 3)");
		while ($p = $stmt->fetch(PDO::FETCH_NUM)) {
			$pmCodes[] = $p[0];
		}
		
		$this->glLineMapper = new GlStmtTotals($pmCodes);
		$this->baLineMapper = new BaStmtTotals();
	}

	/**
	 *
	 * @return GlCodes
	 */
	public function mapRecords() {

		if (count($this->records) < 1) {
			$this->recordError('No Payments Found. ');
		}

		// Filter payment records.
		foreach ($this->records as $r) {

			// Any payments?
			if (count($r['p']) < 1) {
				
				// Don't flag carried.
				if ($r['i']['iStatus'] != InvoiceStatus::Carried) {
					$this->recordError('No payment for Invoice ' . $r['i']['iNumber']);
				}
				
				continue;
			}

			$payments = array();

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

			
			foreach ($payments as $pay) {
				$this->mapPayment($r, $pay);
			}

		}

		if ($this->glLineMapper->getTotalCredit() != $this->glLineMapper->getTotalDebit()) {
			$this->recordError('Credits not equal debits.');
		}
		
		return $this;
	}

	protected function mapPayment($r, $p) {

		$invLines = array();
		$hasWaive = FALSE;

		// Check dates
		if ($p['pTimestamp'] != '') {
			$this->paymentDate = new DateTime($p['pTimestamp']);
		} else {
			$this->recordError("Missing Payment Date. Payment Id = ". $p['idPayment']);
			return;
		}
		
		if ($p['pUpdated'] != '') {
			$pUpDate = new DateTime($p['pUpdated']);
		} else {
			$pUpDate = NULL;
		}
		
		// Copy invoice lines and Look for waived payments.
		foreach ($r['l'] as $l) {

			$invLines[] = $l;

			if ($l['il_Item_Id'] == ItemId::Waive) {
				$hasWaive = TRUE;
			}
		}

		// Special handling for waived payments.
		if ($hasWaive) {
			$invLines = $this->mapWaivePayments($invLines);
		}

		
		$glCode = $p['pm_Gl_Code'];


		// Payment Status
		if ($p['pStatus'] == PaymentStatusCode::Retrn) {
			//Return earlier sale

			if (is_null($pUpDate)) {
				$this->recordError("Missing Last Updated. Payment Id = ". $p['idPayment']);
				return;
			}
			
			// Returned during this period?
			if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
				// It is a return in this period.
				
				// 3rd party payments
				if ($p['ba_Gl_Debit'] != '') {
					$this->baLineMapper->makeLine($p['ba_Gl_Debit'], (0 - abs($p['pAmount'])), 0, $this->paymentDate);
				}
				
				$this->lines[] = $this->glLineMapper->makeLine($glCode, (0 - abs($p['pAmount'])), 0, $pUpDate);
				
				foreach($invLines as $l) {
					
					// map gl code
					$this->lines[] = $this->glLineMapper->makeLine($l['Item_Gl_Code'], 0, (0 - abs($l['il_Amount'])), $pUpDate);
				}
				
			}
			
			if ($this->paymentDate >= $this->startDate && $this->paymentDate < $this->endDate) {
				// It is still a payment in this period.
				
			
				// 3rd party payments
				if ($p['ba_Gl_Debit'] != '') {
					$this->baLineMapper->makeLine($p['ba_Gl_Debit'], $p['pAmount'], 0, $this->paymentDate);
				}
				
				$this->lines[] = $this->glLineMapper->makeLine($glCode, $p['pAmount'], 0, $this->paymentDate);
				
				foreach($invLines as $l) {
										
					$this->lines[] = $this->glLineMapper->makeLine($l['Item_Gl_Code'], 0, $l['il_Amount'], $this->paymentDate);
				}
			}
			
		} else if (($p['pStatus'] == PaymentStatusCode::Paid || $p['pStatus'] == PaymentStatusCode::VoidReturn)  && $p['Is_Refund'] == 0) {
			// Status = Sale
			
			// un-returned payments are dated on the update.
			if (is_null($pUpDate) === FALSE) {
				
				if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
					
					$this->paymentDate = $pUpDate;
					
				} else {
					return;
				}
			}
			
			// Payment is in this period?
			if ($this->paymentDate >= $this->startDate && $this->paymentDate < $this->endDate) {
				
				// 3rd party payments
				if ($p['ba_Gl_Debit'] != '') {
					$this->baLineMapper->makeLine($p['ba_Gl_Debit'], $p['pAmount'], 0, $this->paymentDate);
				}
				
				$this->lines[] = $this->glLineMapper->makeLine($glCode, $p['pAmount'], 0, $this->paymentDate);
				
				foreach($invLines as $l) {
					
					$this->lines[] = $this->glLineMapper->makeLine($l['Item_Gl_Code'], 0, $l['il_Amount'], $this->paymentDate);
				}
			}

		} else if ($p['pStatus'] == PaymentStatusCode::Paid && $p['Is_Refund'] > 0){
			// Status = refund amount
			
			// 3rd party payments
			if ($p['ba_Gl_Debit'] != '') {
				$this->baLineMapper->makeLine($p['ba_Gl_Debit'], (0 - abs($p['pAmount'])), 0, $this->paymentDate);
			}
			
			$this->glLineMapper->makeLine($glCode, (0 - abs($p['pAmount'])), 0, $this->paymentDate);

			foreach($invLines as $l) {

				// map gl code
				$this->glLineMapper->makeLine($l['Item_Gl_Code'], 0, (0 - abs($l['il_Amount'])), $this->paymentDate);
			}

		} else {
			$this->recordError("Unanticipated Payment Status: ". $p['pStatus'] . '  Payment Id = '.$p['idPayment']);
		}
	}
	
	protected function mapWaivePayments(array $invLines) {

		// add up waiveable items
		$waiveAmt = 0;
		$waiveGlCode = '';
		$remainingItems = array();

		foreach ($invLines as $l) {

			if ($l['il_Item_Id'] == ItemId::Waive) {
				$waiveAmt += abs($l['il_Amount']);
				$waiveGlCode = $l['Item_Gl_Code'];
			}
		}

		if ($waiveAmt > 0) {

			// debit the foundation donation
			$this->glLineMapper->makeLine($waiveGlCode, $waiveAmt, 0, $this->paymentDate);

		}

		// reduce the waiveable items
		foreach ($invLines as $l) {

			// Don't return the waiving item
			if ($l['il_Item_Id'] == ItemId::Waive) {
				continue;
			}

			$remainingItems[] = $l;
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
				
		$query = "call gl_report('" . $this->startDate->format('Y-m-d') . "','" . $this->endDate->format('Y-m-d') . "')";
		
    	$stmt = $dbh->query($query);
    	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    	$stmt->nextRowset();
    	
    	foreach ($rows as $p) {
    		
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
    					'iBalance'=>$p['iBalance'],
    					'iDeleted'=>$p['iDeleted'],
    					'Pledged'=>$p['Pledged_Rate'],
    					'Rate'=>$p['Rate'],
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

    				$payments[$idPayment] = array(
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
    	
    	unset($rows);

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

    	$this->records =  $invoices;
	}
	
	public function doReport (\PDO $dbh, $monthArray, $tableAttrs) {

		$uS = Session::getInstance();

		$start = $this->startDate->format('Y-m-d');
		$end = $this->endDate->format('Y-m-d');

		$guestNightsSql = "0 as `Actual_Guest_Nights`, 0 as `PI_Guest_Nights`,";

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
	IFNULL(`p`.`idPayment`, 0) AS `idPayment`,
	IFNULL(`p`.`Amount`, 0) AS `pAmount`,
	IFNULL(`p`.`idPayment_Method`, 0) AS `pMethod`,
	IFNULL(`p`.`Status_Code`, '') AS `pStatus`,
	IFNULL(`p`.`Is_Refund`, 0) AS `Is_Refund`,
	IFNULL(`p`.`Last_Updated`, '') AS `pUpdated`,
	IFNULL(`p`.`idPayor`, 0) AS `idPayor`,
	IFNULL(`p`.`Timestamp`, '') as `pTimestamp`,
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
		
		$priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

		$overPay = 0;
		$preIntervalPay = 0;
		$intervalPay = 0;
		$totalPayment = array();
		
		$intervalCharge = 0;
		$fullInvervalCharge = 0;
		
		$vIntervalCharge = 0;
		$vPreIntervalCharge = 0;
		$vFullIntervalCharge = 0;
		$vIntervalPay = 0;

		$istmt = $dbh->query("select idItem from item");
		while( $i = $istmt->fetch(PDO::FETCH_NUM)) {
			$totalPayment[$i[0]] = 0;
		}
		
		$serialId = 0;
		$visitId = 0;
		$record = NULL;
		
		$stmt = $dbh->query($query);
		
		while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			
			$serial = ($r['idVisit'] * 100) + $r['Span'];
			
			if ($serialId != $serial) {
				// Span Change
				
				If ($visitId != $r['idVisit'] && $visitId != 0) {
					// Visit Change
					
					if ($vIntervalPay > ($vIntervalCharge + $vPreIntervalCharge)) {
						$overPay += $vIntervalPay - ($vIntervalCharge + $vPreIntervalCharge);
						$intervalPay += $vIntervalCharge;
						$preIntervalPay += $vPreIntervalCharge;
					} else if ($vIntervalPay > $vPreIntervalCharge) {
						$preIntervalPay += $vPreIntervalCharge;
						$intervalPay += ($vIntervalPay - $vPreIntervalCharge);
					} else {
						$preIntervalPay += $vIntervalPay;
					}
					
					$intervalCharge += $vIntervalCharge;
					
					// Reset for next visit
					$vIntervalCharge = 0;
					$vPreIntervalCharge = 0;
					$vFullIntervalCharge = 0;
					$vIntervalPay = 0;
					
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
					$vFullIntervalCharge += $priceModel->amountCalculator($r['Actual_Interval_Nights'], 0, RoomRateCategorys::FullRateCategory, $uS->guestLookups['Static_Room_Rate'][$r['Rate_Code']][2], $r['Actual_Guest_Nights']);
					
					if ($adjRatio > 0) {
						// Only adjust when the charge will be more.
						$vFullIntervalCharge = $vFullIntervalCharge * $adjRatio;
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
			

			// Add up payments
			if ($r['pTimestamp'] != '') {
				$paymentDate = new DateTime($r['pTimestamp']);
			} else {
				// No payment
				continue;
			}
			
			if ($r['pUpdated'] != '') {
				$pUpDate = new DateTime($r['pUpdated']);
			} else {
				$pUpDate = NULL;
			}
			
			// Payments
			if (($r['pStatus'] == PaymentStatusCode::Paid || $r['pStatus'] == PaymentStatusCode::VoidReturn) && $r['Is_Refund'] == 0) {
				// Sale
				// un-returned payments are dated on the update.
				if (is_null($pUpDate) === FALSE) {
					
					if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
						$paymentDate = $pUpDate;
					}
				}
				
				// Payment is in this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					
					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay += $r['il_Amount'];
					}
					
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], $r['il_Amount']);
					$totalPayment[$r['Item_Id']] += $r['il_Amount'];
				}
				
			// Refunds
			} else if ($r['pStatus'] == PaymentStatusCode::Paid && $r['Is_Refund'] == 1) {

				// Payment must be within the .
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					
					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay += $r['il_Amount'];
					}
				
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], $r['il_Amount']);
					$totalPayment[$r['Item_Id']] += $r['il_Amount'];
				}
				
			//Returns
			} else if ($r['pStatus'] == PaymentStatusCode::Retrn) {

				if (is_null($pUpDate)) {
					$this->recordError("Missing Last Updated. Payment Id = ". $r['idPayment']);
					continue;
				}
				
				// Returned during this period?
				if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
					// It is a return in this period.
					
					
					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay -= $r['il_Amount'];
					}
					
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], (0 - $r['il_Amount']));
					$totalPayment[$r['Item_Id']] -= $r['il_Amount'];
					
				}
				
				// Paid during this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {
					
					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$vIntervalPay += $r['il_Amount'];
					}
					
					$this->arrayAdd($baArray[$r['ba_Gl_Debit']]['paid'], $r['il_Amount']);
					$totalPayment[$r['Item_Id']] += $r['il_Amount'];

				}
			}
		}

		if ($record != NULL) {
						
			if ($vIntervalPay >= ($vIntervalCharge + $vPreIntervalCharge)) {
				$overPay += $vIntervalPay - ($vIntervalCharge + $vPreIntervalCharge);
				$intervalPay += $vIntervalCharge;
				$preIntervalPay += $vPreIntervalCharge;
			} else if ($vIntervalPay >= $vPreIntervalCharge) {
				$preIntervalPay += $vPreIntervalCharge;
				$intervalPay += ($vIntervalPay - $vPreIntervalCharge);
			} else {
				$preIntervalPay += $vIntervalPay;
			}

			$intervalCharge += $vIntervalCharge;
		}
		
		
		$tbl = new HTMLTable();
		
		$tbl->addHeaderTr(HTMLTable::makeTh('Lodging Payment Distribution', array('colspan'=>'2')));
		$tbl->addBodyTr(HTMLTable::makeTd('Payments to earlier months', array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($preIntervalPay, 2), array('style'=>'text-align:right;')));
		$tbl->addBodyTr(HTMLTable::makeTd('Payments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($intervalPay, 2), array('style'=>'text-align:right;')));
		$tbl->addBodyTr(HTMLTable::makeTd('Prepayments to future months', array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($overPay, 2), array('style'=>'text-align:right;')));
		$tbl->addBodyTr(HTMLTable::makeTd('Total', array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format(($totalPayment[ItemId::Lodging] + $totalPayment[ItemId::LodgingReversal]), 2), array('style'=>'text-align:right;')));

		$tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'2')));
		
		$prepaidCharges = $this->getPrePay();
		
		$unpaidCharges = $intervalCharge - $intervalPay - $this->getPrePay();
		
		$tbl->addBodyTr(HTMLTable::makeTd('Total charges for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($intervalCharge, 2), array('style'=>'text-align:right;')));
		$tbl->addBodyTr(HTMLTable::makeTd('Prepayments from earlier months', array('class'=>'tdlabel')) . HTMLTable::makeTd('(TBD)', array('style'=>'text-align:right;')));
		$tbl->addBodyTr(HTMLTable::makeTd('Payments for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($intervalPay, 2), array('style'=>'text-align:right;')));
		$tbl->addBodyTr(HTMLTable::makeTd('Unpaid Charges for ' . $monthArray[$this->startDate->format('n')][1], array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($unpaidCharges, 2), array('style'=>'text-align:right;')));
				
		return $this->createBAMarkup($baArray, $tableAttrs)
			. $tbl->generateMarkup($tableAttrs)
			. $this->statsPanel($dbh, $totalCatNites, $start, $end, $categories, 'Report_Category', $monthArray, $uS->guestLookups['Static_Room_Rate']['rb'][2]);
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
		
		
		return $tbl->generateMarkup($tableAttrs);
	}
	
	
	protected function statsPanel(\PDO $dbh, $totalCatNites, $start, $end, $categories, $rescGroup, $monthArray, $roomRate) {
		
		$totalOOSNites = 0;
		
		$stDT = new DateTime($start . ' 00:00:00');
		$enDT = new DateTime($end . ' 00:00:00');
		$numNights = $enDT->diff($stDT, TRUE)->days;
				
		$qu = "select r.idResource, rm.Category, rm.Type, rm.Report_Category, rm.Rate_Code, ifnull(ru.Start_Date,'') as `Start_Date`, ifnull(ru.End_Date, '') as `End_Date`, ifnull(ru.Status, 'a') as `RU_Status`
        from resource r left join
resource_use ru on r.idResource = ru.idResource and DATE(ru.Start_Date) < DATE('" . $enDT->format('Y-m-d') . "') and DATE(ru.End_Date) > DATE('" . $stDT->format('Y-m-d') . "')
left join resource_room rr on r.idResource = rr.idResource
left join room rm on rr.idRoom = rm.idRoom
where r.`Type` in ('" . ResourceTypes::Room . "','" . ResourceTypes::RmtRoom . "')
order by r.idResource;";
		
		$rstmt = $dbh->query($qu);
		
		$rooms = array();
		
		// Get rooms and oos days
		while ($r = $rstmt->fetch(PDO::FETCH_ASSOC)) {
			
			$nites = 0;
			
			if ($r['Start_Date'] != '' && $r['End_Date'] != '') {
				$arriveDT = new DateTime($r['Start_Date']);
				$arriveDT->setTime(0, 0, 0);
				$departDT = new DateTime($r['End_Date']);
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
			
		}
		
		// Filter out unavailalbe rooms and add up the nights
		$availableRooms = 0;
		$unavailableRooms = 0;
		
		foreach($rooms as $r) {
			
			foreach ($r as $c) {
				
				if (isset($c[ResourceStatus::Unavailable]) && $c[ResourceStatus::Unavailable] >= $numNights) {
					$unavailableRooms++;
					continue;
				}
				
				$availableRooms++;
				
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
		$sTbl->addHeaderTr(HTMLTable::makeTh('Parameter') . HTMLTable::makeTh('All ' . $availableRooms . ' Rooms'));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Room-Nights in ' . $monthArray[$stDT->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd($numUsefulNights, array('style'=>'text-align:center;')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Visit Nights in ' . $monthArray[$stDT->format('n')][1], array('class'=>'tdlabel'))
				. HTMLTable::makeTd($totalCatNites['All'], array('style'=>'text-align:center;')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Room Utilization', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(($numUsefulNights <= 0 ? '0' : number_format($totalCatNites['All'] * 100 / $numUsefulNights, 1)) . '%', array('style'=>'text-align:center;')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'2')));
		
		$sTbl->addBodyTr(HTMLTable::makeTd('Room Rate', array('class'=>'tdlabel'))
				. HTMLTable::makeTd('$'.number_format($roomRate, 2), array('style'=>'text-align:right;')));
		$sTbl->addBodyTr(HTMLTable::makeTd('Maximum Charge', array('class'=>'tdlabel'))
				. HTMLTable::makeTd('$'.number_format($roomRate * $numRoomNights, 2), array('style'=>'text-align:right;')));
		
		return $sTbl->generateMarkup();
		
	}

	protected function getPrePay() {
		$prePaid = 0;
		
		return $prePaid;
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
	
	public function getGlMarkup($tableAttrs) {
		return $this->glLineMapper->createMarkup($tableAttrs);
	}
	
	public function getBaMarkup($tableAttrs) {
		return $this->baLineMapper->createMarkup($tableAttrs);
	}
	
	public function getStartDay() {
		$iDay = intval($this->startDay, 10);
		$sDay = '';
		
		if ($iDay < 1 || $iDay > 28) {
			throw new Hk_Exception_Runtime('The Start-Day is not viable: ' . $iDay);
		}
		
		// Format with leading 0's
		if ($iDay < 10) {
			$sDay = '0' . $iDay;
		} else {
			$sDay = (string)$iDay;
		}
		return $sDay;
	}
	
	
}



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

	public function makeLine($glCode, $debitAmount, $creditAmount, $purchaseDate) {
		
		if (isset($this->totals[$glCode]) === FALSE) {
			$this->totals[$glCode]['Credit'] = $creditAmount;
			$this->totals[$glCode]['Debit'] = $debitAmount;
		} else {

			$this->totals[$glCode]['Credit'] += $creditAmount;
			$this->totals[$glCode]['Debit'] += $debitAmount;
		}
		
		$this->totalCredit += $creditAmount;
		$this->totalDebit += $debitAmount;
	}

	public function createMarkup($tableAttrs) {
		
		$tbl = new HTMLTable();
		$tbl->addHeaderTr(
				HTMLTable::makeTh('Item')
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
				HTMLTable::makeTd('SubTotals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(($totCredit == 0 ? '' : number_format($totCredit, 2)), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				. HTMLTable::makeTd(($totDebit == 0 ? '' : number_format($totDebit, 2)), array('style'=>'text-align:right;','class'=>'hhk-tdTotals '))
				);
		
		foreach ($this->getTotals() as $k => $t) {
			
			if (isset($this->pmCodes[$k]) === FALSE) {
				
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
				HTMLTable::makeTd('Totals', array('class'=>'tdlabel'))
				. HTMLTable::makeTd(number_format($totCredit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals'))
				. HTMLTable::makeTd(number_format($totDebit, 2), array('style'=>'text-align:right;','class'=>'hhk-tdTotals '))
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

