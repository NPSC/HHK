<?php

namespace HHK\Member;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\SysConst\GLTableNames;
use HHK\SysConst\MemBasis;
use HHK\SysConst\MemDesignation;
use HHK\SysConst\RelLinkType;
use HHK\sec\Session;
use HHK\Exception\InvalidArgumentException;
use HHK\Exception\RuntimeException;
use HHK\Member\Relation\Employees;

/**
 * OrgMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of OrgMember
 * @package name
 * @author Eric
 */
class OrgMember extends AbstractMember {

    protected $employees = array();


    protected function getDefaultMemBasis() {
        return MemBasis::Company;
    }

    /**
     *
     * @return MemDesignation
     */
    public function getMemberDesignation() {
        return MemDesignation::Organization;
    }

    public function getMemberName() {
        return $this->get_company();
    }


    /**
     *
     * @param \PDO $dbh
     * @return string
     */
    public function createMarkupTable() {

        $uS = Session::getInstance();
        $idPrefix = $this->getIdPrefix();

        $table = new HTMLTable();
        $table->addHeaderTr(
                HTMLContainer::generateMarkup('th', 'Id')
                . HTMLContainer::generateMarkup('th', 'Organization Name')
                . HTMLContainer::generateMarkup('th', 'Website', array('colspan'=>'2'))
                . HTMLContainer::generateMarkup('th', 'Status')
                . HTMLContainer::generateMarkup('th', 'Basis', array('id'=>$idPrefix.'basisth'))
                );

        // Id
        $tr = HTMLContainer::generateMarkup('td', ($this->nameRS->idName->getStoredVal() == 0 ? '' : $this->nameRS->idName->getStoredVal()));

        // Org Name
        $tr .= HTMLContainer::generateMarkup('td', HTMLInput::generateMarkup($this->nameRS->Company, array('name'=>$idPrefix.'txtCoName', 'size'=>'30')));

        // website
        $goto = HTMLContainer::generateMarkup('span', '', array('id'=>'goWebSite', 'class'=>'ui-icon ui-icon-circle-arrow-e', 'style'=>'float: left; margin-right:.3em;'));
        $tr .= HTMLContainer::generateMarkup('td', HTMLInput::generateMarkup($this->nameRS->Web_Site, array('name'=>$idPrefix.'txtWebSite', 'size'=>'30')))
                .HTMLContainer::generateMarkup('td', $goto, array('class'=>'hhk-gotoweb', 'title'=>'Go to the website.'));


        // Status
        $tr .= HTMLContainer::generateMarkup('td', HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup(removeOptionGroups($uS->nameLookups[GLTableNames::MemberStatus]),
                        $this->nameRS->Member_Status, FALSE), array('name'=>$idPrefix.'selStatus')));

        // Basis
        $basis = array();
        foreach ($uS->nameLookups[GLTableNames::MemberBasis] as $b) {
            if ($b[AbstractMember::SUBT] == $this->getMemberDesignation()) {
                $basis[$b[AbstractMember::CODE]] = $b;
            }
        }
        $tr .= HTMLContainer::generateMarkup('td', HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup(removeOptionGroups($basis),
                        $this->nameRS->Member_Type, FALSE), array('name'=>$idPrefix.'selMbrType')),
                array('id'=>$idPrefix.'basistd'));

        $table->addBodyTr($tr);

        return $table->generateMarkup();
    }

    public function createMiscTabsMarkup(\PDO $dbh) {

        $panels = "";
        $tabs = "";
        $attrs = array('id'=>'adminTab', 'class'=>'ui-tabs-hide');

        $panels .= HTMLContainer::generateMarkup(
                'div',
                $this->createAdminPanel(),
                $attrs);

        $excl = $this->createExcludesPanel($dbh);
        $attrs['id'] = 'excludesTab';
        $panels .= HTMLContainer::generateMarkup(
                'div',
                $excl['markup'],
                $attrs);

        $tabs .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', 'Admin', array('href'=>'#adminTab', 'title'=>'Administrative Details'))
                );

        $tabs .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', $excl['tabIcon'] . 'Exclude', array('href'=>'#excludesTab', 'title'=>'Exclude Addresses'))
                );

                // wrap tabs in a UL
        $ul = HTMLContainer::generateMarkup('ul', $tabs);

        return $ul . $panels;

    }

    public function createDemographicsPanel(\PDO $dbh) {
        return '';
    }

    public function createRelationsTabs(array $rel, $page = "NameEdit.php") {


        $relTab = HTMLContainer::generateMarkup('div',$rel[RelLinkType::Employee]->createMarkup($page), array('style'=>'float:left; '));

//        $ul = HTMLContainer::generateMarkup('ul',
//            HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Employees', array('href'=>'#empl')))
//            );

        $relDiv = HTMLContainer::generateMarkup('div', $relTab, array('id'=>'empl', 'class'=>'ui-tabs-hide'));

        return $relDiv;
    }


    public function loadRealtionships(\PDO $dbh) {

       return array(
            RelLinkType::Employee => new Employees($dbh, $this->get_idName())
            );
    }



    /**
     *
     * @param \PDO $dbh
     * @param array $post
     */
    protected function processMember(\PDO $dbh, array $post) {
        // Convenience var
        $n = $this->nameRS;

        if (isset($post['txtCoName'])) {
            $n->Company->setNewVal(trim(filter_var($post['txtCoName'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
        }

        if (isset($post['txtWebSite'])) {
            $n->Web_Site->setNewVal(trim(filter_var($post['txtWebSite'], FILTER_SANITIZE_URL)));
        }



    }

    public function getAssocDonorLabel() {
        return "Employee";
    }

    public function getAssocDonorList(array $rel) {
        $rA = array();
        $emps = $rel[RelLinkType::Employee];
        foreach ($emps->getRelNames() as $n) {
            $rA[$n['Id']] = array(0=>$n['Id'], 1=>$n['Name']);
        }
        return $rA;
    }


    //
    // over rides of params
    //
    public function set_memberRcrd($v) {
        if ($v == 1 || $v == TRUE) {
            throw new InvalidArgumentException("Organization Member Record cannot be set to Individual.");
        }
    }

    public function set_firstName($firstName) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_firstName() {
        return '';
    }

    public function set_middleName($middleName) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_middleName() {
        return '';
    }

    public function set_lastName($lastName) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_lastName() {
        return '';
    }

    public function set_lastFirst($lastFirst) {

    }

    public function get_lastFirst() {
        return $this->nameRS->Company->getStoredVal();
    }

    public function set_nickName($nickName) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_nickName() {
        return '';
    }

    public function set_fullName($fullName) {

    }

    public function get_fullName() {
        return $this->nameRS->Company->getStoredVal();
    }

    public function set_previousName($previousName) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_previousName() {
        return '';
    }

    public function set_sex($sex) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_sex() {
        return '';
    }

    public function set_suffix($suffix) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_suffix() {
        return '';
    }

    public function set_prefix($prefix) {
        throw new RuntimeException("Not Supported.");
    }

    public function get_prefix() {
        return '';
    }

    public function set_companyId($companyId) {
        throw new RuntimeException("Company_Id is Not Supported.");
    }

    public function get_companyId() {
        return '';
    }

    public function set_companyCareOf($v) {
        throw new RuntimeException("Company Care/Of is Not Supported.");
    }

    public function get_companyCareOf() {
        return '';
    }

}
?>