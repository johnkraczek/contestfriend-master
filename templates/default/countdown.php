<?php
/**
* Default countdown template called by main widget.php template.
* @package chTemplate
*/

$widget = cf_Widget::current_widget();
$widget_id = $widget->widget_id;
$time = $widget->contest->get_utc_time()-(int)current_time('timestamp', 1);

echo <<<HTML
<div class="cf_countdown">
    <span class="cf_countdown_days"></span><span class="cf_countdown_hours"></span><span class="cf_countdown_minutes"></span><span class="cf_countdown_seconds"></span><span class="sh">remaining</span>
    <div class="cf_clear"></div>
</div>
        
<script type="text/javascript">
jQuery(document).ready(function() {   
    cf_ctdn('{$time}', '{$widget_id}');
});
</script>
HTML;
