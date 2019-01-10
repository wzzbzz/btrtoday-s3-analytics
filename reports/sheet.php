<?php

// we are using wpdb for our queries at this point, for expedience sake.

require_once ('wp_load.php');

#google sheets api
require_once __DIR__ . '/vendor/autoload.php';


class SeriesReportSheet{
    public $spreadsheetId;
    public $title;
    public $seriesId;

    public $initialized;

    private $service;
    private $client;
    private $header_titles = [
                                "A1"=>"MONTH",
                                "B1"=>"ALL FILES",
                                "D1"=>"NEW THIS MONTH",
                                "G1"=>"AVG/NEW EPISODES",
                                "B2"=>"Total",
                                "C2"=>"Rank",
                                "D2"=>"Total",
                                "E2"=>"Rank",
                                "F2"=>"#/eps/mo",
                                "G2"=>"Total",
                                "H2"=>"Rank",
        
                                ];
        
    public function __construct($seriesId,$title=""){
        
        $this->seriesId = $seriesId;
        
        $series_sheet = $this->querySpreadsheet();
        
        $this->title = $series_sheet?$series_sheet->title:$title;
        $this->spreadsheetId = $series_sheet?$series_sheet->spreadsheetId:"";
        $this->initialized = $series_sheet?$series_sheet->initialized:0;

        $this->client = getClient();
        $this->service= new Google_Service_Sheets($this->client);
        
    }
    
    public function __destruct(){
        
    }
    
    public function mark_initialized(){
        global $wpdb;
        $sql = "UPDATE series_sheet SET intialized='1' WHERE seriesId='{$this->seriesId}'";
        $wpdb->query($sql);
    }
    
    public function querySpreadsheet(){
        global $wpdb;
        $sql = "SELECT * from series_sheet WHERE seriesId = '{$this->seriesId}'";
        
        $result = $wpdb->get_results($sql);
        
        if(empty($result)){
            return false;
        }
        else {
            return $result[0];
        }
        
    }
    
    public function getRowCount(){
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, "Sheet1");
        return count($response->values);
    }
    public function removeTopNRows($n=1){
        $start = 2;
        $end = $start+$n;
        $requests =[
            new Google_Service_Sheets_Request(
            [
                "deleteDimension"=>[
                "range"=>[
                     "dimension"=> "ROWS",
                     "startIndex"=> $start,
                     "endIndex"=> $end
                 ],
                ]
             ]
            )];
        
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);
    
        $result = $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
        
    }
    
    
    public function init(){
        
        if(empty($this->spreadsheetId)){
            $spreadsheetId = $this->createReportSpreadsheet($this->title);
            if(is_string($spreadsheetId)){
                $this->spreadsheetId = $spreadsheetId;
                $this->insertIndexRow();
                return true;
            }
            else{
                return false;
            }
        }
        
        return true;
        
    }
    
    private function insertIndexRow(){
        global $wpdb;
        $sql = "INSERT INTO series_sheet (
                    seriesId,
                    spreadsheetId,
                    title,
                    initialized
                 ) VALUES
                        (
                        '{$this->seriesId}',
                        '{$this->spreadsheetId}',
                        '{$this->title}',
                        {$this->initialized}
                 )";
        
        $wpdb->query($sql);
    }
    
    private function update($arg){
        
    }
    
    
    public function insertRow($row, $formats=[]){
        
        /* insert row at top of sheet */
        $requests =[
            new Google_Service_Sheets_Request(
            [
                "insertDimension"=>[
                "range"=>[
                     "dimension"=> "ROWS",
                     "startIndex"=> 2,
                     "endIndex"=> 3
                 ],
                "inheritFromBefore"=>false
                ]
             ]
            )];
        
        // reset defaults
        $requests[] =
            
                new Google_Service_Sheets_Request(
                [
                 'repeatCell' => [
                    'range' => [
                        'startRowIndex'=>2,
                        'endRowIndex'=>3,
                    ],
                    'cell' => [
                        'userEnteredFormat'=>[
                            'textFormat'=>[
                                'bold'=>false,
                                'italic'=>false
                                
                            ],
                            'horizontalAlignment' => "RIGHT",
                            'borders'=>[
                                        'bottom'=>[
                                                    'style'=>'SOLID',
                                                    'color'=>[
                                                              'red'=>51,
                                                              'green'=>51,
                                                              'blue'=>51,
                                                              
                                                              ]
                                         ]
                             ]
                        ]
                    ],
                    'fields' => 'userEnteredFormat.borders.bottom,userEnteredFormat.textFormat.bold,userEnteredFormat.textFormat.italic, userEnteredFormat.horizontalAlignment'
                ]
               ]);

                    
        if(!empty($formats)){
            
            foreach($formats as $format){
                switch($format){
                    case "borderBottom":
                        $requests[] =
                        new Google_Service_Sheets_Request(
                        [
                         'repeatCell' => [
                            'range' => [
                                'startRowIndex'=>2,
                                'endRowIndex'=>3,
                            ],
                            'cell' => [
                                'userEnteredFormat'=>[
                                    'borders'=>[
                                                'bottom'=>[
                                                            'style'=>'SOLID',
                                                            'color'=>[
                                                              'red'=>255,
                                                              'green'=>255,
                                                              'blue'=>255,
                                                              'alpha'=>0.5
                                                              ]
                                                 ]
                                     ]
                                ]
                            ],
                            'fields' => 'userEnteredFormat.borders.bottom'
                        ]
                       ]);
                   break;
                   case "bold":
                        $requests[] =
                        new Google_Service_Sheets_Request(
                        [
                         'repeatCell' => [
                            'range' => [
                                'startRowIndex'=>2,
                                'endRowIndex'=>3,
                            ],
                            'cell' => [
                                'userEnteredFormat'=>[
                                    'textFormat'=>[
                                       'bold'=>true
                                     ]
                                ]
                            ],
                            'fields' => 'userEnteredFormat.textFormat.bold'
                        ]
                       ]);
                   break;
                case "italic":
                        $requests[] =
                        new Google_Service_Sheets_Request(
                        [
                         'repeatCell' => [
                            'range' => [
                                'startRowIndex'=>2,
                                'endRowIndex'=>3,
                            ],
                            'cell' => [
                                'userEnteredFormat'=>[
                                    'textFormat'=>[
                                       'italic'=>true
                                     ]
                                ]
                            ],
                            'fields' => 'userEnteredFormat.textFormat.italic'
                        ]
                       ]);
                   break;
                   
                }
            }
        }
        else{
            /* reset default settings */
            
           
        }

        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);
    
        $result = $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
    
    
        /* batchUpdate new Row into sheet. */
        $valueInputOption = "RAW";
        $params = [
            'valueInputOption' => $valueInputOption
        ];

        $data = [
            new Google_Service_Sheets_ValueRange([
                'range' => "A3",
                'values' => [[$row->interval->label]]
                ]),
            new Google_Service_Sheets_ValueRange([
                'range' => "B3",
                'values' => [[$row->total_downloads]]
                ]),
            new Google_Service_Sheets_ValueRange([
                'range' => "C3",
                'values' => [[$row->total_rank]]
                ]),
            new Google_Service_Sheets_ValueRange([
                'range' => "D3",
                'values' => [[$row->monthly_downloads]]
                ]),
            new Google_Service_Sheets_ValueRange([
                'range' => "E3",
                'values' => [[$row->monthly_rank]]
                ]),
            new Google_Service_Sheets_ValueRange([
                'range' => "F3",
                'values' => [[$row->monthly_episodes]]
                ]),
            new Google_Service_Sheets_ValueRange([
                'range' => "G3",
                'values' => [[$row->average_downloads]]
                ]),
            new Google_Service_Sheets_ValueRange([
                'range' => "H3",
                'values' => [[$row->average_rank]]
                ]),
        ];
            
        
        
        $body = new Google_Service_Sheets_BatchUpdateValuesRequest([
            'valueInputOption' => $valueInputOption,
            'data' => $data
        ]);
        $result = $this->service->spreadsheets_values->batchUpdate($this->spreadsheetId, $body);
        
    }
    
    public function createReportSpreadsheet($title){
       
       $spreadsheet = new Google_Service_Sheets_Spreadsheet([
            'properties' => [
                'title' => $title
            ]
        ]);
       
        $result = $this->service->spreadsheets->create($spreadsheet, [
            'fields' => 'spreadsheetId'
        ]);
        
        $spreadsheetId = $result->spreadsheetId;
        /*
         *
         *  fill in title rows
         *
         */  
        
        
        $valueInputOption = "RAW";
        
        $data = [];
        foreach($this->header_titles as $cell=>$value){
            $values = [[$value]];
            $data[] = new Google_Service_Sheets_ValueRange([
                'range' => $cell,
                'values' => $values
            ]);
            
            
        }
        
        $body = new Google_Service_Sheets_BatchUpdateValuesRequest([
            'valueInputOption' => $valueInputOption,
            'data' => $data
        ]);
        $result = $this->service->spreadsheets_values->batchUpdate($spreadsheetId, $body);

        
        /*
         *
         *  Format Title Rows
         *
         */
        $requests =  [
    
    
                /*
                 * Top Row
                 * Font Size:  9
                 * Bold
                 * Italics
                 */
              new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'endRowIndex'=>1
                        ],
                        'cell' => [
                            'userEnteredFormat' =>[
                                'textFormat'=> [
                                    'bold'=>true,
                                    'italic'=>true,
                                    'fontSize'=>9
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.textFormat.bold,userEnteredFormat.textFormat.italic,userEnteredFormat.textFormat.fontSize'
                    ]
                ]),
              
                /*
                 * Second Row
                 * Bold
                 * Font Size:  10 (Default)
                 * 
                 */
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>1,
                            'endRowIndex'=>2
                        ],
                        'cell' => [
                            'userEnteredFormat' =>[
                                'textFormat'=> [
                                    'bold'=>true,
                                ],
                                'horizontalAlignment' => "RIGHT"
                            ]
                        ],
                        'fields' => 'userEnteredFormat(textFormat.bold,horizontalAlignment)'
                    ]
                ]),
                
                /*
                 *
                 *  Rank Cells
                 *  Font Size:  8
                 *
                 */ 
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>1,
                            'endRowIndex'=>2,
                            'startColumnIndex'=>2,
                            'endColumnIndex'=>3
                        ],
                        'cell' => [
                            'userEnteredFormat' =>[
                                'textFormat'=> [
                                    'fontSize'=>8,
                                    
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.textFormat.fontSize'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>1,
                            'endRowIndex'=>2,
                            'startColumnIndex'=>4,
                            'endColumnIndex'=>5
                        ],
                        'cell' => [
                            'userEnteredFormat' =>[
                                'textFormat'=> [
                                    'fontSize'=>8,
                                    
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.textFormat.fontSize'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>1,
                            'endRowIndex'=>2,
                            'startColumnIndex'=>7,
                            'endColumnIndex'=>8
                        ],
                        'cell' => [
                            'userEnteredFormat' =>[
                                'textFormat'=> [
                                    'fontSize'=>8,
                                    
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.textFormat.fontSize'
                    ]
                ]),
                
                /* Cell background Colors */
                /* Note:  RGB values have to be entered 255 - Value Returned.
                /* WHY? */ 
                /* Month:  light grey 1 */
               
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'endRowIndex'=>2,
                            'endColumnIndex'=>1
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>38,
                                    'green'=>38,
                                    'blue'=>38,
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                 new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>2,
                            'endColumnIndex'=>1
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>16,
                                    'green'=>16,
                                    'blue'=>16,
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                /* all files */               
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'endRowIndex'=>2,
                            'startColumnIndex'=>1,
                            'endColumnIndex'=>3
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>43,
                                    'green'=>88,
                                    'blue'=>66,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>2,
                            'startColumnIndex'=>1,
                            'endColumnIndex'=>3
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>22,
                                    'green'=>46,
                                    'blue'=>35,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                
                 /* new this month */               
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'endRowIndex'=>2,
                            'startColumnIndex'=>3,
                            'endColumnIndex'=>5
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>85,
                                    'green'=>57,
                                    'blue'=>25,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>2,
                            'startColumnIndex'=>3,
                            'endColumnIndex'=>5
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>43,
                                    'green'=>29,
                                    'blue'=>13,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                 /* all files */               
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'endRowIndex'=>2,
                            'startColumnIndex'=>5,
                            'endColumnIndex'=>6
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>72,
                                    'green'=>41,
                                    'blue'=>85,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>2,
                            'startColumnIndex'=>5,
                            'endColumnIndex'=>6
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>38,
                                    'green'=>21,
                                    'blue'=>43,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                 /* all files */               
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'endRowIndex'=>2,
                            'startColumnIndex'=>6,
                            'endColumnIndex'=>8
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>7,
                                    'green'=>53,
                                    'blue'=>96,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>2,
                            'startColumnIndex'=>6,
                            'endColumnIndex'=>8
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'backgroundColor' =>[
                                    'red'=>4,
                                    'green'=>26,
                                    'blue'=>49,
                                    'alpha'=>0
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]),
                
                
                /* row / column borders */
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startRowIndex'=>1,
                            'endRowIndex'=>2,
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'borders'=>[
                                            'bottom'=>[
                                                        'style'=>'SOLID'
                                             ]
                                 ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.borders.bottom'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startColumnIndex'=>0,
                            'endColumnIndex'=>1,
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'borders'=>[
                                            'right'=>[
                                                        'style'=>'SOLID'
                                             ]
                                 ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.borders.right'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startColumnIndex'=>2,
                            'endColumnIndex'=>3,
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'borders'=>[
                                            'right'=>[
                                                        'style'=>'SOLID'
                                             ]
                                 ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.borders.right'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startColumnIndex'=>4,
                            'endColumnIndex'=>5,
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'borders'=>[
                                            'right'=>[
                                                        'style'=>'SOLID'
                                             ]
                                 ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.borders.right'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startColumnIndex'=>5,
                            'endColumnIndex'=>6,
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'borders'=>[
                                            'right'=>[
                                                        'style'=>'SOLID'
                                             ]
                                 ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.borders.right'
                    ]
                ]),
                
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'startColumnIndex'=>7,
                            'endColumnIndex'=>8,
                        ],
                        'cell' => [
                            'userEnteredFormat'=>[
                                'borders'=>[
                                            'right'=>[
                                                        'style'=>'SOLID'
                                             ]
                                 ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.borders.right'
                    ]
                ]),
                
                
                
                new Google_Service_Sheets_Request([
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId'=>0,
                            'title'=>'the sheet',
                            'gridProperties'=>[
                                'columnCount'=>8,
                                'frozenRowCount'=>2
                            ]
                        ],
                        'fields' => 'gridProperties.frozenRowCount, gridProperties.columnCount'
                    ]
                ]),
                
              ];


        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        $result = $this->service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        
        
        return $spreadsheetId;
       
    }
    
    
    public function share($email){
        $client = getFileClient();
        $driveService = new Google_Service_Drive($client);
        $driveService->getClient()->setUseBatch(true);
        try {
            $batch = $driveService->createBatch();
        
            $userPermission = new Google_Service_Drive_Permission(array(
                'type' => 'user',
                'role' => 'writer',
                'emailAddress' => $email
            ));
             $request = $driveService->permissions->create(
                $this->spreadsheetId, $userPermission, array('fields' => 'id'));
            $batch->add($request, 'user');
            $results = $batch->execute();
        
            foreach ($results as $result) {
                if ($result instanceof Google_Service_Exception) {
                    // Handle error
                    printf($result);
                } else {
                    printf("Permission ID: %s\n", $result->id);
                }
            }
        } catch (Exception $e){
        }
     
    }
  
}





function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('BTRtoday Analytics');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

function getFileClient()
{
    $client = new Google_Client();
    $client->setApplicationName('BTRtoday Analytics');
    $client->setScopes(Google_Service_Drive::DRIVE_FILE);
    $client->setAuthConfig('BTRtoday Analytics-f4defa1ce798.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token-drive.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

