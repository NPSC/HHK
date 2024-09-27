<?php

namespace HHK\Payment\PaymentGateway\CreditPayments;

use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Exception\PaymentException;

/**
 * CreditPayments.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019, 2020-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


abstract class AbstractCreditPayments {

    const STATUS_APPROVED = 'AP';
    const STATUS_DECLINED = 'DECLINED';
    const STATUS_ERROR = 'Error';

    /**
     * Summary of processReply
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractCreditResponse $pr
     * @param string $userName
     * @param mixed $payRs
     * @param int $attempts
     * @return AbstractCreditResponse
     */
    public static function processReply(\PDO $dbh, AbstractCreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {

        // Transaction status
        switch ($pr->getStatus()) {

            case self::STATUS_APPROVED:
                $pr = static::caseApproved($dbh, $pr, $userName, $payRs, $attempts);
                break;

            case self::STATUS_DECLINED:
                $pr = static::caseDeclined($dbh, $pr, $userName, $payRs, $attempts);
                break;

            default:
                static::caseOther($dbh, $pr, $userName, $payRs);

        }

        return $pr;
    }


    /**
     * Summary of caseApproved
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractCreditResponse $pr
     * @param mixed $userName
     * @param mixed $payRs
     * @param mixed $attempts
     * @throws \HHK\Exception\PaymentException
     * @return AbstractCreditResponse
     */
    protected static function caseApproved(\PDO $dbh, AbstractCreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        throw new PaymentException('Payments::caseApproved Method not overridden!');
    }

    /**
     * Summary of caseDeclined
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractCreditResponse $pr
     * @param mixed $userName
     * @param mixed $payRs
     * @param mixed $attempts
     * @return AbstractCreditResponse
     */
    protected static function caseDeclined(\PDO $dbh, AbstractCreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        return $pr;
    }
    /**
     * Summary of caseOther
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractCreditResponse $pr
     * @param mixed $userName
     * @param mixed $payRs
     * @param mixed $attempts
     * @return AbstractCreditResponse
     */
    protected static function caseOther(\PDO $dbh, AbstractCreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        return $pr;
    }

}
?>