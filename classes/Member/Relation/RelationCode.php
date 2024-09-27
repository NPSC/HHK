<?php

namespace HHK\Member\Relation;

/**
 * RelationCode.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017, 2018-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class RelationCode {

    private $code;
    private $title;

    /**
     * Summary of __construct
     * @param mixed $codeArray
     */
    public function __construct(array $codeArray) {
        $this->code = $codeArray["Code"];
        $this->title = $codeArray["Description"];
    }

    /**
     * Summary of getCode
     * @return mixed
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * Summary of getTitle
     * @return mixed
     */
    public function getTitle() {
        return $this->title;
    }

}
?>