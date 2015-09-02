/*
 * Collapse/Expand all courses/assessments. If we are in the course,
 * then only collapse/expand all assessments.
 */
function togglecollapseall(iscoursecontext) {
    if($('dl').hasClass('expanded')) {
        $('.toggle').removeClass('open');
        if (!iscoursecontext) {
            $('dd').addClass('block_fn_marking_hide');
        }
        $('dd ul').addClass('block_fn_marking_hide');
        $('dl').removeClass('expanded');
    } else {
        $('.toggle').addClass('open');
        if (!iscoursecontext) {
            $('dd').removeClass('block_fn_marking_hide');
        }
        $('dd ul').removeClass('block_fn_marking_hide');
        $('dl').addClass('expanded');
    }
}
$(document).ready(function() {
    $('div.fn-collapse-wrapper').parent().addClass("fn-full-width");
});