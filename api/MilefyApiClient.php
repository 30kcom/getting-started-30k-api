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
        $this->_apiKey = getenv('MILEFY_KEY');
        
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
        $client = $this->_createHttpClient('POST', '/calculate');
        
        // Traveler ID for individual user can be stored in database with user account or in cookie for temporary visitors. 
        // It's required and needs to be created with Create traveler method first.
        $client->withQuery('traveler', $this->_getTravelerId());
        
        // limits response size returning only mileage earnings (optimizes performance)
        $client->withQuery('fields', 'id,flights(id,programs(code,statusTiers(code,mileageEarnings)))');
        
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
            if(isset($response) && is_array($response->flights)){
                
                // success
                
                // returns award miles for calculated flights
                return $this->_getFlightsAwardMiles($response->flights);
                
            }else{
                
                // failure
                return false;
                
            }
            
        }else{
            
            // failure
            return false;
            
        }
        
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
            // using Get program collection method
            $client = $this->_createHttpClient('GET', '/programs');
            
            $client->send();
            
            // HTTP status code validation
            if($client->getResponseStatus() < 400){
                
                $response = json_decode($client->getResponseBody());
                
                // response body validation
                if(isset($response) && $response->_embedded && is_array($response->_embedded->programs)){
                    
                    // success
                    
                    // list of programs stored in cache
                    $_SESSION['programs'] = json_encode($response->_embedded->programs);
                    
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
        return json_decode($_SESSION['programs']);
        
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
                'id' => $responseFlight->id
            );
            
            // skip flights with no mileage earnings
            $responseProgram = $responseFlight->programs[0];
            
            // ensure program has at least one status tier for which calculations has been made
            if(count($responseProgram->statusTiers) <= 0) continue;
            
            $responseStatus = $responseProgram->statusTiers[0];
            
            // skip programs without mileage earnings
            if(!is_array($responseStatus->mileageEarnings) || count($responseStatus->mileageEarnings) <= 0) continue;
            
            // skip flights with no award miles earned
            $responseAwardMiles = $this->_helper->find($responseStatus->mileageEarnings, self::AWARD_MILES_CODE, 'code');
            if(!$responseAwardMiles) continue;
            
            // fetch frequent flyer program details
            $program = $this->_helper->find($programs, $responseProgram->code, 'code');
            if(!$program || !is_array($program->mileTypes)) continue;
            
            // fetch award miles name specific for the program
            $awardMiles = $this->_helper->find($program->mileTypes, self::AWARD_MILES_CODE, 'code');
            if(!$awardMiles) continue;
            
            // for majority of programs mileage earnings are integer values,
            // but sometimes there might be decimals too!
            $decimal = ($responseAwardMiles->value * 100) % 100;
            $precision = $decimal > 0 ? strlen(strval($decimal)) : 0;
            
            // save number of earned award miles, name of this type of miles and name of default frequent flyer program
            $flight['awardMilesValue'] = number_format($responseAwardMiles->value, $decimal);
            $flight['program'] = $program->name;
            $flight['awardMilesName'] = $awardMiles->name;
            
            $flights[] = $flight;
            
        }
        
        return $flights;
        
    }
    
    /*
        Prepares CalculateMiles Milefy API request body 
        based on specified flight search results.
    */
    protected function _getCalculateRequestBody(){
        
        $body = array(
            
            // List of flights to calculate miles for. Required.
            'flights' => array()
            
        );
        
        // iterate flights to calculate miles for...
        foreach($this->_flightResults as $flight){
            
            $bodyFlight = array(
                'id' => $flight['flightId'],                              // Flight ID. Required.
                'price' => array(                                         // Price. Required.
                    'currency' => $flight['price']['currencyCode'],       // Currency code (3 letters). Required.
                    'total' => $flight['price']['total'],                 // Total price. Required.
                    'baseFare' => $flight['price']['fare'],               // Base fare. Recommended.
                    'taxes' => $flight['price']['taxes'],                 // Taxes. Recommended.
                    'airlineSurcharges' => $flight['price']['surcharges'] // Airline surcharges. Recommended.
                ),
                'legs' => array()                                         // Flight legs. Round trip has two, one-way one. Required.
            );
            
            // iterate throught flight legs
            foreach($flight['legs'] as $leg){
                
                $bodyLeg = array(
                    'id' => $leg['legId'],                              // Flight leg id. Required.
                    'segments' => array()                               // Flight segments - every pair of departure and landing. Required.
                );
                
                // iterate through flight segments
                foreach($leg['segments'] as $segment){
                    
                    $bodySegment = array(
                        'id' => $segment['segmentId'],                               // Segment Id. Required
                        'marketingAirline' => $segment['marketingAirlineCode'],      // Marketing airline IATA code. Required.
                        'operatingAirline' => $segment['operatingAirlineCode'],      // Operating airline IATA code. Required.
                        'departureAirport' => $segment['deptCode'],                  // Departure airport IATA code. Required.
                        'arrivalAirport' => $segment['destCode'],                    // Destination airport IATA code. Required.
                        'departureDate' => $segment['deptDate'],                     // Departure date in format: YYYY-MM-DD. Required.
                        'bookingClass' => $segment['fareCode'],                      // Booking class code, a single letter. Required.
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
        Returns current traveler id
    */
    protected function _getTravelerId(){
        
        // for demo purposes our user id is stored only in cookie
        if(!isset($_COOKIE['travelerId'])){
            
            $traveler = $this->_createTraveler();
            setcookie('travelerId', $traveler->id, time() + 3600 * 24 * 365);
            
        }
        
        return $_COOKIE['travelerId'];
        
    }
    
    /*
        Creates individual traveler
    */
    protected function _createTraveler(){
        
        $client = $this->_createHttpClient('POST', '/travelers');
        
        // Empty object is just enough if you don't know country of traveler's residence
        $client->withBody('{}');
        
        $client->send();
        
        // HTTP status code validation
        if($client->getResponseStatus() < 400){
            
            $response = json_decode($client->getResponseBody());
            
            // response body validation
            if(isset($response) && isset($response->id)){
                
                // success
                return $response;
                
            }else{
                
                // failure
                return false;
                
            }
            
        }else{
            
            // failure
            return false;
            
        }
        
    }
    
    /*
        Returns HTTP client ready to send requests to Milefy API.
    */
    protected function _createHttpClient($method, $endpoint){
        
        $client = EasyRequest::create($this->_apiBaseUrl . $endpoint, $method);
        
        $client
            
            // Basic authentication using API credentials
            ->withQuery('apiKey', $this->_apiKey)
            
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
        'X-Api-Version' => 'v3.0'
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