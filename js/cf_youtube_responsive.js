jQuery(document).ready(function() {
  
   var aspectRatio = 9/16;
   
   resizeVideoYT = function()
   {
       jQuery('.cf_youtube_video').each(function() {
           var width = jQuery(this).width();
           jQuery(this).height(width*aspectRatio);
       });
   }
   
   resizeVideoYT();
   jQuery(window).resize(resizeVideoYT);
});
