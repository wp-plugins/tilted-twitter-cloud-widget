<?php

/*
Plugin Name: Tilted Twitter Cloud Widget
Plugin URI: http://www.whiletrue.it/
Description: Takes latest Twitter updates and aggregates them into a tilted tag cloud widget for sidebar.
Author: WhileTrue
Version: 1.0.1
Author URI: http://www.whiletrue.it/
*/

/*
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2, 
    as published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/

function tilted_twitter_cloud ($instance, $remove_types=array() ) {
	$types = array(
		'@' => true,
		'#' => true,
		'RT' => true
	);
	
	if( !empty( $remove_types ) ){
		foreach( $remove_types as $type ){
			$types[$type] = false;
		}
	}
	
	if( $instance['twitter_username'] == '' ){
		return 'Tilted Twitter Cloud Error: No username given';
	}

	
	// MODIFY FEED CACHE LIFETIME ONLY FOR THIS FEED (2 hours)
	add_filter( 'wp_feed_cache_transient_lifetime', create_function( '$a', 'return 7200;' ) );

	// TAKE (AT MOST) THE 100 LAST TWEETS
	//$rss = fetch_feed('http://twitter.com/statuses/user_timeline/'.$instance['twitter_username'].'.rss?count=100');
	// USE THE NEW TWITTER REST API
	$rss = fetch_feed('http://api.twitter.com/1/statuses/user_timeline.rss?screen_name='.$instance['twitter_username'].'&count=100');

	// RESET STANDARD FEED CACHE LIFETIME (12 hours)
	remove_filter( 'wp_feed_cache_transient_lifetime', create_function( '$a', 'return 1800;' ) );

	if (is_wp_error($rss)) {
		return __('Twitter Feed not created correctly','tilted_twitter_cloud_widget');
	}

	$maxitems = $rss->get_item_quantity(); 

	if ($maxitems==0) {
		return __('No public Twitter messages','tilted_twitter_cloud_widgets');
	}

	// BUILD AN ARRAY OF ALL THE ITEMS, STARTING WITH ELEMENT 0 (FIRST ELEMENT).
	$rss_items = $rss->get_items(0, $maxitems); 

	$excludes = explode(',',$instance['words_excluded']);

	$words = array();
	foreach ($rss_items as $message) {
		$msg = " ".substr(strstr($message->get_description(),': '), 2, strlen($message->get_description()))." ";
		
		$words_msg = explode(' ', $msg);
		foreach($words_msg as $word) {
			if ($word == '') {
				continue;
			}
			$word = strtolower( $word );
			if(
				!(
					( !$types['@'] && substr($word, 0, 1) == '@' )
					||
					( !$types['#'] && substr($word, 0, 1) == '#' )
					||
					( !$types['RT'] && substr($word, 0, 2) == 'RT' )
					||
					( substr($word, 0, 7) == 'http://' || substr($word, 0, 4) == 'www.' )
				)
			){
				$word = html_entity_decode( $word );
				while( !preg_match( '/^[0-9a-zאטילעש@#]/i', $word ) && $word != '' ){
					$word = substr( $word, 1 );
				}
				while( !preg_match( '/[0-9a-zאטילעש]$/i', $word ) && $word != '' ){
					$word = substr( $word, 0, -1 );
				}
				
				//AFTER TEXT CLEANING, CHECK ITS LENGHT (MB_STRLEN PREFERRED IF AVAILABLE) AND EXCLUDES
				$len = (function_exists('mb_strlen')) ? mb_strlen($word) : strlen($word);
				
				if ($len<3 or in_array($word,$excludes)) {
					continue;
				}
				$words[$word]++;
			}
		}
	}
	arsort($words);

	if( is_numeric($instance['words_number']) and $instance['words_number'] > 0 ){
		array_splice( $words, $instance['words_number'] );
	}
	
	$search_username = ($instance['link_only_user_tweets']=='on') ? 'from='.$instance['twitter_username'].'&amp;' : '';

	$i=0;
	foreach( $words as $word => $num ){
		if ($num==0 or $word=='0') {
			continue;
		}
		if( $instance['use_links']=='on' ) {
			$out .= '<a target="_blank" href="http://search.twitter.com/search?'.$search_username.'ands='.urlencode($word).'">';
		}
		$out .=  '<span id="tilted-twitter-cloud-el-'.$i.'">'.$word.'</span>';
		if( $instance['use_links']=='on' ) $out .= '</a>';

		$deg = rand(-45,45);
		/* SUPPORT FOR IE6 DROPPED
		$rad = deg2rad($deg);
		filter: progid:DXImageTransform.Microsoft.Matrix(M11='.cos($rad).', M12=-'.sin($rad).', M21='.sin($rad).', M22='.cos($rad).',sizingMethod=\'auto expand\');
		*/
		$out .=  '
		<style>
		div#tilted-twitter-cloud span#tilted-twitter-cloud-el-'.$i.' {
			position:absolute; padding-bottom:8px; z-index:1;
			margin-top:'.rand(5,round(60*($num+1)/$num)).'px; 
			margin-left:'.rand(0,round(60*($num+1)/$num)).'px; 
			font-size:' . round( (4+$num)/5 ,1) . 'em;
			     -moz-transform: rotate('.$deg.'deg);  
			       -o-transform: rotate('.$deg.'deg);   
			  -webkit-transform: rotate('.$deg.'deg);  
			      -ms-transform: rotate('.$deg.'deg);  
			          transform: rotate('.$deg.'deg);  
			               zoom: 1;
		}
		</style>
		';	
		$i++;
	}
	
	return '<div id="tilted-twitter-cloud">'.$out.'</div>
	<style>
	div#tilted-twitter-cloud {
		position:relative;
		height:180px;
	}
	div#tilted-twitter-cloud span, div#tilted-twitter-cloud a, div#tilted-twitter-cloud a:hover, div#tilted-twitter-cloud a:visited {
		color:gray;
		text-decoration:none;
	}
	</style>
	<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery("#tilted-twitter-cloud span").hover(
			function () {
		    jQuery(this).css("z-index",10);
		    jQuery(this).css("font-weight","bold");
		    jQuery(this).css("color","black");
		  },
		  function () {
		    jQuery(this).css("z-index",0);
		    jQuery(this).css("font-weight","normal");
		    jQuery(this).css("color","gray");
		  }
		);
	});
	</script>
	';
}


//////////


// JQUERY INIT REQUIRED
function tilted_twitter_cloud_init() {
	if (!is_admin()) {
		wp_enqueue_script('jquery');
	}
}
add_action('init', 'tilted_twitter_cloud_init');


/**
 * TiltedTwitterCloudWidget Class
 */
class TiltedTwitterCloudWidget extends WP_Widget {
    /** constructor */
    function TiltedTwitterCloudWidget() {
        parent::WP_Widget(false, $name = 'TiltedTwitterCloudWidget');	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {		
        extract( $args );
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;  
		if ( $title ) echo $before_title . $title . $after_title; 
		echo tilted_twitter_cloud($instance).$after_widget;
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
	$instance = $old_instance;
	$instance['title'] = strip_tags($new_instance['title']);
	$instance['twitter_username'] = strip_tags($new_instance['twitter_username']);
	$instance['words_number'] = strip_tags($new_instance['words_number']);
	$instance['use_links'] = strip_tags($new_instance['use_links']);
	$instance['link_only_user_tweets'] = strip_tags($new_instance['link_only_user_tweets']);
	$instance['words_excluded'] = strip_tags($new_instance['words_excluded']);
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
		if (empty($instance)) {
			$instance['title'] = 'Tilted Twitter Cloud';
			$instance['words_number'] = 20;
			$instance['use_links'] = 'on';
			$instance['link_only_user_tweets'] = '';
			$instance['words_excluded'] = 'and,are,but,can,can\'t,does,don\'t,for,from,get,has,have,her,his,i\'m,not,one,say,she,that,the,their,they,this,will,won\'t,with,you,'
				.'agli,che,chi,coi,come,con,dagli,dal,dalla,degli,del,della,fra,lei,lui,mio,mia,miei,non,per,piש,qua,qui,tra,tua,tuo,tuoi,una,uno';
		}					
        $title = esc_attr($instance['title']);
        $twitter_username = esc_attr($instance['twitter_username']);
        $words_number = esc_attr($instance['words_number']);
        $use_links = ($instance['use_links']=='on') ? 'checked="checked"' : '';
        $link_only_user_tweets = ($instance['link_only_user_tweets']=='on') ? 'checked="checked"' : '';
        $words_excluded = esc_attr($instance['words_excluded']);
        ?>
         <p>
          <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('twitter_username'); ?>"><?php _e('Twitter username:'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('twitter_username'); ?>" name="<?php echo $this->get_field_name('twitter_username'); ?>" type="text" value="<?php echo $twitter_username; ?>" />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('words_number'); ?>"><?php _e('Number of words to show:'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('words_number'); ?>" name="<?php echo $this->get_field_name('words_number'); ?>" type="text" value="<?php echo $words_number; ?>" />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('use_links'); ?>"><?php _e('Link words to twitter search:'); ?></label> 
          <input id="<?php echo $this->get_field_id('use_links'); ?>" name="<?php echo $this->get_field_name('use_links'); ?>" type="checkbox" <?php echo $use_links; ?> />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('link_only_user_tweets'); ?>"><?php _e('Limit links to user tweets:'); ?></label> 
          <input id="<?php echo $this->get_field_id('link_only_user_tweets'); ?>" name="<?php echo $this->get_field_name('link_only_user_tweets'); ?>" type="checkbox" <?php echo $link_only_user_tweets; ?> />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('words_excluded'); ?>"><?php _e('Excluded words (comma separated):'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('words_excluded'); ?>" name="<?php echo $this->get_field_name('words_excluded'); ?>" type="text" value="<?php echo $words_excluded; ?>" />
        </p>
        <?php 
    }

} // class TiltedTwitterCloudWidget

// register TiltedTwitterCloudWidget widget
add_action('widgets_init', create_function('', 'return register_widget("TiltedTwitterCloudWidget");'));

