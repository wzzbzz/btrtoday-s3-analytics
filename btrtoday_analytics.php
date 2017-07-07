<?php
/*
Plugin Name: BTRToday Analytics
Plugin URI:  http://jimwilliamsconsulting.com/wordpress/plugins/btrtoday_analytics
Description: d3js graphing of collected s3 data
Version:     1
Author:      Jim Williams
Author URI:  http://www.jimwilliamsconsulting.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: dontknow
Domain Path: /languages
*/


// menu page
add_action('admin_menu', 'add_btrtoday_analytics_page');
function add_btrtoday_analytics_page(){
    add_submenu_page ( "tools.php", "BTRToday Analytics", "BTRToday Analytics", "manage_options", "btrtoday_analytics", "render_btrtoday_analytics" );
}


// enqueue scripts
function btrtoday_analytics_enqueue_script() {
    // date picker for selecting range
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style('btrtoday-admin-ui-css','http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css',false,"1.9.0",false);
    wp_enqueue_style('btrtoday-analytics-d3-css',plugin_dir_url( __FILE__ ) .'css/css.css');
     
    wp_enqueue_script('d3js', 'http://d3js.org/d3.v3.min.js');
    
    wp_enqueue_script(
        'js-js', 
		plugin_dir_url( __FILE__ ) .'js/js.js', 
		array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker','d3js'),
		time(),
		true
    );
     
    
}

add_action('admin_enqueue_scripts', 'btrtoday_analytics_enqueue_script');

function render_btrtoday_analytics(){
    // get podcast series;
    $series = get_podcast_series();
    ?>
<div class="wrap">
    <h1>BTRToday S3 Analytics</h1>
    <label for="series">Series</label>
    <select id="series" name="series">
        <option value="">select a series</option>
        <?php 
foreach($series as $s): ?>
        <option value="<?php echo $s->slug;?>"><?php echo $s->name;?></option>
        <?php endforeach;?>
    </select>
    <label for="from">From</label>
    <input type="text" id="from" name="from">
    <label for="to">to</label>
    <input type="text" id="to" name="to">
    <button id="get_report">Go</button>
    <button id="clear">Clear</button>
    <div id="graph"></div>
</div>
    <?php
}   

