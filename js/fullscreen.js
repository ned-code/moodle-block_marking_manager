/**
 * Created by CISSQ on 8/30/2016.
 */
$(document).ready(function() {
    $('#page-blocks-fn_marking-fn_gradebook .visibleifjs a.btn').html('Annotate PDF');
    $('#fitem_id_assignfeedbackcomments_editor').has('#menuassignfeedbackcomments_editorformat').next().css( "margin-top", "-55px" );

    $('div.card-block label[for=id_assignfeedbackcomments_editor]').parent().hide();
    $('#id_assignfeedbackcomments_editor').parent().parent().parent().removeClass('col-md-9');
    $('#id_assignfeedbackcomments_editor').parent().parent().parent().addClass('col-md-12');

    var onZoom = function () {
        var zoomin = $('body').hasClass('zoomin');
        if (zoomin) {
            $('body').removeClass('zoomin');
            M.util.set_user_preference('block_fn_marking_zoom', 'nozoom');
        } else {
            $('body').addClass('zoomin');
            M.util.set_user_preference('block_fn_marking_zoom', 'zoomin');
        }
    };

    $(".ned-hide-blocks").click(function() {
        onZoom();
    });

    $(".ned-change-html-editor").click(function() {
        var datastatus = $(this).attr('data-status');
        if (datastatus == 'showonlineeditor') {
            M.util.set_user_preference('block_fn_marking_onlineeditor', 'show');
            alert('HTML editor was enabled.');
        } else {
            M.util.set_user_preference('block_fn_marking_onlineeditor', 'hide');
            alert('HTML editor was disabled.');
        }
        location.reload();
    });

    $("#ned-override-remover").click(function() {
        var userid = $(this).attr('userid');
        var mod = $(this).attr('mod');
        var instance = $(this).attr('instance');
        var action = $(this).attr('action');
        var sesskey = $(this).attr('sesskey');
        $(this).attr('disabled', 'disabled');
        $.ajax({
            type: "POST",
            url: M.cfg.wwwroot +"/blocks/fn_marking/grade_override.php",
            data: {
                'mod': mod,
                'action': action,
                'instance': instance,
                'userid': userid,
                'sesskey': sesskey
            },
            dataType: "json",
            success: function (data) {
                var success = data.success;
                var message = data.message;
                if (success === true) {
                    window.location.reload();
                } else {
                    alert(message);
                }
            }
        });
    });

    $('#open-grade-report-link').popupWindow({
        height:600,
        width:800,
        centerScreen:1,
        scrollbars:1
    });

    $("#open-grade-report-link-check").click(function() {
        window.location.reload();
        return false;
    });
});