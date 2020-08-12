<?php

namespace HHK\Member\Relation;

/**
 * RelationCode.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class RelationCode {

    private $code;
    private $title;

    public function __construct(array $codeArray) {
        $this->code = $codeArray["Code"];
        $this->title = $codeArray["Description"];
    }

    public function getCode() {
        return $this->code;
    }

    public function getTitle() {
        return $this->title;
    }

}
?>