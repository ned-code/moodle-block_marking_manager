if (typeof jQuery == "undefined") {
    document.write(unescape("%3Cscript type=\"text/javascript\" src=\"" + M.cfg.wwwroot + "/blocks/fn_marking/js/tablesorter/jquery-latest.js\"%3E%3C/script%3E"));
}
$(document).ready(function() {
    $("#datatable").tablesorter({
        sortList: [[0,0]],
        headers: {
            '.sorter-false' : {
                sorter: false
            }
        }
    });
    $("table.simplegradebook tr div.row-toggle").click(function() {
        $(this).toggleClass('highlighted');
        var selected = $(this).parent().parent();
        selected.toggleClass("highlight");
    });
});