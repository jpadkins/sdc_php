<?php
// inclusions
require 'vendor/autoload.php';

// glabal variables
$app = new \Slim\Slim();
$m = new MongoClient();
$db = $m->cms;

// make sure the cms db contains a 'data' collection with
// a document { name: 'data', 'Capabilities': [], 'Products': [] }
//
// to migrate database from old backup run these commands in mongo shell:
//
// db.images.update({}, {$set: {'Caption' : ''}}, false, true)
// db.images.update({}, {$set: {'Rank' : []}}, false, true)


/* ENDPOINTS */

// returns image and category data object for the website
$app->get('/cms', function () use ($app, $db) {
  $data = $db->data;
  $data = iterator_to_array($data->find());
  $dat_array = array();
  foreach ($data as $doc) {
    array_push($dat_array, $doc);
  }
  $images = $db->images;
  $images = iterator_to_array($images->find());
  $img_array = array();
  foreach ($images as $doc) {
    array_push($img_array, $doc);
  }
  $response = array('imgs' => $img_array, 'capas' => $dat_array[0]['Capabilities'], 'prods' => $dat_array[0]['Products']);
  echo json_encode($response);
});

// returns an array of all image names in the database
$app->get('/cms/img', function() use ($app, $db) {
  $query = $db->images->find(array(), array("filename" => 1));
  $images = array();
  foreach ($query as $doc) {
    array_push($images, $doc['filename']);
  }
  echo json_encode($images);
});

// uploads an image to the uploads folder and updates the database
$app->post('/cms/img', function () use ($app, $db) {
  // move file
  $target_dir = __DIR__ . '/../images/uploads/';
  $target_file = $target_dir . basename($_FILES['file_to_upload']['name']);
  move_uploaded_file($_FILES['file_to_upload']['tmp_name'], $target_file);
  //header("Location: localhost:9000/webapp_new/");
  // update database
  $doc = array('filename' => $_FILES['file_to_upload']['name'], 'Capabilities' => array(),
    'Products' => array(), 'Flags' => array(), 'Caption' => '');
  $db->images->insert($doc);
  exit();
});

// all-purpose database tag updating function
$app->put('/cms/img', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $operation = $inputs['operation'];
  $category = $inputs['category'];
  $file = $inputs['file'];
  $name = $inputs['name'];
  // push
  if ($operation == 1) {
    switch ($category) {
      case 'capa':
        $db->images->update(array('filename' => $file), array('$push' => array('Capabilities' => $name)));
        break;
      case 'prod':
        $db->images->update(array('filename' => $file), array('$push' => array('Products' => $name)));
        break;
      case 'flag':
        $db->images->update(array('filename' => $file), array('$push' => array('Flags' => $name)));
        break;
    }
  }
  // pull
  else {
    switch ($category) {
      case 'capa':
        $db->images->update(array('filename' => $file), array('$pull' => array('Capabilities' => $name)));
        break;
      case 'prod':
        $db->images->update(array('filename' => $file), array('$pull' => array('Products' => $name)));
        break;
      case 'flag':
        $db->images->update(array('filename' => $file), array('$pull' => array('Flags' => $name)));
        break;
    }
  }
  echo json_encode('success');
});

// deletes an image in the uploads folder
$app->delete('/cms/img', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $target_file = $inputs['filename'];
  $target_dir = __DIR__ . '/../images/uploads/';
  $db->images->remove(array('filename' => $target_file));
  unlink($target_dir . $target_file);
  echo json_encode('success');
});

// returns all of the tags associated with an image
$app->post('/cms/img/tags', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $file = $inputs['filename'];
  $query = $db->images->findOne(array('filename' => $file), array('Capabilities' => 1,
    'Products' => 1, 'Flags' => 1));
  $tags = array('Capabilities' => $query['Capabilities'], 'Products' => $query['Products'],
    'Flags' => $query['Flags']);
  echo json_encode($tags);
});

// sets the importance rank of the images
$app->post('/cms/img/rank', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);  
  foreach($inputs as $doc) {
    $db->images->update(array('filename' => $doc['filename']), array('$unset' => array('Rank' => array($doc['category'] => ''))));
    $db->images->update(array('filename' => $doc['filename']), array('$push' => array('Rank' => array($doc['category'] => $doc['rank']))));
    echo json_encode(implode($doc));
  }
  echo json_encode('success');
});

$app->get('/cms/img/caption', function() use ($app, $db) {
  $filename = $app->request()->params('filename');
  $query = $db->images->findOne(array('filename' => $filename), array('Caption' => 1));
  echo json_encode($query['Caption']);
});

$app->post('/cms/img/caption', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $filename = $inputs['filename'];
  $caption = $inputs['caption'];
  $db->images->update(array('filename' => $filename), array('$set' => array('Caption' => $caption)));
  echo json_encode('success');
});

// returns array of all capability category names
$app->get('/cms/capa', function() use ($app, $db) {
  $query = $db->data->findOne(array(), array("Capabilities" => 1));
  echo json_encode($query['Capabilities']);
});

// adds a new capability category name
$app->post('/cms/capa', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $capa = $inputs['capa'];
  $db->data->update(array('name' => 'data'), array('$push' => array('Capabilities' => $capa)));
  echo json_encode('success');
});

// update capability category name
$app->put('/cms/capa', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $capa = $inputs['capa'];
  $name = $inputs['name'];
  // update value in data collection
  $db->data->update(array('name' => 'data'), array('$pull' => array('Capabilities' => $capa)));
  $db->data->update(array('name' => 'data'), array('$addToSet' => array('Capabilities' => $name)));
  // update value wherever it appears in images collection
  $response = $db->images->find(array('Capabilities' => $capa), array('filename' => 1));
  foreach ($response as $id => $value) {
    $db->images->update(array('filename' => $value['filename']), array('$pull' => array('Capabilities' => $capa)));
    $db->images->update(array('filename' => $value['filename']), array('$addToSet' => array('Capabilities' => $name)));
  }
  // update value in flags for color
  $old_str = '{"rep_bw":"' . $capa . '"}';
  $new_str = '{"rep_bw":"' . $name . '"}';
  $response = $db->images->find(array('Flags' => $old_str), array('filename' => 1));
  foreach ($response as $id => $value) {
   $db->images->update(array('filename' => $value['filename']), array('$pull' => array('Flags' => $old_str)));
   $db->images->update(array('filename' => $value['filename']), array('$addToSet' => array('Flags' => $new_str)));
  }
  $old_str = '{"rep_color":"' . $capa . '"}';
  $new_str = '{"rep_color":"' . $name . '"}';
  $response = $db->images->find(array('Flags' => $old_str), array('filename' => 1));
  foreach ($response as $id => $value) {
   $db->images->update(array('filename' => $value['filename']), array('$pull' => array('Flags' => $old_str)));
   $db->images->update(array('filename' => $value['filename']), array('$addToSet' => array('Flags' => $new_str)));
  }
  echo json_encode('success');
});

// delete a capability category
$app->delete('/cms/capa', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $capa = $inputs['capa'];
  $db->data->update(array('name' => 'data'), array('$pull' => array('Capabilities' => $capa)));
  echo json_encode('success');
});

// returns array of all product category names
$app->get('/cms/prod', function() use ($app, $db) {
  $query = $db->data->findOne(array(), array("Products" => 1));
  echo json_encode($query['Products']);
});

// adds a new product category name
$app->post('/cms/prod', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $prod = $inputs['prod'];
  $db->data->update(array('name' => 'data'), array('$push' => array('Products' => $prod)));
  echo json_encode('success');
});

// update product category name
$app->put('/cms/prod', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $prod = $inputs['prod'];
  $name = $inputs['name'];
  // update value in data collection
  $db->data->update(array('name' => 'data'), array('$pull' => array('Products' => $prod)));
  $db->data->update(array('name' => 'data'), array('$addToSet' => array('Products' => $name)));
  // update value wherever it appears in images collection
  $response = $db->images->find(array('Products' => $prod), array('filename' => 1));
  foreach ($response as $id => $value) {
    $db->images->update(array('filename' => $value['filename']), array('$pull' => array('Products' => $prod)));
    $db->images->update(array('filename' => $value['filename']), array('$addToSet' => array('Products' => $name)));
  }
  // update value in flags for color
  $old_str = '{"rep_bw":"' . $prod . '"}';
  $new_str = '{"rep_bw":"' . $name . '"}';
  $response = $db->images->find(array('Flags' => $old_str), array('filename' => 1));
  foreach ($response as $id => $value) {
   $db->images->update(array('filename' => $value['filename']), array('$pull' => array('Flags' => $old_str)));
   $db->images->update(array('filename' => $value['filename']), array('$addToSet' => array('Flags' => $new_str)));
  }
  $old_str = '{"rep_color":"' . $prod . '"}';
  $new_str = '{"rep_color":"' . $name . '"}';
  $response = $db->images->find(array('Flags' => $old_str), array('filename' => 1));
  foreach ($response as $id => $value) {
   $db->images->update(array('filename' => $value['filename']), array('$pull' => array('Flags' => $old_str)));
   $db->images->update(array('filename' => $value['filename']), array('$addToSet' => array('Flags' => $new_str)));
  }
  echo json_encode('success');
});

// delete a product category
$app->delete('/cms/prod', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);
  $prod = $inputs['prod'];
  $db->data->update(array('name' => 'data'), array('$pull' => array('Products' => $prod)));
  echo json_encode('success');
});

// sends out an email
$app->post('/cms/email', function() use ($app, $db) {
  $request = $app->request();
  $inputs = json_decode($request->getBody(), true);

  $name = $inputs['name'];
  $company = $inputs['company'];
  $phone = $inputs['phone'];
  $email = $inputs['email'];
  $city = $inputs['city'];
  $state = $inputs['state'];
  $info = $inputs['info'];

  $date = date('m/d/Y h:i:s a', time());

  $to = 'vision@thestoredecor.com';
  $subject = 'Contact Us Form Submission';
  $message = 'Someone filled out the contact form at: ' . $date . "\r\n"
    . 'name: ' . $name . "\r\n" . 'company: ' . $company . "\r\n"
    . 'phone: ' . $phone . "\r\n" . 'email: ' . $email . "\r\n"
    . 'city: ' . $city . "\r\n" . 'state: ' . $state . "\r\n"
    . 'info: ' . $info;

  $headers = 'From: webmaster@thestoredecor.com' . "\r\n" .
    'Reply-To: webmaster@thestoredecor.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

  if (mail($to, $subject, $message, $headers)) {
    echo json_encode('success');
  }
  else {
    echo json_encode('error');
  }
});

$app->run()
?>
