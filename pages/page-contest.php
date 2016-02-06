<?php

/**
* Admin page: contest.
* @package chPage 
*/

/**
* Page class that handles back-end page <i>Contest</i> with form generation and processing.
* @package chPage
*/
class cf_Page_Contest
{
    /**
    * Page slug.
    */
    const page_id = 'cf_page_contest';
    /**
    * Page hook.
    * @var string
    */
    protected $page_hook;
    /**
    * Page tabs.
    * @var mixed
    */
    protected $tabs;
    /**
    * Postboxes for current tab.
    * @var mixed
    */
    protected $boxes;
    /**
    * Current contest.
    * @var cf_Contest
    */
    protected $contest;
    
    /**
    * Constructs new page object and adds new entry to WordPress admin menu.
    */
    function __construct()
    {
        $this->contest = false;
        
        $this->page_hook = add_submenu_page(cf_Page_List::page_id, 'Add/Edit Contest', 'Add New', 'manage_options', self::page_id, array(&$this, 'generate'));   
        add_action('load-'.$this->page_hook, array(&$this, 'init'));
    }
    
    /**
    * Init method, called when accessing the page. Handles tab setup, boxes setup, contest object loading and processing of $_POST and $_GET requests.
    */
    function init()
    {        
        $this->tabs = array(
            'description' => array(
                'title' => __('Description', 'contestfriend')
            ),
            'design' => array(
                'title' => __('Design', 'contestfriend')
            ),
            'settings' => array(
                'title' => __('Settings', 'contestfriend')
            ),
            'publish' => array(
                'title' => __('Publish', 'contestfriend'),
                'custom' => true                
            ),
            
            'preview' => array(
                'title' => __('Live Preview', 'contestfriend'),
                'custom' => true
            ),
            
            'dashboard' => array(
                'title' => __('Dashboard', 'contestfriend'),
                'custom' => true
            )
        );
        
        if(empty($_GET['cf_page']))
            $_GET['cf_page'] = 'description';

        $this->tabs = apply_filters('cf_contest_tabs', $this->tabs);
        
        $this->boxes = array();
        
        if($_GET['cf_page']=='description' || empty($_GET['cf_page']))
        {
            $this->setup_description();
        }
        else if($_GET['cf_page']=='design')
        {
            $this->setup_design();
        }
        else if($_GET['cf_page']=='settings')
        {
            $this->setup_settings();
        }
        
        $this->boxes = apply_filters('cf_contest_boxes', $this->boxes, $_GET['cf_page']);
        
        if(empty($_GET['contest']))
        {
            $contest_id = cf_Contest::get_new_id();
            $cf_page = '';
            if(isset($_GET['cf_page']))
                $cf_page = '&cf_page='.$_GET['cf_page'];
                
            wp_safe_redirect(admin_url('admin.php?page='.self::page_id.$cf_page.'&contest='.$contest_id));
            die();
        }
        
        $this->contest = new cf_Contest($_GET['contest']);
        if($this->contest->_valid!=true) 
        {            
            wp_safe_redirect(admin_url());
            die();    
        }
                
        if(isset($_POST['_cf_form_post']))
        {
            $fname = 'process_'.$_GET['cf_page'];
            
            if(isset($this->tabs[$_GET['cf_page']]['custom']) && $this->tabs[$_GET['cf_page']]['custom']==true && method_exists($this, $fname))
                 $this->$fname();
             else 
                 $this->process_default();
        }
        
        wp_enqueue_style('cf_css_base');
        add_action('admin_footer', array(&$this, 'list_scripts'));
        add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts')); 
    }
    
    /**
    * Lists javascripts in page footer.
    */
    function list_scripts()
    {
        /*echo '
        <script type="text/javascript">
        jQuery(".confirm_setwinner").submit(function() {
            var res = confirm("'.__('Are you sure you want to pick winner(s)? You will not be able to resume this contest.', 'contestfriend').'");
            if(!res)
                return false;
            return true;
        });
        </script>
        ';*/
    }
    
    /**
    * Enqueues scripts.
    */
    function enqueue_scripts()
    {
        wp_enqueue_style('cf_css_base');
        wp_enqueue_style('cf_css_jquery_ui');
        wp_enqueue_style('farbtastic');
        wp_enqueue_style('thickbox');
 
        wp_enqueue_script('farbtastic');
        wp_enqueue_script('cf_js_datetimepicker');
        wp_enqueue_script('media-upload');
        wp_enqueue_script('thickbox');
        wp_enqueue_script('jquery');
    }
    
    /**
    * Generates page content.
    */
    function generate()
    {   
        reset($this->tabs);
        $first_fname = key($this->tabs);
        
        if(empty($_GET['cf_page']))
            $_GET['cf_page'] = $first_fname;  
            
        echo '<div class="wrap">
        <h2>contestfriend</h2>';
        
        $this->generate_menu();
                
        $fname = 'generate_'.$_GET['cf_page'];
        
        if(isset($this->tabs[$_GET['cf_page']]['custom']) && $this->tabs[$_GET['cf_page']]['custom']==true && method_exists($this, $fname))
             $this->$fname();
         else
         {
             echo '
            <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-1">
            <div id="post-body-content">
            <form action="admin.php?page='.self::page_id.'&cf_page='.$_GET['cf_page'].'&contest='.$_GET['contest'].'" method="post">
            <input type="hidden" name="_cf_form_post" value="1" />
            ';
            // TODO add nonce
            
             $this->generate_default();  
             
             echo '<input type="submit" name="gonext" value="'.__('Save all and Next', 'contestfriend').'" class="button-primary" /></form></div></div></div>';
         }
      
        echo '</div>';
    }
    
    /**
    * Processes default request and updates the contest settings.
    */
    protected function process_default()
    {
        $boxes = $this->boxes;
        
        $contest = new cf_Contest($_GET['contest']);
        if($contest->_valid!==true)
        {
            wp_safe_redirect(admin_url('admin.php?page='.self::page_id));
            die();
        }
        
        $post_array = array();
        foreach($boxes as $box)
        {
            foreach($box['fields'] as $field_name => $field)
            {
                if(!isset($_POST[$field_name]))
                    $post_array[$field_name] = '';
                else
                    $post_array[$field_name] = $_POST[$field_name];
            }
        }
        
        $contest->from_array($post_array);
        $res = $contest->save();
        
        if($res)
        {
            $goto = $_GET['cf_page'];
            if(isset($_POST['gonext']))
            {
                if($_GET['cf_page']=='description')
                    $goto = 'design';
                else if($_GET['cf_page']=='design')
                    $goto = 'settings';
                else if($_GET['cf_page']=='settings')
                    $goto ='publish';
            }
            wp_safe_redirect(admin_url('admin.php?page='.cf_Page_Contest::page_id.'&cf_page='.$goto.'&contest='.$_GET['contest']));
        } 
    }
    
    /**
    * Generates the default page-tab content.
    */
    protected function generate_default()
    {
        foreach($this->boxes as $key=>$value)
        {
            $css = ' class="postbox';
            if(!empty($value['css']))
                $css .= ' '.$value['css'];
            $css .= '"';
                
            echo '<div id="'.$key.'"'.$css.'>
            <h3>'.$value['title'].'</h3>
            <div class="inside">
            
            <table class="form-table">
            ';
            
            foreach($value['fields'] as $key=>$field)
            {
                $fname = 'field_'.$field['type'];
                $value = '';
                
                if(isset($field['default']))
                    $value = $field['default'];
                    
                if(isset($this->contest->$key))
                    $value = $this->contest->$key;
                
                if(method_exists($this, $fname))
                {
                    if(isset($field['dynamic']) && method_exists($this, $field['dynamic']))
                        $this->$field['dynamic']($key, $field);
                       
                    echo $this->$fname($key, $field, $value);
                }
                else
                    echo '<tr><td colspan="2">'.__('Missing input type:', 'contestfriend').' '.$field['type'].'</td></tr>';
            }
            
            echo '</table><input type="submit" value="'.__('Save all changes', 'contestfriend').'" class="button-primary" /></div></div>';
        }
    }
    
    /**
    * Generates text field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_text($name, $field, $value = '') // TODO unify the field $id, $id_input, $css, ... preprocess for all fields
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css = '';
        if(!empty($field['css']))
            $css = ' class="'.$field['css'].'"';
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label"><label for="'.$id.'">'.$field['title'].'</label></th><td>';
        $output .= '<input type="text"'.$id_input.' name="'.$name.'"'.$css.' value="'.esc_attr($value).'" />';
        
        if(!empty($field['description']))
            $output .= '<br /><small>'.$field['description'].'</small>';
        
        $output .= '</td></tr>';
        
        return $output;
    }
    
    /**
    * Generates textarea field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_textarea($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css = '';
        if(!empty($field['css']))
            $css = ' class="'.$field['css'].'"';
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label"><label for="'.$id.'">'.$field['title'].'</label></th><td>';
        $output .= '<textarea'.$id_input.' name="'.$name.'" rows="6" cols="60"'.$css.'>'.esc_attr($value).'</textarea>';
        
        if(!empty($field['description']))
            $output .= '<br /><small>'.$field['description'].'</small>';
                
        $output .= '</td></tr>';
        
        return $output;
    }
    
    /**
    * Generates TinyMCE field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_editor($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label"><label for="'.$id.'">'.$field['title'].'</label></th><td>';
        
        ob_start();
        wp_editor($value, $id, array('media_buttons' => false, 'textarea_name' => $name, 'teeny' => false));
        $output .= ob_get_clean(); 
        
        if(!empty($field['description']))
            $output .= '<br /><small>'.$field['description'].'</small>';
                
        $output .= '</td></tr>';
        
        return $output;
    }
        
    /**
    * Generates singular checkbox field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_checkbox_singular($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css = '';
        if(!empty($field['css']))
            $css = ' class="'.$field['css'].'"';
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
            
        $checked = '';
        if($value=='1' || $value=='checked')
            $checked = ' checked="checked"';
            
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label"><label for="'.$id.'">'.$field['title'].'</label></th><td><input type="checkbox"'.$id_input.' name="'.$name.'"'.$css.' value="1"'.$checked.' />';
        
        if(!empty($field['description']))
            $output .= '<br /><small>'.$field['description'].'</small>';
        
        $output .= '</td></tr>';
        
        if(!empty($field['conditional']))
        {
            $output .= '<script type="text/javascript">
            jQuery(document).ready(function() {';
            
            $output .= 'if(jQuery(\'#'.$id.'\').is(\':checked\')) {';
                
            foreach($field['conditional'][0] as $val)
                $output .= 'jQuery(\'#'.$val.'\').hide();';
  
            foreach($field['conditional'][1] as $val)
                $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
            
            $output .= '} else {';
            
            foreach($field['conditional'][1] as $val)
                $output .= 'jQuery(\'#'.$val.'\').hide();';
  
            foreach($field['conditional'][0] as $val)
                $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
            
            $output .= '}';         
               
            $output .= 'jQuery(\'#'.$id.'\').change(function() {
                if(jQuery(\'#'.$id.'\').is(\':checked\')) {';
                
            foreach($field['conditional'][0] as $val)
                $output .= 'jQuery(\'#'.$val.'\').hide();';
  
            foreach($field['conditional'][1] as $val)
                $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
            
            $output .= '} else {';
            
            foreach($field['conditional'][1] as $val)
                $output .= 'jQuery(\'#'.$val.'\').hide();';
  
            foreach($field['conditional'][0] as $val)
                $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
            
            $output .= '}
                });
            });
            </script>';
        }
        
        return $output;
    }
    
    /**
    * Generates select field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_select($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css = '';
        if(!empty($field['css']))
            $css = ' class="'.$field['css'].'"';
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label">'.$field['title'].'</th><td><select name="'.$name.'"'.$css.$id_input.'>';
        
        if(isset($field['options']) && is_array($field['options']))
        {
            foreach($field['options'] as $key=>$val)
            {
                if(is_array($val)) // optgroups
                { 
                    $output .= '<optgroup label="'.$key.'">';
                    foreach($val as $key2=>$val2)
                    {
                        $selected = '';
                        if($value==$key2)
                            $selected = ' selected="selected"';
                        $output .= '<option value="'.$key2.'"'.$selected.'>'.$val2.'</option>';
                    }
                    $output .= '</optgroup>';
                }
                else
                {                
                    $selected = '';
                    if($value==$key)
                        $selected = ' selected="selected"';
                    $output .= '<option value="'.$key.'"'.$selected.'>'.$val.'</option>';
                }
            }
        }
        
        $output .= '</select>';
        
        if(!empty($field['description']))
            $output .= '<br /><small>'.$field['description'].'</small>';
        
        $output .= '</td></tr>';
        
        if(!empty($field['conditional']))
        {
            $output .= '<script type="text/javascript">
            jQuery(document).ready(function() {';
            foreach($field['conditional'] as $key=>$val)
            {
                $output .= 'if(jQuery(\'#'.$id.'\').val()==\''.$key.'\') {
                ';
                if(is_array($val))
                {
                    foreach($val as $v)
                        $output .= 'jQuery(\'#'.$v.'\').show(\'fast\');';
                }
                else
                    $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
                
                $output .= '}';
            }
            
            $output .= 'jQuery(\'#'.$id.'\').change(function() {';
                        
            foreach($field['conditional'] as $key=>$val)
            {
                if(is_array($val))
                {
                    foreach($val as $v)
                        $output .= 'jQuery(\'#'.$v.'\').hide();';
                }
                else
                    $output .= 'jQuery(\'#'.$val.'\').hide();';
            }
                
            foreach($field['conditional'] as $key=>$val)
            {
                $output .= 'if(jQuery(\'#'.$id.'\').val()==\''.$key.'\') {
                ';
                
                if(is_array($val))
                {
                    foreach($val as $v)
                        $output .= 'jQuery(\'#'.$v.'\').show(\'fast\');';
                }
                else
                    $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
            
                $output .= '}'; 
            }
               
            $output .= '
                });
            });
            </script>';
        }
        
        return $output;
    }
    
    /**
    * Generates color field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_color($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
                
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label">'.$field['title'].'</th><td><input type="text"'.$id_input.' name="'.$name.'" value="'.esc_attr($value).'" /> <input type="button" class="button" id="'.$id.'_button" value="'.__('Pick a color', 'contestfriend').'" /> <div id="'.$id.'_picker"></div>';
        
        if(!empty($field['description']))
            $output .= '<br /><small>'.$field['description'].'</small>';
        
        $output .= '</td></tr>';
        
        $output .= '<script type="text/javascript">
        jQuery(document).ready(function() {
            jQuery(\'#'.$id.'_picker\').hide();
            jQuery(\'#'.$id.'_picker\').farbtastic(\'#'.$id.'\');
            jQuery(\'#'.$id.'_button\').click(function(){jQuery(\'#'.$id.'_picker\').slideToggle()});
            jQuery(\'#'.$id.'\').click(function(){jQuery(\'#'.$id.'_picker\').slideToggle()});
        });
        </script>';
       
        return $output;
    }
    
    /**
    * Generates checkbox field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_checkbox($name, $field, $value = '')
    {
        if(is_serialized($value))
            $value = unserialize($value);
            
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label">'.$field['title'].'</th><td>';
        
        if(isset($field['options']) && is_array($field['options']))
        {
            foreach($field['options'] as $key=>$val)
            {
                $checked = '';
                if(!empty($value) && ((is_array($value) && in_array($key, $value)) || (!is_array($value) && $key==$value)))
                    $checked = ' checked="checked"';
                    
                $output .= '<input type="checkbox" id="'.$name.'_'.$key.'" name="'.esc_attr($name).'[]" value="'.esc_attr($key).'"'.$checked.' /> <label for="'.$name.'_'.$key.'">'.esc_html($val).'</label><br />';
            }
        }
        
        if(!empty($field['description']))
            $output .= '<small>'.$field['description'].'</small>';
        
        $output .= '</td></tr>';
        
        if(!empty($field['conditional']))
        {
            $output .= '<script type="text/javascript">
            jQuery(document).ready(function() {';
            foreach($field['conditional'] as $key=>$val)
            {
                $output .= 'if(jQuery(\'#'.$id.'_'.$key.'\').is(\':checked\')) {
                ';
                if(is_array($val))
                {
                    foreach($val as $v)
                        $output .= 'jQuery(\'#'.$v.'\').show(\'fast\');';
                }
                else
                    $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
                
                $output .= '}';
            }
            
            foreach($field['conditional'] as $key=>$val)
            {
                $output .= 'jQuery(\'#'.$id.'_'.$key.'\').change(function() {';
                
                $output .= 'if(jQuery(this).is(\':checked\')) {
                ';
                
                if(is_array($val))
                {
                    foreach($val as $v)
                        $output .= 'jQuery(\'#'.$v.'\').show(\'fast\');';
                }
                else
                    $output .= 'jQuery(\'#'.$val.'\').show(\'fast\');';
            
                $output .= '} else {';
            
                if(is_array($val))
                {
                    foreach($val as $v)
                        $output .= 'jQuery(\'#'.$v.'\').hide();';
                }
                else
                    $output .= 'jQuery(\'#'.$val.'\').hide();';
            
                $output .= '}
                });'; 
            }
               
            $output .= '
            });
            </script>';
        }
        
        return $output;
    }
    
    /**
    * Generates datetime field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_datetime($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label">'.$field['title'].'</th><td><input type="text" name="'.$name.'" id="'.$id.'" value="'.esc_attr($value).'" /></td></tr>
        
<script language="JavaScript">   
jQuery(document).ready(function() {     


jQuery(\'#'.$id.'\').datetimepicker({
    dateFormat: "yy-mm-dd",
    timeFormat: "hh:mm"        
});

});
</script>';

        return $output;
    }
    
    /**
    * Generates radio field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_radio($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
            
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label">'.$field['title'].'</th><td>';
        
        if(isset($field['options']) && is_array($field['options']))
        {
            foreach($field['options'] as $key=>$val)
            {
                $checked = '';
                if($value==$key)
                    $checked = ' checked="checked"';
                    
                $output .= '<input type="radio" id="'.$name.'_'.$key.'" name="'.esc_attr($name).'" value="'.esc_attr($key).'"'.$checked.' /> <label for="'.$name.'_'.$key.'">'.esc_html($val).'</label><br />';
            }
        }
        
        if(!empty($field['description']))
            $output .= '<small>'.$field['description'].'</small>';
        
        $output .= '</td></tr>';
        
        return $output;
    }
    
    /**
    * Generates video upload field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_video_upload($name, $field, $value = '')
    {
        $id = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css = '';
        if(!empty($field['css']))
            $css = $field['css'];
            
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' class="cf_hidden" valign="top"><th class="cf_label">'.$field['title'].'</th><td><input type="text" name="'.$name.'" id="'.$id.'" class="'.$css.'" value="'.$value.'" /> <input type="button" class="button" id="'.$id.'_button" value="'.__('Media Library', 'contestfriend').'" />';

$output .= '<script language="JavaScript">
jQuery(document).ready(function() {
jQuery(\'#'.$id.'_button\').click(function() {

    var old_send_fn = window.send_to_editor;
    
    window.send_to_editor = function(html) {

        var videourl = jQuery(html).first().attr(\'href\');
        jQuery(\'#'.$id.'\').val(videourl);
        tb_remove();
        window.send_to_editor = old_send_fn;
        }
        
        tb_show(\'\', \'media-upload.php?type=video&TB_iframe=true\');
        return false;
    
    });

});
</script>';
        
        return $output;
    }
    
    /**
    * Generates image upload field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_image_upload($name, $field, $value = '')
    {
        $id = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css = '';
        if(!empty($field['css']))
            $css = $field['css'];
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
            
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label">'.$field['title'].'</th><td><input type="text" name="'.$name.'" id="'.$id.'" class="'.$css.'" value="'.$value.'" /> <input type="button" class="button" id="'.$id.'_button" value="'.__('Media Library', 'contestfriend').'" />';
       
        $output .= '<script language="JavaScript">
jQuery(document).ready(function() {
jQuery(\'#'.$id.'_button\').click(function() {

    var old_send_fn = window.send_to_editor;
    
    window.send_to_editor = function(html) {

        imgurl = jQuery(\'img\',html).attr(\'src\');
        jQuery(\'#'.$id.'\').val(imgurl);
        tb_remove();
        window.send_to_editor = old_send_fn;
        }
        
        tb_show(\'\', \'media-upload.php?type=image&TB_iframe=true\');
        return false;
    
    });

});
</script>';
        
        return $output;
    }
    
    /**
    * Generates aweber code field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_aweber_code($name, $field, $value = '')
    {
        $id = '';
        $id_input = '';
        $id_container = '';
        if(!empty($field['id']))
        {
            $id = $field['id'];
            $id_input = ' id="'.$id.'"';
            $id_container = ' id="'.$id.'_container"';
        }
        
        $css = '';
        if(!empty($field['css']))
            $css = ' class="'.$field['css'].'"';
        
        $css_container = '';
        if(!empty($field['css_container']))
            $css_container = ' class="'.$field['css_container'].'"';
        
        $output = '<tr'.$id_container.$css_container.' valign="top"><th class="cf_label"><label for="'.$id.'">'.$field['title'].'</label></th><td>';
        $output .= '<input type="text"'.$id_input.' name="'.$name.'"'.$css.' value="'.esc_attr($value).'" /> <input id="'.$id.'_button" type="button" class="button" value="Connect App" /> <img id="'.$id.'_loading" src="'.admin_url('images/wpspin_light.gif').'" style="display: none; " /> <span id="'.$id.'_errors" style="display: none;"></span>';
        
        if(!empty($field['description']))
            $output .= '<br /><small>'.$field['description'].'</small>';
        
        $output .= '</td></tr>
<script type="text/javascript" >
jQuery(document).ready(function($) {
    jQuery(\'#'.$id.'_button\').click(function() {
        jQuery(\'#'.$id.'_button\').val(\'Connecting\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        jQuery(\'#'.$id.'_loading\').show();
        var authkey = jQuery(\'#'.$id.'\').val();
        var data = {
            action: \'cf_aweber_code\',
            auth_key: authkey,
            contest_id: \''.$this->contest->ID.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Connect App\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'_errors\').html(response).fadeIn().delay(1000).fadeOut();
        });
    });    
});
</script>
';
        
        return $output;
    }
    
    /**
    * Processes aweber application link. Called by AJAX script.
    */
    public static function field_aweber_code_ajax()
    {
        require(cf_Manager::$plugin_dir.'lib/aweber/aweber_api.php');
        
        $contest = new cf_Contest($_POST['contest_id']);
        if(!$contest->_valid)
        {
            echo __('Error connecting application, unknown contest.', 'contestfriend');
            die();
        }
        
        if(empty($_POST['auth_key']))
        {
            echo __('Empty auth code. Application disconnected.', 'contestfriend');
            die();
        }        
                
        $authorization_code = urldecode($_POST['auth_key']);
        
        try 
        {
            $auth = AWeberAPI::getDataFromAweberID($authorization_code);
            list($consumerKey, $consumerSecret, $accessKey, $accessSecret) = $auth;
            
            $data = array('consumer_key'=>$consumerKey, 'consumer_secret'=>$consumerSecret, 'access_key'=>$accessKey, 'access_secret'=>$accessSecret);
            
            update_post_meta($contest->ID, 'cf_aweber_auth', $data);
            echo __('App connected.', 'contestfriend');
        }
        catch(AWeberAPIException $exc) {
            echo __('Error connecting app.', 'contestfriend');
        }
        die();        
    }
    
    /**
    * Generates aweber list field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_aweber_list($name, $field, $value = '')
    { 
        $id = '';
        if(!empty($field['id']))
            $id = $field['id'];

        $output = '<tr valign="top"><th class="cf_label">'.$field['title'].'</th><td><select name="'.$name.'" id="'.$id.'">';
       
        $output .= '</select> <input id="'.$id.'_button" type="button" class="button" value="Refresh list" /> <img id="'.$id.'_loading" src="'.admin_url('images/wpspin_light.gif').'" style="display: none; " /></td></tr>
<script type="text/javascript" >
jQuery(document).ready(function($) {
    ';
    if($value!='')
    {
        $output .= 'jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#'.$id.'\').val();
        var data = {
            action: \'cf_aweber_list\',
            contest_id: \''.$this->contest->ID.'\',
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });';
    }
    
    $output .= 'jQuery(\'#'.$id.'_button\').click(function() {
        jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#'.$id.'\').val();
        var data = {
            action: \'cf_aweber_list\',
            contest_id: \''.$this->contest->ID.'\',
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });
    });    
});
</script>
';
        
        return $output;
    }
    
    /**
    * Processes aweber list field. Called by AJAX script.
    */
    public static function field_aweber_list_ajax()
    {
        require cf_Manager::$plugin_dir.'lib/aweber/aweber_api.php';
                
        $contest = new cf_Contest($_POST['contest_id']);
        if(!$contest->_valid)
            die();
        
        $aweber_auth = $contest->cf_aweber_auth;
        if(empty($aweber_auth))
            die();
        
        if(empty($aweber_auth['consumer_key']) || empty($aweber_auth['consumer_secret']) || empty($aweber_auth['access_key']) || empty($aweber_auth['access_secret']))
            die();
        
        $consumerKey = $aweber_auth['consumer_key'];
        $consumerSecret = $aweber_auth['consumer_secret'];
        $access_key = $aweber_auth['access_key'];
        $access_secret = $aweber_auth['access_secret'];
            
        $aweber = new AWeberAPI($consumerKey, $consumerSecret); 
        $account = $aweber->getAccount($access_key, $access_secret); 
        $value = '';
        if(!empty($_POST['value']))
            $value = $_POST['value'];
            
        $output = '';

        foreach($account->lists as $list)
        {
            $selected = '';
            if($list->id==$value)
                $selected = ' selected="selected"';
                
            $output .= '<option value="'.$list->id.'"'.$selected.'>'.$list->name.'</option>';
        }
    
        echo $output;
        die();
    }       

    /**
    * Generates mailchimp list field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_mailchimp_list($name, $field, $value = '')
    { 
        $id = '';
        if(!empty($field['id']))
            $id = $field['id'];

        $output = '<tr valign="top"><th class="cf_label">'.$field['title'].'</th><td><select name="'.$name.'" id="'.$id.'">';
       
        $output .= '</select> <input id="'.$id.'_button" type="button" class="button" value="'.__('Refresh list', 'contestfriend').'" /> <img id="'.$id.'_loading" src="'.admin_url('images/wpspin_light.gif').'" style="display: none; " /></td></tr>
<script type="text/javascript" >
jQuery(document).ready(function($) {
    ';
    if($value!='')
    {
        $output .= 'jQuery(\'#mailchimp_list_loading\').show();
        jQuery(\'#mailchimp_list_button\').val(\'Refreshing\');
        jQuery(\'#mailchimp_list_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#mailchimp_key\').val();
        var data = {
            action: \'cf_mailchimp_list\',
            apikey: api_key,
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#mailchimp_list_button\').val(\'Refresh list\');
            jQuery(\'#mailchimp_list_button\').attr(\'disabled\', false);
            jQuery(\'#mailchimp_list_loading\').hide();
            jQuery(\'#mailchimp_list\').html(response);
        });';
    }
    
    $output .= '
    jQuery(\'#mailchimp_list_button\').click(function() {
        jQuery(\'#mailchimp_list_button\').val(\'Refreshing\');
        jQuery(\'#mailchimp_list_button\').attr(\'disabled\', true);
        jQuery(\'#mailchimp_list_loading\').show();
        var api_key = jQuery(\'#mailchimp_key\').val();
        var data = {
            action: \'cf_mailchimp_list\',
            apikey: api_key,
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#mailchimp_list_button\').val(\'Refresh list\');
            jQuery(\'#mailchimp_list_button\').attr(\'disabled\', false);
            jQuery(\'#mailchimp_list_loading\').hide();
            jQuery(\'#mailchimp_list\').html(response);
        });
    });    
});
</script>
';
        
        return $output;
    }
    
    /**
    * Processes mailchimp list field. Called by AJAX script.
    */
    public static function field_mailchimp_list_ajax()
    {
        if(!class_exists(MCAPI))
            require(cf_Manager::$plugin_dir.'lib/MCAPI.class.php');
                
        $api_key = $_POST['apikey'];
        $mcapi = new MCAPI($api_key, false);
        $lists = $mcapi->lists();
        $value = '';
        if(!empty($_POST['value']))
            $value = $_POST['value'];
        
        $output = '';
        
        if(is_array($lists) && is_array($lists['data']))
        {
            foreach($lists['data'] as $entry)
            {
                $selected = '';
                if($entry['id']==$value)
                    $selected = ' selected="selected"';
                    
                $output .= '<option value="'.$entry['id'].'"'.$selected.'>'.$entry['name'].'</option>';
            }
        }
        
        echo $output;
        die();
    }
    
    /**
    * Generates getresponse list field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_getresponse_list($name, $field, $value = '')
    { 
        $id = '';
        if(!empty($field['id']))
            $id = $field['id'];

        $output = '<tr valign="top"><th class="cf_label">'.$field['title'].'</th><td><select name="'.$name.'" id="'.$id.'">';
       
        $output .= '</select> <input id="'.$id.'_button" type="button" class="button" value="'.__('Refresh list', 'contestfriend').'" /> <img id="'.$id.'_loading" src="'.admin_url('images/wpspin_light.gif').'" style="display: none; " /></td></tr>
<script type="text/javascript" >
jQuery(document).ready(function($) {
    ';
    if($value!='')
    {
        $output .= 'jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#getresponse_key\').val();
        var data = {
            action: \'cf_getresponse_list\',
            apikey: api_key,
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });';
    }
    
    $output .= '
    jQuery(\'#'.$id.'_button\').click(function() {
        jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#getresponse_key\').val();
        var data = {
            action: \'cf_getresponse_list\',
            apikey: api_key,
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });
    });    
});
</script>
';        
        return $output;
    }
    
    /**
    * Processes getresponse list field. Called by AJAX script.
    */
    public static function field_getresponse_list_ajax()
    {
        if(!class_exists(MCAPI))
            require(cf_Manager::$plugin_dir.'lib/GetResponseAPI.class.php');
                
        $api_key = $_POST['apikey'];
        $api = new GetResponseAPI($api_key);
        
        $lists = (array)$api->getCampaigns();
        /*$campaignIDs = array_keys($campaigns);
        $campaign      = $api->getCampaignByID($campaignIDs[0]);
        var_dump($campaigns, $campaign);*/

        $value = '';
        if(!empty($_POST['value']))
            $value = $_POST['value'];
        
        $output = '';

        foreach($lists['data'] as $key=>$val)
        {
            $selected = '';
            if($key==$value)
                $selected = ' selected="selected"';
                
            $output .= '<option value="'.$key.'"'.$selected.'>'.$val.'</option>';
        }
        
        echo $output;
        die();
    }
    
    /**
    * Generates campaignmonitor client field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_campaignmonitor_client($name, $field, $value = '')
    { 
        $id = '';
        if(!empty($field['id']))
            $id = $field['id'];

        $output = '<tr valign="top"><th class="cf_label">'.$field['title'].'</th><td><select name="'.$name.'" id="'.$id.'">';
       
        $output .= '</select> <input id="'.$id.'_button" type="button" class="button" value="'.__('Refresh list', 'contestfriend').'" /> <img id="'.$id.'_loading" src="'.admin_url('images/wpspin_light.gif').'" style="display: none; " /></td></tr>
<script type="text/javascript" >
jQuery(document).ready(function($) {
    ';
    if($value!='')
    {
        $output .= 'jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#campaignmonitor_key\').val();
        var data = {
            action: \'cf_'.$id.'\',
            apikey: api_key,
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });';
    }
    
    $output .= '
    jQuery(\'#'.$id.'_button\').click(function() {
        jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#campaignmonitor_key\').val();
        var data = {
            action: \'cf_'.$id.'\',
            apikey: api_key,
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });
    });    
});
</script>
';
        
        return $output;
    }
    
    /**
    * Generates campaignmonitor list field.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    * @param mixed $value Field value.
    */
    protected function field_campaignmonitor_list($name, $field, $value = '')
    { 
        $id = '';
        if(!empty($field['id']))
            $id = $field['id'];

        $output = '<tr valign="top"><th class="cf_label">'.$field['title'].'</th><td><select name="'.$name.'" id="'.$id.'">';
       
        $output .= '</select> <input id="'.$id.'_button" type="button" class="button" value="'.__('Refresh list', 'contestfriend').'" /> <img id="'.$id.'_loading" src="'.admin_url('images/wpspin_light.gif').'" style="display: none; " /></td></tr>
<script type="text/javascript" >
jQuery(document).ready(function($) {
    ';
    
    $value_client = '';
    if(isset($this->contest->cf_campaignmonitor_client))
        $value_client = $this->contest->cf_campaignmonitor_client;
        
    if($value!='' && $value_client!='')
    {
        $output .= 'jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#campaignmonitor_key\').val();
        var client_id = \''.$value_client.'\';
        var data = {
            action: \'cf_'.$id.'\',
            apikey: api_key,
            client: client_id, 
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });';
    }
    
    $output .= '
    jQuery(\'#'.$id.'_button\').click(function() {
        jQuery(\'#'.$id.'_loading\').show();
        jQuery(\'#'.$id.'_button\').val(\'Refreshing\');
        jQuery(\'#'.$id.'_button\').attr(\'disabled\', true);
        var api_key = jQuery(\'#campaignmonitor_key\').val();
        var client_id = jQuery(\'#campaignmonitor_client\').val();
        var data = {
            action: \'cf_'.$id.'\',
            apikey: api_key,
            client: client_id,
            value: \''.$value.'\'
        };

        $.post(ajaxurl, data, function(response) {
            jQuery(\'#'.$id.'_button\').val(\'Refresh list\');
            jQuery(\'#'.$id.'_button\').attr(\'disabled\', false);
            jQuery(\'#'.$id.'_loading\').hide();
            jQuery(\'#'.$id.'\').html(response);
        });
    });    
});
</script>
';
        
        return $output;
    }
    
    /**
    * Processes campaignmonitor client field. Called by AJAX script.
    */
    public static function field_campaignmonitor_client_ajax()
    {
        if(!class_exists(CS_REST_General))
            require(cf_Manager::$plugin_dir.'lib/campaign_monitor/csrest_general.php');
                
        $api_key = $_POST['apikey'];
        $cmapi = new CS_REST_General($api_key);
        $clients = $cmapi->get_clients();
        
        if(!$clients->was_successful())
            die();
        
        $value = '';
        if(!empty($_POST['value']))
            $value = $_POST['value'];
                
        $output = '';
        $clients = $clients->response;
        
        if(is_array($clients))
        {
            foreach($clients as $client)
            {
                $selected = '';
                if($client->ClientID==$value)
                    $selected = ' selected="selected"';
                    
                $output .= '<option value="'.esc_attr($client->ClientID).'"'.$selected.'>'.esc_html(trim($client->Name)).' </option>';
            }
        }
        
        echo $output;
        die();
    }
    
    /**
    * Processes campaignmonitor list field. Called by AJAX script.
    */
    public static function field_campaignmonitor_list_ajax()
    {
        if(!class_exists(CS_REST_Clients))
            require(cf_Manager::$plugin_dir.'lib/campaign_monitor/csrest_clients.php');
                
        $api_key = $_POST['apikey'];
        $client_id = $_POST['client'];
        
        $cmapi = new CS_REST_Clients($client_id, $api_key);
        $lists = $cmapi->get_lists();
        
        if(!$lists->was_successful())
            die();
        
        $value = '';
        if(!empty($_POST['value']))
            $value = $_POST['value'];
                
        $output = '';
        $lists = $lists->response;
        
        if(is_array($lists))
        {
            foreach($lists as $list)
            {
                $selected = '';
                if($list->ListID==$value)
                    $selected = ' selected="selected"';
                    
                $output .= '<option value="'.esc_attr($list->ListID).'"'.$selected.'>'.esc_html(trim($list->Name)).' </option>';
            }
        }
        
        echo $output;
        die();
    }
    
    /**
    * Retrieves list of google fonts. If the cached list is too old, will request new list from google webfont api and store it. Method is called to fill select field options for available fonts.
    * 
    * @param string $name Field name.
    * @param mixed $field Field attributes.
    */
    protected function prepare_fonts($name, &$field)
    {
        $upload_dir = wp_upload_dir();
        $json_file = $upload_dir['basedir'].'/google_fonts.json';
        
        if(!file_exists($json_file) || (time()-86400) > filemtime($json_file)) // 86400 = 24 hrs
        { // recache google fonts json        
            $url = "https://www.googleapis.com/webfonts/v1/webfonts?key=AIzaSyDE2OGgu88jiLw9UD4ACQNd_prf_V8CwRE";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);
            
            file_put_contents($json_file, $data);
        }
        
        $googlefonts = array();
        if(file_exists($json_file))
        {
            $data = file_get_contents($json_file);
            $data = json_decode($data);

            if(isset($data->items))
            {
                foreach((array)$data->items as $item)
                  $googlefonts['google_'.urlencode($item->family)] = $item->family;
            }
        }
        
        $field['options']['Google Fonts'] = $googlefonts;
    }
    
    /**
    * Generates custom content for publish tab.
    */
    protected function generate_publish()
    {
        echo '<h2>'.__('Publish Your Contest', 'contestfriend').'</h2>
        <ol>
        <li>'.__('Add this WordPress shortcode to a page on your site to display the contest. You can also use the contestfriend widget to display the contest in your sidebar through WordPress Appearance &gt; Widgets menu.', 'contestfriend').'<br />
        <textarea cols="4" class="cf_input_large">[contestfriend contest="'.$this->contest->ID.'"]</textarea></li>
        <li>'.__('Add a call-to-action on your home page to drive traffic.', 'contestfriend').'</li>
        </ol>
        ';
    }
    
    /**
    * Generates custom content for preview tab.
    */
    protected function generate_preview()
    {
        wp_enqueue_style('cf_css_base');
        wp_enqueue_script('jquery-ui-resizable');
        
        echo '<h3>'.__('Before submit preview:', 'contestfriend').'</h3>';
        echo '<div id="preview_before" class="cf_widget_preview"><p>'.__('Resize this block to preview various widget sizes.', 'contestfriend').'</p>';
        $url = add_query_arg('contestfriend_preview', $this->contest->ID, get_site_url());
        echo cf_Widget::html(array('contest' => $this->contest->ID, 'preview' => 'before_submit'));
        echo '</div>';
        
        $widget = cf_Widget::current_widget();
        $widget_id_before = $widget->widget_id;
        
        echo '<h3>'.__('After submit preview:', 'contestfriend').'</h3>';
        echo '<div id="preview_after" class="cf_widget_preview"><p>'.__('Resize this block to preview various widget sizes.', 'contestfriend').'</p>';
        $url = add_query_arg('contestfriend_preview', $this->contest->ID, get_site_url());
        echo cf_Widget::html(array('contest' => $this->contest->ID, 'preview' => 'after_submit'));
        echo '</div>';
        
        $widget = cf_Widget::current_widget();
        $widget_id_after = $widget->widget_id;
        
        if($this->contest->cf_double_optin=='1')
        {
            echo '<div id="preview_doubleoptin" class="cf_widget_preview"><p>'.__('Resize this block to preview various widget sizes.', 'contestfriend').'</p>';
            $url = add_query_arg('contestfriend_preview', $this->contest->ID, get_site_url());
            echo cf_Widget::html(array('contest' => $this->contest->ID, 'preview' => 'doubleoptin'));
            echo '</div>';
        
            $widget = cf_Widget::current_widget();
            $widget_id_doubleoptin = $widget->widget_id;
        }
        
        echo '<script type="text/javascript">jQuery(document).ready(function() { 
        jQuery(\'#preview_before\').resizable({ resize: function(event, ui) { ui.size.height = jQuery(\'#'.$widget_id_before.'\').height()+50; } }); 
        jQuery(\'#preview_after\').resizable({ resize: function(event, ui) { ui.size.height = jQuery(\'#'.$widget_id_after.'\').height()+50; } });';
        
        if($this->contest->cf_double_optin=='1')
            echo 'jQuery(\'#preview_doubleoptin\').resizable({ resize: function(event, ui) { ui.size.height = jQuery(\'#'.$widget_id_doubleoptin.'\').height()+50; } });';
            
        echo '});</script>';
    }

    /** 
    * Generates custom content for dashboard tab.
    */
    protected function generate_dashboard()
    {
        $contest_status = '';
        $isstarted = $this->contest->is_started();
        $isexpired = $this->contest->is_expired();
        
        $contest_status = __('Expired', 'contestfriend');
        if(!$isstarted)
            $contest_status = __('Not active yet', 'contestfriend');
        else if($isexpired && $this->contest->cf_status=='expired')
            $contest_status = __('Expired (forced)', 'contestfriend');
        else if($isexpired && $this->contest->cf_status=='active')
            $contest_status = __('Expired (automatic)', 'contestfriend');
        else if($isexpired && $this->contest->cf_status=='winners_picked')
            $contest_status = __('Expired (winners picked)', 'contestfriend');
        else if($isstarted && !$isexpired)
            $contest_status = __('Active', 'contestfriend');
        
        $part_num = cf_Participant::get_num($this->contest->ID);
        $part_num_all = cf_Participant::get_num($this->contest->ID, 'all');
        
        $participant_num = $part_num;
        if($part_num_all!=$part_num)
            $participant_num .= ' ('.$part_num_all.')';        
        
        echo '<div style="margin-top: 5px;">';

        // Dashboard buttons
        if(!$this->contest->is_expired() || $this->contest->cf_status=='expired') // if contest has not yet expired or was forced to expire
        {
            // display stop / resume contest
            $url = admin_url('admin.php?page='.cf_Page_Participants::page_id.'&contest='.$_GET['contest'].'&redirect=contest');
                    
            echo '<form action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="setexpired" value="1" />';
            $text = __('Stop Contest', 'contestfriend');
            if($this->contest->cf_status=='expired')
                $text = __('Resume Contest', 'contestfriend');
                
            submit_button($text, 'button-secondary action', false, false);
            echo '</form>';
        }
        
        if($this->contest->is_expired() && $this->contest->cf_status=='winners_picked') // expired contest and winners picked
        {
            // display reset contest button
            $url = admin_url('admin.php?page='.cf_Page_Participants::page_id.'&contest='.$_GET['contest'].'&redirect=contest');
                    
            echo '<form action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="resetcontest" value="1" />';
            $text = __('Reset Contest', 'contestfriend');
                
            submit_button($text, 'button-secondary action', false, false);
            echo '</form>';
        }
            
        // if not all winners were picked, show pick buttons
        $winners_num = 1;
        $current_winners = $this->contest->get_current_winner_num();
        
        if(isset($this->contest->cf_winners_num) && is_numeric($this->contest->cf_winners_num) && $this->contest->cf_winners_num>0)
            $winners_num = $this->contest->cf_winners_num;
        
        if($current_winners<$winners_num)
        {
            $confirm_class = '';
            if($current_winners==0 && $this->contest->cf_status!='winners_picked')
                $confirm_class = 'confirm_setwinner';
                    
            $text =  __('Pick Random Winners', 'contestfriend');
            $url = admin_url('admin.php?page='.cf_Page_Participants::page_id.'&contest='.$_GET['contest'].'&redirect=contest');
                
            echo '<form class="'.$confirm_class.'" action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="pickwinners" value="1" />';
            submit_button($text, 'button-secondary action', false, false);
            echo '</form>';
            
            $text = __('Manually Pick Winners', 'contestfriend');
            echo '<form action="'.$url.'" method="post" style="float: left; margin-left: 5px">';
            submit_button($text, 'button-secondary action', false, false);
            echo '</form>';
        }
        
        // if there are some winners picked, show clear winners button
        if($current_winners>0)
        {
            $url = admin_url('admin.php?page='.cf_Page_Participants::page_id.'&contest='.$_GET['contest'].'&redirect=contest');
            $text =  __('Clear All Winners', 'contestfriend');
            
            echo '<form action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="clearwinners" value="1" />';
            submit_button($text, 'button-secondary action', false, false);
            echo '</a></form>';
        }
    
        echo '</div>
        <div id="poststuff" style="clear: both;">
        <div class="postbox">
        <h3>'.__('Contest Status', 'contestfriend').'</h3>
        <div class="inside">    
        <table class="form-table">
        <tbody><tr valign="top"><th class="cf_label">'.__('Status', 'contestfriend').'</th>
        <td>'.$contest_status.'</td>
        </tr>
        <tr valign="top"><th class="cf_label" style="vertical-align: middle">'.__('Number of participants', 'contestfriend').'</th>
        <td style="vertical-align: middle">'.$participant_num.' <a href="'.admin_url('admin.php?page='.cf_Page_Participants::page_id.'&contest='.$_GET['contest']).'" class="button">'.__('View all participants', 'contestfriend').'</a></td>
        </tr>
        </table>
        </div></div>';
        
        if($current_winners>0)
        {
            $winners = cf_Participant::get_all($this->contest->ID, '', '', '', '', 'winner');
            
            echo '<div class="postbox">
            <h3>'.__('Winners', 'contestfriend').'</h3>
            <div class="inside">
            <table class="form-table">
            <tbody>';
            foreach($winners as $winner)
            {
                $url = admin_url('admin.php?page='.cf_Page_Participants::page_id.'&contest='.$_GET['contest'].'&redirect=contest');
                $text = __('Remove Winner', 'contestfriend');
                
                echo '<tr valign="top"><th class="cf_label">'.$winner->email.', '.$winner->first_name.' '.$winner->last_name.'</th><td><form action="'.$url.'" method="post"><input type="hidden" name="removewinner" value="'.esc_attr($winner->id).'" />';
                submit_button($text, 'button-secondary action', false, false);
                
                echo '</td></tr>';
            }
            echo '
            </table>    
            </div>
            </div>
            ';
        }
        
        echo '</div>';
    }
    
    /**
    * Generates tabbed menu.
    */
    protected function generate_menu()
    {
        $num = count($this->tabs);
        
        echo '<h2 style="padding-left: 25px" class="nav-tab-wrapper">';
        
        foreach($this->tabs as $key=>$tab)
        {
            $active = '';
            if($_GET['cf_page']==$key)
                $active = ' nav-tab-active';
                
            echo '<a class="nav-tab'.$active.'" href="admin.php?page='.self::page_id.'&cf_page='.$key.'&contest='.$_GET['contest'].'">'.$tab['title'].'</a>';
        }
        
        echo '</h2>';
    }
    
    /**
    * Sets description tab postboxes and fields.
    */
    function setup_description()
    {
        $this->boxes = array(
            'cf_box_description' => array(
                'title' => __('Description', 'contestfriend'),
                'fields' => array(
                    'cf_headline' => array(
                        'id' => 'cf_headline',
                        'type' => 'text',
                        'title' => __('Contest headline', 'contestfriend'),
                        'description' => __('A brief summary of your contest, e.g. Win $25 to spend in the store', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                    
                    'cf_media' => array(
                        'id' => 'cf_media',
                        'type' => 'select',
                        'title' => __('Media', 'contestfriend'),
                        'options' => array(
                            '' => __('No Media', 'contestfriend'),
                            'image' => __('Image', 'contestfriend'),
                            'video' => __('Custom Video', 'contestfriend'),
                            'video_youtube' => __('Youtube Video', 'contestfriend')
                        ),
                        'default' => '',
                        'conditional' => array(
                            'image' => 'cf_image_container',
                            'video' => 'cf_video_container',
                            'video_youtube' => 'cf_video_youtube_container'
                        )
                    ),
                    
                    'cf_image' => array(
                        'id' => 'cf_image',
                        'type' => 'image_upload',
                        'title' => __('Image', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden'
                    ),
                    
                    'cf_video' => array(
                        'id' => 'cf_video',
                        'type' => 'video_upload',
                        'title' => __('Video', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden'
                    ),
                    
                    'cf_video_youtube' => array(
                        'id' => 'cf_video_youtube',
                        'type' => 'text',
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden',
                        'title' => __('Youtube video', 'contestfriend'),
                        'description' => __('Link to youtube video, e.g. http://www.youtube.com/watch?v=oHg5SJ_RHA0', 'contestfriend')
                    ),
                    
                    'cf_description' => array(
                        'id' => 'cf_description',
                        'type' => 'editor',
                        'title' => __('Contest description', 'contestfriend'),
                        'description' => __('Describe your contest and the prize that you will giveaway.', 'contestfriend'),
                        'css' => 'cf_input_large'
                    )                          
                )
            ),
            
            'cf_box_disclaimer_rules' => array(
                'title' => __('Disclaimer & Rules', 'contestfriend'),
                'fields' => array(
                    'cf_disclaimer_rules_type' => array(
                        'id' => 'cf_disclaimer_rules_type',
                        'type' => 'select',
                        'title' => __('Type', 'contestfriend'),
                        'options' => array(
                            'none' => __('None', 'contestfriend'),
                            'popup' => __('Popup', 'contestfriend'),
                            'url' => __('Link', 'contestfriend')
                        ),
                        'conditional' => array(
                            'popup' => array('cf_disclaimer_container', 'cf_rules_container'),
                            'url' => 'cf_disclaimer_rules_url_container'
                        ),
                        'default' => 'url'
                    ),
                    
                    'cf_disclaimer_rules_url' => array(
                        'id' => 'cf_disclaimer_rules_url',
                        'type' => 'text',
                        'title' => __('URL', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden'
                    ),
                    
                    'cf_rules' => array(
                        'id' => 'cf_rules',
                        'type' => 'textarea',
                        'title' => __('Rules', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden'
                    ),
                    
                    'cf_disclaimer' => array(
                        'id' => 'cf_disclaimer',
                        'type' => 'textarea',
                        'title' => __('Disclaimer', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden'
                    )
                )
            )
        );
    }
    
    /**
    * Sets design tab postboxes and fields.
    */
    function setup_design()
    {
        $this->boxes = array(
            'cf_box_layout' => array(
                'title' => __('Layout', 'contestfriend'),
                'fields' => array(
                    'cf_media_description_layout' => array(
                        'id' => 'cf_media_description_layout',
                        'type' => 'select',
                        'title' => __('Media & Description', 'contestfriend'),
                        'options' => array(
                            'media-top' => __('Media on top', 'contestfriend'),
                            'description-top' => __('Description on top', 'contestfriend'),
                            'inline_media-left' => __('Inline, media first', 'contestfriend'),
                            'inline_description-left' => __('Inline, description first', 'contestfriend')
                        ),
                        'default' => 'media_top'
                    ),
                    
                    'cf_widget_size' => array(
                        'type' => 'text',
                        'title' => __('Maximum widget width', 'contestfriend'),                                
                        'default' => '640',
                        'description' => __('Maximum widget size in pixels.', 'contestfriend'),
                        'css' => 'cf_input_small'
                    )
                )                        
                
            ),
                    
            'cf_box_text' => array(
                'title' => __('Text', 'contestfriend'),
                'fields' => array(
                    'cf_headline_color' => array(
                        'id' => 'cf_headline_color',
                        'type' => 'color',
                        'title' => __('Headline color', 'contestfriend'),
                        'default' => '#FFFFFF'
                    ),
                    
                    'cf_headline_font' => array(
                        'id' => 'cf_headline_font',
                        'type' => 'select',
                        'title' => __('Headline font', 'contestfriend'),
                        'dynamic' => 'prepare_fonts',
                        'options' => array(
                            'Default Fonts' => array(
                                'arial' => 'Arial',
                                'arial+black' => 'Arial Black',
                                'georgia' => 'Georgia',
                                'helvetica+neue' => 'Helvetica Neue',
                                'impact' => 'Impact',
                                'lucida' => 'Lucida Grande',
                                'palatino' => 'Palatino',
                                'tahoma' => 'Tahoma',
                                'times+new+roman' => 'Times New Roman',
                                'trebuchet' => 'Trebuchet',
                                'verdana' => 'Verdana'
                            ),
                            'Google Fonts' => array()  
                        )
                    ),
                    
                    'cf_description_color' => array(
                        'id' => 'cf_description_color',
                        'type' => 'color',
                        'title' => __('Description color', 'contestfriend'),
                        'default' => '#000000'
                    ),
                    
                    'cf_description_font' => array(
                        'id' => 'cf_description_font',
                        'type' => 'select',
                        'title' => __('Description font', 'contestfriend'),
                        'dynamic' => 'prepare_fonts',
                        'options' => array(
                            'Default Fonts' => array(
                                'arial' => 'Arial',
                                'arial+black' => 'Arial Black',
                                'georgia' => 'Georgia',
                                'helvetica+neue' => 'Helvetica Neue',
                                'impact' => 'Impact',
                                'lucida' => 'Lucida Grande',
                                'palatino' => 'Palatino',
                                'tahoma' => 'Tahoma',
                                'times+new+roman' => 'Times New Roman',
                                'trebuchet' => 'Trebuchet',
                                'verdana' => 'Verdana'
                            ),
                            'Google Fonts' => array()
                        )
                    ),
                    
                    'cf_description_align' => array(
                        'id' => 'cf_description_align',
                        'type' => 'select',
                        'title' => __('Description text align', 'contestfriend'),
                        'options' => array(
                            'left' => 'Left',
                            'center' => 'Center',
                            'right' => 'Right'
                        ),
                        'default' => 'center'                                
                    ),
                    
                    'cf_typekit' => array(
                        'id' => 'cf_typekit',
                        'type' => 'text',
                        'title' => __('Typekit ID', 'contestfriend'),
                        'description' => __('Enter your Typekit ID. This will override the fonts above.', 'contestfriend')
                    )
                )
            ),
            
            'cf_box_background' => array(
                'title' => __('Background', 'contestfriend'),
                'fields' => array(
                    'cf_title_background_color' => array(
                        'id' => 'cf_title_background_color',
                        'type' => 'color',
                        'title' => __('Title background color', 'contestfriend'),
                        'default' => '#40b3df'
                    ),
                    'cf_background_color' => array(
                        'id' => 'cf_background_color',
                        'type' => 'color',
                        'title' => __('Background color', 'contestfriend'),
                        'default' => '#ffffff'
                    ),
                    'cf_border_color' => array(
                        'id' => 'cf_border_color',
                        'type' => 'color',
                        'title' => __('Border color', 'contestfriend'),
                        'default' => '#2a71a2'
                    )
                    /*                                                      
                    'cf_container' => array(
                        'id' => 'cf_container',
                        'type' => 'checkbox_singular',
                        'title' => __('Disable Container', 'contestfriend'),
                        'description' => __('A container is a wrapper that will encapsulate your widget.', 'contestfriend')
                    ),
                    */
                )
            )
        );
    }
    
    /**
    * Sets settings tab postboxes and fields.
    */
    function setup_settings()
    {        
        $this->boxes = array(
            'cf_box_settings' => array(
                'title' => __('Widget Settings', 'contestfriend'),
                'fields' => array(
                    'cf_social' => array(
                        'id' => 'cf_social',
                        'type' => 'checkbox',
                        'title' => __('Social buttons', 'contestfriend'),
                        'description' => __('Select the checkboxes above to display social share buttons after you capture an email.', 'contestfriend'),
                        'options' => array(
                            'twitter' => 'Twitter',
                            'facebook' => 'Facebook',
                            'googleplus' => 'Google+',
                            'linkedin' => 'LinkedIn',
                            'pinit' => 'Pinterest'
                        ),
                        
                        'conditional' => array(
                            'twitter' => 'cf_box_twitter',
                            'facebook' => 'cf_box_facebook',
                            'linkedin' => 'cf_box_linkedin',
                            'pinit' => 'cf_box_pinit'
                        )
                    ),
                    
                    'cf_name_field' => array(
                        'id' => 'cf_name_field',
                        'type' => 'checkbox_singular',
                        'title' => __('Display name field', 'contestfriend'),
                        'conditional' => array(0 => array(), 1 => array('cf_name_field_req_container')),
                        'description' => __('Contest participants can submit their full name.', 'contestfriend')
                    ),
                    
                    'cf_name_field_req' => array(
                        'id' => 'cf_name_field_req',
                        'type' => 'checkbox_singular',
                        'title' => __('Contest participants have to submit their full name.', 'contestfriend'),
                        'css_container' => 'cf_hidden'
                    ),
                    
                    'cf_referral_field' => array(
                        'id' => 'cf_referral_field',
                        'type' => 'checkbox_singular',
                        'title' => __('Display referral link', 'contestfriend'),
                        'description' => __('The referral link encourages participants to share the contest url to earn additional entries.', 'contestfriend')
                    ),
                    
                    'cf_countdown_field' => array(
                        'id' => 'cf_countdown_field',
                        'type' => 'checkbox_singular',
                        'title' => __('Display countdown field', 'contestfriend'),
                        'description' => ''
                    ),
                    
                  /*  'cf_thankyou' => array(
                        'id' => 'cf_thankyou',
                        'type' => 'textarea',
                        'title' => __('Thank You or Incentive Message', 'contestfriend'),
                        'description' => __('Thank you email message after participant has subscribed.', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),*/
                    
                    'cf_submit_text' => array(
                        'id' => 'cf_submit_text',
                        'type' => 'text',
                        'title' => __('Submit button text', 'contestfriend'),
                        'default' => __('Join sweepstakes', 'contestfriend')
                    ),
                    
                    'cf_googleapi' => array(
                        'id' => 'cf_googleapi',
                        'type' => 'text',
                        'title' => __('Google API key', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'description' => __('Overrides the default API key for goo.gl url shortening and Google fonts', 'contestfriend')
                    ),
                )
            ),
            
            'cf_box_twitter' => array(
                'id' => 'cf_box_twitter',
                'title' => __('Twitter settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_twitter_text' => array(
                        'id' => 'cf_twitter_text',
                        'type' => 'textarea',
                        'title' => __('Tweet text', 'contestfriend'),
                        'description' => __('Use the following tag to automatically insert contest url: {URL}', 'contestfriend'),
                        'css' => 'cf_input_large'
                    )
                )
            ),
                                
            'cf_box_facebook' => array(
                'id' => 'cf_box_facebook',
                'title' => __('Facebook settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_facebook_title' => array(
                        'id' => 'cf_facebook_title',
                        'type' => 'text',
                        'css' => 'cf_input_large',
                        'title' => __('Title', 'contestfriend')                                
                    ),
                    
                    'cf_facebook_image' => array(
                        'id' => 'cf_facebook_image',
                        'type' => 'image_upload',
                        'title' => __('Image', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                                                
                    'cf_facebook_summary' => array(
                        'id' => 'cf_facebook_sumarry',
                        'type' => 'textarea',
                        'title' => __('Summary', 'contestfriend'),
                        'css' => 'cf_input_large'
                    )
                )
            ),
            
            'cf_box_linkedin' => array(
                'id' => 'cf_box_twitter',
                'title' => __('LinkedIn settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_linkedin_title' => array(
                        'id' => 'cf_linkedin_title',
                        'type' => 'text',
                        'title' => __('LinkedIn title', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                    
                    'cf_linkedin_summary' => array(
                        'id' => 'cf_linkedin_summary',
                        'type' => 'textarea',
                        'title' => __('LinkedIn summary', 'contestfriend'),
                        'default' => '',
                        'css' => 'cf_input_large'
                    ),
                    
                    'cf_linkedin_source' => array(
                        'id' => 'cf_linkedin_source',
                        'type' => 'text',
                        'title' => __('LinkedIn source', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                )
            ),
            
            'cf_box_pinit' => array(
                'id' => 'cf_box_twitter',
                'title' => __('Pinterest settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_pinit_image' => array(
                        'id' => 'cf_pinit_image',
                        'type' => 'image_upload',
                        'title' => __('Pinterest image', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                    
                    'cf_pinit_description' => array(
                        'id' => 'cf_pinit_description',
                        'type' => 'textarea',
                        'title' => __('Pinterest description', 'contestfriend'),
                        'css' => 'cf_input_large'                                
                    )
                )
            ),
             
            'cf_box_entries_winners' => array(
                'title' => 'Entries & Winners',
                'fields' => array(
                    'cf_from_email' => array(
                        'id' => 'cf_from_email',
                        'type' => 'text',
                        'title' => __('Email sender', 'contestfriend'),
                        'description' => __('Example', 'contestfriend').': "contestfriend" &lt;'.get_option('admin_email').'&gt;',
                        'css' => 'cf_input_large'
                    ),
                    'cf_confirmation_email' => array(
                        'id' => 'cf_confirmation_email',
                        'type' => 'checkbox_singular',
                        'title' => __('Send confirmation email', 'contestfriend'),
                        'conditional' => array( 
                            0 => array(),
                            1 => array('cf_confirmation_email_subject_container', 'cf_confirmation_email_text_container')
                        )   
                    ),
                    
                    'cf_confirmation_email_subject' => array(
                        'id' => 'cf_confirmation_email_subject',
                        'type' => 'text',
                        'title' => __('Confirmation email subject', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden'                                
                    ),
                    
                    'cf_confirmation_email_text' => array(
                        'id' => 'cf_confirmation_email_text',
                        'type' => 'textarea',
                        'title' => __('Confirmation email text', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden',
                        'description' => __('Use the following tags to automatically insert first and last name of the participant: {FIRST_NAME} {LAST_NAME}. (Only applies if the participant has submitted his name.)', 'contestfriend')
                    ),
                
                    'cf_double_optin' => array(
                        'id' => 'cf_double_optin',
                        'type' => 'checkbox_singular',
                        'title' => __('Double opt-in', 'contestfriend'),
                        'description' => 'check this to add e-mail confirmation as the last step before the participant can finish his registration.',
                        'conditional' => array(
                            0 => array(),
                            1 => array('cf_double_optin_message_container', 'cf_double_optin_subject_container', 'cf_double_optin_email_container')
                        )
                    ),
                    
                    'cf_double_optin_message' => array(
                        'id' => 'cf_double_optin_message',
                        'type' => 'textarea',
                        'title' => __('Double opt-in message', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden',
                        'default' => __('Please check your e-mail and click the confirmation link to finish your registration.', 'contestfriend')
                    ),
                    
                    'cf_double_optin_subject' => array(
                        'id' => 'cf_double_optin_subject',
                        'type' => 'text',
                        'title' => __('Double opt-in email subject', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden'
                    ),
                    
                    'cf_double_optin_email' => array (
                        'id' => 'cf_double_optin_email',
                        'type' => 'textarea',
                        'title' => __('Double opt-in email', 'contestfriend'),
                        'css' => 'cf_input_large',
                        'css_container' => 'cf_hidden',
                        'description' => __('Tags you can use: {FIRST_NAME} {LAST_NAME} {URL}', 'contestfriend'),
                        'default' => '{URL}'
                    ),
                    
                    'cf_referral_entries' => array(
                        'id' => 'cf_referral_entries',
                        'type' => 'text',
                        'title' => __('Entries per referral', 'contestfriend'),
                        'description' => __('The number of entries you want participants to receive when referring another user. (Only applies if the referral link is displayed.)', 'contestfriend'),
                        'css' => 'cf_input_small'
                    ),
                    
                    'cf_winners_num' => array(
                        'id' => 'cf_winners_num',
                        'type' => 'text',
                        'title' => __('Number of winners', 'contestfriend'),
                        'css' => 'cf_input_small'
                    ),
                                                
                    'cf_autoselect_winner' => array(
                        'id' => 'cf_autoselect_winner',
                        'type' => 'checkbox_singular',
                        'title' => __('Automatically select winner', 'contestfriend'),
                        'description' => __('Winner will be selected in a random drawing using random.org.', 'contestfriend')
                    ),
                    
                    'cf_participants_export' => array(
                        'id' => 'cf_participants_export',
                        'type' => 'select',
                        'title' => __('Save participants to', 'contestfriend'),
                        'options' => array(
                            '' => 'Database only',
                            'mailchimp' => 'Mailchimp',
                            'aweber' => 'AWeber',
                        //    'constantcontact' => 'Constant Contact',
                            'campaignmonitor' => 'Campaign Monitor',
                            'getresponse' => 'Get Response'
                        ),
                        'conditional' => array(
                            'mailchimp' => 'cf_box_participants_export_mailchimp',
                            'aweber' => 'cf_box_participants_export_aweber',
                       //     'constantcontact' => 'cf_box_participants_export_contantcontact',
                            'campaignmonitor' => 'cf_box_participants_export_campaignmonitor',
                            'getresponse' => 'cf_box_participants_export_getresponse'
                        ),
                        'default' => ''
                    )

                )
            ),
            
            'cf_box_participants_export_aweber' => array(
                'title' => __('Aweber settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(         
                    'cf_aweber_code' => array(
                        'id' => 'aweber_code',
                        'type' => 'aweber_code',
                        'title' => __('Authorization code', 'contestfriend'),
                        'description' => '<a href="https://auth.aweber.com/1.0/oauth/authorize_app/76bb6948" target="_blank">'.__('Get your authorization code here', 'contestfriend').'</a>',
                        'css' => 'cf_input_large'
                    ),
                    'cf_aweber_list' => array(
                        'id' => 'aweber_list',
                        'type' => 'aweber_list',
                        'title' => __('List', 'contestfriend')
                    )
                )
            ),
            
            'cf_box_participants_export_mailchimp' => array(
                'title' => __('Mailchimp settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_mailchimp_key' => array(
                        'id' => 'mailchimp_key',
                        'type' => 'text',
                        'title' => __('API key', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                    'cf_mailchimp_list' => array(
                        'id' => 'mailchimp_list',
                        'type' => 'mailchimp_list',
                        'title' => __('List', 'contestfriend')
                    )
                )
            ),
            
            'cf_box_participants_export_campaignmonitor' => array(
                'title' => __('CampaignMonitor settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_campaignmonitor_key' => array(
                        'id' => 'campaignmonitor_key',
                        'type' => 'text',
                        'title' => __('API key', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                    'cf_campaignmonitor_client' => array(
                        'id' => 'campaignmonitor_client',
                        'type' => 'campaignmonitor_client',
                        'title' => __('Client', 'contestfriend')
                    ),
                    'cf_campaignmonitor_list' => array(
                        'id' => 'campaignmonitor_list',
                        'type' => 'campaignmonitor_list',
                        'title' => __('List', 'contestfriend')
                    )
                )
            ),
            
            'cf_box_participants_export_getresponse' => array(
                'title' => __('GetReponse settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_getresponse_key' => array(
                        'id' => 'getresponse_key',
                        'type' => 'text',
                        'title' => __('API key', 'contestfriend'),
                        'css' => 'cf_input_large'
                    ),
                    'cf_getresponse_list' => array(
                        'id' => 'getresponse_list',
                        'type' => 'getresponse_list',
                        'title' => __('Campaign', 'contestfriend')
                    )
                )
            ),
            
        /*    'cf_box_participants_export_contantcontact' => array(
                'title' => __('Constant Contact Settings', 'contestfriend'),
                'css' => 'cf_hidden',
                'fields' => array(
                    'cf_constantcontact_auth' => array(
                        'id' => 'constantcontact_auth',
                        'type' => 'contantcontact_auth',
                        'title' => __('App authorization', 'contestfriend')
                    ),
                    'cf_constantcontact_list' => array(
                        'id' => 'constantcontact_list',
                        'type' => 'constantcontact_list',
                        'title' => __('List', 'contestfriend')
                    )
                )
            ),*/
            
            'cf_box_timing' => array(
                'title' => __('Timing', 'contestfriend'),
                'fields' => array(
                    'cf_timezone' => array(
                        'type' => 'select',
                        'title' => __('Time zone', 'contestfriend'),
                        'options' => array(
                            '-43200' => 'UTC-12',
                            '-39600' => 'UTC-11',
                            '-36000' => 'UTC-10',
                            '-34200' => 'UTC-9:30',
                            '-32400' => 'UTC-9',
                            '-28800' => 'UTC-8',
                            '-25200' => 'UTC-7',
                            '-21600' => 'UTC-6',
                            '-18000' => 'UTC-5',
                            '-16200' => 'UTC-4:30',
                            '-14400' => 'UTC-4',
                            '-12600' => 'UTC-3:30',
                            '-10800' => 'UTC-3',
                            '-7200' => 'UTC-2',
                            '-3600' => 'UTC-1',
                            '+0' => 'UTC+-0',
                            '+3600' => 'UTC+1',
                            '+7200' => 'UTC+2',
                            '+10800' => 'UTC+3',
                            '+12600' => 'UTC+3:30',
                            '+14400' => 'UTC+4',
                            '+16200' => 'UTC+4:30',
                            '+18000' => 'UTC+5',
                            '+19800' => 'UTC+5:30',
                            '+20700' => 'UTC+5:45',
                            '+21600' => 'UTC+6',
                            '+23400' => 'UTC+6:30',
                            '+25200' => 'UTC+7',
                            '+28800' => 'UTC+8',
                            '+31500' => 'UTC+8:45',
                            '+32400' => 'UTC+9',
                            '+34200' => 'UTC+9:30',
                            '+36000' => 'UTC+10',
                            '+37800' => 'UTC+10:30',
                            '+39600' => 'UTC+11',
                            '+41400' => 'UTC+11:30',
                            '+43200' => 'UTC+12',
                            '+45900' => 'UTC+12:45',
                            '+46800' => 'UTC+13',
                            '+50400' => 'UTC+14'
                        ),
                        'default' => '+0'
                    ),
                    
                    'cf_date_start' => array(
                        'id' => 'cf_date_start',
                        'type' => 'datetime',
                        'title' => __('Start date & time', 'contestfriend')
                    ),
                    
                    'cf_date_end' => array(
                        'id' => 'cf_date_end',
                        'type' => 'datetime',
                        'title' => __('End date & time', 'contestfriend')
                    )
                )
            )
        );
    }
}
