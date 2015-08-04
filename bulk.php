<?php


     /* ElasticSearch Configurations  Starts   */

        $esHost             = '127.0.0.1';
        $esPort             = '9200';
        $esIndex            = 'books';
        $esType             = 'book';
        $availAggs          = array('Category','Price','Rating');
        $searchQuery        = '';
        $from               = 0;
        $size               = 12;
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
      
      $category = array('Science','Computer','Fiction','Magic','PHP');
      for($i=0; $i<1000; $i++){
          $book = array();
          $book['Name']         = generateRandomString();
          $book['Category']     = $category[array_rand($category)];
          $book['Price']        = rand(0,100000);
          $book['Rating']       = rand(1,5);
          $book =  json_encode($book, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_NUMERIC_CHECK );
          echo call($book,'','POST');
      }
      
      function generateRandomString($length = 10) {
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
     }