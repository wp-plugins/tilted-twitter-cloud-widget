<?php

/*
Plugin Name: Tilted Twitter Cloud Widget
Plugin URI: http://www.whiletrue.it/
Description: Takes latest Twitter updates and aggregates them into a tilted tag cloud widget for sidebar.
Author: WhileTrue
Version: 1.0.4
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
				
				if ($len<=3 or in_array($word,$excludes)) {
					continue;
				}
				$words[$word]++;
			}
		}
	}
	arsort($words);

	if( is_numeric($instance['words_number']) and $instance['words_number'] > 0 ){
		array_splice( $words, ($instance['words_number']+1) );
	}
	
	// DIRECT TO THE TWITTER.COM SEARCH USERNAME LINK
	$search_username = ($instance['link_only_user_tweets']) ? '%20from%3A'.$instance['twitter_username'] : '';

	// VALIDATE PARAMETER VALUES
	$instance['horizontal_spread'] = (is_numeric($instance['horizontal_spread']) and $instance['horizontal_spread']>0) ? $instance['horizontal_spread'] : 60;

	$i=1;
	foreach( $words as $word => $num ){
		if ($num==0 or $word=='0') {
			continue;
		}
		if( $instance['use_links'] ) {
			// DIRECT TO THE TWITTER.COM SEARCH LINK
			$out .= '<a target="_blank" href="http://twitter.com/#!/search/'.urlencode($word).$search_username.'">';
			//$out .= '<a target="_blank" href="http://search.twitter.com/search?'.$search_username.'ands='.urlencode($word).'">';
		}
		$out .=  '
			<span id="tilted-twitter-cloud-el-'.$i.'">'.$word.'</span>
			';
		if( $instance['use_links'] ) $out .= '</a>';

		$deg = rand(-45,45);

		$out_style .=  '
		div#tilted-twitter-cloud span#tilted-twitter-cloud-el-'.$i.' {
			position:absolute; padding-bottom:8px; z-index:1;
			margin-top:'.rand(5,round(60*($num+1)/$num)).'px; 
			margin-left:'.rand(0,round($instance['horizontal_spread']*($num+1)/$num)).'px; 
			font-size:' . round( (4+$num)/5 ,1) . 'em;
			     -moz-transform: rotate('.$deg.'deg);  
			       -o-transform: rotate('.$deg.'deg);   
			  -webkit-transform: rotate('.$deg.'deg);  
			      -ms-transform: rotate('.$deg.'deg);  
			          transform: rotate('.$deg.'deg);  
			               zoom: 1;
		}
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
	'.$out_style.'
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
		$this->options = array(
			array('name'=>'title', 'label'=>'Title:', 'type'=>'text'),
			array('name'=>'twitter_username', 'label'=>'Twitter Username:', 'type'=>'text'),
			array('name'=>'words_number', 'label'=>'Number of words to show:', 'type'=>'text'),
			array('name'=>'use_links', 'label'=>'Link words to twitter search:', 'type'=>'checkbox'),
			array('name'=>'link_only_user_tweets', 'label'=>'Limit links to user tweets:', 'type'=>'checkbox'),
			array('name'=>'words_excluded', 'label'=>'Excluded words (comma separated):', 'type'=>'text'),
			array('name'=>'horizontal_spread', 'label'=>'Horizontal spread in px (default is 60):', 'type'=>'text'),
		);
       parent::WP_Widget(false, $name = 'Tilted Twitter Cloud');	
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

	foreach ($this->options as $val) {
		if ($val['type']=='text') {
			$instance[$val['name']] = strip_tags($new_instance[$val['name']]);
		} else if ($val['type']=='checkbox') {
			$instance[$val['name']] = ($new_instance[$val['name']]=='on') ? true : false;
		}
	}

       return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
		if (empty($instance)) {
			$instance['title'] = 'Tilted Twitter Cloud';
			$instance['words_number'] = 20;
			$instance['use_links'] = true;
			$instance['link_only_user_tweets'] = false;
			$instance['words_excluded'] = 'can\'t,does,don\'t,from,have,i\'m,that,their,they,this,will,won\'t,with,'
				.'agli,come,dagli,dalla,degli,della,miei,sugli,sulla,sulle,sullo,tuoi';
			$instance['horizontal_spread'] = '60';
		}					

		foreach ($this->options as $val) {
			echo '<p>
				      <label for="'.$this->get_field_id($val['name']).'">'.__($val['label']).'</label> 
				   ';
			if ($val['type']=='text') {
				echo '<input class="widefat" id="'.$this->get_field_id($val['name']).'" name="'.$this->get_field_name($val['name']).'" type="text" value="'.esc_attr($instance[$val['name']]).'" />';
			} else if ($val['type']=='checkbox') {
				$checked = ($instance[$val['name']]) ? 'checked="checked"' : '';
				echo '<input id="'.$this->get_field_id($val['name']).'" name="'.$this->get_field_name($val['name']).'" type="checkbox" '.$checked.' />';
			}
			echo '</p>';
		}


    }

} // class TiltedTwitterCloudWidget

// register TiltedTwitterCloudWidget widget
add_action('widgets_init', create_function('', 'return register_widget("TiltedTwitterCloudWidget");'));

