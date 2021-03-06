<?php

#sheet wrapper
require __DIR__ . '/sheet.php';





class NetworkAnalyticsReports{
	public function __construct(){}
	public function __destruct(){}
	
}

/*
 *  SeriesAnalyticsReports
 *
 *  handles overall reporting functionality for the entire collection of reports.
 *  
 */
class SeriesAnalyticsReports{

    public $reports = [];
	public $first_interval;

    public function __construct(){
		$this->setFirstInterval();
    }
	
    public function __destruct(){}
    
	public function setFirstInterval(){
		
		$sql = "SELECT MIN(request_time) as start_date FROM s3logs";
		global $wpdb;
		$results = $wpdb->get_results($sql);
		$date = $results[0]->start_date;
		$this->first_interval = dateToInterval($date);
		
	}
	
	// for one-off fixes.
	public function fix(){
		$reports = array();
		$podcasts = get_podcast_series();
		foreach($podcasts as $podcast){
			$current = get_interval();
			
			$report = new SeriesAnalyticsMonthlyReport($podcast->term_id, $current);
			if( !$report->hasBeenRun() ){
				$report->run();
			}
			$report->doDeltas();
			$reports[] = $report;
		}
		usort($reports, build_sorter("monthly_delta_year"));
		$monthly_delta_leaders = $reports;
		foreach($monthly_delta_leaders as $i=>$leader){
			$series = get_term($leader->series_id, 'podcast-series');
			echo $i+1 . ": " . $series->name . " " . $leader->total_delta_month."\n";
		}
		die;
	}
	
	public function runPodcastInterval($podcast, $interval){
		
		$report = new SeriesAnalyticsMonthlyReport($podcast->term_id, $interval);
            $report->run();
            
            # do quarterly stuff.
            if(!( $interval->month <=3 && $interval->year != 2017 ) && ( $interval->month%3==0 ) ){
				
                $quarter = monthToQuarter( $interval->month ) ." " . $interval->year;
                $report = new SeriesAnalyticsQuarterlyReport($podcast->term_id, $quarter);
                $report->run();
            }
			
			
	}
	
	public function runInterval($interval){
		
		$podcasts = get_podcast_series();
		
		foreach($podcasts as $podcast){
			
			$this->runPodcastInterval( $podcast, $interval );
           
		}
		
		echo "reports run\n";
		
		$this->doRankings($interval, "total");
		$this->doRankings($interval, "monthly");
		$this->doRankings($interval, "average");
		echo "rankings done\n";
			
		echo "updating sheets\n";
		foreach($podcasts as $podcast){

			# create sheet if not there.
			$sheet = new SeriesReportSheet($podcast->term_id, $podcast->name);
			if( !( $sheet->initialized == 1 ) ){
				echo "\tinitializing sheet:  {$podcast->name}\n";
				$sheet->init();
				$sheet->mark_initialized();
			}
			$rows = [];

				$formats=[];
				
				$report = new SeriesAnalyticsMonthlyReport($podcast->term_id, $interval);
				$report->loadFromQuery();
				$report->doDeltas();
				if($interval->month%3==1){
					$formats[]='borderBottom';
				}
				
				$sheet->insertRow($report, $formats);
				echo "\t$interval->label inserted\n";
				
				if($interval->month%3==0){
					if(!($interval->year==2017&&$interval->month==3)){
						$q = monthToQuarter($interval->month)." ".$interval->year;
						$report = new SeriesAnalyticsQuarterlyReport($podcast->term_id, $q);
						$report->loadFromQuery();
						$report->doDeltas();
						$formats = ['borderBottom','bold', 'italic'];

						$sheet->insertRow($report, $formats);
						echo "\t$q inserted\n";
					}
				}
				
				sleep(10);    
			
		}
			
	}
	
    public function run(){
		
		echo "\n";
		echo "Running BTRtoday Monthly Google Sheets Update\n";
		
        
		$this->updateReports();
		
		$this->doRankings();
		
		//$this->doQuarterlyRankings();
		$this->updateSheets();
		
		return;
        echo "Running Podcast Reports (".count($podcasts).")";
        foreach($podcasts as $i=>$podcast){
            echo "\t {$i}: {$podcast->name}\n";
			$this->updatePodcastSheet($podcast);
			$this->runInterval($interval);
            
        }
        
    }
    
    public function system_init()
    {
        global $wpdb;
        
        $podcasts = get_podcast_series();
        
        // run all reports;
        $intervals = null;
        foreach($podcasts as $podcast){
        
            if(empty($intervals)){
                $intervals = get_intervals($podcast->term_id);
            }
       
            foreach($intervals as $i=>$interval){
                
                $report = new SeriesAnalyticsMonthlyReport($podcast->term_id, $interval);
                $report->run();
                
                # do quarterly stuff.
                if(!( $interval->month <=3 && $interval->year != 2017 ) && ( $interval->month%3==0 ) ){
                    $quarter = monthToQuarter( $interval->month ) ." " . $interval->year;
                    $report = new SeriesAnalyticsQuarterlyReport($podcast->term_id, $quarter);
                    $report->run();
                } 
            }
            
            
            
            
        }
       
       # rankings complete:  update the rankings 
       foreach($intervals as $interval){
            #$this->doRankings($interval, "total");
            #$this->doRankings($interval, "monthly");
            #$this->doRankings($interval, "average");
        }
        
       
       
        # assemble reports for sheets.
        foreach($podcasts as $podcast){
            # create sheet if not there.
            $sheet = new SeriesReportSheet($podcast->term_id, $podcast->name);
            if( !( $sheet->initialized == 1 ) ){
                $sheet->init();
            }
            $rows = [];
            foreach($intervals as $interval){
                $formats=[];
                
                $report = new SeriesAnalyticsMonthlyReport($podcast->term_id, $interval);
                $report->loadFromQuery();

                if($interval->month%3==1){
                    $formats[]='borderBottom';
                }
                $sheet->insertRow($report, $formats);
                
                
                if($interval->month%3==0){
                    if(!($interval->year==2017&&$interval->month==3)){
                        $q = monthToQuarter($interval->month)." ".$interval->year;
                        $report = new SeriesAnalyticsQuarterlyReport($podcast->term_id, $q);
                        $report->loadFromQuery();
                        
                        $formats = ['borderBottom','bold', 'italic'];

                        $sheet->insertRow($report, $formats);
                    }
                }
                
                
                sleep(5);    
            }
            
            $sheet->mark_initialized();    
        }
        
        
            
    }
	
	// takes a string e.g. "Apr 2019" and returns an interval object
	// with month, year, and label members.
	private function intervalFromLabel($label){
		$parts = explode(" " ,$label);
		$interval = get_interval( date( "m" , strtotime( $parts[0] ." " . $parts[1] ) ), $parts[1] ) ;
		return $interval;
		
	}
	



    private function updateReports(){

		// update podcast data
		$podcasts = get_podcast_series();
		foreach($podcasts as $podcast){
			echo $podcast->name."\n";
			$this->updatePodcastReports( $podcast );
			
		}
		return;
		
		
		
	}
	
	private function updatePodcastReports($podcast){
		
		
		// find first interval get oldest post
		$sql = "SELECT
					post_date
				FROM wp_posts p
					JOIN wp_term_relationships tr on tr.object_id = p.ID
					JOIN wp_term_taxonomy tt on tt.term_taxonomy_id = tr.term_taxonomy_id
					JOIN wp_terms t on t.term_id = tt.term_id
				WHERE t.term_id = '{$podcast->term_id}'
				ORDER BY p.post_date ASC
				LIMIT 0,1";
				
		global $wpdb;
		$results = $wpdb->get_results($sql);
		
		$date = $results[0]->post_date;
		$month = date("m", strtotime($date));
		$year = date("Y", strtotime($date));
		$first_post_interval = get_interval( $month , $year );
		
		$cmp = interval_compare($first_post_interval, $this->first_interval);
		
		if($cmp<0){
			$current_interval = clone $this->first_interval;
		}
		else{
			$current_interval = $first_post_interval;
		}

		$last_interval = get_interval();
		
		while( interval_compare( $current_interval , $last_interval ) < 1 ){
			
			$this->runPodcastInterval( $podcast , $current_interval );
			$current_interval = increment_interval( $current_interval );
			
		}
		
	}
	
    private function doRankings(){
		
		$fields = array("total","monthly","average");
		$last_interval = get_interval();
		
		foreach($fields as $field){
			
			$interval = clone $this->first_interval;
			
			while ( interval_compare ($interval , $last_interval ) < 1 ){
					
				global $wpdb;
				echo "doing Rankings:  ".$interval->label. " " . $field . "\n";
				$sql = "SELECT * from series_monthly_reports WHERE label='$interval->label' ORDER BY {$field}_downloads DESC";
				$rankings = $wpdb->get_results($sql);
				
				
				foreach($rankings as $i=>$rowinfo){
						$rank=$i+1;
						$sql = "UPDATE series_monthly_reports SET {$field}_rank='$rank' WHERE ID='{$rowinfo->ID}'";
						$wpdb->query($sql);
					}
				
				
				#now do the quarterly ones. yes this implies refactor is necessary.
				$q = monthToQuarter($interval->month). " " .$interval->year;
				echo "doing Rankings {$q}:\n";
				$sql = "SELECT * from series_quarterly_reports WHERE label='{$q}' ORDER BY {$field}_downloads DESC";
				$rankings = $wpdb->get_results($sql);
				
				
				foreach($rankings as $i=>$rowinfo){
						$rank=$i+1;
						$sql = "UPDATE series_quarterly_reports SET {$field}_rank='$rank' WHERE ID='{$rowinfo->ID}'";
						$wpdb->query($sql);
					}
					
			
				$interval = increment_interval( $interval );
			}
		}
    }
   
    private function doQuarterlyRankings($q, $field){
			
	global $wpdb;

	if(!in_array($field, array('total', 'monthly', 'average'))){
		return false;
	
	}
	echo "Doing Quarterly Rankings: $q $field \n";
	$sql = "SELECT * from series_quarterly_reports WHERE label='{$q}' ORDER BY {$field}_downloads DESC";
        $rankings = $wpdb->get_results($sql);
        
        
        foreach($rankings as $i=>$rowinfo){
                $rank=$i+1;
                $sql = "UPDATE series_quarterly_reports SET {$field}_rank='$rank' WHERE ID='{$rowinfo->ID}'";
                $wpdb->query($sql);
            }
			
	}

	public function updateSheets(){
		
		$podcasts = get_podcast_series();
		
	// update spreadsheets
		foreach($podcasts as $podcast){
			try{
				$sheet = new SeriesReportSheet($podcast->term_id, $podcast->name);
			}
			catch(Google_Service_Exception $e){
				var_dump($e);
				die;
			}
			$last_sheet_interval = $this->intervalFromLabel( $sheet->getLatestInterval() );
			echo "updating {$podcast->name}	sheet\n";
			$current_interval = increment_interval($last_sheet_interval);
			$last_interval = get_interval();
			while( interval_compare( $current_interval , $last_interval ) < 1 ){
				
				# create sheet if not there.
				$sheet = new SeriesReportSheet($podcast->term_id, $podcast->name);
				
				if( !( $sheet->initialized == 1 ) ){
					echo "\tinitializing sheet:  {$podcast->name}\n";
					$sheet->init();
					$sheet->mark_initialized();
				}
				
				$rows = [];
				$formats=[];
				
				$report = new SeriesAnalyticsMonthlyReport($podcast->term_id, $current_interval);
				$report->loadFromQuery();

				if($current_interval->month%3==1){
					$formats[]='borderBottom';
				}
				
				$sheet->insertRow($report, $formats);
				echo "\t$current_interval->label inserted\n";
				
				if($current_interval->month%3==0){
					if(!($current_interval->year==2017&&$current_interval->month==3)){
						$q = monthToQuarter($current_interval->month)." ".$current_interval->year;
						$report = new SeriesAnalyticsQuarterlyReport($podcast->term_id, $q);
						$report->loadFromQuery();
						
						$formats = ['borderBottom','bold', 'italic'];

						$sheet->insertRow($report, $formats);
						echo "\t$q inserted\n";
					}
				}
				
								
				sleep(10);    
				$current_interval = increment_interval( $current_interval );
			}
		}
	}
		
    private function getIntervals($series_id){
        global $wpdb;
        
        $sql = "SELECT DISTINCT
                (
                    YEAR (request_time)
                ) as year,
                
                    MONTH(request_time) as month
                
                    FROM
                        s3logs s3
                    JOIN file_series fs ON fs.request_key = s3.request_key
                    WHERE
                        fs.series_id = '{$series_id}'";
        $results = $wpdb->get_results($sql);
        
        // get rid of current month, which is inherently incomplete.
        // if we run this exactly at midnight, this is a problem.  So, we'll run it on the 2nd?  =/
        $current_month = array_pop($results);
        
        foreach($results as $i=>$interval){
            $dateObj   = DateTime::createFromFormat('!m', $interval->month);
            $monthName = $dateObj->format('M'); // March
            $label = $monthName . " " . $interval->year;
            $results[$i]->label = $label;
        }
        
        
        return $results; 
    }
    
}

/*
 * class SeriesAnalyticsMonthlyReport
 * Handles DB Queries for Reports
 *
 */ 
class SeriesAnalyticsMonthlyReport{
    
    public $series_id;
    
    public $interval;
    public $total_downloads;
    public $total_rank;
	public $total_delta;
	public $total_month_delta_rank;
    public $monthly_downloads;
    public $monthly_rank;
	public $monthly_delta;
	public $monthly_delta_rank;
    public $monthly_episodes;
    public $average_downloads;
    public $average_rank;
	public $average_delta;
	public $average_delta_rank;
    
    public function __construct($series_id,$interval){
        $this->series_id = $series_id;
        $this->interval = $interval;

        if($this->hasBeenRun()){
            $this->loadFromQuery();
        }
        
    }
    public function __destruct(){}
    
    public function run(){

        if(!$this->hasBeenRun()){
            echo "\t\t Running Report for {$this->interval->label}\n";
            $this->totalDownloadsReport();
            $this->monthlyDownloadsReport();
            $this->monthlyEpisodesReport();
            $this->calculateAverages();
            $this->insert();
            
        }
		else{
			echo "\t\t {$this->interval->label} already run. \n";
		}
        
    }
    
    public function hasBeenRun(){
        global $wpdb;
		
        $sql = "SELECT * from series_monthly_reports WHERE series_id='{$this->series_id}' AND label='{$this->interval->label}'";
        
        $result= $wpdb->get_results($sql);
        return !empty($result);
    }
    
    public function totalDownloadsReport(){
        global $wpdb;
        
        $sql = "SELECT
                COUNT(s3.request_key) as total
            FROM
                s3logs s3
            JOIN file_series fs ON s3.request_key = fs.request_key
            WHERE
                MONTH (s3.request_time) = '{$this->interval->month}'
            AND YEAR (s3.request_time) = '{$this->interval->year}'
            AND fs.series_id = '{$this->series_id}'";
        
        $total = $wpdb->get_results($sql);
        
        $this->total_downloads = $total[0]->total;
    }
    
    public function monthlyDownloadsReport(){
        global $wpdb;
        
        $sql = "SELECT
                    COUNT(s3.request_key) as total
                FROM
                    s3logs s3
                JOIN file_series fs ON s3.request_key = fs.request_key
                WHERE
                    MONTH (s3.request_time) = '{$this->interval->month}'
                AND YEAR (s3.request_time) = '{$this->interval->year}'
                AND fs.series_id = '{$this->series_id}'
                AND MONTH(fs.post_date) = '{$this->interval->month}'
                AND YEAR(fs.post_date) = '{$this->interval->year}'";
        
        $total = $wpdb->get_results($sql);
        $this->monthly_downloads = $total[0]->total;
        
    }
    
    public function calculateAverages(){
        
        $this->average_downloads = round($this->monthly_downloads / $this->monthly_episodes);
        
    }
    public function monthlyEpisodesReport(){
        global $wpdb;
        
        $sql = "SELECT
                    COUNT(post_id) as episode_count
                FROM file_series
                WHERE
                    MONTH(post_date)='{$this->interval->month}'
                AND YEAR(post_date)='{$this->interval->year}'
                AND series_id='{$this->series_id}'";
                
        $this->monthly_episodes = $wpdb->get_results($sql)[0]->episode_count;
    }
    
    public function insert(){
        global $wpdb;

        $sql = "INSERT INTO series_monthly_reports (
                    series_id,
                    label,
                    total_downloads,
                    monthly_downloads,
                    monthly_episodes,
                    average_downloads
                )
                VALUES
                      (
                      '{$this->series_id}',
                      '{$this->interval->label}',
                      '{$this->total_downloads}',
                      '{$this->monthly_downloads}',
                      '{$this->monthly_episodes}',
                      '{$this->average_downloads}'
                )";
       
        $wpdb->query($sql);
    }
    
    public function loadFromQuery(){
        global $wpdb;
        $sql = "SELECT * from series_monthly_reports WHERE series_id='{$this->series_id}' AND label='{$this->interval->label}'";
        $results = $wpdb->get_results($sql);
        if(empty($results)){
            return false;
        }
        else{
            $this->total_downloads = $results[0]->total_downloads;
            $this->total_rank = $results[0]->total_rank;
            $this->monthly_downloads = $results[0]->monthly_downloads;
            $this->monthly_rank = $results[0]->monthly_rank;
            $this->monthly_episodes = $results[0]->monthly_episodes;
            $this->average_downloads = $results[0]->average_downloads;
            $this->average_rank = $results[0]->average_rank;
            return true;
        }
        
    }
	
	public function doDeltas(){
		if(empty($this->total_downloads)){
			$this->loadFromQuery();
		}
		
		$monthly_delta_interval = decrement_interval($this->interval);
		$monthly_delta_report = new SeriesAnalyticsMonthlyReport($this->series_id, $monthly_delta_interval);
		$monthly_delta_report->loadFromQuery();
		
		$yearly_delta_interval = decrement_interval($this->interval, 11);
		
		$yearly_delta_report = new SeriesAnalyticsMonthlyReport($this->series_id, $yearly_delta_interval);
		
		$this->total_delta_month = $this->doDelta($monthly_delta_report, "total_downloads");
		$this->total_delta_year = $this->doDelta($yearly_delta_report, "total_downloads");
		$this->monthly_delta_month = $this->doDelta($monthly_delta_report, "monthly_downloads");
		$this->monthly_delta_year = $this->doDelta($yearly_delta_report, "monthly_downloads");
		$this->average_delta_month = $this->doDelta($monthly_delta_report, "average_downloads");
		$this->average_delta_month_year = $this->doDelta($yearly_delta_report, "average_downloads");

		
	}
	
	public function doDelta($cmp_report, $field){
		
		if(empty($cmp_report->$field)){
			return false;
		}
		return ($this->$field /$cmp_report->$field);
	}
}



class SeriesAnalyticsQuarterlyReport{
    
    public $series_id;
    
    public $interval;
    public $quarter;
    public $total_downloads;
    public $total_rank;
    public $monthly_downloads;
    public $monthly_rank;
    public $monthly_episodes;
    public $average_downloads;
    public $average_rank;
    
    
     public function __construct($series_id,$quarter){
        
        $this->series_id = $series_id;
        $this->interval = new stdClass();
        $this->interval->label = $quarter;
        $reg = "/Q([0-9]) ([0-9][0-9][0-9][0-9])/";
        preg_match($reg,$quarter,$matches);
        $this->interval->year = $matches[2];
        $this->interval->quarter = $matches[1];
        $this->interval->month_start = ($this->interval->quarter*3-2);
        $this->interval->month_end = ($this->interval->quarter*3);
        
        if($this->hasBeenRun()){
			echo "\t\t Quarterly Report $quarter has been run\n";
            $this->loadFromQuery();
        }
        
    }
    
    
    public function hasBeenRun(){
        global $wpdb;
        $sql = "SELECT * from series_quarterly_reports WHERE series_id='{$this->series_id}' AND label='{$this->interval->label}'";
        $result= $wpdb->get_results($sql);
        return !empty($result);
    }
     public function run(){
        
        if(!$this->hasBeenRun()){
            echo $this->series_id.":".$this->interval->label."\n";
            $this->totalDownloadsReport();
            $this->monthlyDownloadsReport();
            $this->monthlyEpisodesReport();
            $this->calculateAverages();
            $this->insert();    
        }
		else {
			echo $this->series_id.":".$this->interval->label." has been run\n";
		}
        
    }
    
    public function totalDownloadsReport(){
        global $wpdb;
        
        $sql = "SELECT
                COUNT(s3.request_key) as total
            FROM
                s3logs s3
            JOIN file_series fs ON s3.request_key = fs.request_key
            WHERE
                MONTH (s3.request_time) >= '{$this->interval->month_start}'
            AND MONTH (s3.request_time) <= '{$this->interval->month_end}'
            AND YEAR (s3.request_time) = '{$this->interval->year}'
            AND fs.series_id = '{$this->series_id}'";
        
        $total = $wpdb->get_results($sql);
        
        $this->total_downloads = $total[0]->total;
    }
    
    public function monthlyDownloadsReport(){
        global $wpdb;
        
        $sql = "SELECT
                    COUNT(s3.request_key) as total
                FROM
                    s3logs s3
                JOIN file_series fs ON s3.request_key = fs.request_key
                WHERE
                   MONTH (s3.request_time) >= '{$this->interval->month_start}'
                AND MONTH (s3.request_time) <= '{$this->interval->month_end}'
                AND YEAR (s3.request_time) = '{$this->interval->year}'
                AND fs.series_id = '{$this->series_id}'
                AND MONTH(fs.post_date) >= '{$this->interval->month_start}'
                AND MONTH(fs.post_date) <= '{$this->interval->month_end}'
                AND YEAR(fs.post_date) = '{$this->interval->year}'";
        
        $total = $wpdb->get_results($sql);
        $this->monthly_downloads = $total[0]->total;
        
    }
    
    public function calculateAverages(){
        if(empty($this->monthly_episodes) || $this->monthly_episodes == 0 ){
			$this->average_downloads = 0;
		}
        $this->average_downloads = round($this->monthly_downloads / $this->monthly_episodes);
        
    }
    public function monthlyEpisodesReport(){
        global $wpdb;
        
        $sql = "SELECT
                    COUNT(post_id) as episode_count
                FROM file_series
                WHERE
                    MONTH (post_date) >= '{$this->interval->month_start}'
                AND MONTH (post_date) <= '{$this->interval->month_end}'
                AND YEAR(post_date)='{$this->interval->year}'
                AND series_id='{$this->series_id}'";
                
        $this->monthly_episodes = $wpdb->get_results($sql)[0]->episode_count;
    }
    
    public function insert(){
        global $wpdb;
        
        $sql = "INSERT INTO series_quarterly_reports (
                    series_id,
                    label,
                    total_downloads,
                    monthly_downloads,
                    monthly_episodes,
                    average_downloads
                )
                VALUES
                      (
                      '{$this->series_id}',
                      '{$this->interval->label}',
                      '{$this->total_downloads}',
                      '{$this->monthly_downloads}',
                      '{$this->monthly_episodes}',
                      '{$this->average_downloads}'
                )";
       
        $wpdb->query($sql);
    }
    
    public function loadFromQuery(){
        global $wpdb;
        $sql = "SELECT * from series_quarterly_reports WHERE series_id='{$this->series_id}' AND label='{$this->interval->label}'";
        
        $results = $wpdb->get_results($sql);
        if(empty($results)){
            return false;
        }
        else{
            $this->total_downloads = $results[0]->total_downloads;
            $this->total_rank = $results[0]->total_rank;
            $this->monthly_downloads = $results[0]->monthly_downloads;
            $this->monthly_rank = $results[0]->monthly_rank;
            $this->monthly_episodes = $results[0]->monthly_episodes;
            $this->average_downloads = $results[0]->average_downloads;
            $this->average_rank = $results[0]->average_rank;
            return true;
        }
        
    }
    
    
    
}    

/*
 * Convert a month number (1-12) to a Quarter (Q1-Q4)
 */ 

function monthToQuarter($month){
   $quarter = floor($month/4) + 1;
   
   return "Q".$quarter;
}

function dateToInterval($strdate){
	return get_interval( date( "m" , strtotime( $strdate ) ), date( "Y" , strtotime( $strdate ) ) );
}

function increment_interval( $interval, $steps=1 ){
	for( $i=0 ; $i < $steps ; $i++ ){
		$interval->month++;
		if( $interval->month > 12 ){
			$interval->month = 1;
			$interval->year++;
		}
	}
	$interval = get_interval( $interval->month, $interval->year);
	
	return $interval;
}

function decrement_interval( $interval , $steps = 1 ){
	
	for( $i = 0 ; $i < $steps ; $i++ ){
		$interval->month--;
		if( $interval->month < 1 ){
			$interval->month = 12;
			$interval->year--;
		}
	}
	$_interval = get_interval( $interval->month, $interval->year);

	return $_interval;
}

function get_interval($month=null, $year=null){
		
		$interval = new stdClass();
        $interval->month = $month?$month:(string) (date("m",time()) - 1);
        $interval->year = $year?$year:date("Y",time());
		
		/* detect year change / month reset, and adjust interval. */
		if($interval->month==0){
			$interval->month=12;
			$interval->year--;
		}
		
		##$text = date("F", strtotime("2001-" . $month . "-01"));
		
		$interval->label = date("M",strtotime("2001-".$interval->month."-01"))." ".$interval->year;
		
		return $interval;
		
	}


function build_sorter($key) {
    return function ($a, $b) use ($key) {
		if($a->$key === $b->$key){
			return 0;
		}
        else{
			return ($a->$key > $b->$key)?1:-1;
		}
	};

}

	// compare two intervals.
	// -1, interval1 < interval2
	// 0 interval1 == interval2
	// 1 interval1 > interval2
 function interval_compare( $interval1 , $interval2 ){
		
	if( $interval1->year == $interval2->year ){
		if( $interval1->month == $interval2->month ){
			return 0;
		}
		return ($interval1->month > $interval2->month)?1:-1;
	}
	else{
		return ( $interval1->year > $interval2->year)?1:-1;
	}
}

