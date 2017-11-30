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


function seed_post_date(){
			global $wpdb;
		$sql = "SELECT * from file_series";
		$results = $wpdb->get_results($sql);
	    
		for ($i=0;$i<count($results);$i++)
		{
			$post = get_post($results[$i]->post_id);
			$sql = $wpdb->prepare("UPDATE file_series set post_date = '%s' WHERE post_id='%s'",$post->post_date, $post->ID);
			$wpdb->query($sql);
		}
		die;
}
function by_subarray_count($a,$b){
    if(count($a["post_count"]) == count($b["post_count"]))
        return 0;
    return count($a["post_count"])<count($b["post_count"])?1:-1;
}

class BTRtoday_Analytics{
    
	const default_page_slug = "btrtoday_analytics";
    //const base = "settings_page_btrtoday_default_recommended_posts";
	
	private $current_page;
	
	private $series;
	
	public function __construct(){
		$this->init();
		$this->register_routes();
	}
	
	public function init(){

		/* btr daily dashboard */
		add_action('wp_dashboard_setup', array($this,'add_daily_podcast_downloads_meta_box'));
		add_action( 'add_meta_boxes', array($this, 'add_meta_box') );
        add_action( 'admin_enqueue_scripts', array( $this,'enqueue_scripts' ) );
        add_action('admin_menu', array($this,'create_menu'));
      //  add_action('admin_init',array($this,'update_file_series_dates'));
		
		$this->set_current_page();
		
	}
	
    public function update_file_series_dates(){
        set_time_limit(0);
        global $wpdb;
        $sql = "SELECT * from file_series";
        $files = $wpdb->get_results($sql);
        foreach($files as $file){
            $post = get_post($file->post_id);
            $sql = $wpdb->prepare("UPDATE file_series SET post_date='%s' WHERE post_id='%s'",$post->post_date, $post->ID);
            $wpdb->query($sql);
        }
        die("done");
    }
	private function set_current_page(){
		if(!empty($_GET['series'])){
			$this->current_page = "series_detail";
			$this->series = $_GET['series'];
		}
		else{
			$this->current_page = "overview";
		}
	}
	
	public function add_meta_box(){}
	
	public function enqueue_scripts(){
		if(get_current_screen()->id != 'tools_page_btrtoday_analytics'){
			return;
		}
		 // date picker for selecting range
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style('btrtoday-admin-ui-css','http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css',false,"1.9.0",false);
		wp_enqueue_style('btrtoday-analytics-d3-css',plugin_dir_url( __FILE__ ) .'css/css.css');
		 
		wp_enqueue_script('d3js', 'http://d3js.org/d3.v3.min.js');
		wp_enqueue_script('sortable', plugin_dir_url( __FILE__ ) .'js/sorttable.js');
		
		wp_enqueue_script(
			'js-js', 
			plugin_dir_url( __FILE__ ) .'js/js.js', 
			array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker','d3js'),
			time(),
			true
		);
		
		
	}
	
	public function create_menu(){
		add_submenu_page ( "tools.php", "BTRtoday Analytics", "BTRtoday Analytics", "manage_options", "btrtoday_analytics", array($this,"render_btrtoday_analytics") );
		add_submenu_page ( "tools.php", "BTR Top 10", "BTR Top Artists", "manage_options", "btrtoday_top10", array($this,"render_btrtop10") );
	}
	
	
	public function add_daily_podcast_downloads_meta_box() {
		global $wp_meta_boxes;
		$date = date("m/d/Y",time() - 86400);
		wp_add_dashboard_widget('top_10_downloads_widget', 'Top 10 Downloads for ' . $date, array($this,'btrtoday_daily_podcast_activity'));
	}
	
	// REFACTOR TBD
	public function btrtoday_daily_podcast_activity() {
	?>
		<div id="loading" style="text-align:center"><img src="http://blog.teamtreehouse.com/wp-content/uploads/2015/05/InternetSlowdown_Day.gif" width="150"></div>
		<div id="graph"></div>
		
		<script>
			var margin = {top: 40, right: 20, bottom:350, left: 40},
			width = 560 - margin.left - margin.right,
			height = 800 - margin.top - margin.bottom;
	
		
		// set the ranges
		var x = d3.scale.ordinal().rangeRoundBands([0, width], .05);
		
		var y = d3.scale.linear().range([height, 0]);
		
		// define the axis
		var xAxis = d3.svg.axis()
			.scale(x)
			.orient("bottom")
		
		var yAxis = d3.svg.axis()
			.scale(y)
			.orient("left")
	
	
		// add the SVG element
		var svg = d3.select("#graph").append("svg")
			.attr("width", width + margin.left + margin.right)
			.attr("height", height + margin.top + margin.bottom)
		  .append("g")
			.attr("transform", 
				  "translate(" + margin.left + "," + margin.top + ")");
		
		
		var json_url = "http://www.btrtoday.com/json/s3/daily-podcast";
		// load the data
		d3.json(json_url, function(error, data) {
			jQuery("#loading").hide();
			var total = 0;
			// converts string to int.
			data.forEach(function(d) {
				d[0] = d[0];
				d[1] = +d[1];
				total += d[1];
			});
			
			
			
		  // scale the range of the data
		  x.domain(data.map(function(d) { return d[0]; }));
		  y.domain([0, d3.max(data, function(d) { return d[1]; })]);
		  
			svg.append("text")
				.attr("x", (width / 2))             
				.attr("y", 0 - (margin.top / 2))
				.attr("text-anchor", "middle")  
				.style("font-size", "16px") 
				//.style("text-decoration", "underline")  
				.text("today's top 10 podcasts")
				.attr("x", (width / 2))             
				.attr("y", 0 - (margin.top / 2))
				.attr("text-anchor", "middle")  
				.style("font-size", "12px") 
				//.style("text-decoration", "underline")  
				.text("unique IPs")
				
		  // add axis
		  svg.append("g")
			  .attr("class", "x axis")
			  .attr("transform", "translate(0," + height + ")")
			  .call(xAxis)
			.selectAll("text")
			  .style("text-anchor", "end")
			  .attr("dx", "-.8em")
			  .attr("dy", "-.55em")
			  .attr("transform", "rotate(-90)" );
		
		  svg.append("g")
			  .attr("class", "y axis")
			  .call(yAxis)
			.append("text")
			  .attr("transform", "rotate(-90)")
			  .attr("y", 5)
			  .attr("dy", ".71em")
			  .style("text-anchor", "end")
			  .text("File Requests");
		
		
		  // Add bar chart
		  svg.selectAll("bar")
			  .data(data)
			.enter().append("rect")
			  .attr("class", "bar")
			  .attr("x", function(d) { return x(d[0]); })
			  .attr("width", x.rangeBand())
			  .attr("y", function(d) { return y(d[1]); })
			  .attr("height", function(d) { return height - y(d[1]); })
			  .on("mouseover", function() { tooltip.style("display", null); })
			  .on("mouseout", function() { tooltip.style("display", "none"); })
			  .on("mousemove", function(d) {
				  var xPosition = d3.mouse(this)[0] - 5;
				  var yPosition = d3.mouse(this)[1] - 5;
				  tooltip.attr("transform", "translate(" + xPosition + "," + yPosition + ")");
				  tooltip.select("text").text(d[1]);
			  });
	
			  
		// Prep the tooltip bits, initial display is hidden
	  var tooltip = svg.append("g")
		.attr("class", "tooltip")
		.style("display", "none");
		  
	  tooltip.append("rect")
		.attr("width", 60)
		.attr("height", 20)
		.attr("fill", "white")
		.style("opacity", 0.5);
	
	  tooltip.append("text")
		.attr("x", 30)
		.attr("dy", "1.2em")
		.style("text-anchor", "middle")
		.attr("font-size", "12px")
		.attr("font-weight", "bold");          
		});
		
		</script>
		<?
	?>
	
	<?php
	}
	
	public function render_btrtoday_analytics(){
		
		switch($this->current_page){
			case "series_detail":
				$this->render_series_detail();
				break;
			case "overview":
				$this->render_overview();
			default:
				break;
		}
		return;
		// get podcast series;
die;		
    ?>

		<div style="float:left; width:500px">
			<h3>Downloads by series/post</h3>
			
			
				<input type="hidden" name="page" value="<?php echo $_GET['page'];?>">
				<h4>select new range</h4>
				<div>
					<label for="series-from">From</label>
					<input type="text" id="series-from" name="series-from">
					<label for="series-to">to</label>
					<input type="text" id="series-to" name="series-to">
				</div>
			
			<br><br>
			
			<label for="series">Series</label>
			<select id="series" name="series">
				<option value="">select a series</option>
				<?php 
			foreach($series as $s): ?>
				<option value="<?php echo $s->slug;?>"><?php echo $s->name;?></option>
				<?php endforeach;?>
			</select>
			<!--<label for="from">From</label>
			<input type="text" id="from" name="from">
			<label for="to">to</label>
			<input type="text" id="to" name="to">-->
			<button id="get_report">Go</button>
			<button id="clear">Clear</button>
			<div id="graph"></div>
		</div>
		<?php
	}

	private function render_overview(){
		$series = get_podcast_series();
		
		
		// default time-range = previous 7 days for starters.
		$now = time();
		$end = empty($_GET['to'])? date("Y-m-d", $now) . " 00:00:00" : $_GET['to'] . " 00:00:00";
		$start = empty($_GET['from']) ? date("Y-m-d", $now - 60*60*24*7) . " 00:00:00" : $_GET['from'] . " 00:00:00";
		
		$total =0;
		foreach($series as $i=>$s){
			$total += $series[$i]->total_range_downloads = $this->count_all_series_requests_range($s->term_id, $start, $end);
			$series[$i]->range_episode_count = $this->count_published_series_posts_in_range($s->term_id, $start, $end);
			$series[$i]->range_episode_downloads = $this->count_published_series_posts_requests_in_range($s->term_id, $start, $end);
		}
		
		usort($series,array($this,'sort_series_by_count'));
		?>
		<div class="wrap">
		
		<h1>Podcast Downloads</h1>

		<div style="float:left;padding-right:40px;">
			
			<h2><?php echo date("M d, Y",strtotime($start));?> - <?php echo date("M d, Y",strtotime($end));?></h2>
			<form action="" method = "get">
				<input type="hidden" name="page" value="<?php echo $_GET['page'];?>">
				<h4>select new range</h4>
				<div>
					<label for="from">From</label>
					<input type="text" id="from" name="from">
					<label for="to">to</label>
					<input type="text" id="to" name="to">
					<button id="submit">Go</button>
				</div>
			</form>
			<br><br>
			<table class="sortable">
				<thead>
					<th style="text-align:left">Series Name</th>
					<th>Total Downloads</th>
					<th>In-Range Downloads</th>
                    <th>Episodes in Range</th>
                    <th>Episode Average</th>
				</thead>
				<tr>
					<td style="text-align:left">TOTAL</td>
					<td style="text-align:center;"><?php echo $total;?></td>
					<td style="text-align:center;">N/A</td>
                    <td style="text-align:center;">N/A</td>
                    <td style="text-align:center;">N/A</td>
				</tr>
		<?php foreach ($series as $s):?>
				<tr>
					<td style="text-align:left"><?php echo $s->name;?></td>
					<td style="text-align:center"><?php echo $s->total_range_downloads;?></td>
					<td style="text-align:center"><?php echo $s->range_episode_downloads;?></td>
                    <td style="text-align:center"><?php echo $s->range_episode_count;?></td>
                    <td style="text-align:center"><?php echo ($s->range_episode_count>0)?ceil($s->range_episode_downloads/$s->range_episode_count):"N/A";?></td>
				</tr>
		<?php endforeach;?>
			</table>
					
		</div>
	<?php		
	}
	// create new plugin for this
	function render_btrtop10(){
    
		$args = array(
		 'post_type' => 'listen',
		 'post_status' => 'publish',
		 'orderby' => 'date',
		 'order' => 'DESC',
		 'numberposts'=>-1,
		 // Using the date_query to filter posts from last week
		 'date_query' => array(
			 array(
				 'after' => '1 week ago'
			 )
		 )
		 );
		
		$title = "Past 7 days";
		$is_range = !empty($_GET['from']) && !empty($_GET['to']);
		if($is_range){
			 $start = split("-",$_GET['from']);
			 $end = split("-",$_GET['to']);
			 
			 $args['date_query'] = array(
									 array(
										   'after'=>array('year'=>$start[0],'month'=>$start[1],'day'=>$start[2]-1),
										   'before'=>array('year'=>$end[0],'month'=>$end[1],'day'=>$end[2]+1)
									 )
								   );
			 
			 $title = $_GET['from']." - ".$_GET['to'];
		}
		
		$posts = get_posts($args);
		$artist_post_totals = array();
		foreach($posts as $post){
		 $artists =  get_post_artists($post->ID);
		 if(!empty($artists)){
			 foreach($artists as $artist){
				 $artist_post_totals[$artist->name]["post_count"][] = $post->ID;
			 }
		 }
		 
		 $playlist = get_field("playlist",$post->ID);
		 if(!empty($playlist)){
			 foreach($playlist as $entry){
				 if(!empty($entry['artist']) && !empty($artist_post_totals[$entry['artist']])){
					 $artist_post_totals[$entry['artist']]["track_mention"][] = $entry['title'];
				 }
			 }
		  }
		}
		
		uasort($artist_post_totals, 'by_subarray_count');
	 
		 ?>
		<div class="wrap">
		 <h1>BTRToday Top Artists</h1>
		 <form action="tools.php" method="get">
			 <input type="hidden" name="page" value="btrtoday_top10">
			 <label for="from">From</label>
			 <input type="text" id="from" name="from">   
			 <label for="to">to</label>
			 <input type="text" id="to" name="to">
			 
			 <button type="submit">go</button>
		 </form>
		 <hr>
		 <h2 style="text-align:center;"><?php echo $title;?></h2>
		 <style>
			 table.sortable tr:nth-child(10) td {
				 border-bottom:1px solid black;
				 margin:none;
			 }
		 </style>
		 <table class="sortable" cellspacing="5" cellpadding="5">
			 <thead>
				 <th>Artist Name</th>
				 <th>Post Mentions</th>
				 <th>Playlist Track Appearances</th>
			 </thead>
			 <?php $i=0;foreach($artist_post_totals as $name=>$posts):$i++;
			 
			 ?>
				 <tr>
	 
					 <td><?php echo $name;?></td>
					 <td style="text-align:center"><?php echo count($posts['post_count']);?></td>
					 <td style="text-align:center"><?php echo count($posts['track_mention']);?></td>
			 <?php endforeach;?>    
		 </table>
	 
	 </div>
		 <?php
	 }   

	public function count_all_series_requests_range($series_id, $start, $end){
		
		global $wpdb;
		$sql = $wpdb->prepare("SELECT
							count(s3.id)  as count
						FROM
							file_series fs
						JOIN s3logs s3 ON fs.request_key = s3.request_key
						WHERE
							fs.series_id = '%d'
						AND s3.request_time > '%s' 
						AND s3.request_time < '%s'", $series_id, $start, $end);
		
		$results = $wpdb->get_results($sql);
		if (count($results)){
			return $results[0]->count;	
		}
		else
			return 0;
		
	}
	
	public function count_published_series_posts_in_range($series_id, $start, $end){
		
		global $wpdb;
		$sql = $wpdb->prepare("SELECT
							count(DISTINCT(fs.request_key))  as count
						FROM
							file_series fs
						WHERE
							fs.series_id = '%d'
                        AND fs.post_date > '%s'
                        AND fs.post_date < '%s'", $series_id, $start, $end,$start,$end);
        
		$results = $wpdb->get_results($sql);
        
		if (count($results)){
			return $results[0]->count;	
		}
		else
			return 0;
	}
	
    public function count_published_series_posts_requests_in_range($series_id, $start, $end){
        
        global $wpdb;
        
        $sql = $wpdb->prepare("SELECT
							count(s3.id)  as count
						FROM
							file_series fs
						JOIN s3logs s3 ON fs.request_key = s3.request_key
						WHERE
							fs.series_id = '%d'
						AND s3.request_time > '%s' 
						AND s3.request_time < '%s'
                        AND fs.post_date >'%s'
                        AND fs.post_date < '%s'", $series_id, $start, $end,$start,$end);
        $results = $wpdb->get_results($sql);
        if (count($results)){
			return $results[0]->count;	
		}
		else
			return 0;
    }
    
	public function register_routes(){}

	public function sort_series_by_count($a,$b){
		if($a->total_range_downloads==$b->total_range_downloads)
			return 0;
		if ($a->total_range_downloads < $b->total_range_downloads)
			return 1;
		return -1;
	}
}


$btr_analytics = new BTRtoday_Analytics();