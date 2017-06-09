<?php

class Helper{
    
    public function find($array, $value, $key = 'id'){
        
        foreach($array as $obj){
            
            if(is_array($obj) && $obj[$key] == $value) return $obj;
            else if(is_object($obj) && $obj->{$key} == $value) return $obj;
            
        }
        
        return null;
        
    }
    
}