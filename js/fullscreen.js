/**
 * Created by CISSQ on 8/30/2016.
 */
$(document).ready(function() {
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
});