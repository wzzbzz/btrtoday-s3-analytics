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

/* btr daily dashboard */
add_action('wp_dashboard_setup', 'add_daily_podcast_downloads_meta_box');
 
function add_daily_podcast_downloads_meta_box() {
	global $wp_meta_boxes;
	$date = date("m/d/Y",time() - 86400);
	wp_add_dashboard_widget('custom_help_widget', 'Top 10 Downloads for ' . $date, 'btrtoday_daily_podcast_activity');
}

function btrtoday_daily_podcast_activity() {
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
    wp_enqueue_script('sortable', plugin_dir_url( __FILE__ ) .'js/sorttable.js');
    
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


// menu page
add_action('admin_menu', 'add_btrtop10_page');
function add_btrtop10_page(){
    add_submenu_page ( "tools.php", "BTR Top 10", "BTR Top Artists", "manage_options", "btrtoday_top10", "render_btrtop10" );
}


function by_subarray_count($a,$b){
    if(count($a["post_count"]) == count($b["post_count"]))
        return 0;
    return count($a["post_count"])<count($b["post_count"])?1:-1;
}

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
