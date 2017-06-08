$(document).ready(function(){
    
    var self = this;
    
    this.showSearchesError = function(select, error){
        
        select.html('<option value="">' + error + '</option>');
        
    };
    
    this.showSearches = function(select, searches){
        
        var selected = ' selected="selected"';
        
        for(var i = 0 ; i < searches.length ; i++){
            
            select.append('<option value="' + searches[i].id + '"' + selected + '>' + searches[i].name + '</option>');
            selected = '';
            
        }
        
        select.removeAttr('disabled');
        $('#submit').removeAttr('disabled');
        
    };
    
    this.loadSearches = function(){
        
        var select = $('#sample-searches');
        var error = 'Could not load collection of flight searches.';
        
        $.get('./api/search/collection.php').always(function(){
            
            select.find('option').remove();
            
        }).then(function(response){
            
            if(!$.isPlainObject(response) || !$.isArray(response.content) || response.content.length <= 0 || !response.success){
                
                self.showSearchesError(select, error);
                
            }else{
                
                self.showSearches(select, response.content);
                
            }
            
        }, function(response){
            
            if($.isPlainObject(response) && response.message) error = response.message;
            self.showSearchesError(select, error);
            
        });
          
    };
    
    this.loadResults = function(search){
        
        var results = $('#results');
        var error = 'Could not load flight search results';
        var submit = $('#submit');
        
        submit.attr('disabled', 'disabled');
        results.html('<p class="loading">Loading&hellip;</p>');
        
        $.get('./api/search/single.php', {
            id: search
        }).always(function(){
            
            submit.removeAttr('disabled');
            
        }).then(function(response){
            
            if(!$.isPlainObject(response) || !response.content || !response.success){
                
                if($.isPlainObject(response) && response.message) error = response.message;
                self.showResultsError(results, error);
                
            }else{
                
                self.showResults(results, response.content);
                
            }
            
        }, function(response){
            
            self.showResultsError(results, error);
            
        });
        
    };
    
    this.showResults = function(results, html){
        
        results.html(html);
        
    };
    
    this.showResultsError = function(results, error){
        
        results.html('<p class="error">' + error + '</p>');
        
    };
    
    this.onSubmit = function(event){
        
        event.preventDefault();
        
        var select = $('#sample-searches');
        var id = select.val();
        
        if(id) self.loadResults(id);
          
    };
    
    this.init = function(){
        
        $(document).foundation();
        
        $('#form').on('submit', self.onSubmit);
        
        self.loadSearches();
        
    };
    
    this.init();
    
});