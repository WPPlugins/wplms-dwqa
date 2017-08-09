<?php
/**
 * Plugin Name: WPLMS DW Q&A Add-On
 * Plugin URI: http://www.vibethemes.com/
 * Description: Integrates DW Q&A with WPLMS
 * Author: VibeThemes
 * Version: 1.3
 * Author URI: https://vibethemes.com/
 * License: GNU AGPLv3
 * License URI: http://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: wplms-dwqa
 * Domain Path: /languages/
 */


/* ===== INTEGRATION with DW Q&A PLUGIN =========
 * 1. Add Course Nav Menu using the DW Question Connect , post meta field : vibe_course for post_type
 * 2. Add Questions list below Units using After Unit content hook.
 *==============================================*/


 
class WPLMS_DWQA {

  public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new WPLMS_DWQA();
        return self::$instance;
    }

  private function __construct(){
      add_action( 'plugins_loaded', array($this,'language_locale'));
      add_filter('wplms_course_nav_menu',array($this,'wplms_course_nav_menu_dw_qna'));    
      add_action('wplms_load_templates',array($this,'wplms_course_dwqna_question_list'));
      add_action('wplms_after_every_unit',array($this,'wplms_qna_unit_questions'),10,1);

      add_filter('wplms_course_locate_template',array($this,'wplms_dwqa_template_fitler'),10,2); 

      add_action( 'dwqa_submit_question_ui',array($this, 'wplms_dwqa_course_unit'));
      add_action('dwqa_add_question',array($this,'wplms_dwqa_add_question_connect_unit'),10,2);
      add_filter('custom_meta_box_type',array($this,'add_wplms_dwqa_tag'),10,5);
      add_action('template_redirect',array($this,'dwqa_styles'));

      add_action('dwqa_before_question_submit_button',array($this,'course_list'));
      add_action('dwqa_after_insert_question',array($this,'record_course'),10,1);
      add_action('dwqa_before_single_question_content',array($this,'check_course'));
   }

   function course_list(){
      $args = apply_filters('wplms_dwqa_course_list_in_question',array(
        'post_type' =>'course',
        'posts_per_page'=>-1
      ));

      $the_query = new WP_Query($args);
      if ( $the_query->have_posts() ) {
        echo '<p id="wplms_dwqa_question_course"><label>'.__('Select course','wplms-dwqa').'</label><select name="vibe_question_course" class="chosen"><option value="">'.__('None','wplms-dwqa').'</option>';
        while ( $the_query->have_posts() ) {
          $the_query->the_post();
          echo '<option value="'.get_the_ID().'">' . get_the_title() . '</option>';
        }
        echo '</select></p>';
      }
   }

  function record_course($question_id){
      if(!empty($_POST['vibe_question_course']) && get_post_type($_POST['vibe_question_course']) == 'course'){
        update_post_meta($question_id,'vibe_question_course',$_POST['vibe_question_course']);
      }
      if(!empty($_POST['vibe_question_unit']) && get_post_type($_POST['vibe_question_unit']) == 'unit'){
        update_post_meta($question_id,'vibe_question_unit',$_POST['vibe_question_unit']);
      }
  }

  function check_course(){
    $course_id = get_post_meta(get_the_ID(),'vibe_question_course',true);
    if(!empty($course_id)){
      echo '<label style="margin:0 0 20px 0;">'.sprintf(__('For Course %s','wplms-dwqa'),'<a href="'.get_permalink($course_id).'" target="_blank" class="link">'.get_the_title($course_id).'</a>').'</label>';
    }
  }

   function dwqa_styles(){
      if(isset($_GET['questions']) && is_singular('course')){
          if(function_Exists('dwqa_enqueue_scripts')){
            add_action( 'wp_enqueue_scripts', 'dwqa_enqueue_scripts' );
          }
      }
   }
   function language_locale(){
      $locale = apply_filters("plugin_locale", get_locale(), 'wplms-dwqa');
        if ( file_exists( dirname( __FILE__ ) . '/languages/wplms-dwqa-' . $locale . '.mo' ) ){
            load_textdomain( 'wplms-dwqa', dirname( __FILE__ ) . '/languages/wplms-dwqa-' .$locale . '.mo' );
        }
   }

   function wplms_course_nav_menu_dw_qna($course_menu){
      $link = bp_get_course_permalink();
      $course_menu['questions']=array(
                                    'id' => 'dwqna',
                                    'label'=>__('Questions','wplms-dwqa'),
                                    'action' => 'questions',
                                    'link'=> $link,
                                );
      return $course_menu;
    }

    function wplms_dwqa_template_fitler($template,$action){
      if($action == 'questions'){ 
          $template= array(get_template_directory('course/single/plugins.php'));
      }
      return $template;
    }
    function wplms_course_dwqna_question_list(){
      global $wpdb;
      $course_id=get_the_ID();
      if(!isset($_GET['action']) || ($_GET['action'] != 'questions') || !(in_array('dw-question-answer/dw-question-answer.php', apply_filters('active_plugins', get_option('active_plugins')))))
        return;

      do_action('wplms-question-course-load');
      echo '<h3 class="heading">'.__('Questions & Answers','wplms-dwqa').'</h3>';

      echo do_shortcode('[dwqa-list-questions]');
    }

    function wplms_qna_unit_questions($unit_id){
      if(!post_type_exists('dwqa-question'))
        return;

        $post_per_page = 5;
        if(function_exists('vibe_get_option')){
          $post_per_page = vibe_get_option('loop_number');
        }
        $course_id= $_COOKIE['course'];
        $query_args = apply_filters('wplms_dwqna_unit_query',array(
          'post_type' => 'dwqa-question',
          'orderby'=>'meta_value_num',
          'meta_key' => '_dwqa_votes',
          'order' => 'DESC',
          'post_per_page' => $post_per_page,
          'meta_query' => array(
              'relation'=>'AND',
                                  array(
                                      'key' => 'vibe_question_unit',
                                      'value' => $unit_id,
                                      'compare' => '=',
                                    )
            )
          )
        );
        $qna_course_tag = 1;
        if(isset($course_id)){
            $query_args['meta_query'][] =  array(
                                      'key' => 'vibe_question_course',
                                      'value' => $course_id,
                                      'compare' => '=',
                                    );
        }
        $the_questions = new WP_Query($query_args);
        echo '<div class="widget">
          <h3 class="heading">'.__('Questions & Answers','wplms-dwqa');
          echo '<small><a href="'.(isset($course_id)?get_permalink($course_id).'?action=questions':get_post_type_archive_link('dwqa-question').'?uid='.$unit_id).'" target="_blank" class="'.(isset($course_id)?'dwqa-ajax-question-list':'').'"><i class="icon-question"></i><strong>'.__('All Questions','wplms-dwqa').'</strong></small></a>
        </h3>';
        if($the_questions->have_posts()):
          echo '<ul class="dwqa-unit-questions-list">';
          while($the_questions->have_posts()):$the_questions->the_post();
            $votes = get_post_meta(get_the_ID(),'_dwqa_votes',true);
            echo '<li ><a href="'.get_permalink().'" class="dwqa-ajax-ask-question">'.get_the_title().'</a><span class="right"><i class="icon-fontawesome-webfont-18"></i> '.(($votes)?$votes:0).'</span></li>';
          endwhile;
          echo '</ul>';
        endif;
        global $dwqa_options;
        if ( isset( $dwqa_options['pages']['submit-question']) ) {
          $submit_link = get_permalink( $dwqa_options['pages']['submit-question'] );
          echo "<script>
          jQuery(document).ready(function(){
            jQuery('.dwqa-ajax-ask-question').magnificPopup({
                type: 'ajax',
                alignTop: true,
                fixedContentPos: true,
                fixedBgPos: true,
                overflowY: 'auto',
                closeBtnInside: true,
                preloader: false,
                midClick: true,
                removalDelay: 300,
                mainClass: 'my-mfp-zoom-in',
                callbacks: {
                   parseAjax: function( mfpResponse ) {
                    mfpResponse.data = jQuery(mfpResponse.data).find('#content .content');
                  },
                  ajaxContentAdded: function() {
                    jQuery('#wplms_dwqa_question_course').before('<input type=\"hidden\" name=\"vibe_question_unit\" value=\"".$unit_id."\">');
                    jQuery('#wplms_dwqa_question_course').before('<input type=\"hidden\" name=\"vibe_question_course\" value=\"".$course_id."\">');
                    jQuery('#wplms_dwqa_question_course').remove();
                  }
                }
              });  
            });
          </script><style>.mfp-ajax-holder .mfp-content .content{padding:45px;}.js .tmce-active .wp-editor-area{color:#444 !important;}</style>";
          echo '<a href="'.$submit_link.'?uid='.$unit_id.'&course_tag='.$qna_course_tag.'" class="button dwqa-ajax-ask-question">'.__('Ask Question','wplms-dwqa').'</a>
          </div>';
        }
    }

    function wplms_dwqa_course_unit(){
      ?>
      <div class="question-course-settings clearfix">
        <div class="register-select-question">
          <input type="hidden" name="vibe_question_unit" id="vibe_question_unit" value ="" />
        </div>
      </div>
      <?php
    }
    function wplms_dwqa_add_question_connect_unit($new_question,$user_id){
       $vibe_question_unit = $_POST['vibe_question_unit'];
       if(isset($vibe_question_unit) && is_numeric($vibe_question_unit)){
         update_post_meta($new_question,'vibe_question_unit',$vibe_question_unit);
       }
    }

    function add_wplms_dwqa_tag($type,$meta,$id,$desc,$post_type){
      if($type == 'dwqa-tags'){
        $args = array('hide_empty'=>false);
        $terms = get_terms('dwqa-question_tag',$args);
        if(isset($terms) && is_array($terms)){
          echo '<select name="' . $id . '" id="' . $id . '" class="select"><option value="">'.__('Select Question Tag','wplms-dwqa').'<option>';

          if($meta == '' || !isset($meta)){$meta=$std;}
          foreach($terms as $term){
            echo '<option' . selected( esc_attr( $meta ), $term->slug, false ) . ' value="' . $term->slug . '">' . $term->name . '</option>';
          }
          echo '</select><br />' . $desc;
        }
      }
      return $type;
    }

}

if(class_exists('WPLMS_DWQA')){ 
  $wplms_dwqa = WPLMS_DWQA::init();
}

add_shortcode( 'dwqa-list-questions-with-taxonomy', 'dwqa_archive_question_shortcode');
function dwqa_archive_question_shortcode( $atts ) {
  global $script_version, $dwqa_sript_vars;
  
  extract( shortcode_atts( array(
      'taxonomy_category' => '',//Use slug
      'taxonomy_tag' => '',//Use slug
  ), $atts, 'bartag' ) );
  
  global $dwqa, $script_version, $dwqa_sript_vars;
    ob_start();

    $dwqa->template->remove_all_filters( 'the_content' );

    echo '<div class="dwqa-container" >';
    dwqa_load_template( 'archive', 'question' );
    echo '</div>';
    $html = ob_get_contents();

    $dwqa->template->restore_all_filters( 'the_content' );

    ob_end_clean();
    wp_enqueue_script( 'jquery-ui-autocomplete' );
    wp_enqueue_script( 'dwqa-questions-list', DWQA_URI . 'templates/assets/js/dwqa-questions-list.js', array( 'jquery', 'jquery-ui-autocomplete' ), $script_version, true );
    wp_localize_script( 'dwqa-questions-list', 'dwqa', $dwqa_sript_vars );
    return apply_filters( 'dwqa-shortcode-question-list-content', $html );
  }

function dwqa_questions_load_course_questions( $query ) {
  global $post;

  if($post->post_type == 'course'){
    if ( in_array ( $query->get('post_type'), array('dwqa-question') ) ) {
       $meta_query = $query->get('meta_query');
       $meta_query[] = array(
                    'key' => 'vibe_question_course',
                    'value' => $post->ID,
                    'compare' => '='
                );
        $query->set('meta_query',$meta_query);
    }
  }


  return $query;
}
add_filter( 'pre_get_posts', 'dwqa_questions_load_course_questions',999 );

 /*==============================================
 *         END DW Q&A PLUGIN INTEGRATION 
 *==============================================*/
