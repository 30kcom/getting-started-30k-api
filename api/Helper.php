<?php

/*
    Simple helper class
*/

class Helper{
    
    
    /*
        Iterates through array in order to find 
        object or array with a specified value.
    */
    public function find($array, $value, $key = 'id'){
        
        foreach($array as $obj){
            
            if(is_array($obj) && $obj[$key] == $value) return $obj;
            else if(is_object($obj) && $obj->{$key} == $value) return $obj;
            
        }
        
        return null;
        
    }
    
}