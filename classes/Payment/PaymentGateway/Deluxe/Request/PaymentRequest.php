<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

Class PaymentRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }
}