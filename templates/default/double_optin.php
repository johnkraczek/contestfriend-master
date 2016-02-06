<?php
/**
* Default double opt-in (after registering for a contest and it has double opt-in) template called by main widget.php template.
* @package chTemplate
*/

$widget = cf_Widget::current_widget();
$text = nl2br(esc_attr($widget->contest->cf_double_optin_message));

echo <<<HTML
<div class="double_optin_message">
{$text}
</div>
HTML;
