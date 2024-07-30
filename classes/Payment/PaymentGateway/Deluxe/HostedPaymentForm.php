<?php

namespace HHK\Payment\PaymentGateway\Deluxe;

use HHK\sec\Session;

/**
 * HostedPaymentForm.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class HostedPaymentForm {

    /**
     * Summary of sendToPortal
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway $gway
     * @param int $idPayor
     * @param int $idGroup
     * @throws \HHK\Exception\PaymentException
     * @return array
     */
    public static function sendToPortal(\PDO $dbh, DeluxeGateway $gway, $idPayor, $idGroup, $manualKey, $postbackUrl, $cmd, $invoiceNum = 0) {

        $uS = Session::getInstance();
        $dataArray = array();
        $trace = FALSE;

        $creds = $gway->getCredentials();

        $dataArray["type"] = "deluxe-hpf";
        $dataArray["idPayor"] = $idPayor;
        $dataArray["idGroup"] = $idGroup;
        $dataArray["hpfToken"] = $creds['hpfAccessToken'];
        $dataArray["useSwipe"] = $creds["Retry_Count"]; // Retry_Count maps to useSwipe in site config
        $dataArray["pbp"] = html_entity_decode($postbackUrl);
        $dataArray["cmd"] = $cmd;
        $dataArray["invoiceNum"] = $invoiceNum;

        return $dataArray;
    }
}