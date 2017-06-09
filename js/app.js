/*
    
    Simple script to load available searches from 
    the server and display search results.
    
    The script does not communicate with Milefy API directly, 
    but through the proxy - PHP scripts located in /api/search
    
*/

$(document).ready(function(){
    
    var self = this;
    
    /*
        Displays error inside select element
    */
    this.showSearchesError = function(select, error){
        
        select.html('<option value="">' + error + '</option>');
        
    };
    
    /*
        Displays available searches in select element
    */
    this.showSearches = function(select, searches){
        
        var selected = ' selected="selected"';
        
        for(var i = 0 ; i < searches.length ; i++){
            
            select.append('<option value="' + searches[i].id + '"' + selected + '>' + searches[i].name + '</option>');
            selected = '';
            
        }
        
        select.removeAttr('disabled');
        $('#submit').removeAttr('disabled');
        
    };
    
    /*
        Loads available sample searches
        into select element.
    */
    this.loadSearches = function(){
        
        // element
        var select = $('#sample-searches');
        
        // default error
        var error = 'Could not load collection of flight searches.';
        
        // searches request
        $.get('./api/search/collection.php').always(function(){
            
            // turn off loading
            select.find('option').remove();
            
        }).then(function(response){
            
            if(!$.isPlainObject(response) || !$.isArray(response.content) || response.content.length <= 0 || !response.success){
                
                // failure
                self.showSearchesError(select, error);
                
            }else{
                
                // success
                self.showSearches(select, response.content);
                
            }
            
        }, function(response){
            
            // failure
            if($.isPlainObject(response) && response.message) error = response.message;
            self.showSearchesError(select, error);
            
        });
          
    };
    
    /*
        
        Loads flight results from the proxy server
        and displays them on the page.
        
    */
    this.loadResults = function(search){
        
        // placeholder
        var results = $('#results');
        
        // default error
        var error = 'Could not load flight search results';
        
        // button
        var submit = $('#submit');
        
        // loading turned on
        submit.attr('disabled', 'disabled');
        results.html('<p class="loading">Loading&hellip;</p>');
        
        // request search results
        $.get('./api/search/single.php', {
            id: search
        }).always(function(){
            
            // loading turned off
            submit.removeAttr('disabled');
            
        }).then(function(response){
            
            // response validation
            if(!$.isPlainObject(response) || !response.content || !response.success){
                
                // failure
                if($.isPlainObject(response) && response.message) error = response.message;
                self.showResultsError(results, error);
                
            }else{
                
                // success
                self.showResults(results, response.content);
                
            }
            
        }, function(response){
            
            // failure
            self.showResultsError(results, error);
            
        });
        
    };
    
    /*
        Show flight results
    */
    this.showResults = function(results, html){
        
        // copy HTML to the placeholder
        results.html(html);
        
    };
    
    /*
        Display request errors
    */
    this.showResultsError = function(results, error){
        
        results.html('<p class="error">' + error + '</p>');
        
    };
    
    /*
        After search form has been submitted
    */
    this.onSubmit = function(event){
        
        event.preventDefault();
        
        // get search id
        var select = $('#sample-searches');
        var id = select.val();
        
        // load search with selected id
        if(id) self.loadResults(id);
        
    };
    
    /*
        Entry point on page load
    */
    this.init = function(){
        
        // init Foundation
        $(document).foundation();
        
        // init form submit event listener
        $('#form').on('submit', self.onSubmit);
        
        // load list of available sample searches
        self.loadSearches();
        
    };
    
    this.init();
    
});