<?php

class MilefyApiClient{
    
    public function __construct($flightResults){
        
        session_start();
        
        $this->_flightResults = $flightResults;
        $this->_helper = new Helper();
        
    }
    
    public function getFlights(){
        
        $client = $this->_createHttpClient('POST', '/api/miles/calculate');
        
        $body = $this->_getCalculateRequestBody();
        
        $client->withJson($body);
        $client->send();
        
        if($client->getResponseStatus() < 400){
            
            $response = json_decode($client->getResponseBody());
            
            if(isset($response) && $response->Success && $response->Value && is_array($response->Value->flights)){
                
                return $this->_getFlightsAwardMiles($response->Value->flights);
                
            }else{
                
                return false;
                
            }
            
        }else{
            
            return false;
            
        }
        
    }
    
    protected function _getFlightsAwardMiles($responseFlights){
        
        $flights = [];
        
        $programs = $this->getPrograms();
        
        if(!is_array($programs)) return false;
        
        foreach($responseFlights as $responseFlight){
            
            if(!is_array($responseFlight->programs) || count($responseFlight->programs) <= 0) continue;
            
            $flight = array(
                'flightId' => $responseFlight->flightId
            );
            
            $responseProgram = $responseFlight->programs[0];
            if(!is_array($responseProgram->earnings) || count($responseProgram->earnings) <= 0) continue;
            
            $responseAwardMiles = $this->_helper->find($responseProgram->earnings, self::AWARD_MILES_CODE, 'metricCode');
            if(!$responseAwardMiles) continue;
            
            $program = $this->_helper->find($programs, $responseProgram->programCode, 'programCode');
            if(!$program || !is_array($program->metrics)) continue;
            
            $awardMiles = $this->_helper->find($program->metrics, self::AWARD_MILES_CODE, 'metricCode');
            if(!$awardMiles) continue;
            
            $decimal = ($responseAwardMiles->value * 100) % 100;
            $precision = $decimal > 0 ? strlen(strval($decimal)) : 0;
            
            $flight['awardMilesValue'] = number_format($responseAwardMiles->value, $decimal);
            $flight['program'] = $program->programNameWithoutAirline;
            $flight['awardMilesName'] = $awardMiles->metricName;
            
            $flights[] = $flight;
            
        }
        
        return $flights;
        
    }
    
    public function getPrograms(){
        
        if(!isset($_SESSION['programs']) || !is_array($_SESSION['programs'])){
            
            $client = $this->_createHttpClient('GET', '/api/miles/programs');
            $client->send();
            
            if($client->getResponseStatus() < 400){
                
                $response = json_decode($client->getResponseBody());
                
                if(isset($response) && $response->Success && is_array($response->Value)){
                    
                    $_SESSION['programs'] = $response->Value;
                    
                }else{
                    
                    return false;
                    
                }
                
            }else{
                
                return false;
                
            }
               
        }
        
        return $_SESSION['programs'];
        
    }
    
    protected function _getCalculateRequestBody(){
        
        $body = array(
            'clientUserId' => $this->_getUserId(),
            'flights' => array()
        );
        
        foreach($this->_flightResults as $flight){
            
            $bodyFlight = array(
                'flightId' => $flight['flightId'],
                'price' => array(
                    'currencyCode' => $flight['price']['currencyCode'],
                    'total' => $flight['price']['total'],
                    'fare' => $flight['price']['fare'],
                    'taxes' => $flight['price']['taxes'],
                    'surcharges' => $flight['price']['surcharges']
                ),
                'legs' => array()
            );
            
            foreach($flight['legs'] as $leg){
                
                $bodyLeg = array(
                    'legId' => $leg['legId'],
                    'segments' => array()
                );
                
                foreach($leg['segments'] as $segment){
                    
                    $bodySegment = array(
                        'segmentId' => $segment['segmentId'],
                        'marketingAirlineCode' => $segment['marketingAirlineCode'],
                        'operatingAirlineCode' => $segment['operatingAirlineCode'],
                        'deptCode' => $segment['deptCode'],
                        'destCode' => $segment['destCode'],
                        'deptDate' => $segment['deptDate'],
                        'fareCode' => $segment['fareCode'],
                        'flightNumber' => $segment['flightNumber'],
                        'fareBasisCode' => $segment['fareBasisCode'],
                        'distance' => $segment['distance']
                    );
                    
                    $bodyLeg['segments'][] = $bodySegment;
                    
                }
                
                $bodyFlight['legs'][] = $bodyLeg;
                
            }
            
            $body['flights'][] = $bodyFlight;
            
        }
        
        return $body;
        
    }
    
    protected function _getUserId(){
        
        if(!isset($_COOKIE['userId'])) setcookie('userId', uniqid(), time() + 3600 * 24 * 365);
        return $_COOKIE['userId'];
        
    }
    
    protected function _createHttpClient($method, $endpoint){
        
        $client = EasyRequest::create(self::API_BASE_URL . $endpoint);
        
        $client
            ->withAuth(self::API_USERNAME . ':' . self::API_PASSWORD)
            ->withHeader(self::$_DEFAULT_HEADERS)
            ->withTimeout(self::REQUEST_TIMEOUT);
        
        return $client;
        
    }
    
    protected $_flightResults = null;
    protected $_helper = null;
    
    protected static $_DEFAULT_HEADERS = array(
        'Accept-Language' => 'en-US,en;q=1',
        'Accept' => 'application/hal+json;q=1, application/json;q=0.8',
        'Content-Type'=> 'application/json;charset=UTF-8',
        'X-Api-Version' => 'v2.8'
    );
    
    const AWARD_MILES_CODE = 1;
    
    const DEFAULT_FAILURE_MESSAGE = 'Unknown processing error.';
    
    const REQUEST_TIMEOUT = 120000;
    
    const API_BASE_URL = 'https://milefyapi-ext-dev.30k.com';
    const API_USERNAME = 'showcasetool.dev';
    const API_PASSWORD = 'b4a27326b4c2';
    
}