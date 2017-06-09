<?php

/*
    
    Class responsible to communicate with
    Milefy API and formatting basic frequent flyer info.
    
*/
class MilefyApiClient{
    
    /* PUBLIC =================================================================*/
    
    /*
        Entry point
    */
    public function __construct($flightResults){
        
        session_start();
        
        // Read Milefy API credentials stored as environmental variables
        $this->_apiBaseUrl = getenv('MILEFY_BASE_URL');
        $this->_apiUsername = getenv('MILEFY_USERNAME');
        $this->_apiPassword = getenv('MILEFY_PASSWORD');
        
        // Store flight search results info
        $this->_flightResults = $flightResults;
        
        $this->_helper = new Helper();
        
    }
    
    /*
        Returns frequent flyer info for 
        flights specified in _flightResults array.
    */
    public function getFlights(){
        
        // creates HTTP client to communicate with Milefy API
        // CalculateMiles API method returns mileage earnings for specified list of flights
        $client = $this->_createHttpClient('POST', '/api/miles/calculate');
        
        // generates CalculateMiles request body
        $body = $this->_getCalculateRequestBody();
        
        // appends request body to the request
        $client->withJson($body);
        
        // sends CalculateMiles request to Milefy API
        $client->send();
        
        // response HTTP status code validation
        if($client->getResponseStatus() < 400){
            
            $response = json_decode($client->getResponseBody());
            
            // response body validation
            if(isset($response) && $response->Success && $response->Value && is_array($response->Value->flights)){
                
                // success
                
                // returns award miles for calculated flights
                return $this->_getFlightsAwardMiles($response->Value->flights);
                
            }else{
                
                // failure
                
                return false;
                
            }
            
        }else{
            
            // failure
            
            return false;
            
        }
        
    }
    
    /* PROTECTED ==============================================================*/
    
    /*
        Returns number of earned award miles for
        all flights in Milefy API CalculateMiles method response.
    */
    protected function _getFlightsAwardMiles($responseFlights){
        
        // result
        $flights = [];
        
        // returns list of supported frequent flyer programs in Milefy API
        $programs = $this->getPrograms();
        
        if(!is_array($programs)) return false;
        
        // for every flight in CalculateMiles mehtod response
        foreach($responseFlights as $responseFlight){
            
            // Skip flights with no frequent flyer program specified.
            
            // There might be more than one program earning miles to one flight, but
            // for the sake of simplicity we will display just one.
            if(!is_array($responseFlight->programs) || count($responseFlight->programs) <= 0) continue;
            
            $flight = array(
                'flightId' => $responseFlight->flightId
            );
            
            // skip flights with no mileage earnings
            $responseProgram = $responseFlight->programs[0];
            if(!is_array($responseProgram->earnings) || count($responseProgram->earnings) <= 0) continue;
            
            // skip flights with no award miles earned
            $responseAwardMiles = $this->_helper->find($responseProgram->earnings, self::AWARD_MILES_CODE, 'metricCode');
            if(!$responseAwardMiles) continue;
            
            // fetch frequent flyer program details
            $program = $this->_helper->find($programs, $responseProgram->programCode, 'programCode');
            if(!$program || !is_array($program->metrics)) continue;
            
            // fetch award miles name specific for the program
            $awardMiles = $this->_helper->find($program->metrics, self::AWARD_MILES_CODE, 'metricCode');
            if(!$awardMiles) continue;
            
            // for majority of programs mileage earnings are integer values,
            // but sometimes there might be decimals too!
            $decimal = ($responseAwardMiles->value * 100) % 100;
            $precision = $decimal > 0 ? strlen(strval($decimal)) : 0;
            
            // save number of earned award miles, name of this type of miles and name of default frequent flyer program
            $flight['awardMilesValue'] = number_format($responseAwardMiles->value, $decimal);
            $flight['program'] = $program->programNameWithoutAirline;
            $flight['awardMilesName'] = $awardMiles->metricName;
            
            $flights[] = $flight;
            
        }
        
        return $flights;
        
    }
    
    /*
        Returns list of all supported frequent flyer programs
        in Milefy API 
    */
    public function getPrograms(){
        
        // Try to read list of programs from cache
        // - this information can be stored on client server for 1 day
        
        if(!isset($_SESSION['programs']) || !is_array($_SESSION['programs'])){
            
            // create HTTP client to connect with Milefy API
            // using Programs method
            $client = $this->_createHttpClient('GET', '/api/miles/programs');
            
            $client->send();
            
            // HTTP status code validation
            if($client->getResponseStatus() < 400){
                
                $response = json_decode($client->getResponseBody());
                
                // response body validation
                if(isset($response) && $response->Success && is_array($response->Value)){
                    
                    // success
                    
                    // list of programs stored in cache
                    $_SESSION['programs'] = $response->Value;
                    
                }else{
                    
                    // failure
                    return false;
                    
                }
                
            }else{
                
                // failure
                return false;
                
            }
               
        }
        
        // from cache
        
        return $_SESSION['programs'];
        
    }
    
    /*
        Prepares CalculateMiles Milefy API request body 
        based on specified flight search results.
    */
    protected function _getCalculateRequestBody(){
        
        $body = array(
            
            // User id used by client - can be stored in database with user account or in cookie for temporary visitors. Required.
            'clientUserId' => $this->_getUserId(), 
            
            // List of flights to calculate miles for. Required.
            'flights' => array()
        );
        
        // iterate flights to calculate miles for...
        foreach($this->_flightResults as $flight){
            
            $bodyFlight = array(
                'flightId' => $flight['flightId'],                      // Flight Id. Required.
                'price' => array(                                       // Price. Required.
                    'currencyCode' => $flight['price']['currencyCode'], // Currency code (3 letters). Required.
                    'total' => $flight['price']['total'],               // Total price. Required.
                    'fare' => $flight['price']['fare'],                 // Base fare. Recommended.
                    'taxes' => $flight['price']['taxes'],               // Taxes. Recommended.
                    'surcharges' => $flight['price']['surcharges']      // Airline surcharges. Recommended.
                ),
                'legs' => array()                                       // Flight legs. Round trip has two, one-way one. Required.
            );
            
            // iterate throught flight legs
            foreach($flight['legs'] as $leg){
                
                $bodyLeg = array(
                    'legId' => $leg['legId'],                           // Flight leg id. Optional for CalcualteMiles method.
                    'segments' => array()                               // Flight segments - every pair of departure and landing. Required.
                );
                
                // iterate through flight segments
                foreach($leg['segments'] as $segment){
                    
                    $bodySegment = array(
                        'segmentId' => $segment['segmentId'],                        // Segment Id. Optional for CalculateMiles.
                        'marketingAirlineCode' => $segment['marketingAirlineCode'],  // Marketing airline IATA code. Required.
                        'operatingAirlineCode' => $segment['operatingAirlineCode'],  // Operating airline IATA code. Required.
                        'deptCode' => $segment['deptCode'],                          // Departure airport IATA code. Required.
                        'destCode' => $segment['destCode'],                          // Destination airport IATA code. Required.
                        'deptDate' => $segment['deptDate'],                          // Departure date in format: YYYY-MM-DD. Required.
                        'fareCode' => $segment['fareCode'],                          // Booking code, a single letter. Required.
                        'flightNumber' => $segment['flightNumber'],                  // Flight number, just digits. Required.
                        'fareBasisCode' => $segment['fareBasisCode'],                // Fare basis code. Recommended.
                        'distance' => $segment['distance']                           // Distance in miles. Recommended.
                    );
                    
                    $bodyLeg['segments'][] = $bodySegment;
                    
                }
                
                $bodyFlight['legs'][] = $bodyLeg;
                
            }
            
            $body['flights'][] = $bodyFlight;
            
        }
        
        return $body;
        
    }
    
    /*
        Returns current user id
    */
    protected function _getUserId(){
        
        // our user id is stored only in cookie
        if(!isset($_COOKIE['userId'])) setcookie('userId', uniqid(), time() + 3600 * 24 * 365);
        return $_COOKIE['userId'];
        
    }
    
    /*
        Returns HTTP client ready to send requests to Milefy API.
    */
    protected function _createHttpClient($method, $endpoint){
        
        $client = EasyRequest::create($this->_apiBaseUrl . $endpoint);
        
        $client
            
            // Basic authentication using API credentials
            ->withAuth($this->_apiUsername . ':' . $this->_apiPassword)
            
            // Headers sent with every request
            ->withHeader(self::$_DEFAULT_HEADERS)
            
            // Timeout to prevent request termination
            ->withTimeout(self::REQUEST_TIMEOUT);
        
        return $client;
        
    }
    
    // Flight search results - list of flights
    protected $_flightResults = null;
    
    // A helper object
    protected $_helper = null;
    
    // Default HTTP headers for Milefy API
    protected static $_DEFAULT_HEADERS = array(
        'Accept-Language' => 'en-US,en;q=1',
        'Accept' => 'application/hal+json;q=1, application/json;q=0.8',
        'Content-Type'=> 'application/json;charset=UTF-8',
        'X-Api-Version' => 'v2.8'
    );
    
    // Milefy API access credentials
    protected $_apiBaseUrl;
    protected $_apiUsername;
    protected $_apiPassword;
    
    // Code of award miles in Milefy API
    const AWARD_MILES_CODE = 1;
    
    // Default error
    const DEFAULT_FAILURE_MESSAGE = 'Unknown processing error.';
    
    // Default timeout
    const REQUEST_TIMEOUT = 120000;
    
}