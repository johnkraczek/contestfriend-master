jQuery(document).ready(function() {
  
   var aspectRatio = 9/16;
   
   resizeVideoJS = function()
   {
       jQuery('.cf_video').each(function() {
           var video_id = jQuery(this).attr('id');
           var width = jQuery(this).parent().width();
           _V_(video_id).width(width).height(width*aspectRatio);

       });
   }
   
   resizeVideoJS();
   jQuery(window).resize(resizeVideoJS);
});
