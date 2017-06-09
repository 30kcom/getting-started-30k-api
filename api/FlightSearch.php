<?php

require('../../lib/EasyRequest.php');
require('FlightResults.php');
require('Helper.php');

class FlightSearch{
    
    public function __construct(){
        
    }
    
    public function getAvailableSearches(){
        
        $client = $this->_createHttpClient('/api/searches');
        $this->_sendHttpRequest($client, array($this, '_getAvailableSearchesSuccess'), array($this, '_getAvailableSearchesFailure'));
        
    }
    
    public function getFlightResults(){
        
        if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
            
            $this->_returnFailure(self::SEARCH_ID_FAILURE_MESSAGE);
            
        }else{
            
            $client = $this->_createHttpClient('/api/searches');
            $client->withQuery('id', $_GET['id']);
            $this->_sendHttpRequest($client, array($this, '_getFlightResultsSuccess'), array($this, '_getFlightResultsFailure'));
            
        }
        
    }
    
    protected function _getFlightResultsSuccess($response){
        
        if(is_array($response) && is_array($response['flights'])){
            
            if(count($response['flights']) <= 0){
                
                $this->_returnFailure(self::NO_FLIGHTS_FAILURE_MESSAGE);
                
            }else{
                
                $results = new FlightResults($response);
                $html = $results->toHtml();
                $this->_returnSuccess($html);
                
            }
            
        }else{
            
            $this->_getFlightResultsFailure($response);
            
        }
        
    }
    
    protected function _getFlightResultsFailure($response = null){
        
        $this->_returnFailure(self::RESULTS_FAILURE_MESSAGE);
        
    }
    
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
            
            if(!is_array($search['legs']) || count($search['legs']) <= 0) continue;
            else if(count($search['legs']) == 1){
                
                $firstLeg = $search['legs'][0];
                $deptDate = date_create($firstLeg['deptDate']);
                $r['name'] = 'One way ' . $firstLeg['deptCode'] . ' ' . $arr . ' ' . $firstLeg['destCode'] . $on . date_format($deptDate, 'j M');
                
            }else if(count($search['legs']) == 2 && $search['legs'][0]['deptCode'] == $search['legs'][1]['destCode']){
                
                $arr = '↔';
                $firstLeg = $search['legs'][0];
                $secondLeg = $search['legs'][1];
                $deptDate = date_create($firstLeg['deptDate']);
                $destDate = date_create($secondLeg['deptDate']);
                $r['name'] = 'Round trip ' . $firstLeg['deptCode'] . ' ' . $arr . ' ' . $firstLeg['destCode'] . $on . date_format($deptDate, 'j M') . $ret . date_format($destDate, 'j M');
                
            }else{
                
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
    
    protected function _getAvailableSearchesSuccess($response){
        
        if(is_array($response) && count($response) > 0){
            
            $searches = $this->_formatAvailableSearches($response);
            $this->_returnSuccess($searches);
            
        }else{
            
            $this->_getAvailableSearchesFailure($response);
            
        }
        
    }
    
    protected function _getAvailableSearchesFailure($response = null){
        
        $this->_returnFailure(self::SEARCHES_FAILURE_MESSAGE);
        
    }
    
    protected function _returnFailure($message = null){
        
        if(!$message) $message = self::DEFAULT_FAILURE_MESSAGE;
        
        $this->_return(array(
            'success' => false,
            'message' => $message
        ));
        
    }
    
    protected function _returnSuccess($data){
        
        $this->_return(array(
            'success' => true,
            'content' => $data
        ));
        
    }
    
    protected function _return($data){
        
        header('Content-Type: application/json');
        die(json_encode($data));
        
    }
    
    protected function _sendHttpRequest($client, $successFunc = null, $failureFunc = null){
        
        $client->send();
        
        $body = $client->getResponseBody();
        if($body) $body = json_decode($client->getResponseBody(), true);
        
        if($client->getResponseStatus() < 400){
            
            if(!is_array($body) || $body['Success'] != 1 || !isset($body['Value'])){
                
                if(is_callable($failureFunc)) call_user_func($failureFunc, $body);
                
            }else{
                
                call_user_func($successFunc, $body['Value']);
                
            }
            
        }else{
            
            if(is_callable($failureFunc)) call_user_func($failureFunc, $body);
            
        }
        
    }
    
    protected function _createHttpClient($endpoint){
        
        $client = EasyRequest::create(self::API_BASE_URL . $endpoint);
        
        $client
            ->withAuth(self::API_USERNAME . ':' . self::API_PASSWORD)
            ->withHeader(self::$_DEFAULT_HEADERS)
            ->withTimeout(self::REQUEST_TIMEOUT);
        
        return $client;
        
    }
    
    protected static $_DEFAULT_HEADERS = array(
        'Accept-Language' => 'en-US,en;q=1',
        'Accept' => 'application/hal+json;q=1, application/json;q=0.8',
        'Content-Type'=> 'application/json;charset=UTF-8'
    );
    
    const DEFAULT_FAILURE_MESSAGE = 'Unknown processing error.';
    const SEARCHES_FAILURE_MESSAGE = 'Could not load collection of flight searches.';
    const SEARCH_ID_FAILURE_MESSAGE = 'Specified search ID is invalid or unspecified.';
    const RESULTS_FAILURE_MESSAGE = 'Could not load flight search results.';
    const NO_FLIGHTS_FAILURE_MESSAGE = 'There are no flights for this search. Please try a different one.';
    
    const REQUEST_TIMEOUT = 120000;
    
    const API_BASE_URL = 'https://qpx-dev.30k.com';
    const API_USERNAME = 'showcasetool.dev';
    const API_PASSWORD = 'b4a27326b4c2';
    
}