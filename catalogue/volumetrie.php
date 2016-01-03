<?php


$nbDocumentsDemandes = [
    'usine' => 10,
    'produitTechnique' => 350,
    'operation' => 10,
    'unite' => 20,
    'valeur' => 300,
    'natureCaracteristique' => 9,
    'caracteristique' => 500,  // Ajout des RG éligibilité dans le calcul (et engloble caractA & B)
    //'caracteristiqueTableau' => 200,  // Ajout des RG éligibilité dans le calcul (et engloble caractA & B)
    'produitModele' => 20,
    'produitGenerique' => 80,
    'produitCommercial' => 200,
    'offreCommerciale' => 300,
  ];
  
$nbVersions = 10;
  

// Connecting to Couchbase
$cluster = new CouchbaseCluster("http://db");
$cb = $cluster->openBucket("catalogue-volumetrie", "");
$cb->enableN1ql(array('http://db:8093/'));  



// foreach ($modelesFlux as $k => $flux) {
  // for($i=0; $i<$nbDocumentsDemandes[$k]; $i++) {
  // for($i=0; $i<4; $i++) {

  
    // echo $nbDocumentsDemandes[$k].' : '.$flux.'<br/>';
    // $cb->upsert($k.'_'.($i+1), $flux);
  // }
// }

for($version=0; $version < $nbVersions; $version++) {

  foreach ($nbDocumentsDemandes as $k => $v) {
    for($i=0; $i<$v; $i++) {
      $modelesFlux = [
    'usine' => '
  {
    "type": "usine",
    "libelle": "'.generate("string",7,30,$version).'"
  }
    ',
    'produitTechnique' => '
  {
    "type": "produitTechnique",
    "libelle": "'.generate("string",40,60,$version).'",
    "libelleCourt": "'.generate("string",7,15,$version).'",
    "varianteProduit": "'.generate("nb",4,6,$version).'",
    "idUsine": "'.generate("id","usine","",$version).'"
  }  
    ',
    'operation' => '
  {
    "type": "operation",
    "libelle": "'.generate("string",10,20,$version).'"
  }    
    ',
    'unite' => '
  {
    "type": "unite",
    "libelle": "'.generate("string",7,15,$version).'",
    "valeur": "'.generate("string",7,15,$version).'"
  }
    ',
    'valeur' => '
  {
    "type": "valeur",
    "libelle": "'.generate("intOrString",10,15,$version).'",
    "idUnite": "'.generate("id","unite","",$version).'"
  }
    ',
    'natureCaracteristique' => '
  {
    "type": "natureCaracteristique",
    "libelle": "'.generate("string",7,15,$version).'"
  }
    ',
    'caracteristiqueTableau' => '
  {
    "type": "caracteristique",
    "format": "simple",
    "libelle": "'.generate("string",20,50,$version).'",
    "nature": "'.generate("id","natureCaracteristique","",$version).'",
    "valeurAutorisees": [
      '.generate("id","valeur",10,$version).'
    ],
    "valeurParDefaut": "'.generate("id","valeur","",$version).'"
  }
    ',
    'caracteristique' => '
  {
    "type": "caracteristique",
    "format": "plage",
    "libelle": "'.generate("string",20,50,$version).'",
    "nature": "'.generate("id","natureCaracteristique","",$version).'",
    "valeurMinimale": "'.generate("id","valeur","",$version).'",
    "valeurMaximale": "'.generate("id","valeur","",$version).'",
    "valeurParDefaut": "'.generate("id","valeur","",$version).'"
  }
    ',
    'produitModele' => '
  {
    "type": "produitModele",
    "libelle": "'.generate("string",20,50,$version).'"
  }
    ',
    'produitGenerique' => '
  {
    "type": "produitGenerique",
    "libelle": "'.generate("string",30, 50,$version).'",
    "idProduitModele": "'.generate("id","produitModele","",$version).'",
    "caracteristiques": [
      '.generate("caracteristiques",5,20,$version).'
    ]
  }  
    ',
    'produitCommercial' => '
  {
    "type": "produitCommercial",
    "libelle": "'.generate("string",30, 50,$version).'",
    "idProduitGenerique": "'.generate("id","produitGenerique","",$version).'",
    "categoriesAffichage": "categorie affichage a definir",
    "caracteristiques": [
      '.generate("caracteristiques",3,10,$version).' 
    ]
  }  
    ',
    'offreCommerciale' => '
  {
    "type": "offreCommerciale",
    "libelle": "'.generate("string",30, 50,$version).'",
    "idProduitCommercial": "'.generate("id","produitCommercial","",$version).'",
    "codeTarifaire": "GrilleTarifaireADefinir",
    "caracteristiques": [
      '.generate("caracteristiques",0,5,$version).' 
    ]
  }'
  ];


      echo $k.'_'.($i+1).'_V'.$version.' : '.$modelesFlux[$k].'<br/><br/>';
      $cb->upsert($k.'_'.($i+1).'_V'.$version, $modelesFlux[$k] );

    }
  }

  
}






function generate($type,$min,$max,$version) {
  global $nbDocumentsDemandes; 

    
  switch($type) {
    case "string": 
      $melange = str_shuffle('abc def ghijklmn opqrstuv wxyz012 34567 89ABCDEFGHIJKLMNOPRSTUVWXYZ');
      return 'V'.$version.'_'.substr($melange,0, rand($min,$max)); 
    case "nb":
      $melange = str_shuffle('0123456789');
      return substr($melange,0, rand($min,$max));
    case "id":
      if($max > 0) {
        $imax = rand(1,$max);
        $ret = '';
        for($i=0; $i<$imax; $i++) {
          $ret .= ',"'.$min.'_'.rand(1,$nbDocumentsDemandes[$min]).'"';
        }
        return substr($ret,1).'_V'.$version;
      }
      else
        return $min.'_'.rand(1,$nbDocumentsDemandes[$min]).'_V'.$version;
    case "intOrString":
      if(rand(0,9) > 6)
        $melange = 'V'.$version.'_'.str_shuffle('abc def ghijklmn opqrstuv wxyzABCDEFGHIJKLMNOPRSTUVWXYZ');
      else
        $melange = str_shuffle('1234567890');
      return substr($melange,0, rand($min,$max));
      
      
    case "caracteristiques":
      $ret = '';
      $imax = rand(1,4);
      for ($i=0; $i<$imax; $i++) {
        $ret .= ',{ "operation": "operation_'.rand(1,$nbDocumentsDemandes['operation']).'_V'.$version.'",';
        $ret .= '"caracteristiques": [ ';
          $jmax = rand($min,$max);
          $ret2 = '';
          for($j=0; $j<$jmax; $j++) {
            $ret2 .= ',{ "caracteristique": "caracteristique_'.rand(1,$nbDocumentsDemandes['caracteristique']).'_V'.$version.'", "valeur": "valeur_'.rand(1,$nbDocumentsDemandes['valeur']).'_V'.$version.'" }';
          }
        $ret .= substr($ret2,1);
        $ret .= '] }';
      }
      return substr($ret,1);
    
  }
  return '**'.$type;
}
