pad = function (number, length) { 
    return (number+"").length >= length ? number + "" : pad("0" + number, length);
}

cf_ctdn = function (timeDiff, contest_id) 
{
    timeDiff = parseInt(timeDiff);
    
    if (timeDiff <= 0) { // timer ends
        clearTimeout(timer);
        jQuery('#'+contest_id+' .cf_countdown_days').html("0 days ");
        jQuery('#'+contest_id+' .cf_countdown_hours').html("00h ");
        jQuery('#'+contest_id+' .cf_countdown_minutes').html("00m ");
        jQuery('#'+contest_id+' .cf_countdown_seconds').html("00s ");
        return;
    }
    var seconds = timeDiff;
    var minutes = Math.floor(seconds / 60);
    var hours = Math.floor(minutes / 60);
    var days = Math.floor(hours / 24);
    days = parseInt(days, 10);
    hours %= 24;
    hours = parseInt(hours, 10);
    minutes %= 60;
    minutes = parseInt(minutes, 10);
    seconds %= 60;
    seconds = parseInt(seconds, 10);

    var days_text = days;
    if(days>1 || days<=0)
        days_text += ' days ';
    else
        days_text += ' day ';
        
    jQuery('#'+contest_id+' .cf_countdown_days').html(days_text);
    jQuery('#'+contest_id+' .cf_countdown_hours').html(pad(hours,2)+"h ");
    jQuery('#'+contest_id+' .cf_countdown_minutes').html(pad(minutes,2)+"m ");
    jQuery('#'+contest_id+' .cf_countdown_seconds').html(pad(seconds,2)+"s ");

    var timer = setTimeout(function() { cf_ctdn(timeDiff-1, contest_id)},1000);
}

jQuery(document).ready(function() {
        
    jQuery('.cf_rules_disclaimer_link').live('click', function(evt) {
        evt.preventDefault();
        var id = jQuery(this).attr('id');
        id = id.replace('_link', '');
        jQuery('#'+id).fadeIn();
        return false;
    });
    
    jQuery('.dialog_close').live('click', function(evt) {
        evt.preventDefault();
        var id = jQuery(this).parent().parent().attr('id');
        jQuery('#'+id).fadeOut();
        return false;    
    });
    
    jQuery('.cf_rules_disclaimer_wrap').live('click', function(evt) {
       evt.preventDefault();
       jQuery(this).fadeOut(); 
    });
    
    jQuery('.cf_rules_disclaimer_dialog').live('click', function(evt) {
       return false; 
    });
    
    jQuery('.cf_widget').each(function(){
        var widget_div = jQuery(this).attr('id');

        jQuery('#'+widget_div+' form').submit(function(evt) {
           evt.preventDefault();
           
           if(jQuery('#'+widget_div+' .cf_submit_div').hasClass('cf_loading'))
               return false;

           var first_name_sel = '#'+widget_div+' input.first_name';
           var last_name_sel = '#'+widget_div+' input.last_name';
           var email_sel = '#'+widget_div+' input.cf_email';
           
           var cf_email = jQuery(email_sel).val();
           var cf_first_name = jQuery(first_name_sel).val();
           var cf_last_name = jQuery(last_name_sel).val();
           var cf_contest_id = jQuery('#'+widget_div+' .cf_contest_id').val();
           var cf_ref = jQuery('#'+widget_div+' .cf_ref').val();
           var cf_url = jQuery('#'+widget_div+' .cf_url').val();
           
           var req_error = false;
           
           var focus = null;
           
           // validate first name
           if(jQuery(first_name_sel).hasClass('cf_required'))
           {
               if(cf_first_name.length<2)
               {
                   focus = first_name_sel;
                   jQuery(first_name_sel).addClass('error_req');
                   req_error = true;
               }
               else
                jQuery(first_name_sel).removeClass('error_req');
           }
           
           // validate last name
           if(jQuery(last_name_sel).hasClass('cf_required'))
           {
               if(cf_last_name.length<2)
               {
                   if(focus==null)
                        focus = last_name_sel;
                        
                   jQuery(last_name_sel).addClass('error_req');
                   req_error = true;
               }
               else
                jQuery(last_name_sel).removeClass('error_req');
           }
           
           // validate email
           if(cf_email.match(/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/)!=cf_email)
           {
               if(focus==null)
                    focus = email_sel;
                    
               jQuery(email_sel).addClass('error_req');
               req_error = true;
           }
           else
            jQuery(email_sel).removeClass('error_req');
           
           if(req_error)
           {
               jQuery(focus).focus();
               return false;
           }
           
           if(jQuery('#'+widget_div).parent().hasClass('cf_widget_preview'))
               return false;
           
           // ajax data
           var data = {
               // TODO: take all input parameters automatically, do not enum them
               'action': 'contestfriend_process',
               'email': cf_email,
               'first_name': cf_first_name,
               'last_name': cf_last_name,
               'contest_id': cf_contest_id,
               'div_id': widget_div,
               'cf_ref': cf_ref,
               'url': cf_url
           };
           
           // loading
           jQuery('#'+widget_div+' .cf_submit_div').addClass("cf_loading");
        
           // post ajax
           jQuery.post(cf_ajax.ajaxurl, data, function(response) {
               
               // responsive video hacks part1
               var custom_video = false;
               var custom_youtube = false;
               
               if(jQuery('.cf_youtube_video').length>0)
                   custom_youtube = true;
               
               if(jQuery('#'+widget_div+'_video').length > 0 && (typeof _V_ !== 'undefined'))
                   custom_video = true;
               
               if(custom_video)
                   _V_(widget_div+'_video').destroy();
               
               // replace widget content
               jQuery('#'+widget_div).replaceWith(response);
               
               // call widget_onresize function to add small / large class
               widget_onresize();
               
               // responsive video hacks part2
               if(custom_youtube)
                   resizeVideoYT();
                   
               if(custom_video)
               {
                    _V_(widget_div+"_video", { "controls": true, "autoplay": false, "preload": "auto" });
                    resizeVideoJS();
               }
               
           });
        
           return false;  
        
        });
    });

    widget_onresize = function()
    {
        jQuery(".cf_widget").each(function() {
          var sidebarWidth = jQuery(this).outerWidth(true);
          jQuery(this).toggleClass('small', sidebarWidth < 400);
          jQuery(this).toggleClass('large', sidebarWidth >= 400);
        });
    }
    
    widget_onresize();        
    jQuery(window).resize(function() {
        widget_onresize();
    });
    
});