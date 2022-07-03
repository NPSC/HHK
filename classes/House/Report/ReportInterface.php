<?php
namespace HHK\House\Report;

interface ReportInterface {

    /**
     * Build the report query
     *
     * Be sure to set $this->query
     */
    public function buildQuery():void;

    /**
     * Build your cFields array
     *
     * @return array
     */
    public function makeCFields():array;

}
?>