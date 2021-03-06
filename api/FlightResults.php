<?php 

/*
    
    Generates flight search results HTML from flight search data
    received from APIs.
    
*/
class FlightResults{
    
    /* PUBLIC =================================================================*/
    
    /*
        Entry point
        
        @param $response - object containing base flight search result 
        information such as itinerary, date, flight numbers etc.
        
    */
    public function __construct($response){
        
        $this->_helper = new Helper();
        
        $this->_response = $response;
        
    }
    
    /*
        Returns HTML containing flight search results
    */
    public function toHtml(){
        
        ob_start();
        
        // include flight search result template
        require('../../partials/results.php');
        
        $html = ob_get_contents();
        ob_end_clean();
        
        return $html;
        
    }
    
    /* PROTECTED ==============================================================*/
    
    /*
        Formats flight total price
    */
    protected function _getPrice($flight){
        
        if(!isset($flight['price']) || !is_array($flight['price']) 
            || !is_numeric($flight['price']['total']) || !is_string($flight['price']['currencyCode'])) return;
        
        return number_format($flight['price']['total'], 2) . ' ' . htmlspecialchars($flight['price']['currencyCode']);
        
    }
    
    /*
        Formats flight id
    */
    protected function _getFlightId($flight){
        
        if(!isset($flight['flightId']) || !is_string($flight['flightId'])) return;
        
        return htmlspecialchars($flight['flightId']);
        
    }
    
    /*
        Returns list of merketing airlines for particular flight leg
    */
    protected function _getAirlines($leg){
        
        if(!isset($leg['segments']) || !is_array($leg['segments']) || count($leg['segments']) <= 0) return;
        
        // list with unique airline codes
        $airlines = [];
        
        // for each seegment in flight leg
        foreach($leg['segments'] as $segment){
            
            if(is_string($segment['marketingAirlineCode']) && !in_array($segment['marketingAirlineCode'], $airlines)){
                
                $airlines[] = $segment['marketingAirlineCode'];
                
            }
            
        }
        
        $names = [];
        
        // change airline codes into names
        foreach($airlines as $code){
            
            $airline = $this->_helper->find($this->_response['airlines'], $code, 'airlineCode');
            if(is_array($airline) && isset($airline['airlineName'])) $names[] = $airline['airlineName'];
            
        }
        
        return $names;
        
    }
    
    /*
        Returns formatted time of arrival and departure
    */
    protected function _getTime($leg, $isDeparture){
        
        if(!$leg || !is_array($leg['segments']) || count($leg['segments']) <= 0) return;
        
        $index = $isDeparture ? 0 : count($leg['segments']) - 1;
        $prop = $isDeparture ? 'deptDate' : 'arrDate';
        
        if(!is_string($leg['segments'][$index][$prop])) return;
        
        return date_format(date_create($leg['segments'][$index][$prop]), 'G:i');
        
    }
    
    /*
        Returns departure and arrival airport for particular flight leg
    */
    protected function _getAirport($leg, $isDeparture){
        
        if(!$leg || !is_array($leg['segments']) || count($leg['segments']) <= 0) return;
        
        $index = $isDeparture ? 0 : count($leg['segments']) - 1;
        $prop = $isDeparture ? 'deptCode' : 'destCode';
        
        if(!is_string($leg['segments'][$index][$prop])) return;
        
        return htmlspecialchars($leg['segments'][$index][$prop]);
        
    }
    
    /*
        Returns formatted duration of particular flight leg
    */
    protected function _getDuration($leg){
        
        if(!isset($leg['legDuration']) || !is_numeric($leg['legDuration'])) return;
        
        $m = $leg['legDuration'];
        $h = floor($m / 60);
        $d = floor($h / 24);
        
        if($h > 0) $m = $m - $h * 60;
        
        $result = '';
        
        if($h > 0) $result .= $h . 'h';
        if(($m > 0) || ($h <= 0 && $d <= 0)) $result .= (strlen($result) > 0 ? ' ' : '') . $m . 'm';
        
        return $result;
        
    }
    
    /*
        Returns formatted number of stops in particular flight leg
    */
    protected function _getStops($leg){
        
        $result = self::NONSTOP;
        
        if(!is_array($leg['segments'])) return $result;
        
        $count = count($leg['segments']);
        
        if($count > 1){
            
            $result = count($leg['segments']) == 2 ? self::ONE_STOP : sprintf(self::MULTIPLE_STOPS, $count);
            
        }
        
        return $result;
        
    }
    
    protected $_helper = null;
    
    // response containing flight search results
    protected $_response = null;
    
    // texts
    const NONSTOP = 'Nonstop';
    const ONE_STOP = '1 stop';
    const MULTIPLE_STOPS = '%d stops';
    
}