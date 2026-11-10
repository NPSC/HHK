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
     * @return void
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

    /**
     * Label for this report in the Email Report cron job's report list, or null to leave it out.
     * Only opt in (override AbstractReport's null default) if the report produces useful output from
     * its stored filter options alone, with no interactive filter form.  EmailReportJob finds reports
     * through the Composer classmap, so run `composer dump-autoload -o` after adding a report class.
     *
     * @return string|null
     */
    public static function getEmailReportTitle(): ?string;

    /**
     * Filter option definitions, keyed by option name
     *
     * @return array
     */
    public function getFilterOpts(): array;

}
?>