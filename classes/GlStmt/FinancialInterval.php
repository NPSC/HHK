<?php
namespace HHK\GlStmt;

use HHK\Common;
use HHK\SysConst\{InvoiceStatus, ItemId, PaymentStatusCode, RoomRateCategories};
use HHK\sec\Session;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\ItemPriceCode;


class FinancialInterval {

	protected $startDate;
	protected $endDate;

	protected $totalItemPayment;
	protected $totalCatNites;
	protected $categories;
	protected $payAmounts;

	protected $baArray;
	protected $errorMsg;


	public function __construct(\DateTimeInterface $startDate, \DateTimeInterface $endDate) {
		$this->startDate = $startDate;
		$this->endDate = $endDate;
	}

	/**
	 * Summary of collectData
	 * @param \PDO $dbh
	 * @param \HHK\Purchase\PriceModel\AbstractPriceModel $priceModel
	 * @param mixed $extraVisitsSQL
	 * @return StmtCalc
	 */
	public function collectData (\PDO $dbh, AbstractPriceModel $priceModel, $extraVisitsSQL) {

		$uS = Session::getInstance();

		$this->errorMsg = [];
		$this->totalCatNites = [];
		$this->totalItemPayment = [];
		$this->baArray = [];

		// Category Nights Counter
		$this->categories = Common::readGenLookupsPDO($dbh, 'Room_Category');
		$this->categories[] = array(0=>'', 1=>'(default)');

		foreach ($this->categories as $c) {
			$this->totalCatNites[$c[0]] = 0;
		}

		$this->totalCatNites['All'] = 0;

		// Payments by Item
		$istmt = $dbh->query("select idItem from item");
		while( $i = $istmt->fetch(\PDO::FETCH_NUM)) {
			$this->totalItemPayment[$i[0]] = 0;
		}

		// Third party
		$this->baArray['']['paid'] = 0;
		$this->baArray['']['pend'] = 0;


		$stmtCalc = new StmtCalc();
		$visitCalc = new VisitIntervalCalculator();


		$this->payAmounts = array();
		$serialId = 0;
		$visitId = 0;
		$record = NULL;


		$stmt = $dbh->query($this->makeQuery($extraVisitsSQL));
		while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

			$serial = ($r['idVisit'] * 100) + $r['Span'];

			if ($serialId != $serial) {
				// Span Change

				If ($visitId != $r['idVisit'] && $visitId != 0) {
					// Visit Change

					$stmtCalc->addVisit($visitCalc->closeInterval($record['Has_Future_Nights']), $serialId);

					// Testing only
					if ($stmtCalc->getIncome() != $stmtCalc->getFullIntervalCharge()) {
						$this->errorMsg[] = 'Visit ' . $visitId . ' breaks the balance. Sold to '. $record['Sold_To_Id'] . '.  Income = '.$stmtCalc->getIncome()
								. ' Full Charge = '.$stmtCalc->getFullIntervalCharge();
					}

					// Reset for next visit
					$visitCalc = new VisitIntervalCalculator();
					$visitId = $r['idVisit'];
				}

				$adjRatio = (1 + $r['Expected_Rate']/100);

				$this->totalCatNites[$r['Room_Category']] += $r['Actual_Interval_Nights'];
				$this->totalCatNites['All'] += $r['Actual_Interval_Nights'];

				//  Add up any pre-interval charges
				if ($r['Pre_Interval_Nights'] > 0) {

					// collect all pre-charges
					$priceModel->setCreditDays(0);
					$c = $priceModel->amountCalculator($r['Pre_Interval_Nights'], $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $r['PI_Guest_Nights']);
					$visitCalc->updatePreIntervalCharge($c * $adjRatio);

				}

				// Add up interval charges
				if ($r['Actual_Interval_Nights'] > 0) {

					// Reated Charges
					$priceModel->setCreditDays($r['Pre_Interval_Nights']);
					$charge = $priceModel->amountCalculator($r['Actual_Interval_Nights'], $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $r['Actual_Guest_Nights']) * $adjRatio;

					// Full charge
					$priceModel->setCreditDays($r['Pre_Interval_Nights']);
					$fullCharge = $priceModel->amountCalculator($r['Actual_Interval_Nights'], 0, RoomRateCategories::FullRateCategory, $uS->guestLookups['Static_Room_Rate'][$r['Rate_Code']][2], $r['Actual_Guest_Nights']);

					$visitCalc->updateIntervalCharge($charge, $fullCharge, $r['Rate_Category']);

				}
			}

			$serialId = $serial;
			$visitId = $r['idVisit'];
			$record = $r;

			// Unpaid invoices
			$invDate = new \DateTime($r['Invoice_Date']);
			if ($r['Invoice_Status'] == InvoiceStatus::Unpaid
					&& $invDate >= $this->startDate && $invDate < $this->endDate) {
				$this->arrayAdd($this->baArray[$r['ba_Gl_Debit']]['pend'], $r['il_Amount']);
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
			if (isset($this->payAmounts[$r['idPayment']]) === FALSE) {
				$this->payAmounts[$r['idPayment']] = $r['pAmount'];
			}


			// Sale
			if (($r['pStatus'] == PaymentStatusCode::Paid || $r['pStatus'] == PaymentStatusCode::VoidReturn) && $r['Is_Refund'] == 0) {

				// un-returned payments are dated on the update.
				if (is_null($pUpDate) === FALSE) {
					$paymentDate = $pUpDate;
				}

				$this->payAmounts[$r['idPayment']] -= $ilAmt;

				// Payment is in this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {

					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						// Lodging Amount
						$visitCalc->updateIntervalPay($ilAmt);
					} else if ($r['Item_Id'] == ItemId::Waive) {
						// waive amount.
						$visitCalc->updateIntervalWaiveAmt($ilAmt);
					}

					$this->arrayAdd($this->baArray[$r['ba_Gl_Debit']]['paid'], $ilAmt);
					$this->totalItemPayment[$r['Item_Id']] += $ilAmt;

				} else if ($paymentDate < $this->startDate) {
					// Pre payment from before

					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$visitCalc->updatePreIntervalPay($ilAmt);
					} else if ($r['Item_Id'] == ItemId::Waive) {
						// waive amount.
						$visitCalc->updatePreIntervalWaiveAmt($ilAmt);
					}

				}

			// Refunds
			} else if ($r['pStatus'] == PaymentStatusCode::Paid && $r['Is_Refund'] == 1) {

				// payment is positive in this case.
				$this->payAmounts[$r['idPayment']] += $ilAmt;

				// Payment must be within the .
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {

					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$visitCalc->updateIntervalPay($ilAmt);
					}

					$this->arrayAdd($this->baArray[$r['ba_Gl_Debit']]['paid'], $ilAmt);
					$this->totalItemPayment[$r['Item_Id']] += $ilAmt;

				} else if ($paymentDate < $this->startDate) {
					// Pre payment from before

					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$visitCalc->updatePreIntervalPay($ilAmt);
					}

				}

			//Returns
			} else if ($r['pStatus'] == PaymentStatusCode::Retrn) {
				// The invoice line amount (ilAmt) is positive.

				if (is_null($pUpDate)) {
					$this->recordError("Missing Last Updated. Payment Id = ". $r['idPayment']);
					continue;
				}

				$this->payAmounts[$r['idPayment']] -= $ilAmt;

				// Returned during this period?
				if ($pUpDate >= $this->startDate && $pUpDate < $this->endDate) {
					// It is a return in this period.


					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$visitCalc->updateIntervalPay(0 - $ilAmt);
					} else if ($r['Item_Id'] == ItemId::Waive) {
						// Reduce charge by waive amount.
						$visitCalc->updateIntervalWaiveAmt(0 - $ilAmt);
					}

					$this->arrayAdd($this->baArray[$r['ba_Gl_Debit']]['paid'], (0 - $ilAmt));
					$this->totalItemPayment[$r['Item_Id']] -= $ilAmt;

				} else if ($pUpDate < $this->startDate) {
					// Pre return from before

					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$visitCalc->updatePreIntervalPay(0 - $ilAmt);
					} else if ($r['Item_Id'] == ItemId::Waive) {
						// Reduce charge by waive amount.
						$visitCalc->updatePreIntervalWaiveAmt(0 - $ilAmt);
					}
				}

				// Paid during this period?
				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {

					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$visitCalc->updateIntervalPay($ilAmt);
					} else if ($r['Item_Id'] == ItemId::Waive) {
						// waive amount.
						$visitCalc->updateIntervalWaiveAmt($ilAmt);
					}

					$this->arrayAdd($this->baArray[$r['ba_Gl_Debit']]['paid'], $ilAmt);
					$this->totalItemPayment[$r['Item_Id']] += $ilAmt;

				} else if ($paymentDate < $this->startDate) {
					// Pre payment from before

					if ($r['Item_Id'] == ItemId::Lodging || $r['Item_Id'] == ItemId::LodgingReversal) {
						$visitCalc->updatePreIntervalPay($ilAmt);;
					} else if ($r['Item_Id'] == ItemId::Waive) {
						// Reduce charge by waive amount.
						$visitCalc->updatePreIntervalWaiveAmt($ilAmt);
					}
				}

			// Discounts
			} else if ($r['idPayment'] == 0 && $r['Invoice_Status'] == InvoiceStatus::Paid
					&& $r['Item_Id'] = ItemId::Discount) {

				$paymentDate = new \DateTime($r['Invoice_Date']);

				if ($paymentDate >= $this->startDate && $paymentDate < $this->endDate) {

					// Discounts
					$this->totalItemPayment[$r['Item_Id']] += abs($ilAmt);

					// Reduces the charges.
					$visitCalc->updateIntervalDiscount($ilAmt);

				} else if ($paymentDate < $this->startDate) {
					$visitCalc->updatePreIntervalDiscount($ilAmt);
				}
			}
		}

		if ($record != NULL) {
			$stmtCalc->addVisit($visitCalc->closeInterval($record['Has_Future_Nights']), $serialId);
		}

		return $stmtCalc;
	}

	protected function makeQuery($extraVisitsSQL) {

		$start = $this->startDate->format('Y-m-d');
		$end = $this->endDate->format('Y-m-d');

		$query = "select
	v.idVisit,
	v.Span,
	v.Arrival_Date,
	v.Expected_Departure,
	ifnull(v.Actual_Departure, '') as `Actual_Departure`,
	v.Span_Start,
	ifnull(v.Span_End, '') as `Span_End`,
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
	CASE
		WHEN DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) > DATE('$end')
		THEN 1
		ELSE 0
	END AS `Has_Future_Nights`, ".
	$this->getGuestNightsSQL($start, $end) ."
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
				and Arrival_Date < '$end'
				and ifnull(Span_End,
					case
					when now() > Expected_Departure then now()
					else Expected_Departure
                	end) >= '$start'
		) " .
		$extraVisitsSQL .
		" order by v.idVisit, v.Span";

		return $query;
	}

	protected function getGuestNightsSQL($start, $end) {

		$uS = Session::getInstance();

		if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
			return "CASE WHEN DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start') THEN 0
        WHEN DATE(v.Span_Start) >= DATE('$end') THEN 0
        ELSE (SELECT SUM(DATEDIFF(CASE WHEN DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) > DATE('$end')
        THEN DATE('$end') ELSE DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) END,
        CASE WHEN DATE(s.Span_Start_Date) < DATE('$start') THEN DATE('$start') ELSE DATE(s.Span_Start_Date) END))
        FROM stays s WHERE s.idVisit = v.idVisit AND s.Visit_Span = v.Span)
    	END AS `Actual_Guest_Nights`,
    	CASE WHEN DATE(v.Span_Start) >= DATE('$start') THEN 0 WHEN DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start')
    	THEN (SELECT SUM(DATEDIFF(DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))), DATE(s.Span_Start_Date)))
    	FROM stays s WHERE s.idVisit = v.idVisit AND s.Visit_Span = v.Span)ELSE (SELECT SUM(DATEDIFF(CASE
      	WHEN DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) > DATE('$start') THEN DATE('$start')
      	ELSE DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) END, DATE(s.Span_Start_Date)))
        FROM stays s WHERE s.idVisit = v.idVisit AND s.Visit_Span = v.Span) END AS `PI_Guest_Nights`, ";
		}

		return " 0 as `Actual_Guest_Nights`, 0 as `PI_Guest_Nights`, ";

	}

	protected function arrayAdd(&$arrayMember, $amount) {

		if (isset($arrayMember)) {
			$arrayMember += $amount;
		} else {
			$arrayMember = $amount;
		}

	}

	public function getRoomCategories() {
		return $this->categories;
	}

	public function getPayAmounts() {
		return $this->payAmounts;
	}

	public function getBaArray() {
		return $this->baArray;
	}

	public function getTotalCatNites() {
		return $this->totalCatNites;
	}

	public function getTotalItemPayment() {
		return $this->totalItemPayment;
	}

	protected function recordError($error) {
		$this->errorMsg[] = $error;
	}

	public function getErrorArray() {
		return $this->errorMsg;
	}
}
