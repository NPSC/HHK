<?php
/**
 * alertMessage.php
 *
 * @category  utility
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class AlertControl {

    const None = 0;
    const Alert = 1;
    const Notice = 2;
    const Info = 3;
    const Help = 4;
    const Success = 5;

    const Id_Message = 'msg';
    const Id_Icon = 'icon';
    const Id_Control = 'ctrl';
    const Id_Style = 'style';

    public static function makeJsonPackage($htmlIdPrefix) {
        return array(
            'Id_Control' => $htmlIdPrefix . self::Id_Control,
            'Id_Message' => $htmlIdPrefix . self::Id_Message,
            'Id_Icon' => $htmlIdPrefix . self::Id_Icon,
            'Id_Style' => $htmlIdPrefix . self::Id_Style,
            'Icons' => self::getIconClasses(),
            'States' => self::getStateClasses()
        );
    }

    protected static function getIconClasses() {
        return array(
            self::None=>"ui-icon ui-icon-info",
            self::Alert=>"ui-icon ui-icon-alert",
            self::Notice=>"ui-icon ui-icon-notice",
            self::Info=>"ui-icon ui-icon-info",
            self::Help=>"ui-icon ui-icon-help",
            self::Success=>"ui-icon ui-icon-info");
    }

    protected static function getStateClasses() {
        return array(
            self::None=>"",
            self::Alert=>"ui-state-error",
            self::Notice=>"ui-state-highlight",
            self::Info=>"ui-state-highlight",
            self::Help=>"ui-state-highlight",
            self::Success=>"ui-state-highlight");
    }

    public static function createMarkup($htmlIdPrefix, $context = 0, $message = "", $show = FALSE) {

        $imageIcons = self::getIconClasses();
        $mState = self::getStateClasses();

        $mPrefix = array(
            self::None=>" ",
            self::Alert=>"<strong>Alert: </strong>",
            self::Notice=>"<strong>Notice: </strong>",
            self::Info=>"<strong>Info: </strong>",
            self::Help=>"<strong>Help: </strong>",
            self::Success=>"<strong>Success: </strong>");


        // Inside mesasge construction
        $par = HTMLContainer::generateMarkup('p',
            HTMLContainer::generateMarkup('span', '',
                    array('id'=>$htmlIdPrefix.self::Id_Icon, 'class'=>$imageIcons[$context], 'style'=>'float: left; margin-right: .3em;'))
            .HTMLContainer::generateMarkup('span', $mPrefix[$context].$message,
                    array('id'=>$htmlIdPrefix.self::Id_Message, 'class'=>'ui-widget'))
        );

        // Show Control?
        if ($show) {
            $display = 'display:block;';
        } else {
            $display = 'display:none;';
        }
        // Outside shell
        return HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('div', $par,
                        array('id'=>$htmlIdPrefix.self::Id_Style, 'class'=>$mState[$context].' ui-corner-all', 'style'=>'padding: .2em .7em;')),
                array('id'=>$htmlIdPrefix.self::Id_Control, 'class'=>'ui-widget', 'style'=>$display)
        );

    }

}

class alertMessage {

    private $mText = "";
    private $mContext = 0;
    private $mId = "";
    private $sDisplay = "";
    private $styleId = "";
    private $iconId = "";
    private $txtSpanId = "";
    private $maxWth = '';

    const None = 0;
    const Alert = 1;
    const Notice = 2;
    const Info = 3;
    const Help = 4;
    const Success = 5;

    protected $imageIcons = array(0=>"ui-icon-info", 1=>"ui-icon-alert", 2=>"ui-icon-notice", 3=>"ui-icon-info", 4=>"ui-icon-help", 5=>"ui-icon-info");
    protected $mPrefix = array(0=>"", 1=>"<strong>Alert: </strong>", 2=>"<strong>Notice: </strong>", 3=>"<strong>Info: </strong>", 4=>"<strong>Help: </strong>", 5=>"<strong>Success: </strong>");
    protected $mState = array(0=>"ui-state-highlight", 1=>"ui-state-error", 2=>"ui-state-highlight", 3=>"ui-state-highlight", 4=>"ui-state-highlight", 5=>"ui-state-highlight");

    function  __construct($controlId) {
        $this->mId = $controlId;
    }

    public function appendText($moreText) {
        $this->mText .= $moreText;
    }

    public function createMarkup($message = "", $maxWidth = '450px') {

        if ($message != "") {
            $this->set_Text($message);
        }

        $this->maxWth = $maxWidth;

        if ($this->get_Text() != "") {
            $resultMessage = "<div ".$this->get_ControlId()." class='ui-widget' " . $this->get_DisplayAttr() . ">
            <div ".$this->get_styleId()." class='" . $this->mState[$this->mContext] . " ui-corner-all' style='padding: .2em .7em;'>
                    <p ><span ".$this->get_iconId()." class='ui-icon ".$this->imageIcons[$this->mContext]."' style='float: left; margin-right: .3em;'></span>".
                    "<span ".$this->get_txtSpanId()." >".$this->mPrefix[$this->mContext] .  $this->get_Text() . "</span></p>
            </div></div>";
        }
        else {
            $resultMessage = "";
        }
        return $resultMessage;
   }

    // Returns the markup for an HTML control's id attribute.
    protected function getIdMarkup($idText) {
        if ($idText != "")
            $dis =  " id='".$idText."' ";
        else
            $dis = "";

        return $dis;

    }


//---------------------------------------------------------
    public function set_Text($v) {
        $this->mText = $v;
    }
    public function get_Text() {
        return $this->mText;
    }
//---------------------------------------------------------
    public function set_Context($v) {
        if ($v >= 0 && $v < count($this->mState))
            $this->mContext = $v;
        else
            $this->mContext = 0;

    }
    public function get_Context() {
        return $this->mContext;
    }
//---------------------------------------------------------
    public function set_DisplayAttr($v) {
        $this->sDisplay = $v;
    }

    public function get_DisplayAttr() {

        if ($this->sDisplay != "") {
            $dis =  " style='display:".$this->sDisplay."; max-width=".$this->maxWth.";' ";
        } else {
            $dis = " style='max-width=".$this->maxWth.";' ";
        }

        return $dis;
    }
//---------------------------------------------------------
    public function get_ControlId() {
        return $this->getIdMarkup($this->mId);
    }
//---------------------------------------------------------
    public function set_styleId($v) {
        $this->styleId = $v;
    }
    public function get_styleId() {
        return $this->getIdMarkup($this->styleId);
    }
//---------------------------------------------------------
    public function set_iconId($v) {
        $this->iconId = $v;
    }
    public function get_iconId() {
        return $this->getIdMarkup($this->iconId);
    }
//---------------------------------------------------------
    public function set_txtSpanId($v) {
        $this->txtSpanId = $v;
    }
    public function get_txtSpanId() {
        return $this->getIdMarkup($this->txtSpanId);
    }

}
?>
