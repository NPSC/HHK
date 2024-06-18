<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

Class VoidRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments/cancel";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }
}