<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

Class AuthorizeRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments/authorize";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }
}