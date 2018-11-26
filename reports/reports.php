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
    
    public function run(){

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
            
        
    private function doRankings($interval, $field){
        global $wpdb;
        
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
            
            $this->totalDownloadsReport();
            $this->monthlyDownloadsReport();
            $this->monthlyEpisodesReport();
            $this->calculateAverages();
            $this->insert();
            
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