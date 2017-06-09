<?php
// Helper to build HTTP requests
require('../../lib/EasyRequest.php');

// Builds HTML structure of flight results
require('FlightResults.php');

// Simple helper
require('Helper.php');

/*
    
    Class responsible for fatching list of available 
    searches and producing flight search results.
    
*/

class FlightSearch{
    
    /* PUBLIC =================================================================*/
    
    public function __construct(){
        
        // enjoy the silence
        
    }
    
    /*
        Returns list of available sample searches as JSON
    */
    public function getAvailableSearches(){
        
        $client = $this->_createHttpClient('/api/searches');
        
        // request for list of searches
        $this->_sendHttpRequest($client, array($this, '_getAvailableSearchesSuccess'), array($this, '_getAvailableSearchesFailure'));
        
    }
    
    /*
        Returns flight search results as HTML
    */
    public function getFlightResults(){
        
        if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
            
            // failure
            $this->_returnFailure(self::SEARCH_ID_FAILURE_MESSAGE);
            
        }else{
            
            // success
            $client = $this->_createHttpClient('/api/searches');
            $client->withQuery('id', $_GET['id']);
            
            // request for flight search results
            $this->_sendHttpRequest($client, array($this, '_getFlightResultsSuccess'), array($this, '_getFlightResultsFailure'));
            
        }
        
    }
    
    /* PROTECTED ==============================================================*/
    
    /*
        Request with flight search 
        results finished with success
    */
    protected function _getFlightResultsSuccess($response){
        
        // response validation 
        if(is_array($response) && is_array($response['flights'])){
            
            if(count($response['flights']) <= 0){
                
                // failure
                $this->_returnFailure(self::NO_FLIGHTS_FAILURE_MESSAGE);
                
            }else{
                
                // success - transforming JSON response into HTML with ready flight results
                $results = new FlightResults($response);
                $html = $results->toHtml();
                $this->_returnSuccess($html);
                
            }
            
        }else{
            
            // failure
            $this->_getFlightResultsFailure($response);
            
        }
        
    }
    
    /*
        Request with flight search 
        results finished with failure
    */
    protected function _getFlightResultsFailure($response = null){
        
        // return error
        $this->_returnFailure(self::RESULTS_FAILURE_MESSAGE);
        
    }
    
    /*
        Transform searches itinerary into flat
        array with search name and id
    */
    protected function _formatAvailableSearches($searches){
        
        $result = array();
        $on = ' on ';
        $and = ' and ';
        $ret = ', return ';
        
        foreach($searches as $search){
            
            $r = array(
                'id' => $search['id'],
                'name' => ''
            );
            
            $arr = '→';
            
            if(!is_array($search['legs']) || count($search['legs']) <= 0){
                
                // invalid search
                
                continue;
                
            }else if(count($search['legs']) == 1){
                
                // one-way search
                
                $firstLeg = $search['legs'][0];
                $deptDate = date_create($firstLeg['deptDate']);
                $r['name'] = 'One way ' . $firstLeg['deptCode'] . ' ' . $arr . ' ' . $firstLeg['destCode'] . $on . date_format($deptDate, 'j M');
                
            }else if(count($search['legs']) == 2 && $search['legs'][0]['deptCode'] == $search['legs'][1]['destCode']){
                
                // round-trip search
                
                $arr = '↔';
                $firstLeg = $search['legs'][0];
                $secondLeg = $search['legs'][1];
                $deptDate = date_create($firstLeg['deptDate']);
                $destDate = date_create($secondLeg['deptDate']);
                $r['name'] = 'Round trip ' . $firstLeg['deptCode'] . ' ' . $arr . ' ' . $firstLeg['destCode'] . $on . date_format($deptDate, 'j M') . $ret . date_format($destDate, 'j M');
                
            }else{
                
                // multi-city search
                
                $r['name'] = 'Multi-city ';
                $dates = $on;
                $i = 0;
                
                foreach($search['legs'] as $leg){
                    
                    if($i == 0) $r['name'] .= $leg['deptCode'];
                    $r['name'] .= ' ' . $arr . ' ' . $leg['destCode'];
                    $dates .= date_format(date_create($leg['deptDate']), 'j M');
                    if(count($search['legs']) - 2 == $i) $dates .= $and;
                    else if(count($search['legs']) - 2 > $i) $dates .= ', ';
                    $i++;
                    
                }
                
                $r['name'] .= $dates;
                
            }
            
            array_push($result, $r);
            
        }
        
        return $result;
        
    }
    
    /*
        Processes list of available searches
    */
    protected function _getAvailableSearchesSuccess($response){
        
        // validation
        
        if(is_array($response) && count($response) > 0){
            
            // success
            $searches = $this->_formatAvailableSearches($response);
            $this->_returnSuccess($searches);
            
        }else{
            
            // failure    
            $this->_getAvailableSearchesFailure($response);
            
        }
        
    }
    
    /*
        Request for list of available flight 
        searches finished with error
    */
    protected function _getAvailableSearchesFailure($response = null){
        
        // return error
        $this->_returnFailure(self::SEARCHES_FAILURE_MESSAGE);
        
    }
    
    /*
        Generic method to return error on front-end
    */
    protected function _returnFailure($message = null){
        
        if(!$message) $message = self::DEFAULT_FAILURE_MESSAGE;
        
        $this->_return(array(
            'success' => false,
            'message' => $message
        ));
        
    }
    
    /*
        Generic method to return JSON when processing 
        finished with success
    */
    protected function _returnSuccess($data){
        
        $this->_return(array(
            'success' => true,
            'content' => $data
        ));
        
    }
    
    /*
        Return data, finish execution.
    */
    protected function _return($data){
        
        header('Content-Type: application/json');
        die(json_encode($data));
        
    }
    
    /*
        Method working similar to jQuery Deferred object returned by $.ajax method
    */
    protected function _sendHttpRequest($client, $successFunc = null, $failureFunc = null){
        
        // send request
        $client->send();
        
        // read response body
        $body = $client->getResponseBody();
        if($body) $body = json_decode($client->getResponseBody(), true);
        
        // validate response status
        if($client->getResponseStatus() < 400){
            
            // validate resposne body
            if(!is_array($body) || $body['Success'] != 1 || !isset($body['Value'])){
                
                // failure
                if(is_callable($failureFunc)) call_user_func($failureFunc, $body);
                
            }else{
                
                // success
                call_user_func($successFunc, $body['Value']);
                
            }
            
        }else{
            
            // failure
            if(is_callable($failureFunc)) call_user_func($failureFunc, $body);
            
        }
        
    }
    
    /*
        Creates HTTP client to fetch flight search results
        from 30K server
    */
    protected function _createHttpClient($endpoint){
        
        // create client
        $client = EasyRequest::create(self::API_BASE_URL . $endpoint);
        
        $client
            ->withAuth(self::API_USERNAME . ':' . self::API_PASSWORD)
            ->withHeader(self::$_DEFAULT_HEADERS)
            ->withTimeout(self::REQUEST_TIMEOUT);
        
        return $client;
        
    }
    
    // HTTP headers
    
    protected static $_DEFAULT_HEADERS = array(
        'Accept-Language' => 'en-US,en;q=1',
        'Accept' => 'application/hal+json;q=1, application/json;q=0.8',
        'Content-Type'=> 'application/json;charset=UTF-8'
    );
    
    // Error messages
    
    const DEFAULT_FAILURE_MESSAGE = 'Unknown processing error.';
    const SEARCHES_FAILURE_MESSAGE = 'Could not load collection of flight searches.';
    const SEARCH_ID_FAILURE_MESSAGE = 'Specified search ID is invalid or unspecified.';
    const RESULTS_FAILURE_MESSAGE = 'Could not load flight search results.';
    const NO_FLIGHTS_FAILURE_MESSAGE = 'There are no flights for this search. Please try a different one.';
    
    // Flight search result API settings
    
    const REQUEST_TIMEOUT = 120000;
    
    const API_BASE_URL = 'https://qpx-dev.30k.com';
    const API_USERNAME = 'showcasetool.dev';
    const API_PASSWORD = 'b4a27326b4c2';
    
}