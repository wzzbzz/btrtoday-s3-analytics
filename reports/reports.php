<?php

#sheet wrapper
require __DIR__ . '/sheet.php';



/*
 *  SeriesAnalyticsReports
 *
 *  handles overall reporting functionality for the entire collection of reports.
 *  
 */
class SeriesAnalyticsReports{

    public $reports = [];

    public function __construct(){

        
    }
    public function __destruct(){}
    
	public function fix(){
		
		$podcasts = get_podcast_series();
		
		echo "\n";
/*		foreach($podcasts as $podcast){
			
			echo "fixing {$podcast->name}\n";

			$sheet = new SeriesReportSheet($podcast->term_id, $podcast->name);
                        if( !( $sheet->initialized == 1 ) ){
                                echo "\tinitializing sheet:  {$podcast->name}\n";
                                $sheet->init();
                                $sheet->mark_initialized();
                        }


			$rows = $sheet->getRowCount();
			echo "\t{$rows} found\n";
			$over = $rows - 27;
			if($over>0){
				echo "\tdeleting top $over rows for {$podcast->name}\n";
				$sheet->removeTopNRows($over);
			}
			else{
				echo "\tNo rows deleted\n";
			}
			
			
		
		}
		
*/		
		
//		$interval = $this->getInterval("01","2019");
		
//		$this->runInterval($interval);
		
//		$interval = $this->getInterval("02","2019");
		
//		$this->runInterval($interval);
		
//		$interval = $this->getInterval("03","2019");
//		$this->runInterval($interval);
	
//                $interval = $this->getInterval("04","2019");
//                $this->runInterval($interval);

		$podcasts = get_podcast_series();
		
		$q = "Q1 2019";

		$this->doQuarterlyRankings($q,'average');
		$this->doQuarterlyRankings($q,'monthly');
		$this->doQuarterlyRankings($q, 'total');

		foreach($podcasts as $pod){
			$r = new SeriesAnalyticsQuarterlyReport($pod->term_id, $q);
		 	if(!$r->hasBeenRun()){
				$r->run();
			}
		
			$r->loadFromQuery();

			$sheet = new SeriesReportSheet($pod->term_id, $pod->name);
                        if( !( $sheet->initialized == 1 ) ){
                                echo "\tinitializing sheet:  {$podcast->name}\n";
                                $sheet->init();
                                $sheet->mark_initialized();
                        }
			$sheet->updateExistingRow(4,$r);
		}

		echo "\n";
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
						
						$formats = ['borderBottom','bold', 'italic'];

						$sheet->insertRow($report, $formats);
						echo "\t$q inserted\n";
					}
				}
				
				sleep(10);    
			
		}
		
			
	}
	
	public function getInterval($month=null, $year=null){
		
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
	
    public function run(){
		
		echo "\n";
		echo "Running BTRtoday Monthly Google Sheets Update\n";
		
        
		$this->updateSheets();
		die;
		$this->doRankings();
		$this->doQuarterlyRankings();
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
                $intervals = $this->getIntervals($podcast->term_id);
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
		$interval = $this->getInterval( date( "m" , strtotime( $parts[0] ." " . $parts[1] ) ), $parts[1] ) ;
		return $interval;
		
	}
	
	// compare two intervals.
	// -1, interval1 < interval2
	// 0 interval1 == interval2
	// 1 interval1 > interval2
	private function interval_compare( $interval1 , $interval2 ){
		
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
	
	
	private function increment_interval( $interval ){
		$interval->month++;
		if( $interval->month > 12 ){
			$interval->month = 1;
			$interval->year++;
		}
		
		$interval = $this->getInterval( $interval->month, $interval->year);
		
		return $interval;
	}
	
    private function updateSheets(){

		// update podcast data
		$podcasts = get_podcast_series();
		foreach($podcasts as $podcast){
			echo $podcast->name."\n";
			
			try{
				$sheet = new SeriesReportSheet($podcast->term_id, $podcast->name);
			}
			catch(Google_Service_Exception $e){
				var_dump($e);
				die;
			}
			
			// find out where we left off.
			$last_sheet_interval = $this->intervalFromLabel( $sheet->getLatestInterval() );
			sleep(1);
			
			$current_interval = $this->increment_interval($last_sheet_interval);
			
			$last_interval = $this->getInterval();
			while( $this->interval_compare( $current_interval , $last_interval ) < 1 ){
				$this->runPodcastInterval( $podcast , $current_interval );
				$current_interval = $this->increment_interval( $current_interval );
			}
		}
		
		// update rankings
		$current_interval = $this->increment_interval($last_sheet_interval);
		while( $this->interval_compare( $current_interval , $last_interval ) < 1 ){
			$this->doRankings($current_interval , "total");
			$this->doRankings($current_interval , "monthly");
			$this->doRankings($current_interval , "average");
			$current_interval = $this->increment_interval( $current_interval );
			
			if(!( $current_interval->month <=3 && $current_interval->year != 2017 ) && ( $current_interval->month%3==0 ) ){
				$q = monthToQuarter( $current_interval->month ) . " " . $current_interval->year;
				
				$this->doQuarterlyRankings( $q , 'average' );
				$this->doQuarterlyRankings( $q , 'monthly' );
				$this->doQuarterlyRankings( $q , 'total' );
				
			}
		
		
		}
		
		// update spreadsheets
		foreach($podcasts as $podcast){
			echo "updating {$podcqst->name}	sheet\n";
			$current_interval = $this->increment_interval($last_sheet_interval);
			var_dump($this->interval_compare( $current_interval , $last_interval ) < 1 );
			die;
			while( $this->interval_compare( $current_interval , $last_interval ) < 1 ){
				
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
				$current_interval = $this->increment_interval( $current_interval );
			}
		}
		
	}
	
    private function doRankings($interval, $field){
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
        $sql = "SELECT * from series_quarterly_reports WHERE label='{$q}' ORDER BY {$field}_downloads DESC";
        $rankings = $wpdb->get_results($sql);
        
        
        foreach($rankings as $i=>$rowinfo){
                $rank=$i+1;
                $sql = "UPDATE series_quarterly_reports SET {$field}_rank='$rank' WHERE ID='{$rowinfo->ID}'";
                $wpdb->query($sql);
            }
            
        return;
        
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
    public $monthly_downloads;
    public $monthly_rank;
    public $monthly_episodes;
    public $average_downloads;
    public $average_rank;
    
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
