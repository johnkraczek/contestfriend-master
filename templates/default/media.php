<?php
/**
* Default media template called by main widget.php template.
* @package chTemplate
*/

$widget = cf_Widget::current_widget();
$contest = $widget->contest;
$widget_id = $widget->widget_id;

$media = $contest->cf_media;
if(empty($media))
    return '';

if($media=='image')
{
    echo '<img src="'.esc_attr($contest->cf_image).'" />';
}    
else if($media=='video')
{
    $video_js = 'http://vjs.zencdn.net/c/video.js';
    $video_css = 'http://vjs.zencdn.net/c/video-js.css';
    
    wp_enqueue_script('cf_js_videojs', $video_js);
    wp_enqueue_script('cf_js_videojs_responsive', cf_Manager::$plugin_url.'/js/cf_videojs_responsive.js');
    wp_enqueue_style('cf_css_videojs', $video_css);
    
    $video_type = 'video/'.substr($contest->cf_video, strrpos($contest->cf_video, '.')+1);

    echo '<video id="'.$widget_id.'_video" style="width: 100%" class="cf_video video-js vjs-default-skin" controls preload="auto" poster="" data-setup="{}">
<source src="'.esc_attr($contest->cf_video).'" type="'.esc_attr($video_type).'">
</video>';

}
else if($media=='video_youtube')
{
    wp_enqueue_script('cf_js_youtube_responsive', cf_Manager::$plugin_url.'/js/cf_youtube_responsive.js');
    
    $youtube = $contest->cf_video_youtube;
    $youtube_id = strstr($youtube, 'v=');
    if($youtube_id===false)
    {
        $youtube_id = '';
        $youtube = '';
    }
    else
        $youtube_id = substr($youtube_id, 2, strlen($youtube_id));

    if(!empty($youtube_id))
        echo '<iframe src="http://www.youtube.com/embed/'.esc_attr($youtube_id).'" class="cf_youtube_video" frameborder="0" allowfullscreen></iframe>';
}
