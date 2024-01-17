<?php

namespace HHK\Notification\SMS;

use HHK\Exception\RuntimeException;
use HHK\House\Visit\Visit;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Labels;
use HHK\SysConst\VisitStatus;

abstract class AbstractMessages
{

    protected \PDO $dbh;
    protected string $accountPhone;

    public function __construct(\PDO $dbh, string $accountPhone = "")
    {
        $this->dbh = $dbh;
        $this->accountPhone = $accountPhone;
    }

    public function getVisitMessagesMkup(int $idVisit, int $idSpan){

        $mkup = "";

        $stmt = $this->dbh->prepare("select distinct n.idName, n.Name_Full, np.Phone_Num, np.Phone_Search from stays s left join name n on s.idName = n.idName left join name_phone np on s.idName = np.idName where s.idVisit = :idVisit and s.Visit_Span = :idSpan and s.Status = :status and np.Phone_Search != '' and np.Phone_Code = 'mc';");

        $stmt->execute([
            ':idVisit' => $idVisit,
            ':idSpan' => $idSpan,
            ':status' => VisitStatus::Active
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $li = '';
        $allRecipientsMkup = HTMLContainer::generateMarkup("h5", "To");

        foreach($rows as $row){
            $li .= HTMLContainer::generateMarkup("li", HTMLContainer::generateMarkup("a", $row["Name_Full"], array("href" => "#msgsTabContent")), array("data-idName" => $row["idName"], "data-name"=>$row["Name_Full"], "data-phone" => $row["Phone_Num"], "data-phone-unformatted" => $row["Phone_Search"]));
            $allRecipientsMkup .= HTMLContainer::generateMarkup("div", $row["Name_Full"] . " - " . $row["Phone_Num"], array("class"=>"ui-widget-content ui-corner-all recipient"));
        }

        $li .= HTMLContainer::generateMarkup("li", HTMLContainer::generateMarkup("a", "Current " . Labels::getString("MemberType", "guest", "Guest") . 's', array("href" => "#allGuestsTabContent")), array("data-idVisit" => $idVisit));

        $ul = HTMLContainer::generateMarkup("ul", $li, array("class"=>"ui-tabs-nav ui-corner-all ui-helper-reset ui-helper-clearfix ui-widget-header"));

        $tabContent = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h4", "", array("class"=>"msgTitle")).
            HTMLContainer::generateMarkup("div", '<div id="hhk-loading-spinner" class="center p-3"><img src="../images/ui-anim_basic_16x16.gif"></div>', array("class"=>"msgsContainer loading")).
            HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("textarea", "", array("class"=>"ui-widget-content ui-corner-all", "maxlength"=>"160")).
                HTMLContainer::generateMarkup("button", "Send Message", array("class"=>"ui-button ui-corner-all"))
            , array("class"=>"newMsg hhk-flex"))
        , array("id"=>"msgsTabContent", "class"=>"hhk-overflow-x ui-tabs-panel ui-corner-bottom ui-widget-content"));

        //all guests tab
        $tabContent .= HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h4", "", array("class"=>"msgTitle")).
            HTMLContainer::generateMarkup("div", $allRecipientsMkup, array("class"=>"allRecipients ui-widget-content ui-corner-all")).
            HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("textarea", "", array("class"=>"ui-widget-content ui-corner-all", "maxlength"=>"160")).
                HTMLContainer::generateMarkup("button", "Send Message", array("class"=>"ui-button ui-corner-all"))
            , array("class"=>"newMsg hhk-flex"))
        , array("id"=>"allGuestsTabContent", "class"=>"hhk-overflow-x ui-tabs-panel ui-corner-bottom ui-widget-content"));

        $mkup .= HTMLContainer::generateMarkup("div", $ul . $tabContent, array("id" => "smsTabs", "class" => "ui-tabs ui-corner-all, ui-widget ui-widget-content ui-tabs-vertical ui-helper-clearfix"));

        return $mkup;
    }

    public function getMockupMessages(int $idName){
        return [
            "idName" => "123",
            "Name_Full"=>"Matt Smith",
            "phone"=>"(555) 666-5656",
            "msgs"=>[
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"When is check in time?",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MI",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"3pm, See you soon!",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MO",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"Where's the entrance?",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MI",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"123 Main St",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MO",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"Dinner starts at 6pm in the dining room",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MO",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"We'll be there!",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MI",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"When is check in time?",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MI",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"3pm, See you soon!",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MO",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"Where's the entrance?",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MI",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"123 Main St",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MO",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"Dinner starts at 6pm in the dining room",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MO",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"We'll be there!",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MI",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
                [
                    "id"=>"507f191e810c19729de860ea",
                    "subject"=>"Some message from SimpleTexting",
                    "text"=>"What happens when the message is very long, like so long that you dread even reading it because it hurts your eyes and your brain just shuts off?",
                    "contactPhone"=>"8001234567",
                    "accountPhone"=>"8005551234",
                    "directionType"=>"MI",
                    "timestamp"=>"2024-01-15T23:20:08.489Z",
                    "referenceType"=>"API_SEND",
                    "category"=>"SMS",
                    "mediaItems"=>[]
                ],
            ]
        ];
    }

}
?>