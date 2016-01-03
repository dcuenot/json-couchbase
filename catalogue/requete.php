<?php
  

// Connecting to Couchbase
$cluster = new CouchbaseCluster("http://db");
$cb = $cluster->openBucket("catalogue-assemblage", "");
$cb->enableN1ql(array('http://db:8093/'));  



// Liste des documents via N1QL
$query = CouchbaseN1qlQuery::fromString('
  SELECT ARRAY_AGG(ARRAY_CONCAT(fils.caracteristiques,pere.caracteristiques))
  FROM `catalogue-assemblage` fils 
    JOIN `catalogue-assemblage` pere 
    ON KEYS fils.idProduitCommercial 
  WHERE fils.type = "offreCommerciale"
  ;
');

$res = $cb->query($query);
//var_dump($res);
echo '<pre>',  json_encode($res,JSON_PRETTY_PRINT) ,'</pre>';


    





//SELECT ARRAY_CONCAT(fils.caracteristiques,pere.caracteristiques) FROM `catalogue-assemblage` fils JOIN `catalogue-assemblage` pere ON KEYS fils.idProduitCommercial WHERE fils.type = "offreCommerciale";