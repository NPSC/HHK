
function resvManager(initData) {
    var t = this;

    var patLabel = initData.patLabel;
    var resvTitle = initData.resvTitle;
    var saveButtonLabel = initData.saveButtonLabel;
    var patBirthDate = initData.patBD;
    var patAddrRequired = initData.patAddr;
    var gstAddrRequired = initData.gstAddr;
    var patAsGuest = initData.patAsGuest;
    var fillEmergencyContact = (initData.emergencyContact === undefined ? false: initData.emergencyContact);
    var isCheckin = (initData.isCheckin === undefined ? false: initData.isCheckin);
    var addrPurpose = initData.addrPurpose;
    var idPsg = initData.idPsg;
    var idResv = initData.rid;
    var idName = initData.id;
    var rooms = [];

    var people = new Items();
    var addrs = new Items();
    var familySection = new FamilySection($('#famSection'));
    var resvSection = new ResvSection($('#resvSection'));
    var hospSection = new HospitalSection($('#hospitalSection'));
    var expDatesSection = new ExpDatesSection($('#datesSection'));
    
    // Exports
    t.getReserve = getReserve;
    t.verifyInput = verifyInput;
    t.loadResv = loadResv;
    t.deleteReserve = deleteReserve;
    t.doOnDatesChange = doOnDatesChange;
    t.resvTitle = resvTitle;
    t.people = people;
    t.addrs = addrs;
    t.getIdPsg = getIdPsg;
    t.getIdResv = getIdResv;
    t.getIdName = getIdName;


    function getIdResv() {
        return idResv;
    }
    
    function getIdPsg() {
        return idPsg;
    }
    
    function getIdName() {
        return idName;
    }

    function FamilySection($wrapper) {
        var t = this;
        var divFamDetailId = 'divfamDetail';
        var setupComplete = false;
        var $famTbl;
        
        // Exports
        t.findStaysChecked = findStaysChecked;
        t.findPrimaryGuest = findPrimaryGuest;
        t.setUp = setUp;
        t.newGuestMarkup = newGuestMarkup;
        t.verify = verify;
        t.divFamDetailId = divFamDetailId;
        t.$famTbl = $famTbl;

        
        function findStaysChecked() {
            var numGuests = 0;

            // Each available stay control
            $('.hhk-cbStay').each(function () {

                var prefix = $(this).data('prefix');

                if ($(this).prop('checked')) {

                    people.list()[prefix].stay = '1';
                    numGuests++;

                } else {

                    people.list()[prefix].stay = '0';
                }
            });

            return numGuests;
        }

        function findPrimaryGuest() {

            var pgPrefix = $( "input[type=radio][name=rbPriGuest]:checked" ).val();

            // Clear out primary guest
            for (var p in people.list()) {
                people.list()[p].pri = '0';
            }

            // Set Primary guest
            if (pgPrefix !== undefined) {
                people.list()[pgPrefix].pri = '1';

            }
        }

        function openSection(torf) {

            var $fDiv = $('#divfamDetail');

            if (torf === true) {
                $fDiv.show('blind');
                $fDiv.prev('div').removeClass('ui-corner-all').addClass('ui-corner-top');
            } else {
                $fDiv.hide('blind');
                $fDiv.prev('div').addClass('ui-corner-all').removeClass('ui-corner-top');
            }
        }

        function addGuest(item, data) {

            hideAlertMessage();

            // Check for guest already added.
            //

            if (item.No_Return !== undefined && item.No_Return !== '') {
                flagAlertMessage('This person is set for No Return: ' + item.No_Return + '.', true);
                return;
            }

            if (typeof item.id === 'undefined') {
                return;
            }

            if (item.id > 0 && people.findItem('id', item.id) !== null) {
                flagAlertMessage('This person is already listed here. ', true);
                return;
            }
            
            var resv = {
                id: item.id,
                rid: data.rid,
                idPsg: data.idPsg,
                isCheckin: isCheckin,
                cmd: 'addResvGuest'
            };

            getReserve(resv);

        }

        function getECRel(item) {
            "use strict";
            // item returned from createAutocomoletre.
            $('#ecSearch').dialog('close');
            
            var cid = parseInt(item.id, 10);
            
            if (isNaN(cid) === false && cid > 0) {
                
                var prefix = $('#hdnEcSchPrefix').val();
                
                if (prefix == '') {
                    return;
                }
                
                $('#' + prefix + 'txtEmrgFirst').val(item.first);
                $('#' + prefix + 'txtEmrgLast').val(item.last);
                $('#' + prefix + 'txtEmrgPhn').val(item.phone);
                $('#' + prefix + 'txtEmrgAlt').val('');
                $('#' + prefix + 'selEmrgRel').val('');
            }
        }

        function verifyAddress(prefix) {

            var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
            var msg = false;

            // Incomplete checked?
            if ($('#' + prefix + 'incomplete').length > 0 && $('#' + prefix + 'incomplete').prop('checked') === false) {

                // Look at each entry
                $('.' + prefix + 'hhk-addr-val').not('.hhk-MissingOk').each(function() {

                    if ($(this).val() === '' && !$(this).hasClass('bfh-states')) {

                        // Missing
                        $(this).addClass('ui-state-error');
                        msg = true;

                    } else {
                        $(this).removeClass('ui-state-error');
                    }
                });

                // Did we catch any?
                if (msg) {
                    // Yes,open address row.
                    if ($('#' + prefix + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                        $('#' + prefix + 'toggleAddr').click();
                    }

                    return 'Some or all of the indicated addresses are missing.  ';

                }
            }

            // Validate Phone Number
            $('.hhk-phoneInput[id^="' +prefix + 'txtPhone"]').each(function (){

                if ($.trim($(this).val()) !== '' && testreg.test($(this).val()) === false) {

                    // error
                    $(this).addClass('ui-state-error');

                    //Open address row
                    if ($('#' + prefix + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                        $('#' + prefix + 'toggleAddr').click();
                    }

                    // open phone tab
                    $('#' + prefix + 'phEmlTabs').tabs("option", "active", 1);

                    msg = true;

                } else {
                    $(this).removeClass('ui-state-error');
                }
            });

            if (msg) {
                return 'Indicated phone numbers are invalid.  ';
            }

            return '';

        }

        function verifyEmergencyContacts(prefix) {

            var isMissing = false,
                $eFirst = $('#' + prefix + 'txtEmrgFirst'),
                $eLast = $('#' + prefix + 'txtEmrgLast'),
                $ephone = $('#' + prefix + 'txtEmrgPhn'),
                $eRel = $('#' + prefix + 'selEmrgRel');

            // Clear error class
            $eFirst.removeClass('ui-state-error');
            $eLast.removeClass('ui-state-error');
            $ephone.removeClass('ui-state-error');
            $eRel.removeClass('ui-state-error');

            // Emergency Contact
            if ($('#' + prefix + 'cbEmrgLater').length > 0 && $('#' + prefix + 'cbEmrgLater').prop('checked') === false) {


                // check the emergency contact
                if ($eFirst.val() === '' && $eLast.val() === '') {
                    $eFirst.addClass('ui-state-error');
                    $eLast.addClass('ui-state-error');
                    isMissing = true;
                }

                if ($ephone.val() === '') {
                    $ephone.addClass('ui-state-error');
                    isMissing = true;
                }

                if ($eRel.val() === '') {
                    $eRel.addClass('ui-state-error');
                    isMissing = true;
                }

                if (isMissing) {
                    return 'Some or all of the indicated Emergency Contact Information is missing.  ';
                }
            }

            return '';
        }
        
        function addrCopyDown(sourcePrefix) {

            for (var prefix in addrs.list()) {

                if (sourcePrefix == prefix) {
                    continue;
                }
                
                // Don't over write an address.
                if ($('#' + prefix + 'adraddress1' + addrPurpose).val() !== '' && $('#' + prefix + 'adrzip' + addrPurpose).val() !== '') {
                    continue;
                }

                $('#' + prefix + 'adraddress1' + addrPurpose).val(addrs.list()[sourcePrefix].Address_1);
                $('#' + prefix + 'adraddress2' + addrPurpose).val(addrs.list()[sourcePrefix].Address_2);
                $('#' + prefix + 'adrcity' + addrPurpose).val(addrs.list()[sourcePrefix].City);
                $('#' + prefix + 'adrcounty' + addrPurpose).val(addrs.list()[sourcePrefix].County);
                $('#' + prefix + 'adrzip' + addrPurpose).val(addrs.list()[sourcePrefix].Postal_Code);

                if ($('#' + prefix + 'adrcountry' + addrPurpose).val() != addrs.list()[sourcePrefix].Country_Code) {
                    $('#' + prefix + 'adrcountry' + addrPurpose)
                            .val(addrs.list()[sourcePrefix].Country_Code)
                            .change();
                }

                $('#' + prefix + 'adrstate' + addrPurpose).val(addrs.list()[sourcePrefix].State_Province);

                // Match the source incomplete checkbox
                if ($('#' + sourcePrefix + 'incomplete').prop('checked') === true) {
                    $('#' + prefix + 'incomplete').prop('checked', true);
                } else if (isAddressComplete(prefix) && $('#' + prefix + 'incomplete').prop('checked') === true) {
                    $('#' + prefix + 'incomplete').prop('checked', false);
                }

                // Update the address flag
                setAddrFlag($('#' + prefix + 'liaddrflag'));

            }

        }

        function isAddressComplete(prefix) {
            
            if (prefix === undefined || !prefix || prefix == '') {
                return false;
            }
            
            if ($('#' + prefix + 'adraddress1' + addrPurpose).val() !== '' 
                    && $('#' + prefix + 'adrzip' + addrPurpose).val() !== ''
                    && $('#' + prefix + 'adrstate' + addrPurpose).val() !== ''
                    && $('#' + prefix + 'adrcity' + addrPurpose).val() !== '') {
                
                return true;
            }
            return false;
        }
        
        function copyAddrSelector($button, prefix) {
            
            var $sel = $('<select id="selAddrch" multiple="multiple"/>');
            var options = 0;
            var optTexts = [];
            
            for (var p in addrs.list()) {
                
                if (addrs.list()[p].Address_1 != '' || addrs.list()[p].Postal_Code != '') {
                    
                    var notFound = true,
                        optText = addrs.list()[p].Address_1 + ', ' 
                            + (addrs.list()[p].Address_2 == '' ? '' : addrs.list()[p].Address_2 + ', ')
                            + addrs.list()[p].City  + ', '
                            + addrs.list()[p].State_Province  + '  '
                            + addrs.list()[p].Postal_Code;

                    for (var i=0; i <= optTexts.length; i++) {
                        if (optTexts[i] == optText) {
                            notFound = false;
                            continue;
                        }
                    }
                    
                    if (notFound) {
                        // Add as option
                        optTexts[options] = optText;
                        options++;

                        $('<option value="' + p + '">' + optText + '</option>')
                            .appendTo($sel);
                    }
                }
            }

            if (options > 0) {
                
                $sel.prop('size', options + 1).prepend($('<option value="0" >(Cancel)</option>'));
                
                $sel.change(function () {
                    setAddress(prefix, $(this).val());
                });
            
                var $selDiv = $('<div id="divSelAddr" style="position:absolute; vertical-align:top;" class="hhk-addrPicker"/>')
                        .append($('<p>Choose an Address: </p>'))
                    .append($sel)
                    .appendTo($('body'));
            
                $selDiv.position({
                    my: 'left top',
                    at: 'right center',
                    of: $button
                });
            }

        }

        function setAddress(prefix, p) {
 
            if (p == 0) {
                $('#divSelAddr').remove();
                return;
            }

            $('#' + prefix + 'adraddress1' + addrPurpose).val(addrs.list()[p].Address_1);
            $('#' + prefix + 'adraddress2' + addrPurpose).val(addrs.list()[p].Address_2);
            $('#' + prefix + 'adrcity' + addrPurpose).val(addrs.list()[p].City);
            $('#' + prefix + 'adrcounty' + addrPurpose).val(addrs.list()[p].County);
            $('#' + prefix + 'adrzip' + addrPurpose).val(addrs.list()[p].Postal_Code);

            if ($('#' + prefix + 'adrcountry' + addrPurpose).val() != addrs.list()[p].Country_Code) {
                $('#' + prefix + 'adrcountry' + addrPurpose).val(addrs.list()[p].Country_Code).change();
            }

            $('#' + prefix + 'adrstate' + addrPurpose).val(addrs.list()[p].State_Province);


            // Clear the incomplete address checkbox if the address is valid.
            if (isAddressComplete(prefix) && $('#' + prefix + 'incomplete').prop('checked') === true) {
                $('#' + prefix + 'incomplete').prop('checked', false);
            }

            // Update the address flag
            setAddrFlag($('#' + prefix + 'liaddrflag'));
            $('#divSelAddr').remove();

        }

        function eraseAddress(prefix) {

            $('#' + prefix + 'adraddress1' + addrPurpose).val('');
            $('#' + prefix + 'adraddress2' + addrPurpose).val('');
            $('#' + prefix + 'adrcity' + addrPurpose).val('');
            $('#' + prefix + 'adrcounty' + addrPurpose).val('');
            $('#' + prefix + 'adrstate' + addrPurpose).val('');
            $('#' + prefix + 'adrcountry' + addrPurpose).val('');
            $('#' + prefix + 'adrzip' + addrPurpose).val('');

            setAddrFlag($('#' + prefix + 'liaddrflag'));

        }

        function loadAddress(prefix) {

            if (prefix === undefined) {
                return;
            }

            addrs.list()[prefix].Address_1 = $('#' + prefix + 'adraddress1' + addrPurpose).val();
            addrs.list()[prefix].Address_2 = $('#' + prefix + 'adraddress2' + addrPurpose).val();
            addrs.list()[prefix].City = $('#' + prefix + 'adrcity' + addrPurpose).val();
            addrs.list()[prefix].County = $('#' + prefix + 'adrcounty' + addrPurpose).val();
            addrs.list()[prefix].State_Province = $('#' + prefix + 'adrstate' + addrPurpose).val();
            addrs.list()[prefix].Country_Code = $('#' + prefix + 'adrcountry' + addrPurpose).val();
            addrs.list()[prefix].Postal_Code = $('#' + prefix + 'adrzip' + addrPurpose).val();

            setAddrFlag($('#' + prefix + 'liaddrflag'));

        }

        function setAddrFlag($addrFlag) {

            var prefix = $addrFlag.data('pref');

            // Address status icon
            if ($('#' + prefix + 'incomplete').prop('checked') === true) {

                $addrFlag.show().find('span').removeClass('ui-icon-alert').addClass('ui-icon-check').attr('title', 'Incomplete Address is checked');
                $addrFlag.removeClass('ui-state-error').addClass('ui-state-highlight');

            } else {

                if (!isAddressComplete(prefix)) {
                    $addrFlag.show().find('span').removeClass('ui-icon-check').addClass('ui-icon-alert').attr('title', 'Address is Incomplete');
                    $addrFlag.removeClass('ui-state-highlight').addClass('ui-state-error');
                } else {
                    $addrFlag.hide();
                }
            }
        }

        function initFamilyTable(data) {

            var fDiv, fHdr, expanderButton;

            fDiv = $('<div/>').addClass('ui-widget-content ui-corner-bottom hhk-tdbox').prop('id', divFamDetailId).css('padding', '5px');

            $famTbl = $('<table/>')
                        .prop('id', data.famSection.tblId)
                        .addClass('hhk-table')
                        .append($('<thead/>').append($(data.famSection.tblHead)))
                        .append($('<tbody/>'));
                
            fDiv.append($famTbl).append($(data.famSection.adtnl));

            expanderButton = $("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>")
                .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
                .append($("<span id='f_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));

            fHdr = $('<div id="divfamHdr" style="padding:2px; cursor:pointer;"/>')
                    .append($(data.famSection.hdr))
                    .append(expanderButton).append('<div style="clear:both;"/>');

            fHdr.addClass('ui-widget-header ui-state-default ui-corner-top');
            fHdr.click(function() {
                if (fDiv.css('display') === 'none') {
                    fDiv.show('blind');
                    fHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                } else {
                    fDiv.hide('blind');
                    fHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                }
            });

            $wrapper
                    .empty()
                    .append(fHdr)
                    .append(fDiv)
                    .show();

        }

        function setUp(data) {

            var $addrTog, $addrFlag;

            if (data.famSection === undefined || data.famSection.tblId === undefined || data.famSection.tblId === '') {
                return;
            }

            if (setupComplete === false) {
                initFamilyTable(data);
            }

            // Remove any previous entries.
            for (var i in data.famSection.mem) {
                
                var item = people.findItem('id', data.famSection.mem[i].id);
                
                if (item && item.id > 0) {
                    $famTbl.find('tr#' + item.id + 'n').remove();
                    $famTbl.find('tr#' + item.id + 'a').remove();
                    $famTbl.find('input#' + item.pref + 'idName').parents('tr').next('tr').remove();
                    $famTbl.find('input#' + item.pref + 'idName').parents('tr').remove();
                    people.removeIndex(item.pref);
                    addrs.removeIndex(item.pref);
                }
            }

            // Add new people to the lists.
            people.makeList(data.famSection.mem, 'pref');
            addrs.makeList(data.famSection.addrs, 'pref');

            // Add people to the UI
            for (var t=0; t < data.famSection.tblBody.length; t = t + 2) {
                
                // Patient is first
                if (t === 0) {
                    $famTbl.find('tbody:first').prepend($(data.famSection.tblBody[t+1])).prepend($(data.famSection.tblBody[t]));
                } else {
                    $famTbl.find('tbody:first').append($(data.famSection.tblBody[t]))
                    .append($(data.famSection.tblBody[t+1]));
                }
            }

            // Staying controls
            $('.hhk-cbStay').checkboxradio({
                classes: {"ui-checkboxradio-label": "hhk-unselected-text" }
            });

            $('.hhk-lblStay').each(function () {
                if ($(this).data('stay') == '1') {
                    $(this).click();
                }
            });

            // Birthday Date picker
            $('.ckbdate').datepicker({
                yearRange: '-99:+00',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                maxDate: 0,
                dateFormat: 'M d, yy'
            });

            // set country and state selectors
            $('.hhk-addrPanel').find('select.bfh-countries').each(function() {
                var $countries = $(this);
                $countries.bfhcountries($countries.data());
            });
            $('.hhk-addrPanel').find('select.bfh-states').each(function() {
                var $states = $(this);
                $states.bfhstates($states.data());
            });

            $('.hhk-phemtabs').tabs();

            verifyAddrs('#divfamDetail');

            $('input.hhk-zipsearch').each(function() {
                var lastXhr;
                createZipAutoComplete($(this), 'ws_admin.php', lastXhr, loadAddress);
            });

                
            if (setupComplete === false) {
                
                // Last Name Copy down
                $('#lnCopy').click(function () {

                    var lastNameCopy = $('input.hhk-lastname').first().val();

                    $('input.hhk-lastname').each(function () {
                        if ($(this).val() === '') {
                            $(this).val(lastNameCopy);
                        }
                    });
                });

                // Address Copy down
                $('#adrCopy').click(function () {

                    var p = $('li.hhk-AddrFlag').first().data('pref');
                    addrCopyDown(p);

                });

                // toggle address row
                $('#' + divFamDetailId).on('click', '.hhk-togAddr', function () {

                    $addrTog = $(this);
                    $addrFlag = $(this).siblings();

                    if ($(this).parents('tr').next('tr').css('display') === 'none') {
                        $(this).parents('tr').next('tr').show();
                        $addrTog.find('span').removeClass('ui-icon-circle-triangle-s').addClass('ui-icon-circle-triangle-n');
                        $addrTog.attr('title', 'Hide Address Section');
                    } else {
                        $(this).parents('tr').next('tr').hide();
                        $addrTog.find('span').removeClass('ui-icon-circle-triangle-n').addClass('ui-icon-circle-triangle-s');
                        $addrTog.attr('title', 'Show Address Section');
                    }

                    
                });

                // Incomplete Address Flag
                $('#' + divFamDetailId).on('click', '.hhk-AddrFlag', function () {
                    // Click the incomplete address checkbox.
                    $('#' + $(this).data('pref') + 'incomplete').click();
                });

                // Load the addresses into the addrs object if changed.
                $('#' + divFamDetailId).on('change', '.hhk-copy-target', function() {
                    loadAddress($(this).data('pref'));
                });

                // Copy Address
                $('#' + divFamDetailId).on('click', '.hhk-addrCopy', function() {
                    copyAddrSelector($(this), $(this).data('prefix'));
                });

                // Delete address
                $('#' + divFamDetailId).on('click', '.hhk-addrErase', function() {
                    eraseAddress($(this).data('prefix'));
                });

                // Incomplete address bind to address flag.
                $('#' + divFamDetailId).on('click', '.hhk-incompleteAddr', function() {
                    setAddrFlag($('#' + $(this).data('prefix') + 'liaddrflag'));
                });

                // Remove button
                $('#' + divFamDetailId).on('click', '.hhk-removeBtn', function () {

                    // Is the name entered?
                    if ($('#' + $(this).data('prefix') + 'txtFirstName').val() !== '' || $('#' + $(this).data('prefix') + 'txtLastName').val() !== '') {
                        if (confirm('Remove this person: ' + $('#' + $(this).data('prefix') + 'txtFirstName').val() + ' ' + $('#' + $(this).data('prefix') + 'txtLastName').val() + '?') === false) {
                            return;
                        }
                    }

                    people.removeIndex($(this).data('prefix'));
                    addrs.removeIndex($(this).data('prefix'));

                    $(this).parentsUntil('tbody', 'tr').next().remove();
                    $(this).parentsUntil('tbody', 'tr').remove();
                });

                // Relationship chooser
                $('#' + divFamDetailId).on('change', '.patientRelch', function () {

                    if ($(this).val() === 'slf') {
                        people.list()[$(this).data('prefix')].role = 'p';
                    } else {
                        people.list()[$(this).data('prefix')].role = 'g';
                    }
                });

                // Add people search
                createAutoComplete($('#txtPersonSearch'), 3, {cmd: 'role', gp:'1'}, function (item) {
                    addGuest(item, data);
                });

                // Emergency Contact search icon hook to emergency contact dialog box
                $('#' + divFamDetailId).on('click', '.hhk-emSearch', function() {
                    $('#hdnEcSchPrefix').val($(this).data('prefix'));
                    $("#ecSearch").dialog('open');
                });

                // Emergency Contact dialog box search text box.
                createAutoComplete($('#txtemSch'), 3, {cmd: 'filter', add: 'phone', basis: 'g'}, getECRel);

                // Hover icons
                $( "ul.hhk-ui-icons li" ).hover(
                    function() {
                            $( this ).addClass( "ui-state-hover" );
                    },
                    function() {
                            $( this ).removeClass( "ui-state-hover" );
                    }
                );
        
            }

            // set the address flags
            for (var p in people.list()) {
                setAddrFlag($('#' + p + 'liaddrflag'));
            }
            
            // Shut Address rows
            $('.hhk-togAddr').each(function () {
                
                $(this).parents('tr').next('tr').hide();
                $(this).find('span').removeClass('ui-icon-circle-triangle-n').addClass('ui-icon-circle-triangle-s');
                $(this).attr('title', 'Show Address Section');
            });
            
            setupComplete = true;
        };

        function newGuestMarkup(data, prefix) {

            var $countries, $states, stripeClass, $addrFlag, $addrTog;

            if (data.tblId === undefined || data.tblId == '') {
                return;
            }

            if ($famTbl.length === 0) {
                return;
            }

            if ($famTbl.children('tbody').children('tr').last().hasClass('odd')) {
                stripeClass = 'even';
            } else {
                stripeClass = 'odd';
            }

            $famTbl.find('tbody:first').append($(data.ntr).addClass(stripeClass)).append($(data.atr).addClass(stripeClass));

            // prepare stay button
            $('#' + prefix + 'cbStay').checkboxradio({
                classes: {"ui-checkboxradio-label": "hhk-unselected-text" }
            });

            if ($('#' + prefix + 'lblStay').data('stay') == '1') {
                $('#' + prefix + 'lblStay').click();
            }

            // Prepare birth date picker
            $('.ckbdate').datepicker({
                yearRange: '-99:+00',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                maxDate: 0,
                dateFormat: 'M d, yy'
            });

            // Address button
            $addrFlag = $('#' + prefix + 'liaddrflag');
            $addrTog = $addrFlag.siblings();
            
            setAddrFlag($addrFlag);
            
            // Shut address line if filled in.
//            if ($addrFlag.css('display') === 'none') {
                
                $addrTog.parents('tr').next('tr').hide();
                $addrTog.find('span').removeClass('ui-icon-circle-triangle-n').addClass('ui-icon-circle-triangle-s');
                $addrTog.attr('title', 'Show Address Section');
//            } else {
//                $addrTog.attr('title', 'Hide Address Section');
//            }

            // set country and state selectors
            $countries = $('#' + prefix + 'adrcountry' + addrPurpose);
            $countries.bfhcountries($countries.data());

            $states = $('#' + prefix + 'adrstate' + addrPurpose);
            $states.bfhstates($states.data());

            $('#' + prefix + 'phEmlTabs').tabs();

            $('input#' + prefix + 'adrzip1').each(function() {
                var lastXhr;
                createZipAutoComplete($(this), 'ws_admin.php', lastXhr, loadAddress);
            });

        };

        function verify() {

            var numFamily = 0,
                numPat = 0,
                numGuests = 0,
                numPriGuests = 0,
                nameErr = false,
                ecIgnoreCount = 0;


            // Flag blank Relationships
            $('.patientRelch').removeClass('ui-state-error');
            $('.patientRelch').each(function () {

                if ($(this).val() === '') {

                    $(this).addClass('ui-state-error');
                    flagAlertMessage('Set the highlighted Relationship.', true);
                    return false;

                }
            });

            findPrimaryGuest();
            findStaysChecked();

            // Compute number of guests and patients
            for (var i in people.list()) {

                numFamily++;

                // Patients
                if (people.list()[i].role === 'p') {
                    numPat++;
                }
                // guests
                if (people.list()[i].stay === '1') {
                    numGuests++;
                }
                // Primary Guests
                if (people.list()[i].pri === '1') {
                    numPriGuests++;
                }
                // Close address boxes.
                if ($('#' + i + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-n')) {
                    // Close address row
                    $('#' + i + 'toggleAddr').click();
                }

            }

            // Only one patient allowed.
            if (numPat < 1) {

                flagAlertMessage('Choose a ' + patLabel + '.', true);

                $('.patientRelch').addClass('ui-state-error');
                return false;

            } else if (numPat > 1) {

                flagAlertMessage('Only 1 ' + patLabel + ' is allowed.', true);

                for (var i in people.list()) {
                    if (people.list()[i].role === 'p') {
                        $('#' + i + 'selPatRel').addClass('ui-state-error');
                    }
                }
                return false;
            }

            // Someone checking in?
            if (numGuests < 1) {
                flagAlertMessage('There is no one actually staying.  Pick someone to stay.', true);
                return false;
            }

            // Primary guests
            if (numPriGuests === 0 && numFamily === 1) {
                // Set the only guest as primary guest
               for (var i in people.list()) {
                   people.list()[i].pri = '1';
               }

            } else if (numPriGuests === 0) {
                flagAlertMessage('Set one guest as primary guest.', true);
                return false;
            }


            // Last names
            $wrapper.find('.hhk-lastname').each(function () {
                if ($(this).val() == '') {
                    $(this).addClass('ui-state-error');
                    nameErr = true;
                } else {
                    $(this).removeClass('ui-state-error');
                }
            });

            // First names
            $wrapper.find('.hhk-firstname').each(function () {
                if ($(this).val() == '') {
                    $(this).addClass('ui-state-error');
                    nameErr = true;
                } else {
                    $(this).removeClass('ui-state-error');
                }
            });

            if (nameErr === true) {
                openSection(true);
                flagAlertMessage("Enter a first and last name for the people highlighted.", true);
                return false;
            }
            
            // Optional Emergency Contact.
            if (fillEmergencyContact) {
                // Count the skipped.
                $wrapper.find('.hhk-EmergCb').each( function () {
                    
                    var msg = verifyEmergencyContacts($(this).data('prefix'));
                    
                    if ($(this).prop('checked') === true || msg === '') {
                        ecIgnoreCount++;
                    }
                 });
            }


            // each person
            for (var p in people.list()) {

                if (people.list()[p].role === 'p') {

                    // Check patient birthdate
                    if (patBirthDate & $('#' + p + 'txtBirthDate').val() === '') {
                        $('#' + p + 'txtBirthDate').addClass('ui-state-error');
                        flagAlertMessage(patLabel + ' is missing the Birth Date.', true);
                        openSection(true);
                        return false;
                    } else {
                        $('#' + p + 'txtBirthDate').removeClass('ui-state-error');
                    }

                    // Check patient address
                    if (patAddrRequired || patAsGuest) {

                        var pMessage = verifyAddress(p);

                        if (pMessage !== '') {

                            flagAlertMessage(pMessage, true);
                            openSection(true);

                            // Open address row
                            if ($('#' + p + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                                $('#' + p + 'toggleAddr').click();
                            }

                            return false;
                        }
                    }

                // Guests
                } else {

                    // Check Patient Relationship
                    if ($('#' + p + 'selPatRel').val() === '') {

                        $('#' + p + 'selPatRel').addClass('ui-state-error');
                        flagAlertMessage('Person highlighted is missing their ' + patLabel + ' Relationship.', true);
                        openSection(true);
                        return false;

                    } else {
                        $('#' + p + 'selPatRel').removeClass('ui-state-error');
                    }

                    // Check Guest address
                    if (gstAddrRequired) {

                        var pMessage = verifyAddress(p);

                         if (pMessage !== '') {

                            flagAlertMessage(pMessage, true);
                            openSection(true);

                            // Open address row
                            if ($('#' + p + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                                $('#' + p + 'toggleAddr').click();
                            }

                            return false;
                        }
                    }
                   
                }

                // Check birth dates
                if ($('#' + p + 'txtBirthDate').length > 0 && $('#' + p + 'txtBirthDate').val() !== '') {

                    var bDate = new Date($('#' + p + 'txtBirthDate').val());
                    var today = new Date();

                    if (bDate > today) {
                        $('#' + p + 'txtBirthDate').addClass('ui-state-error');
                        flagAlertMessage('This birth date cannot be in the future.', true);
                        openSection(true);
                        return false;
                    } else {
                        $('#' + p + 'txtBirthDate').removeClass('ui-state-error');
                    }
                }
                
                // Check Emergen
                if (fillEmergencyContact && ecIgnoreCount < 1) {

                    var pMessage = verifyEmergencyContacts(p);

                    if (pMessage !== '') {

                        flagAlertMessage(pMessage, true);
                        openSection(true);

                        // Open address row
                        if ($('#' + p + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                            $('#' + p + 'toggleAddr').click();
                        }

                        return false;
                    }
                }

            }

            setupComplete = false;
            return true;
        };
    }

    function ExpDatesSection($dateSection) {

        var t = this;

        // Export
        t.setupComplete = false;
        t.ciDate = new Date();
        t.coDate = new Date();


        t.setUp = function(data, doOnDatesChange) {

            $dateSection.empty();
            $dateSection.append($(data.expDates.mu));

            var gstDate = $('#gstDate'),
                gstCoDate = $('#gstCoDate'),
                nextDays = parseInt(data.expDates.defdays, 10);

            // default number of days for a new stay.
            if (isNaN(nextDays) || nextDays < 1) {
                nextDays = 21;
            }

            $('#spnRangePicker').dateRangePicker({
                format: 'MMM D, YYYY',
                separator : ' to ',
                minDays: 1,
                autoClose: true,
                showShortcuts: true,
                shortcuts :
                {
                        'next-days': [nextDays]
                },
                getValue: function()
                {
                    if (gstDate.val() && gstCoDate.val() ) {
                        return gstDate.val() + ' to ' + gstCoDate.val();
                    } else {
                        return '';
                    }
                },
                setValue: function(s,s1,s2)
                {
                    gstDate.val(s1);
                    gstCoDate.val(s2);
                }
            }).bind('datepicker-change', function(event, dates) {

                // Update the number of days display text.
                var numDays = Math.ceil((dates['date2'].getTime() - dates['date1'].getTime()) / 86400000);

                $('#' + data.expDates.daysEle).val(numDays);

                if ($('#spnNites').length > 0) {
                    $('#spnNites').text(numDays);
                }
                
                if ($.isFunction(doOnDatesChange)) {
                    doOnDatesChange(dates);
                }
            });


            $dateSection.show();

            // Open the dialog if the dates are not defined yet.
//            if ($('#gstDate').val() == '') {
//                $('#spnRangePicker').data('dateRangePicker').open();
//            }

            setupComplete = true;
        };

        t.verify = function() {

            var $arrDate = $('#gstDate'),
                $deptDate = $('#gstCoDate');

            $arrDate.removeClass('ui-state-error');
            $deptDate.removeClass('ui-state-error');

            // Check in Date
            if ($arrDate.val() === '') {

                $arrDate.addClass('ui-state-error');
                flagAlertMessage("This " + resvTitle + " is missing the check-in date.", true);
                return false;

            } else {

                t.ciDate = new Date($arrDate.val());

                if (isNaN(t.ciDate.getTime())) {
                    $arrDate.addClass('ui-state-error');
                    flagAlertMessage("This " + resvTitle + " is missing the check-in date.", true);
                    return false;
                }
            }

            // Check-out date
            if ($deptDate.val() === '') {

                $deptDate.addClass('ui-state-error');
                flagAlertMessage("This " + resvTitle + " is missing the expected departure date.", true);
                return false;

            } else {

                t.coDate = new Date($deptDate.val());

                if (isNaN(t.coDate.getTime())) {
                    $deptDate.addClass('ui-state-error');
                    flagAlertMessage("This " + resvTitle + " is missing the expected departure date", true);
                    return false;
                }

                if (t.ciDate > t.coDate) {
                    $arrDate.addClass('ui-state-error');
                    flagAlertMessage("This " + resvTitle + "'s check-in date is after the expected departure date.", true);
                    return false;
                }
            }

            return true;
        };
    }

    function doOnDatesChange(dates) {

        var hasIds = false;
        for (var p in people.list()) {
            if (people.list()[p].id > 0) {
                hasIds = true;
            }
        }

        // Update the Staying controls
        if (hasIds) {
            // Hide controls
            $('.hhk-stayIndicate').hide().parent('td').addClass('hhk-loading');
            
            var parms = {
                cmd:'updateAgenda', 
                idPsg: idPsg,
                idResv: idResv,
                dt1:dates["date1"].toUTCString(), 
                dt2:dates["date2"].toUTCString(), 
                mems:people.list()};

            $.post('ws_resv.php', parms, function(data) {

                $('.hhk-stayIndicate').show().parent('td').removeClass('hhk-loading');
                
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    flagAlertMessage(err.message, true);
                    return;
                }

                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }

                if (data.error) {
                    flagAlertMessage(data.error, true);
                }

                if (data.stayCtrl) {

                    for (var i in data.stayCtrl) {
                        var $lbl;

                        $('#sb' + i).empty().html(data.stayCtrl[i]['ctrl']);

                        $('#' + i + 'cbStay').checkboxradio({
                            classes: {"ui-checkboxradio-label": "hhk-unselected-text" }
                        });

                        people.list()[i].stay = '0';
                        
                        $lbl = $('#' + i + 'lblStay')

                        if ($lbl.length > 0 && $lbl.data('stay') == '1') {
                            $lbl.click();
                        }
                    }
                    
                    // visit buttons
                    $('.hhk-getVDialog').button();
                }
            });
        }
        
        // Update the room chooser.
        if ($('#gstDate').val() != '' && $('#gstCoDate').val() != '') {
            updateRescChooser($('#gstDate').val(), $('#gstCoDate').val());
        }
        
        // Checking in now button
        manageCheckInNowButton(dates["date1"].t, idResv);
    }
    
    function manageCheckInNowButton(arrDate, rid) {
        
        // Assumes the date is set to the format indicated
        var start = moment(arrDate, 'MMM D, YYYY');
        var now = moment().endOf('date');
        
        if (rid > 0 && start <= now) {
            $('#btnCheckinNow').show();
        } else {
            $('#btnCheckinNow').hide();
        }
    }
    
    function updateRescChooser(arrivalDate, departureDate) {
    
        var cbRS = {};
        var idResc;
        var $selResource = $('#selResource');

        if ($selResource.length === 0) {
            return;
        }

        idResc = $selResource.find('option:selected').val();

        hideAlertMessage();

        $selResource.prop('disabled', true);
        $('#hhk-roomChsrtitle').addClass('hhk-loading');
        $('#hhkroomMsg').text('').hide();

        $('input.hhk-constraintsCB:checked').each(function () {
            cbRS[$(this).data('cnid')] = 'ON';
        });

        $.post('ws_ckin.php', 
            {  //parameters
                cmd: 'newConstraint', 
                rid: idResv, 
                numguests: 1, 
                expArr: arrivalDate, 
                expDep: departureDate, 
                idr: idResc, 
                cbRS:cbRS
            },
            function(data) {
                var newSel;

                $selResource.prop('disabled', false);
                $('#hhk-roomChsrtitle').removeClass('hhk-loading');

                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }

                if (data.error) {
                    if (data.gotopage) {
                        window.location.assign(data.gotopage);
                    }
                    flagAlertMessage(data.error, true);
                    return;
                }


                if (data.selectr) {

                    newSel = $(data.selectr);
                    $selResource.children().remove();

                    newSel.children().appendTo($selResource);
                    $selResource.val(data.idResource).change();

                    if (data.msg && data.msg !== '') {
                        $('#hhkroomMsg').text(data.msg).show();
                    }
                }

                if (data.rooms) {
                    rooms = data.rooms;
                }

        });

    }

    function HospitalSection($hospSection) {
        var t = this;
        t.setupComplete = false;

        t.setUp = function(hosp) {

            var hDiv = $(hosp.div).addClass('ui-widget-content').prop('id', 'divhospDetail').hide();
            var expanderButton = $("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>")
                .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
                .append($("<span id='h_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));
            var hHdr = $('<div id="divhospHdr" style="padding:2px; cursor:pointer;"/>')
                    .append($(hosp.hdr))
                    .append(expanderButton).append('<div style="clear:both;"/>');

            hHdr.addClass('ui-widget-header ui-state-default ui-corner-all');

            hHdr.click(function() {
                if (hDiv.css('display') === 'none') {
                    hDiv.show('blind');
                    hHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                } else {
                    hDiv.hide('blind');
                    hHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                }
            });

            $hospSection.empty().append(hHdr).append(hDiv);

            $('#txtEntryDate, #txtExitDate').datepicker({
                yearRange: '-01:+01',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                dateFormat: 'M d, yy'
            });

            if ($('#txtAgentSch').length > 0) {
                createAutoComplete($('#txtAgentSch'), 3, {cmd: 'filter', basis: 'ra'}, getAgent);
                if ($('#a_txtLastName').val() === '') {
                    $('.hhk-agentInfo').hide();
                }
            }

            if ($('#txtDocSch').length > 0) {
                createAutoComplete($('#txtDocSch'), 3, {cmd: 'filter', basis: 'doc'}, getDoc);
                if ($('#d_txtLastName').val() === '') {
                    $('.hhk-docInfo').hide();
                }
            }

            verifyAddrs('#divhospDetail');
            
            $hospSection.on('change', '#selHospital, #selAssoc', function() {
                var hosp = $('#selAssoc').find('option:selected').text();
                if (hosp != '') {
                    hosp += '/ ';
                }
                $('span#spnHospName').text(hosp + $('#selHospital').find('option:selected').text());
            });


            $hospSection.show();

            if ($('#selHospital').val() === '') {
                hHdr.click();
            }

            t.setupComplete = true;
        };

        t.verify = function() {

            $hospSection.find('.ui-state-error').each(function() {
                $(this).removeClass('ui-state-error');
            });

            if ($('#selHospital').length > 0 && t.setupComplete === true) {

                if ($('#selHospital').val() == "" ) {

                    $('#selHospital').addClass('ui-state-error');

                    flagAlertMessage("Select a hospital.", false);

                    $('#divhospDetail').show('blind');
                    $('#divhospHdr').removeClass('ui-corner-all').addClass('ui-corner-top');
                    return false;
                }
            }

            $('#divhospDetail').hide('blind');
            $('#divhospHdr').removeClass('ui-corner-top').addClass('ui-corner-all');

            return true;
        };

    }

    function ResvSection($wrapper) {
        var t = this;
        var $rDiv, $veh, $rHdr, $expanderButton;

        t.setupComplete = false;
        t.setUp = setUp;
        t.verify = verify;

        function setupVehicle(veh) {
            var nextVehId = 1;
            var $cbVeh = veh.find('#cbNoVehicle');
            var $nextVeh = veh.find('#btnNextVeh');
            var $tblVeh = veh.find('#tblVehicle');

            $cbVeh.change(function() {
                if (this.checked) {
                    $tblVeh.hide('scale, horizontal');
                } else {
                    $tblVeh.show('scale, horizontal');
                }
            });

            $cbVeh.change();
            $nextVeh.button();

            $nextVeh.click(function () {
                veh.find('#trVeh' + nextVehId).show('fade');
                nextVehId++;
                if (nextVehId > 4) {
                    $nextVeh.hide('fade');
                }
            });

        }

        function setupRate(data) {

            var reserve = {};
            var $finAppBtn = $wrapper.find('#btnFapp');

            if ($finAppBtn.length > 0) {

                $("#faDialog").dialog({
                    autoOpen: false,
                    resizable: true,
                    width: 680,
                    modal: true,
                    title: 'Income Chooser',
                    close: function () {$('div#submitButtons').show();},
                    open: function () {$('div#submitButtons').hide();},
                    buttons: {
                        Save: function() {
                            $.post('ws_ckin.php', $('#formf').serialize() + '&cmd=savefap' + '&rid=' + data.rid, function(rdata) {
                                try {
                                    rdata = $.parseJSON(rdata);
                                } catch (err) {
                                    alert('Bad JSON Encoding');
                                    return;
                                }
                                if (rdata.gotopage) {
                                    window.open(rdata.gotopage, '_self');
                                }
                                if (rdata.rstat && rdata.rstat == true) {
                                    var selCat = $('#selRateCategory');
                                    if (rdata.rcat && rdata.rcat != '' && selCat.length > 0) {
                                        selCat.val(rdata.rcat);
                                        selCat.change();
                                    }
                                }
                            });
                            $(this).dialog("close");
                        },
                        "Exit": function() {
                            $(this).dialog("close");
                        }
                    }
                });

                $finAppBtn.button().click(function() {
                    getIncomeDiag(data.rid);
                });
            }

            reserve.rateList = data.resv.rdiv.ratelist;
            reserve.resources = data.resv.rdiv.rooms;
            reserve.visitFees = data.resv.rdiv.vfee;

            setupRates(reserve);

            $('#selResource').change(function () {
                $('#selRateCategory').change();

                var selected = $("option:selected", this);
                var selparent = selected.parent()[0].label;
                
                if (selparent === undefined || selparent === null ) {
                    $('#hhkroomMsg').hide();
                } else {
                    $('#hhkroomMsg').text(selparent).show();
                }
            });

        }

        function setupPay(data){
            
            $('#paymentDate').datepicker({
                yearRange: '-1:+01',
                numberOfMonths: 1
            });
                
            setupPayments(data.resv.rdiv.rooms, $('#selResource'), $('#selRateCategory'));
        }

        function setupRoom() {

            // Room selector update for constraints changes.
            $('input.hhk-constraintsCB').change( function () {
                // Disable max room size.
                updateRescChooser($('#gstDate').val(), $('#gstCoDate').val());
            });

        }

        function setupNotes(rid, $container) {
            
            $container.notesViewer({
                linkId: rid,
                linkType: 'reservation',
                newNoteAttrs: {id:'taNewNote', name:'taNewNote'},
                alertMessage: function(text, isError) {
                    flagAlertMessage(text, isError);
                }
            });
            
            return $container;
        }
        
        function setUp(data) {

            $rDiv = $('<div id="divResvDetail" style="padding:2px; float:left; width: 100%;" class="ui-widget-content ui-corner-bottom hhk-tdbox"/>');
            $rDiv.append($(data.resv.rdiv.rChooser));

            // Rate section
            if (data.resv.rdiv.rate !== undefined) {
                $rDiv.append($(data.resv.rdiv.rate));
            }

            // Stat
            $rDiv.append($(data.resv.rdiv.rstat));
            
            // Vehicle section
            if (data.resv.rdiv.vehicle !== undefined) {
                $veh = $(data.resv.rdiv.vehicle);
                $rDiv.append($veh);
                setupVehicle($veh);
            }
            
            if (data.resv.rdiv.pay !== undefined) {
                $rDiv.append($(data.resv.rdiv.pay));
            }

            // Reservation notes.
            $rDiv.append(setupNotes(data.rid, $(data.resv.rdiv.notes)));

            // waitlist notes
            if (data.resv.rdiv.wlnotes !== undefined) {
                $rDiv.append($(data.resv.rdiv.wlnotes));
            }
            

            // Header
            $expanderButton = $("<ul style='list-style-type:none; float:right; margin-left:5px; padding-top:2px;' class='ui-widget'/>")
                .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
                .append($("<span id='r_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));
            $rHdr = $('<div id="divResvHdr" style="padding:2px; cursor:pointer;"/>')
                    .append($(data.resv.hdr))
                    .append($expanderButton).append('<div style="clear:both;"/>');

            $rHdr.addClass('ui-widget-header ui-state-default ui-corner-top');

            $rHdr.click(function() {
                if ($rDiv.css('display') === 'none') {
                    $rDiv.show('blind');
                    $rHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                } else {
                    $rDiv.hide('blind');
                    $rHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                }
            });

            // Add to the page.
            $wrapper.empty().append($rHdr).append($rDiv).show();

            t.$totalGuests = $('#spnNumGuests');

            // Reservation history button
            if ($('.hhk-viewResvActivity').length > 0) {
                $('.hhk-viewResvActivity').click(function () {

                  $.post('ws_ckin.php', {cmd:'viewActivity', rid: $(this).data('rid')}, function(data) {
                    data = $.parseJSON(data);

                    if (data.error) {

                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, true);
                        return;
                    }
                     if (data.activity) {

                        $('div#submitButtons').hide();
                        $("#activityDialog").children().remove();
                        $("#activityDialog").append($(data.activity));
                        $("#activityDialog").dialog('open');
                    }
                    });

                });
            }
            
            // Show confirmation form button.
            $('#btnShowCnfrm').button().click(function () {
                var amount = $('#spnAmount').text();
                if (amount === '') {
                    amount = 0;
                }
                $.post('ws_ckin.php', {cmd:'confrv', rid: $(this).data('rid'), amt: amount, eml: '0'}, function(data) {

                    data = $.parseJSON(data);

                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, true);
                        return;
                    }

                     if (data.confrv) {

                        $('div#submitButtons').hide();
                        $("#frmConfirm").children().remove();
                        $("#frmConfirm").html(data.confrv)
                            .append($('<div style="padding-top:10px;" class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix"><span>Email Address </span><input type="text" id="confEmail" value="'+data.email+'"/></div>'));

                        $("#confirmDialog").dialog('open');
                    }
                });
            });
            
            setupRoom(data.rid);

            if (data.resv.rdiv.rate !== undefined) {
                setupRate(data);
            }

            if (data.resv.rdiv.pay !== undefined) {
                setupPay(data);
            }

            t.setupComplete = true;
        };

        function validateCar(cnum) {
            "use strict";
            var err = '';
            if ($('#car' + cnum + 'txtVehLic').val() === '' && $('#car' + cnum + 'txtVehMake').val() === '') {
                return "Enter vehicle info or check the 'No Vehicle' checkbox. ";
            }
            if ($('#car' + cnum + 'txtVehLic').val() === '') {
                if ($('#car' + cnum + 'txtVehModel').val() === '') {
                    $('#car' + cnum + 'txtVehModel').addClass('ui-state-highlight');
                    err = 'Enter Model';
                }
                if ($('#car' + cnum + 'txtVehColor').val() === '') {
                    $('#car' + cnum + 'txtVehColor').addClass('ui-state-highlight');
                    err = 'Enter Color';
                }
                if ($('#car' + cnum + 'selVehLicense').val() === '') {
                    $('#car' + cnum + 'selVehLicense').addClass('ui-state-highlight');
                    err = 'Enter state license plate registration';
                }
            } else if ($('#car' + cnum + 'txtVehMake').val() === '') {
                if ($('#car' + cnum + 'txtVehLic').val() === '') {
                    $('#car' + cnum + 'txtVehLic').addClass('ui-state-highlight');
                    err = 'Enter a license plate number.';
                }
            }
            return err;
        }
        
        function verify() {

            // vehicle
            if ($('#cbNoVehicle').length > 0) {
                if ($('#cbNoVehicle').prop("checked") === false) {
                    var carVal = validateCar(1);
                    if (carVal != '') {
                        var carVal2 = validateCar(2);
                        if (carVal2 != '') {
                            $('#vehValidate').text(carVal2);
                            flagAlertMessage(carVal, false);
                            return false;
                        }
                    }
                }
                $('#vehValidate').text('');
            }
            
            return true;
        }
    }

    function Items () {

        var _list = {};
        var _index;
        var t = this;
        t.hasItem = hasItem;
        t.findItem = findItem;
        t.addItem = addItem;
        t.removeIndex = removeIndex;
        t.list = list;
        t.makeList = makeList;
        t._list = _list;

        function list() {
            return _list;
        };

        function makeList(theList, indexProperty) {

            _index = indexProperty;
            //_list = {};

            for (var i in theList) {
                addItem(theList[i]);
            }
        };

        function addItem(item) {

            if (hasItem(item) === false) {
                _list[item[_index]] = item;
                return true;
            }

            return false;
        };

        function removeIndex(index) {
            delete _list[index];
        }

        function hasItem(item) {

            if (_list[item[_index]] !== undefined) {
                return true;
            }

            return false;
        };

        function findItem(property, value) {
            for (var i in _list) {
                if (_list[i][property] == value) {
                    return _list[i];
                }
            }
            return null;
        }

    }

    function transferToGw(data) {

        var xferForm = $('#xform');
        xferForm.children('input').remove();
        xferForm.prop('action', data.xfer);
        if (data.paymentId && data.paymentId != '') {
            xferForm.append($('<input type="hidden" name="PaymentID" value="' + data.paymentId + '"/>'));
        } else if (data.cardId && data.cardId != '') {
            xferForm.append($('<input type="hidden" name="CardID" value="' + data.cardId + '"/>'));
        } else {
            flagAlertMessage('PaymentId and CardId are missing!', true);
            return;
        }
        xferForm.submit();
    }

    function resvPicker(data, $resvDiag, $psgDiag) {
        "use strict";
        var buttons = {};

        // Clear add person textbox
        $('input#txtPersonSearch').val('');
        
        // reset then fill the reservation dialog
        $resvDiag.empty()
            .append($(data.resvChooser))
            .children().find('input:button').button();

        // Set up 'Check-in Now' button
        $resvDiag.children().find('.hhk-checkinNow').click(function () {
            window.open('CheckingIn.php?rid=' + $(this).data('rid') + '&gid=' + data.id, '_self');
        });

        // Set up go to PSG chooser button
        if (data.psgChooser && data.psgChooser !== '') {
            buttons[data.patLabel + ' Chooser'] = function() {
                $(this).dialog("close");
                psgChooser(data, $psgDiag);
            };
        }

        // Set up New Reservation button.
        if (data.resvTitle) {
            buttons['New ' + data.resvTitle] = function() {
                data.rid = -1;
                data.cmd = 'getResv';
                $(this).dialog("close");
                getReserve(data);
            };
        }

        buttons['Exit'] = function() {
            $(this).dialog("close");

            $('input#gstSearch').val('').focus();
        };
        
        $resvDiag.dialog('option', 'width', '95%');
        $resvDiag.dialog('option', 'buttons', buttons);
        $resvDiag.dialog('option', 'title', data.resvTitle + ' Chooser');
        $resvDiag.dialog('open');
        
        var table = $resvDiag.find('table').width();
        $resvDiag.dialog('option', 'width', table + 80);

    }

    function psgChooser(data, $dialog) {
        "use strict";

        // Clear add person textbox
        $('input#txtPersonSearch').val('');
        
        $dialog
            .empty()
            .append($(data.psgChooser))
            .dialog('option', 'buttons', {
                Open: function() {
                    $(this).dialog('close');
                    getReserve({idPsg: $dialog.find('input[name=cbselpsg]:checked').val(), id: data.id, cmd: 'getResv'});
                },
                Cancel: function () {
                    $(this).dialog('close');

                    $('input#gstSearch').val('').focus();
                }
            })
            .dialog('option', 'title', data.patLabel + ' Chooser' + (data.fullName === undefined ? '' : ' For: ' + data.fullName))
            .dialog('open');
    }

    function getReserve(sdata) {
        
        var parms = {
            id:sdata.id, 
            rid:sdata.rid, 
            idPsg:sdata.idPsg,
            isCheckin: isCheckin,
            cmd:sdata.cmd};

        $.post('ws_resv.php', parms, function(data) {

            try {
                data = $.parseJSON(data);
            } catch (err) {
                flagAlertMessage(err.message, true);
                return;
            }

            if (data.gotopage) {
                window.open(data.gotopage, '_self');
            }

            if (data.error) {
                flagAlertMessage(data.error, true);
                $('#btnDone').val('Save ' + resvTitle).show();
            }

            loadResv(data);
        });

    }

    function deleteReserve(rid, idForm, $delButton) {

        var cmdStr = '&cmd=delResv' + '&rid=' + rid;
        $.post(
                'ws_ckin.php',
                cmdStr,
                function(datas) {
                    var data;
                    try {
                        data = $.parseJSON(datas);
                    } catch (err) {
                        flagAlertMessage(err.message, true);
                        $(idForm).remove();
                    }

                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, true);
                        $(idForm).remove();
                    }

                    if (data.warning) {
                        flagAlertMessage(data.warning, false);
                        $delButton.hide();
                    }

                    if (data.result) {
                        $(idForm).remove();
                        flagAlertMessage(data.result + ' <a href="Reserve.php">Continue</a>', false);
                    }
                }
        );
    }

    function loadResv(data) {

        if (data.xfer) {
            transferToGw(data);
        }

        // Patient management.
        if (data.resvChooser && data.resvChooser !== '') {
            resvPicker(data, $('#resDialog'), $('#psgDialog'));
            return;
        } else if (data.psgChooser && data.psgChooser !== '') {
            psgChooser(data, $('#psgDialog'));
            return;
        }

        // Assign variables.
        if (data.idPsg) {
            idPsg = data.idPsg;
        }
        if (data.id) {
            idName = data.id;
        }
        if (data.rid) {
            idResv = data.rid;
        }

        // Hospital
        if (data.hosp !== undefined) {
            hospSection.setUp(data.hosp);
        }

        // Build a new Family section.
        if (data.famSection) {

            familySection.setUp(data);

            $('div#guestSearch').hide();

            $('#btnDone').val('Save Family').show();
        }

        // Expected Dates Control
        if (data.expDates !== undefined && data.expDates !== '') {
            expDatesSection.setUp(data, doOnDatesChange);
        }
        
        if (data.warning !== undefined && data.warning !== '') {
            flagAlertMessage(data.warning, true);
        }

        // Reservation
        if (data.resv !== undefined) {
            
            if (data.resv.rdiv.rooms) {
                rooms = data.resv.rdiv.rooms;
            }

            resvSection.setUp(data);

            // String together some events
            $('#' + familySection.divFamDetailId).on('change', '.hhk-cbStay', function () {

                var tot = familySection.findStaysChecked();
                resvSection.$totalGuests.text(tot);

                if (tot > 0) {
                    resvSection.$totalGuests.parent().removeClass('ui-state-highlight');
                } else {
                    resvSection.$totalGuests.parent().addClass('ui-state-highlight');
                }
            });

            // Visit Dialog
            if ($('.hhk-getVDialog').length > 0) {
                
                $('.hhk-getVDialog').button();

                $('#' + familySection.divFamDetailId).on('click', '.hhk-getVDialog', function () {
                    var buttons;
                    var vid = $(this).data('vid');
                    var span = $(this).data('span');
                    buttons = {
                        "Show Statement": function() {
                            window.open('ShowStatement.php?vid=' + vid, '_blank');
                        },
                        "Show Registration Form": function() {
                            window.open('ShowRegForm.php?vid=' + vid, '_blank');
                        },
                        "Save": function() {
                            saveFees(0, vid, span, false, payFailPage);
                        },
                        "Cancel": function() {
                            $(this).dialog("close");
                        }
                    };
                    viewVisit(0, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
                    $('#submitButtons').hide();
                });
            }
            
            $('.hhk-cbStay').change();

            $('#btnDone').val(saveButtonLabel).show();

            if (data.rid > 0) {
                $('#btnDelete').val('Delete ' + resvTitle).show();
                $('#btnShowReg').show();
                $('#spnStatus').text(data.resv.rdiv.rStatTitle === '' ? '' : ' - ' + data.resv.rdiv.rStatTitle);
            }
            
            // Checking in now button
            manageCheckInNowButton($('#gstDate').val(), data.rid);
        
        }

        if (data.addPerson !== undefined) {

            // Clear the person search textbox.
            $('input#txtPersonSearch').val('');

            if (people.addItem(data.addPerson.mem)) {
                addrs.addItem(data.addPerson.addrs);
                familySection.newGuestMarkup(data.addPerson, data.addPerson.mem.pref);
                familySection.findStaysChecked();
                
                $('.hhk-cbStay').change();

                $('#' + data.addPerson.mem.pref + 'txtFirstName').focus();
            }
        }
    }

    function verifyInput() {

        // dates
        if (expDatesSection.verify() === false) {
            return false;
        }

        // Family
        if (familySection.verify() === false) {

            return false;
        }

        // hospital
        if (hospSection.verify() === false) {
            return false;
        }

        if (resvSection.setupComplete === true) {

            if (resvSection.verify() === false) {
                return false;
            }
        }

        return true;

    }
}

