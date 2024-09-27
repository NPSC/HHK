

$(document).ready(function () {

    var table = new Object(),
        accordIndex = $('#accordIndex').val();

    $("input[type=submit], input[type=button]").button();

    $.ajaxSetup({
        beforeSend: function () {
            //$('#loader').show()
            $('body').css('cursor', "wait");
        },
        complete: function () {
            $('body').css('cursor', "auto");
            //$('#loader').hide()
        },
        cache: false
    });
    $('#accordion').tabs();
    $('#accordion').tabs("option", "active", accordIndex);
    if (accordIndex === 3) {
        $('#dataTbl').dataTable({
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top"ilfp>rt<"bottom"p>'
        });
    }
    $("input.autoCal").datepicker({
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        dateFormat: 'M d, yy'
    });
    $('#selLookup').change(function () {
        $.ajax(
                {type: "POST",
                    url: "Misc.php",
                    data: ({
                        table: $("#selLookup").val(),
                        cmd: "get"
                    }),
                    success: handleResponse,
                    error: handleError,
                    datatype: "json"
                });
    });
    $('#selCode').change(function () {
        if (table) {
            for (let code in table) {

                if (table[code].Code == this.value) {
                    $('#txtCode').val(this.value).prop("readonly", true);
                    $('#txtDesc').val(table[code].Description);
                    $('#txtAddl').val(table[code].Substitute);
                }
            }
        }
    });
    $('#accordion').show();
    
    function handleResponse(dataTxt, statusTxt, xhrObject) {
    if (statusTxt !== "success")
        alert('Server had a problem.  ' + xhrObject.status + ", " + xhrObject.responseText);

    if (dataTxt.length > 0) {
        table = $.parseJSON(dataTxt);
        showTable(table);
    }
}

function handleError(xhrObject, stat, thrwnError) {
    alert("Server error: " + stat + ", " + thrwnError);
}

function showTable(data) {
    // remove any previous entries
    $('#selCode').children().remove();

    // first option is "New"
    var objOption = document.createElement("option");

    objOption.text = "New";
    objOption.value = "n_$";

    objOption.setAttribute("selected", "selected");
    $('#selCode').append(objOption);

    for (var x = 0; x < data.length; x++) {
        var tbl = data[x];
        objOption = document.createElement("option");

        objOption.text = tbl.Description;
        objOption.value = tbl.Code;
        $('#selCode').append(objOption);
    }
    // clear the other text boxes
    $('#txtCode').val('').prop("disabled", false);
    $('#txtDesc').val('');
    $('#txtAddl').val('');
}

});

