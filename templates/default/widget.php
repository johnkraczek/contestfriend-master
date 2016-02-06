<?php
/**
* Default core template file called automatically by cf_Widget::html().
* @package chTemplate
*/

$widget = cf_Widget::current_widget();
$contest = $widget->contest;
$participant = $widget->participant;
$url = $widget->url;
$ref = $widget->ref;
$widget_id = $widget->widget_id;

// fonts
//***********
$typekit_id = $contest->cf_typekit;

$headline_font = '';
$description_font = '';

// adobe typekit
if(!empty($typekit_id))
{
    wp_enqueue_script('cf_js_typekit_remote', 'http://use.typekit.net/'.esc_attr($typekit_id).'.js');
    wp_enqueue_script('cf_js_typekit', cf_Manager::$plugin_url.'/js/cf_typekit.js');
}
else // google or default fonts
{
    $fonts = $widget->prepare_font(); // TODO location of font helper functions does not make much sense
    $google_style = $widget->google_style($fonts);
    
    if(!empty($google_style))
        wp_enqueue_style('cf_google_fonts_'.$contest->ID, $google_style);

    $headline_font = ' font-family: \''.esc_attr($fonts['headline']['font']).'\', sans-serif;';
    $description_font =  ' font-family: \''.esc_attr($fonts['description']['font']).'\', serif;';
}

// max widget size
//**********
$widget_size = $contest->cf_widget_size;
if(!empty($widget_size) && is_numeric($widget_size))
    $widget_size = ' max-width: '.$widget_size.'px;';

// widget container
//***********
$widget_container = false;
if($contest->cf_container=='1')
    $widget_container = true;

// colors
//***********
$bg_title_color = esc_attr($contest->cf_title_background_color);
$bg_color = esc_attr($contest->cf_background_color);
$border_color = esc_attr($contest->cf_border_color);
$headline_color = esc_attr($contest->cf_headline_color);
$description_color = esc_attr($contest->cf_description_color);

// description and media layout
//**********
$layout = $contest->cf_media_description_layout;
if(!in_array($layout, array('media-top', 'description-top', 'inline_media-left', 'inline_description-left')))
    $layout = 'inline_description-left';   

$media_css = 'cf_media';
$description_css = 'cf_description';

$override_layout = false;
$description_text = $contest->cf_description;
if(empty($description_text) || !in_array($contest->cf_media, array('image', 'video', 'video_youtube')))
    $override_layout = true;

if($layout=='inline_media-left' && !$override_layout)
{
    $media_css .= ' inline float_left';
    $description_css .= ' inline float_right';
}   
else if($layout=='inline_description-left' && !override_layout)
{
    $media_css .= ' inline float_right';
    $description_css .= ' inline float_left';
}

// description text align
//***********
$description_align = ' text-align: center;';
if($contest->cf_description_align=='left')
    $description_align = ' text-align: left;';
else if($contest->cf_description_align=='right')
    $description_align = ' text-align: right;';

// widget html code start
//**********

// head, title
echo '<div id="'.$widget_id.'" class="cf_widget large" style="'.$widget_size.'background-color: '.$bg_color.'">
<div class="cf_widget-inside" style="border-color: '.$border_color.'">
<div class="cf_title" style="border-bottom-color: '.$border_color.'; background-color: '.$bg_title_color.'; color: '.$headline_color.';'.$headline_font.'">
<img src="'.cf_Manager::$plugin_url.'/img/trophy.png" alt="Trophy"/>'.esc_html($contest->cf_headline).'
</div>';

// boxes
echo cf_Widget::get_template('boxes');

// media, description
$media_data = cf_Widget::get_template('media');
$media = '<div align="center" class="'.$media_css.'">'.$media_data.'</div>';
$description = '<div class="'.$description_css.'" style="color: '.$description_color.';'.$description_align.$description_font.'">'.apply_filters('cf_description', $description_text).'</div>';

if($layout=='description-top')
    echo $description.$media;
else // media-top or others
    echo $media.$description;

echo '<div class="cf_actions cf_clear" style="border-top-color: '.$border_color.'">
    <div class="cf_actions_inner">';

if(!$contest->is_expired() && $contest->is_started()) // if the contest is active
{
    if(!empty($participant) && $participant->status!='not_confirmed')
        echo cf_Widget::get_template('actions_submit');
    else if(!empty($participant) && $participant->status=='not_confirmed')
        echo cf_Widget::get_template('double_optin');
    else
        echo cf_Widget::get_template('actions');
            
    if($contest->cf_countdown_field=='1')
        echo cf_Widget::get_template('countdown');
    
    if(!empty($participant) && $participant->status!='not_confirmed')
        echo '<div class="cf_contact_message">'.__('Winner(s) will be contacted by email.', 'contestfriend').'</div>';
}
else if(!$contest->is_started())
    echo '<div class="cf_error">'.__('Contest has not yet started.', 'contestfriend').'</div>';
else
    echo '<div class="cf_error">'.__('This contest expired.', 'contestfriend').'</div>';
 
echo '</div></div>
<div class="cf_footer">
        <span class="cf_rules_disclaimer">';

if($contest->cf_disclaimer_rules_type!='none')
{
    if($contest->cf_disclaimer_rules_type=='popup')
        echo '<a href="#" class="cf_rules_disclaimer_link" id="'.$widget_id.'_dialog_link">';
    else if($contest->cf_disclaimer_rules_type=='url')
        echo '<a href="'.$contest->cf_disclaimer_rules_url.'" target="_blank">';

    echo __('Official Rules', 'contestfriend').'</a>';
}
require 'setup.php';

if($contest->cf_disclaimer_rules_type=='popup')
{
    echo '
    <div id="'.$widget_id.'_dialog" class="cf_rules_disclaimer_wrap">
        <div class="cf_rules_disclaimer_dialog">
            <span class="dialog_close"><u>close</u></span>
            <h2>'.__('Contest Rules & Disclaimer', 'contestfriend').'</h2>
            <p>'.nl2br(esc_html($contest->cf_rules)).'</p><p>'.nl2br(esc_html($contest->cf_disclaimer)).'</p>
        </div>
    </div>';
}

echo '</div>';
