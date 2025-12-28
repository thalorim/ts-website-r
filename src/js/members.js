$(document).ready(function() {
    var membersTable = $("#memberlist").DataTable({
        responsive: {
            details: {
                type: "column",
                target: "tr"
            }
        },
        order: [
            [0, "asc"]  // Sort by Client ID (column 0) ascending (lowest first)
        ],
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.10.19/i18n/" + DATATABLES_LANGUAGE_NAME + ".json"
        },
        initComplete: function(settings, json) {
            console.log("DataTables Loaded")
            $("#members-loader").hide()
            $("#memberlist").show()
        }
    });

    var responsiveTip = $("#responsive-table-details-tip")

    // show / hide the tip about responsive tables
    membersTable.on("responsive-resize", function () {
        if (membersTable.responsive.hasHidden() && !Cookies.get("tswebsite_memberrowtip_hide")) {
            responsiveTip.show()
        } else {
            responsiveTip.hide()
        }
    });

    // preserve alert dismiss with a cookie
    responsiveTip.find(".close").click(function (e) {
        e.preventDefault()
        Cookies.set("tswebsite_memberrowtip_hide", true, {expires: 365});
    })
});
