<?php
namespace HHK\AlertControl;

use HHK\HTMLControls\HTMLContainer;

/**
 * AlertControl.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
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

?>