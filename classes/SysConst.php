<?php
/**
 * SysConst.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ActivityTypes {
    const Donation = 'don';
    const Volunteer = 'vol';
}

class Address_Purpose {
    const Home = '1';
    const Work = '2';
    const Alt = '3';
    const Office = '4';
    const Billing = 'b';
}

class Attribute_Types {
    const Room = '1';
    const Hospital = '2';
    const House = '3';
    const Resource = '4';
}

class BillStatus {
    const Billed = 'b';
}

class CampaignType {
    const Normal = 'as';
    const Percent = 'pct';
    const InKind = 'ink';
    const Scholarship = 'sch';
}

class Constraint_Type {
    const Reservation = 'rv';
    const Hospital = 'hos';
    const Visit = 'v';
}

class Default_Settings {
    const Rate_Category = 'e';
    const Fixed_Rate_Category = 'f';
}

class Email_Purpose {
    const Home = '1';
    const Work = '2';
    const Alt = '3';
    const Office = '4';
}

class ExcessPay {
    const RoomFund = 'd';
    const Hold = 'e';
    const Ignore = 'i';
    const Refund = 'r';
}

class FinAppStatus {
    const Granted = 'a';
    const Denied = 'n';
}

class GL_TableNames {
    const AddrPurpose = 'Address_Purpose';
    const EmailPurpose = 'Email_Purpose';
    const PhonePurpose = 'Phone_Type';
    //const Gender = 'Gender';
    const MemberBasis = 'Member_Basis';
    const MemberStatus = 'mem_status';
    const NamePrefix = 'Name_Prefix';
    const NameSuffix = 'Name_Suffix';
    const RoleCode = 'Role_Codes';
    const PatientRel = 'Patient_Rel_Type';
    const RelTypes = 'rel_type';
    const Hospital = 'Hospitals';
    const RescType = 'Resource_Type';
    const RescStatus = 'Resource_Status';
    const PayType = 'Pay_Type';
//    const WL_Status = 'WL_Status';
//    const WL_Final_Status = 'WL_Final_Status';
//    const FeesPayType = 'FeesPayType';
//    const KeyDispositions = 'Key_Disposition';
    const SalutationCodes = 'Salutation';
    //const AgeBracket = 'Age_Bracket';
    //const IncomeBracket = 'Income_Bracket';
    //const Ethnicity = 'Ethnicity';
    //const SpecialNeeds = 'Special_Needs';

    const RoomType = 'Room_Type';
    const RoomStatus = 'Room_Status';
    const Patient = 'Patient';
    const RoomCategory = 'Room_Category';
    const KeyDepositCode = 'Key_Deposit_Code';
}

class GlTypeCodes {
    const Archive = 'arc';
    const Demographics = 'd';
    const CA = 'ca';
    const H = 'h';
    const HA = 'ha';
    const m = 'm';
    const U = 'u';
}

class NameGuestStatus {
    const Active = 'a';
}

class InvoiceStatus {
    const Paid = 'p';
    const Unpaid = 'up';
    const Carried = 'c';
}

class InvoiceLineType {
    const Recurring = 1;
    const OneTime = 6;
    const Invoice = 3;
    const Hold = 4;
    const Reimburse = 7;
}

class ItemId {
    const Lodging = 1;
    const VisitFee = 2;
    const KeyDeposit = 3;
    const DepositRefund = 4;
    const InvoiceDue = 5;
    const Discount = 6;
    const LodgingReversal = 7;
    const LodgingDonate = 8;
    const LodgingMOA = 10;
    const AddnlCharge = 9;
    const Waive = 11;
}


class ItemPriceCode {
    const Basic = 'b';
    const Step3 = 'ns';
    const Dailey = 'd';
    const PerGuestDaily = 'g';
    const PerpetualStep = 'p';
    const NdayBlock = 'bl';
    const None = 'xx';
}

class MemBasis {
    const Indivual = "ai";
    const Company = "c";
    const NonProfit = "np";
}

class MemDesignation {
    const Individual = "i";
    const Organization = "o";
    const Not_Set = "n";
}

class MemGender {
    const Male = 'm';
    const Female = 'f';
    const Other = 't';
}

class MemStatus {
    const Active = "a";
    const Inactive = "in";
    const Deceased = "d";
    const Pending = "p";
    const ToBeDeleted = "TBD";
    const Duplicate = "u";
}

class MemType {
    const Indivual = "ai";
    const Company = "c";
    const NonProfit = "np";

}

// operating mode of site, live, demo or training
// in site.cfg file.
class Mode {
    const Live = "live";
    const Demo = "demo";
    const Training = "train";
}


class PayType {
    const Cash = 'ca';
    const Charge = 'cc';
    const Check = 'ck';
    const Invoice = 'in';
    const ChargeAsCash = 'cx';
    const Transfer = 'tf';
}

class PaymentStatusCode {
    const Paid = 's';
    const VoidSale = 'v';
    const Retrn = 'r';
    const Reverse = 'rv';
    const VoidReturn = "vr";
    const Declined = 'd';
}

class PaymentMethod {
    const Cash = 1;
    const Charge = 2;
    const Check = 3;
    const ChgAsCash = 4;
    const Transfer = 5;
}

class Phone_Purpose {
    const Home = 'dh';
    const Work = 'gw';
    const Cell = 'mc';
    const Cell2 = 'mc2';
    const Fax = 'xf';
    const Office = 'hw';
}

class RateStatus {
    const Active = 'a';
    const NotActive = 'n';
    const Retired = 'r';
}

class RelLinkType {
    const Spouse = "sp";
    const Child = "chd";
    const Parnt = "par";
    const Sibling = "sib";
    const Employee = "emp";
    const Relative = "rltv";
    const Friend = "frd";
    const Company = "co";
    const Self = 'slf';
    const Grandparent = 'gp';
    const Aunt = 'ant';
    const Uncle = 'unc';
    const Niece = 'nic';
    const Nephew = 'nfw';
}

class ReservationStatus {
    const Committed = 'a';
    const Waitlist = 'w';
    const NoShow = 'ns';
    const TurnDown = 'td';
    const Canceled = 'c';
    const ToHotel = 'h';
    const Pending = 'p';
    const Staying = 's';
    const Checkedout = 'co';
    const UnCommitted = 'uc';
    const Imediate = 'im';
}

class ResourceStatus {
    const Unavailable = 'un';
    const Available = 'a';
    const OutOfService = 'oos';
    const Delayed = 'dld';
}

class ReturnIndex {
    const ReturnIndex = 'r';
}

class RoomRateCategorys {
    const FlatRateCategory = 'e';
    const Fixed_Rate_Category = 'f';
    const FullRateCategory = 'y';
    const NewRate = 'r';
}

class RoomState {
    const Dirty = 'dty';
    const Clean = 'a';
    const TurnOver = 'to';
}
class RoomAvailable {
    const Unavailable = 'un';
    const Available = 'a';
}
class RoomService {
    const OutOfService = 'oos';
    const InService = 'a';
}

class RoomType {
    const Room =  'r';
    const Suite = 's';
    const Host  = 'hr';
    const Hotel = 'mr';
}

class SalutationCodes {
    const Formal = 'for';
    const FirstOnly = 'fno';
    const FirstLast = 'fln';
    const Retro = 'mm';
}

class SalutationPurpose {
    const Envelope = 'e';
    const Letter = "l";
}

class TransMethod {
    const Token = 'tkn';
    const HostedPayment = 'hp';
    const Cash = 'cash';
    const Check = 'check';
    const Transfer = 'txfer';
}

class TransType {
    const Sale = 's';
    const Void = 'vs';
    const Retrn = 'r';
    const VoidReturn = 'vr';
    const Reverse = 'rv';
}


class VisitStatus {
    const Active = "a";
    const CheckedIn = "a";
    const CheckedOut = "co";
    const Pending = "p";
    const NewSpan = "n";
    const ChangeRate = "cp";
    const OnLeave = 'l';
}

// Calendar status for Table mcalendar
class Vol_Calendar_Status {
    const Active = 'a';
    const Logged = 't'; // Time is logged in the volunteer time table.
    const Deleted = 'd';
}

class VolStatus {
    const Active = 'a';
    const Retired = 'i';
}

class VolRank {
    const Chair = "c";
    const CoChair = "cc";
    const Member = "m";
    const Guest = "rg";
}

class VolMemberType {
    const VolCategoryCode = 'Vol_Type';
    const Guest = 'g';
    const Patient = 'p';
    const Donor = 'd';
    const ReferralAgent = 'ra';
    const Doctor = 'doc';
    const BillingAgent = 'ba';
}

class WebRole {
    const DefaultRole = 100;
    const WebUser = 100;
    const Admin = 10;
    const Guest = 700;
}

class WebPageCode {
    const Page = 'p';
    const Component = 'c';
    const Service = 's';
}

class WebSiteCode {
    const  House = 'h';
    const Volunteer = 'v';
    const Admin = 'a';
    const Root = 'r';
}

