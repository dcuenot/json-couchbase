<?php
/**
 * Copyright (C) 2009-2012 Couchbase, Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALING
 * IN THE SOFTWARE.
 */

use Symfony\Component\HttpFoundation\Request;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;



/**
 * Config Block.
 *
 * To keep all settings (not many) organized in one place, they are defined
 * here as constants. They could be done inline as well, but keeping them in
 * one place makes it better organized and easier to refactor.
 */
define("SILEX_DEBUG", true);
define("COUCHBASE_CONNSTR", "http://db");
define("COUCHBASE_BUCKET", "catalogue-assemblage");
//define("COUCHBASE_BUCKET", "beer-sample");
define("COUCHBASE_PASSWORD", "");

define("INDEX_DISPLAY_LIMIT", 20);

/**
 * Init Block.
 *
 * This block requires the autoloader and initializes the Silex Application.
 * It also connects to our Couchbase Cluster and registeres the template
 * engine (Twig).
 */
 
 
// Autoloader
require_once __DIR__.'/vendor/autoload.php';

// Silex-Application Bootstrap
$app = new Application();
$app['debug'] = SILEX_DEBUG;

// Connecting to Couchbase
$cluster = new CouchbaseCluster(COUCHBASE_CONNSTR);
$cb = $cluster->openBucket(COUCHBASE_BUCKET, COUCHBASE_PASSWORD);
$cb->enableN1ql(array('http://db:8093/'));

// Commande pour transférer des données
// /opt/couchbase/bin/cbtransfer http://127.0.0.1:8091 http://127.0.0.1:8091 -u Administrator -p Password -b catalogue-assemblage -B catalogue-volumetrie

// Commande pour créer l'index
// /opt/couchbase/bin/cbq CREATE PRIMARY INDEX on `catalogue-assemblage` USING GSI;

// Liste des documents via N1QL
$query = CouchbaseN1qlQuery::fromString('
  SELECT * FROM `catalogue-assemblage` WHERE type="admin";
');
$res = $cb->query($query);
$tmp = $res[0];
$liste = $tmp->{'catalogue-assemblage'}->listeCategorie;


$pages = array();
$categories['modeles'] = ['admin'];
foreach($liste as $k => $v) {
    if(isset($categories[$k])) $categories[$k] = array_merge($categories[$k], $v);
    else $categories[$k] = $v;
    
    $pages = array_merge($pages, $categories[$k]);
}




// Register the Template Engine
$app->register(new TwigServiceProvider(), array(
	'twig.path' => __DIR__.'/templates'
));

/**
 * Action Block.
 *
 * From here on all actions are defined as simple closures. They render view
 * templates or direct responses as needed.
 */


$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);

        $request->request->set('fluxJson', is_array($data) ? $data : array());
    }
});

// Show the Welcome Page (GET /)
$app->get('/', function() use ($app, $cb) {
    global $categories;
  
    // Modèles non gérés actuellement, donc supprimer
    unset($categories['modeles']);

    return $app['twig']->render('welcome.twig.html', compact('categories'));
});




//$pages = array('usine','produitTechnique','operation','unite','valeur','caracteristique');
foreach($pages as $page) {

    $app->get('/'.$page, function(Request $request) use ($app, $cb) {
        return listeTypeDocument(cutUri($request), $app, $cb);
    });

    $app->get('/'.$page.'/json', function(Request $request) use ($app, $cb) {
        return $app->json(listeDocumentJSON(cutUri($request), $app, $cb), 200);
    });
    
    $app->get('/'.$page.'/show/{id}', function(Request $request, $id) use ($app, $cb) {
        return getDocument($id, cutUri($request), $app, $cb);
    });

    $app->get('/'.$page.'/edit/{id}', function(Request $request, $id) use ($app, $cb) {
        return addDocument($id, cutUri($request), $app, $cb);
    })->value('id', 'new');


    $app->post('/'.$page.'/edit/{id}', function(Request $request, $id) use ($app, $cb) {
        return pushJson($request,$id,cutUri($request),$app,$cb);
    })->value('id', 'new');

}




/*********************************
Fonctions transverses
 ***********************************/
 function listeDocumentJSON($type, $app, $cb) {
    if ("caracteristique" == substr($type,0,15) and strlen($type) > 16 ) {
      return json_decode($cb->get($type)->value);    
    }
    
    
    // Liste des documents via N1QL
    $query = CouchbaseN1qlQuery::fromString('
      SELECT meta(`catalogue-assemblage`).id, libelle
      FROM `catalogue-assemblage` WHERE type="'.$type.'";
    ');

    $res = $cb->query($query);
    //var_dump($res);

    $documents['type'] = "string";
    //$documents['title'] = "Lien vers ".$type;
    $documents['enumSource'] = array();
    foreach($res as $row) {
        $documents['enumSource'][0]['source'][] = array(
            'title' => $row->libelle,
            'value' => $row->id
        );
    }
    $documents['enumSource'][0]['title'] = "{{item.title}}";
    $documents['enumSource'][0]['value'] = "{{item.value}}";
    
    //var_dump($documents);
    
 

    return $documents;

}
 
function listeTypeDocument($type, $app, $cb) {
    // Liste des documents via N1QL
    $query = CouchbaseN1qlQuery::fromString('
      SELECT meta(`catalogue-assemblage`).id, libelle
      FROM `catalogue-assemblage` WHERE type="'.$type.'";
    ');

    $res = $cb->query($query);
    //var_dump($res);

    $documents['type'] = $type;
    $documents['docs'] = array();
    foreach($res as $row) {
        $documents['docs'][] = array(
            'libelle' => $row->libelle,
            'id' => $row->id
        );
    }
    //var_dump($documents);


    return $app['twig']->render('elementsUnitaires/index.twig.html', compact('documents') );

}

function addDocument($id, $type, $app, $cb) {
    // récupération du modèle
    $modele = $cb->get($type);
    if($modele) {
        $modele_array = array();
        if($id !== 'new') {
            $modele_array = json_decode($modele->value,1)['items'];
            unset($modele_array['headerTemplate']);
        } else {
            $modele_array = json_decode($modele->value,1);
        }
        $doc = array();
        $doc['modele'] = preg_replace('/\"(\w+)\"\:[[:blank:]]/i','$1: ',json_encode($modele_array, JSON_PRETTY_PRINT));
        $doc['modele'] = str_replace('"$ref":','$ref:',$doc['modele']);
        $doc['type'] = $type;
        $doc['data'] = '{}';
        $doc['url'] = '';
    } else {
        fail($type.' inconnu');
    }

    if($id !== 'new') {
        // récupération des infos en base
        $res = $cb->get($id);
        $doc['data'] = preg_replace('/\"(\w+)\"\:[[:blank:]]/i','$1: ',json_encode(json_decode($res->value), JSON_PRETTY_PRINT));$res->value;
        $doc['modele'] = str_replace('"$ref:"','$ref:',$doc['modele']);
        $doc['url'] = '/'.$id;
    }

    return $app['twig']->render('elementsUnitaires/edit.twig.html', compact('doc') );
}

function getDocument($id, $type, $app, $cb) {
    if($id !== 'new') {
        // récupération des infos en base
        $res = $cb->get($id);
        $doc['data'] = json_encode(json_decode($res->value), JSON_PRETTY_PRINT);
        $doc['url'] = '/'.$id;
        $doc['type'] = $type;
        $doc['id'] = $id;
    }

    return $app['twig']->render('elementsUnitaires/show.twig.html', compact('doc') );
}

function pushJson($request, $id, $type, $app, $cb) {
    $data = $request->request->get('fluxJson');
    //var_dump($data);

    if($id == 'new') {
        $query = CouchbaseN1qlQuery::fromString('SELECT COUNT(*) AS nb FROM `catalogue-assemblage` WHERE type="'.$type.'"; ');
        $res = $cb->query($query);
        //var_dump($res);

        if($res[0]->nb > 0) $nb = ($res[0]->nb + 1); else $nb = 1;

        for($i=0; $i<sizeof($data); $i++) {
            $array = $data[$i];
            $array['type'] = $type;
            $cb->insert($type.'_'.($nb+$i), json_encode($array));
        }
    } else {
        $cb->upsert($id, json_encode($data));
    }

    return $app->json($array, 200);
}




function cutUri($request) {
    $tmp = substr(str_replace($request->getBasePath(),'',$request->getRequestUri()),1);
    if(strpos($tmp,'/') !== false)
        return substr($tmp,0,strpos($tmp,'/'));

    return $tmp;
}

// Run the Application
$app->run();
?>
