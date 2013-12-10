M.local_panopto = {
    Y : null,
    transaction : [],
    init : function(Y, courseid, permstr, role_assign_bool, editing) {
        var panopto = Y.one("#panopto-text");
        var panoptofooter = Y.one("#panopto-footer");
        panopto.setHTML("Requesting data...");
        
        Y.io(M.cfg.wwwroot + "/blocks/panopto/ajax.php", {

            timeout: 8000,
            method: "GET",
            data: {
                sesskey: M.cfg.sesskey,
                courseid: courseid,
                permstr: permstr,
                role_assign_bool: role_assign_bool,
                editing: editing
            },
            on: {
                success : function (x,o) {
                    // Process the JSON data returned from the server
                    try {
                        data = Y.JSON.parse(o.responseText);
                    }
                    catch (e) {
                        panopto.setHTML("Unable to obtain panopto data (Err Id: 1)");
                        return;
                    }

                    if (data.error) {
                        panopto.setHTML("Unable to obtain panopto data (Err Id: 2)");
                    } else {
                        panopto.setHTML(data.text);
                        panoptofooter.setHTML(data.footer);
                    }
                },

                failure : function (x,o) {
                    if (o.statusText == "timeout") {
                        panopto.setHTML("Panopto seems to be a bit busy right now! Try again later.");
                    } else {
                        panopto.setHTML("Unable to obtain panopto data (Err Id: 3)");
                    }
                }
            }
        });
    }
}