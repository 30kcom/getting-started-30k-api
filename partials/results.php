<!-- FLIGHT RESULTS TEMPLATE -->

<ul class="flight-results">
    <?php foreach($this->_response['flights'] as $flight): ?>
    
        <!-- FLIGHT RESULT -->
        
        <li class="flight" id="<?php echo $this->_getFlightId($flight); ?>">
            
            <!-- HEADER -->
            
            <div class="flight-header">
                
                <span class="flight-price"><?php echo $this->_getPrice($flight); ?></span>
                
                <!-- FREQUENT FLYER INFO PLACEHOLDER -->
                
                <?php if(isset($flight['frequentFlyer'])): ?>
                
                    <span class="flight-freq-flyer">
                        <strong><?php echo $flight['frequentFlyer']['program']; ?>:</strong> 
                        <span class="flight-miles"><?php echo $flight['frequentFlyer']['awardMilesValue']; ?></span>
                        <span class="flight-mile-type"><?php echo $flight['frequentFlyer']['awardMilesName']; ?></span>
                    </span>
                    
                <?php else: ?>
                    
                    <span class="flight-no-miles">No earnings :(</span>
                    
                <?php endif; ?>
                
                <!-- FREQUENT FLYER INFO PLACEHOLDER -->
                
            </div>
            
            <!-- /HEADER -->
            
            <div class="flight-body">
                
                <?php foreach($flight['legs'] as $leg): ?>
                    
                    <!-- FLIGHT LEG -->
                    
                    <div class="flight-leg">
                
                        <span class="leg-airlines">
                            <?php foreach($this->_getAirlines($leg) as $airline): ?>
                                <em><?php echo $airline; ?></em>
                            <?php endforeach; ?>
                        </span>
                        
                        <span class="leg-departure">
                            <span class="leg-time"><?php echo $this->_getTime($leg, true); ?></span>
                            <span class="leg-airport"><?php echo $this->_getAirport($leg, true) ?></span>
                        </span>
                        
                        <span class="leg-arrow">&rsaquo;</span>
                        
                        <span class="leg-destination">
                            <span class="leg-time"><?php echo $this->_getTime($leg, false); ?></span>
                            <span class="leg-airport"><?php echo $this->_getAirport($leg, false) ?></span>
                        </span>
                        
                        <span class="leg-duration"><?php echo $this->_getDuration($leg); ?></span>
                        
                        <span class="leg-stops"><?php echo $this->_getStops($leg); ?></span>
                        
                    </div>
                    
                    <!-- /FLIGHT LEG -->
                    
                <?php endforeach; ?>
                
            </div>
        </li>
        
        <!-- /FLIGHT RESULT -->
    
    <?php endforeach; ?>
</ul>

<!-- /FLIGHT RESULTS TEMPLATE -->