<?php 

/*
    Returns list of available sample flight searches.
*/

require('../FlightSearch.php');

$flightSearch = new FlightSearch();
$flightSearch->getAvailableSearches();
