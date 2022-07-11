<?php
namespace HHK\House\Report;

interface ReportInterface {

    /**
     * Build the report query string, set $this->query
     */
    public function makeQuery():void;

    /**
     * Build your cFields array
     *
     * @return array
     */
    public function makeCFields():array;

    /**
     * Build your interior filter markup (wrapper, form and buttons are added via AbstractReport::generateFilterMarkup())
     *
     * @return array
     */
    public function makeFilterMkup():void;

    /**
     * Build your summary content, (wrapper + logo will be added by AbstractReport::generateSummaryMkup())
     *
     * @return string
     */
    public function makeSummaryMkup():string;

}
?>