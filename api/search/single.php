<?php

/*
    Returns flight search with specified ID.
*/

require('../FlightSearch.php');

$flightSearch = new FlightSearch();
$flightSearch->getFlightResults();