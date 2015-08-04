<?php

     /* ElasticSearch Configurations  Starts   */

        $esHost             = '127.0.0.1';
        $esPort             = '9200';
        $esIndex            = 'books';
        $esType             = 'book';
        $availAggs          = array('Category','Price','Rating');
        $searchQuery        = '';
        $from               = 0;
        $size               = 16;
        $postFilters        = array();
        $result             = '';
        
     /* ElasticSearch Configurations  Ends   */    
        
     /*
      *     This function used to fire query to Elasticsearch
      * 
      */   
      function call($queryData, $esAPI = '/_search', $method='POST'){
          global $esHost,$esPort,$esIndex,$esType;
	    try {
                $esURL = 'http://'.$esHost.':'.$esPort.'/'.$esIndex.'/'.$esType.$esAPI;
                $ci = curl_init();
                curl_setopt($ci, CURLOPT_URL, $esURL);
                curl_setopt($ci, CURLOPT_PORT, $esPort);
                curl_setopt($ci, CURLOPT_TIMEOUT, 200);
                curl_setopt($ci, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ci, CURLOPT_FORBID_REUSE, 0);
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST,$method);
                curl_setopt($ci, CURLOPT_POSTFIELDS, $queryData);
                return curl_exec($ci);
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\r\n";
            }
      }
  
       // If Any filter applied or search keyword entered
         
          if(isset($_POST['search']) && strlen($_POST['search']) > 2){
            $searchQuery = ' "query": {
                                       
                                    
                                "bool": {
                                  "should": [
                                    { 
                                        "multi_match" : {
                                            "query":      "' . $_POST['search'] . '",
                                            "type":       "most_fields",
                                            "fields":     [ "Name.analyzed", "Category.analyzed"],
                                            "operator": "and",
                                            "tie_breaker": 0.3
                                          } 
                                    },
                                    {
                                      "term": { "Category":  "' . $_POST['search'] . '"  }
                                    }
                                    
                                  ],
                                  "minimum_number_should_match": 1
                                }
                              },
                        ';
            
          }
      
      if(isset($_POST['search']) &&  isset($_POST['filters'])){
          $mainQuery    = '';
    
          $mainFilter   = buildFilter($_POST['filters']);
          $mainAggs     = buildAggregation($_POST['filters']);
          $postFilters  = $_POST['filters'];
         
          $mainQuery    = '{ '.$searchQuery.$mainFilter.','.$mainAggs.', "from":'.$from.', "size":'.$size.' }';
         
          $result = call($mainQuery);
          
          $result = json_decode($result, true);
          
      }else {
          //  ElasticSearch Query without Any filters.      
      
                $mainQuery    =  '
                        "aggs": {
                           "Category": {
                              "filter": {},
                              "aggs": {
                                 "Category": {
                                    "terms": {
                                       "field": "Category",
                                       "size": 200
                                    }
                                 }
                              }
                           },
                           "Price": {
                              "filter": {
                                 "and": {
                                    "filters": [
                                       {
                                          "range": {
                                             "Price": {
                                                "gt": 0
                                             }
                                          }
                                       }
                                    ]
                                 }
                              },
                              "aggs": {
                                 "Price": {
                                    "range": {
                                       "field": "Price",
                                       "ranges": [
                                                      {
                                                         "key": "0 TO 1000",
                                                         "to": 1000
                                                      },
                                                      {
                                                         "key": "1000 TO 3000",
                                                         "from": 1000,
                                                         "to": 3000
                                                      },
                                                      {
                                                         "key": "3000 TO 5000",
                                                         "from": 3000,
                                                         "to": 5000
                                                      },
                                                      {
                                                         "key": "5000 TO 10000",
                                                         "from": 5000,
                                                         "to": 10000
                                                      },
                                                      {
                                                         "key": "10000 TO 50000",
                                                         "from": 10000,
                                                         "to": 50000
                                                      },
                                                      {
                                                         "key": "50000 TO 100000",
                                                         "from": 50000,
                                                         "to": 100000
                                                      }
                                                   ]
                                    }
                                 }
                              }
                           },
                           "Rating": {
                              "filter": {},
                              "aggs": {
                                 "Rating": {
                                    "terms": {
                                       "field": "Rating",
                                       "size": 200
                                    }
                                 }
                              }
                           }
                        }
                     ';
                
          $mainQuery    = '{ '.$searchQuery.''.$mainQuery.', "from":'.$from.', "size":'.$size.' }';      
          $result = call($mainQuery);
          
          $result = json_decode($result, true);    
      }
      
      /*
       * Building Filter query 
       * 
       */
    function buildFilter($filters = array(), $exclude = ''){
        
        if(is_array($filters) && count($filters) > 0){
            $filterQuery = '';
            foreach($filters as $index => $value){
                if($index == 'Price' && $index != $exclude){

                    $filterQuery .=  setPriceRange($value);
                            
                }else if($index != $exclude){
                   
                    $filterQuery .= setTerm($index,$value);
                    
                } // End If for Price filter condition check
                
            } // End Foreach for user submitted filters
            
            if($filterQuery == ''){
                
                return '"filter": { }';
                
            }else {
                $filterQuery = '"filter": {
                                    "and": {
                                      "filters": ['.rtrim($filterQuery, ",") . '] } }';
                return $filterQuery;
            }
            
            
        }else {
            return '"filter": { }';
        } // End If check for filters query string
        
        return $filterQuery;
    } // End buildFilter Function
      
    /*
     * Set Term Condition for individual Fields. 
     * If filter has more than one value its OR nested condition
     * 
     */
   function setTerm($index,$value){
       $filterQuery = '';
       if(is_array($value) && count($value) > 1){
           $orFilterQuery = '';
                       foreach($value as $orValue){
                            $orFilterQuery .= '{
                                                "term": {
                                                   "' . $index . '": "' . $orValue . '"
                                                 }
                                                },';
                        }
                        $filterQuery .= '{"or": ['.rtrim($orFilterQuery, ",") . '] },';
                       
                   }else {
                        $filterQuery .= '{
                                                "term": {
                                                   "' . $index . '": "' . $value[0] . '"
                                                 }
                                          },';
                   } // End If for AND or OR operator selection
       return $filterQuery;            
   } 
   
     /*
     * Set Price Range is special condition for only range conditin
     * If filter has more than one value its OR nested condition
     * 
     */
   function setPriceRange($value){
       $filterQuery = '';
       if(is_array($value) && count($value) > 1){
           $orFilterQuery = '';
                       foreach($value as $orValue){
                           if(strpos($orValue, ' TO ') !== false) {
                                $range     = explode(" TO ", $orValue);
                                $range_str = '"from": ' . $range[0] . ',  "to": ' . $range[1] . '';
                            }
                            $orFilterQuery .= '{
                                                    "range" : {
                                                              "Price" : {
                                                                  ' . $range_str . '
                                                              }
                                                          }
                                                },';
                        }
                        $filterQuery .= '{"or": ['.rtrim($orFilterQuery, ",") . '] },';
                       
                   }else {
                       
                       if (strpos($value[0], ' TO ') !== false) {
                            $range     = explode(" TO ", $value[0]);
                            $range_str = '"from": ' . $range[0] . ',  "to": ' . $range[1] . '';
                        }
                        $filterQuery .= '{
                                            "range" : {
                                                      "Price" : {
                                                          ' . $range_str . '
                                                      }
                                                  }
                                         },';

                   } // End If for AND or OR operator selection
       return $filterQuery;            
   } 
    
   
   /*
    *  Build Aggregation with filters. 
    *  Aggregation filter needs to be exclude own field filters. 
    * 
    */
   
   function buildAggregation($formFilters = array()){
       global $availAggs; 
       $formFiltersIndex = array_keys($formFilters);
       $aggsQuery = '';
       foreach($availAggs as $value){
           $excludeFilter = '';
           if(in_array($value, $formFiltersIndex)){
              $excludeFilter = $value; 
           }
           if($value == 'Price'){
             $aggFilter = buildFilter($formFilters,$excludeFilter);
             $aggsQuery .= ' "Price": {
                                            '.$aggFilter.',
                                          
                                          "aggs": {
                                             "Price": {
                                                "range": {
                                                   "field": "Price",
                                                   "ranges": [
                                                      {
                                                         "key": "0 TO 1000",
                                                         "to": 1000
                                                      },
                                                      {
                                                         "key": "1000 TO 3000",
                                                         "from": 1000,
                                                         "to": 3000
                                                      },
                                                      {
                                                         "key": "3000 TO 5000",
                                                         "from": 3000,
                                                         "to": 5000
                                                      },
                                                      {
                                                         "key": "5000 TO 10000",
                                                         "from": 5000,
                                                         "to": 10000
                                                      },
                                                      {
                                                         "key": "10000 TO 50000",
                                                         "from": 10000,
                                                         "to": 50000
                                                      },
                                                      {
                                                         "key": "50000 TO 100000",
                                                         "from": 50000,
                                                         "to": 100000
                                                      }
                                                   ]
                                                }
                                             }
                                          }
                                       },';
              
           }else {
               
               $aggFilter = buildFilter($formFilters,$excludeFilter);
               $aggsQuery .= '"'.$value.'": {
                                '.$aggFilter.',
                                "aggs": {
                                   "'.$value.'": {
                                      "terms": {
                                         "field": "'.$value.'",
                                         "size": 200
                                      }
                                   }
                                }
                             },';
           }    // End If Price Condition.
       } // End Foreach Aggregation loop
       
       $aggsQuery = '"aggs": {'.rtrim($aggsQuery, ",") . '}';
       return $aggsQuery;
   }
   
   
  