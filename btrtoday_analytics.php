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
    private $start;
    private $end;
	
	public function __construct(){
		$this->hooks();
		$this->register_routes();
		
	}
	
	public function hooks(){

		/* btr daily dashboard */
		#add_action( 'wp_dashboard_setup', array( $this,'add_daily_podcast_downloads_meta_box' ));
		//add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'admin_enqueue_scripts', array( $this,'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this,'create_menu' ) );
        add_action( 'admin_init', array( $this, 'init' ) );
		
        // default time-range = previous 7 days for starters.
		
	}
    
    public function enqueue_scripts(){
        
        $pages = array('tools_page_btrtoday_analytics','tools_page_btrtoday_top10','toplevel_page_series_analytics');
		
		if(!in_array(get_current_screen()->id,$pages)){
			return;
		}
        
		
		
        switch(get_current_screen()->id){
                case "tools_page_btrtoday_analytics":
				case "toplevel_page_series_analytics":
                    wp_enqueue_style('btrtoday-analytics-d3-css',plugin_dir_url( __FILE__ ) .'css/css.css');
                    break;
                case "tools_page_btrtoday_top10":
                    break;
                default:
                    break;
        }
		
		 // date picker for selecting range
		wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'jquery-ui-spinner' );
		wp_enqueue_style('btrtoday-admin-ui-css','https://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css',false,"1.9.0",false);
		
		 
		wp_enqueue_script('d3js', 'http://d3js.org/d3.v3.min.js');
		wp_enqueue_script('sortable', plugin_dir_url( __FILE__ ) .'js/sorttable.js');
		
		wp_enqueue_script(
			'js-js', 
			plugin_dir_url( __FILE__ ) .'js/js.js', 
			array('jquery', 'jquery-ui-core', 'jquery-ui-spinner','jquery-ui-datepicker','d3js'),
			time(),
			false
		);
        
        //wp_localize_script( 'js-js', 'data', $this->data );
		
		
	}
    
    public function init(){
        
        // remember to find where to do this properly!
        $this->seed_post_date();
        
        // set the current page
        $this->set_current_page();
        $this->set_series();
        $this->set_date_range();
        $this->prepare_data();
    }
    
    private function set_date_range(){
        $now = time();
		
		if( isset( $_GET['page'] ) && $_GET['page'] == 'series_analytics'){
			$days = 7;  #as per Jeremiah's request
		}
		else {
			$days = 7;
		}
		
		$this->end = empty($_GET['to'])? date("Y-m-d", $now) . " 23:59:59" : $_GET['to'] . " 23:59:59";
		$this->start = empty($_GET['from']) ? date("Y-m-d", $now - 60*60*24*$days) . " 00:00:00" : $_GET['from'] . " 00:00:00";
    }
	
    //NOTE:  Need to find out where to put this in the post_save process where these rows are being created.  Big mystery!!!  head scratcher for sure.
    public function seed_post_date(){
        global $wpdb;
		$sql = "SELECT * from file_series WHERE post_date IS NULL";
        
		$results = $wpdb->get_results($sql);
	    
		for ($i=0;$i<count($results);$i++)
		{
			$post = get_post($results[$i]->post_id);
			$sql = $wpdb->prepare("UPDATE file_series set post_date = '%s' WHERE post_id='%s'",$post->post_date, $post->ID);
			$wpdb->query($sql);
		}
		
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
	
    private function set_series(){
        if(isset($_GET['series'])){
            $slug = $_GET['series'];
            $this->series = get_term_by('slug',$slug,'podcast-series');
        }
    }
    
    public function prepare_data(){
        switch($this->current_page){
            case 'series_detail':
                $range_data = $this->get_series_range_count_by_post(array($this->start, $this->end),$this->series);
                foreach($range_data as $i=>$data){
                    $post = get_post($data->post_id);
                    $range_data[$i]->post_title=$post->post_title;
                    $range_data[$i]->post_name = $post->post_name;
                }
                $this->data = $range_data;
                break;
            case 'overview':
            default:
                break;
        };
        
    }

	
	public function create_menu(){
		
		add_menu_page(
					  "Series Analytics",
					  "Series Analytics",
					  "view_series_analytics",
					  "series_analytics",
					  array($this,'render_series_analytics'),
					  'dashicons-chart-bar'
					  );
    
		
		#old page
		add_submenu_page ( "tools.php", "BTRtoday Analytics", "BTRtoday Analytics", "manage_options", "btrtoday_analytics", array($this,"render_btrtoday_analytics") );
		add_submenu_page ( "tools.php", "BTR Top 10", "BTR Top Artists", "manage_options", "btrtoday_top10", array($this,"render_btrtop10") );
	}
	
	
	
	public function render_series_analytics(){
		
		set_time_limit(0);
		
		$user = wp_get_current_user();
		$roles = (( array ) $user->roles);
		$role = $roles[0];
		
		if($role!="administrator"){
			$user_id = get_current_user_id();
			$user_series = get_user_podcasts($user_id);
			
			if(empty($user_series)){
				echo "<h3>Current User Not A Podcaster";
				return;
			}
		}
		else{
			$user_series = get_podcast_series(false);
		}
		
		$current_series = empty($_GET['podcast'])?$user_series[0]:get_the_podcast(get_term($_GET['podcast']));
		
		$reports = $this->getPodcastSeriesPostReport($current_series->term_id, $this->start, $this->end);
	
		?>
		
		<div class="wrap episodes">
		
		<h1><?php echo $current_series->name;?></h1>

		<div style="float:left;padding-right:40px;">
			
			<h2><?php echo date("M d, Y",strtotime($this->start));?> - <?php echo date("M d, Y",strtotime($this->end));?></h2>
			<form action="" method = "get">
				<?php if (count($user_series)>1){?>
				<h4>select new series</h4>
				<select name="podcast">
					<?php foreach($user_series as $series):
					$selected = ($series->term_id ==$_GET['podcast'])?"selected":"";?>
					<option value="<?php echo $series->term_id;?>" <?php echo $selected;?>><?php echo $series->name;?></option>
					<?php endforeach;?>
				</select>	
				<?php }?>
				
				<input type="hidden" name="page" value="<?php echo $_GET['page'];?>">
				<h4>select new range</h4>
				<div>
					<label for="from">From</label>
					<input type="text" id="from" name="from">
					<label for="to">to</label>
					<input type="text" id="to" name="to">
					
				</div>
				<button id="submit">Go</button>
			</form>
			<br><br>
			<table class="sortable">
				<thead>
					<th>Ep Date</th>
					<th style="text-align:left">Ep Title</th>
					<th>In Range Total</th>
					<th>All Time total</th>
				</thead>
		<?php foreach ($reports as $report):?>
	
				<tr>
					<td style="text-align:center"><?php echo $report->post_date?></td>
					<td style="text-align:left"><a href="<?php echo $report->url;?>" target="_blank"><?php echo $report->title;?></a></td>
					<td style="text-align:center"><?php echo $report->downloads;?></td>
					<td style="text-align:center"><?php echo $report->total_downloads;?></td>
				</tr>
		<?php endforeach;?>
			</table>
					
		</div>
		
		<?php
		
		// now do the html;
		
		
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
		set_time_limit(0);
		
		$series = get_podcast_series(false);
		
		$total = $this->total_requests_range($this->start, $this->end);
		foreach($series as $i=>$s){
			
			$series[$i]->total_range_downloads = $this->count_all_series_requests_range($s->term_id, $this->start, $this->end);
			if($s->is_active){
				$active_total+= $series[$i]->total_range_downloads;
				$series[$i]->range_episode_count = $this->count_published_series_posts_in_range($s->term_id, $this->start, $this->end);
				$series[$i]->range_episode_downloads = $this->count_published_series_posts_requests_in_range($s->term_id, $this->start, $this->end);
			}
			
			
		}
		
		
		usort($series,array($this,'sort_series_by_count'));
		?>
		<div class="wrap overview">
		
		<h1>Podcast Downloads</h1>

		<div style="float:left;padding-right:40px;">
			
			<h2><?php echo date("M d, Y",strtotime($this->start));?> - <?php echo date("M d, Y",strtotime($this->end));?></h2>
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
				<tr>
					<td style="text-align:left">ACTIVE TOTAL</td>
					<td style="text-align:center;"><?php echo $active_total;?></td>
					<td style="text-align:center;">N/A</td>
                    <td style="text-align:center;">N/A</td>
                    <td style="text-align:center;">N/A</td>
				</tr>
		<?php foreach ($series as $s):?>
				<tr>
					<td style="text-align:left">
						<?php if($s->is_active):?>
						<a href="<?php echo admin_url(); ?>/admin.php?page=series_analytics&podcast=<?php echo $s->term_id;?>"><?php echo $s->name;?></a>
						<?php else:?>
						<?php echo $s->name;?> ** inactive
						<?php endif;?>
					</td>
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
    
    private function render_series_detail(){
         $this->data;
         $threshold = 1;
         function filter_data($data,$threshold){
            
            
            foreach($data as $key=>$element){
                
                if($element->c>=$threshold){
                    $new[] = $element;
                }
            }
            
            
            return count($new);
         }
         while(filter_data($this->data,$threshold) > 20){
            $threshold = $threshold+1;
         }
         
         
         
        ?>
        <div class="wrap series-detail">
		<h1>Downloads for <?php echo $this->series->name;?></h1>
        
        <div style="float:left;padding-right:40px;">
			
			<h2><?php echo date("M d, Y",strtotime($this->start));?> - <?php echo date("M d, Y",strtotime($this->end));?></h2>
			<form action="" method = "get">
				<input type="hidden" name="page" value="<?php echo $_GET['page'];?>">
				<input type="hidden" name="series" value="<?php echo $_GET['series'];?>">
				<h4>select new range</h4>
				<div>
					<label for="from">From</label>
					<input type="text" id="from" name="from">
					<label for="to">to</label>
					<input type="text" id="to" name="to">
					<button id="submit">Go</button>
				</div>
			</form>
            <label for="threshold">Filter Posts Less Than </label><input id='threshold' value="<?php echo $threshold;?>" width="2">
            <div id="graph">
                
            </div>
        </div>
        <script>
         jQuery(document).ready(function(){
            
            jQuery("#threshold").spinner({
                    min:1 
                }).on( "spinstop", function( event, ui ) {
                    jQuery("#threshold").trigger("change");
                } );
            
            jQuery("#threshold").on("change",function(){
                
                var filtered_data = data.filter(function(datum){
                        return  datum.c >= document.getElementById("threshold").value;
                    });
                do_graph(filtered_data);
         })
            
            
         });
         
        // convert counts to integers.
        data.forEach(function(d) {
				d.c = +d.c;
        });
        
        
        var filtered_data = data.filter(function(datum){
            return  datum.c >= document.getElementById("threshold").value;
        
        });
       
        var do_graph = function(filtered_data){
            var margin = {top: 40, right: 20, bottom:350, left: 40},
			width = 960 - margin.left - margin.right,
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
        
        d3.select("svg").remove();
		// add the SVG element
		var svg = d3.select("#graph").append("svg")
			.attr("width", width + margin.left + margin.right)
			.attr("height", height + margin.top + margin.bottom)
		  .append("g")
			.attr("transform", 
				  "translate(" + margin.left + "," + margin.top + ")");
        
        // scale the range of the data
		  x.domain(filtered_data.map(function(d) { return d.post_title; }));
		  y.domain([0, d3.max(filtered_data, function(d) { return d.c; })]);
		  
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
			  .data(filtered_data)
			.enter().append("rect")
			  .attr("class", "bar")
			  .attr("x", function(d) { return x(d.post_title); })
			  .attr("width", x.rangeBand())
			  .attr("y", function(d) { return y(d.c); })
			  .attr("height", function(d) { return height - y(d.c); })
              .on("mouseover", function() { tooltip.style("display", null); })
			  .on("mouseout", function() { tooltip.style("display", "none"); })
			  .on("mousemove", function(d) {
				  var xPosition = d3.mouse(this)[0] - 25;
				  var yPosition = d3.mouse(this)[1] - 25;
				  tooltip.attr("transform", "translate(" + xPosition + "," + yPosition + ")");
				  tooltip.select("text").text(d.c);
			  });
              
              
      var tooltip = svg.append("g")
		.attr("class", "tooltip")
		.style("display", "none");
		  
	  tooltip.append("rect")
		.attr("width", 60)
		.attr("height", 20)
		.attr("fill", "white")
		.style("opacity", 0.75);
	
	  tooltip.append("text")
		.attr("x", 30)
		.attr("dy", "1.2em")
		.style("text-anchor", "middle")
		.attr("font-size", "12px")
		.attr("font-weight", "bold");  
        }
        
        do_graph(filtered_data);
        
        
        
               
		
        </script>
        <?php
    }
	// create new plugin for this
	function render_btrtop10(){
    
        $artist_post_totals = array();
        global $wpdb;

		$title = "Past 7 days";
		$is_range = !empty($_GET['from']) && !empty($_GET['to']);
		if($is_range){
			 $start = $_GET['from'];
			 $end = $_GET['to'];
			 $title = $_GET['from']." - ".$_GET['to'];
		}
        else{
            $end = date("Y-m-d",time());
            $start = date("Y-m-d", time() + 60*60*24*-7);
        }
        $sql = $wpdb->prepare("SELECT
                t.name, count(tr.object_id) as count
            FROM
                wp_terms t
            JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
            JOIN wp_term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN wp_posts p on tr.object_id = p.ID 
            WHERE
                tt.taxonomy = 'artist'
                AND 
                p.post_status = 'publish'
                AND 
                p.post_date>'%s'
                AND 
                p.post_date<'%s'
            group by 
                t.name
            order by count desc",$start, $end);
        
        $post_mentions = $wpdb->get_results($sql);
        
        foreach($post_mentions as $mention){
            
            $artist_post_totals[$mention->name] = array('name'=>$mention->name, 'post_mentions'=>$mention->count);
        }
        
		$sql = $wpdb->prepare("SELECT
                t.name,
                count(pm.meta_id) as count
            FROM
                wp_terms t
            JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
            JOIN wp_postmeta pm ON t.name = pm.meta_value
            JOIN wp_posts p ON pm.post_id = p.ID
            WHERE
                tt.taxonomy = 'artist'
            AND p.post_status = 'publish'
            AND p.post_date > '%s'
            AND p.post_date < '%s'
            AND pm.meta_key LIKE 'playlist_%_artist'
            AND t.name = pm.meta_value
            group by t.name
            ORDER BY
                count DESC
            ", $start, $end);
        $playlist_mentions = $wpdb->get_results($sql);
        
        foreach($playlist_mentions as $mention){
            $artist_post_totals[$mention->name]['playlist_mentions'] = $mention->count;
        }
		 ?>
		<div class="wrap topartists">
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
			 <?php foreach($artist_post_totals as $name=>$mentions):;
			 
			 ?>
				 <tr>
	 
					 <td><?php echo $name;?></td>
					 <td style="text-align:center"><?php echo $mentions['post_mentions'];?></td>
					 <td style="text-align:center"><?php echo $mentions['playlist_mentions'];?></td>
			 <?php endforeach;?>    
		 </table>
	 
	 </div>
		 <?php
	 }   

	public function total_requests_range($start, $end){
		global $wpdb;
		$sql = $wpdb->prepare("SELECT count(id) as total FROM s3logs WHERE request_time BETWEEN '%s' AND '%s'", $start, $end);
		$results = $wpdb->get_results($sql);
		return $results[0]->total;
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
    
    public function get_series_range_count_by_day(){
        
        $ranges = $this->get_data_range_list($this->start, $this->end, 7);
        
        // calculate totals for series within ranges
        $d = array();
        foreach($ranges as $range){
            // get total for range
            $d[] = $this->get_series_range_count_by_post($range, $this->series->term_id);
        }
        
        return $d;
    }
    
    public function get_data_range_list($start, $end, $count){
        
        // rewrite this to do DAYS
        
        $start_seconds = strtotime($start);
        $end_seconds = strtotime($end);
        
        $width = ($end_seconds -  $start_seconds) / $count;
        
        $ranges = array();
        for($i = $start_seconds; $i<$end_seconds; $i= $i+$width){
            $ranges[] = array(date('Y-m-d H:i:s',$i), date('Y-m-d H:i:s',$i+$width),date('Y-m-d H:i:s', $i+$width/2));
        }

        return $ranges; 
    
    }
    
    function get_series_range_count_by_post($range, $series){
    
        global $wpdb;
        
        if(is_object($series)){
            $series = $series->term_id;
        }
        
        $sql = "SELECT
                    post_id, count(post_id) c
                FROM
                    s3logs s
                JOIN file_series f ON f.request_key = s.request_key
                WHERE s.request_time BETWEEN '{$range[0]}'
                AND '{$range[1]}'
                AND f.series_id = '{$series}'
                GROUP BY post_id
                ORDER BY
                    c DESC";
    
        $result = $wpdb->get_results($sql);
        
        if(!empty($result)){
            
            return $result;
        }
        return;
    }
	
	function getPodcastReport($podcast){
		global $wpdb;
		
		$filename = basename($podcast->src);
		
		$sql = "SELECT COUNT(*) as total FROM s3logs WHERE request_key='{$filename}'";
		
		$report = new stdClass();
		$report->title = $podcast->post_title;
		$report->post_date = date("M d, Y",strtotime($podcast->post_date));
		$results = $wpdb->get_results($sql);
		
		$report->downloads = $wpdb->get_results($sql)[0]->total;
		
		return $report;
	}
	
	
	function getPodcastSeriesPostReport($series_id, $start, $end){
		global $wpdb;
		
		$sql = "SELECT
					COUNT(s3.id) as downloads, fs.post_id, fs.post_date
				FROM
					s3logs s3
				JOIN file_series fs ON s3.request_key = fs.request_key
				WHERE
					s3.request_time BETWEEN '{$start}'
				AND '{$end}'
				AND fs.series_id = '{$series_id}'
				GROUP BY
				fs.post_id
				ORDER BY fs.post_date DESC";
		
		$results = $wpdb->get_results($sql);
		
		$reports = [];
		foreach($results as $report){
			$post = postify(get_post($report->post_id));
			
			$report->title = $post->post_title;
			$report->post_date =date("m-d-Y",strtotime($post->post_date));
			$report->url = $post->permalink;
			$totalReport = $this->getPodcastReport($post);
			$report->total_downloads = $totalReport->downloads;
			
			$reports[] = $report;
			
		}
		
		return $reports;
	}
}


$btr_analytics = new BTRtoday_Analytics();