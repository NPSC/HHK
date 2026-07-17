<?php
namespace HHK\House\Report;

interface ReportInterface {

    public function __construct(\PDO $dbh, array $filterOpts = []);
    
    /**
     * Build the report query string, set $this->query
     */
    public function makeQuery():void;

    /**
     * Build your fields array
     *
     * @return array
     */
    public function makeFields():array;

    /**
     * Build your interior filter markup (wrapper, form and buttons are added via AbstractReport::generateFilterMarkup())
     *
     * @return array
     */
    public function makeFilterMkup():void;

    public function makeFilterOptsMkup(): string;

    /**
     * Build your summary content, (wrapper + logo will be added by AbstractReport::generateSummaryMkup()). If you'd also like to add a stats box, set $this->statsMkup in here
     *
     * @return string
     */
    public function makeSummaryMkup():string;

    public function getInputSetReportName() :string;

    public function sendEmail(\PDO $dbh, string $emailAddress, string $subject, bool $dryRun): array;

}
?>