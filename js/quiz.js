/**
 * Created by MOODLER on 8/19/2015.
 */

$( document ).ready(function() {
    $(function(){
        $("#qall-participants-chk").change(function(){
            var item = $(this);
            if(item.is(":checked")) {
                window.location.href = item.data("target")
            } else {
                window.location.href = item.data("target-off")
            }
        });
        $('.markingmanagercontainer-footer-link').popupWindow({
            height:600,
            width:800,
            centerScreen:1,
            scrollbars:1
        });
    });
});