<?php
  include "lib/wiki.class.php";
  
  // short-circuit: no incoming topic
  if (!isset($_GET['topic'])) { die(); }
  
  // create a new wiki instance and connect to it
  $wiki = new Wiki();
  
  // short-circuit: doesn't exist, not valid
  if (!$wiki->exists($_GET['topic'])){ die(); }
  
  // get a reference to the topic
  $topic = $wiki->get($_GET['topic']);
  
  // if there is incoming content, write it and die
  if (isset($_GET['content'])) {
    $wiki->put($topic, $_GET['content'], $_GET['location']);
    flush();
    die();
  }
  
  // set the last timestamp
  $last = isset($_GET['timestamp']) ? $_GET['timestamp'] : 0;
  
  // fetch the last topic modification
  $stat = $wiki->stat($topic);
  
  // loop until the topic's timestamp is updated or the loop runs its course
  for ($i = 0; $i < 10 && $last >= $stat['updated']; $i++) {
    // spin for 3s
    usleep(3000000);
    
    // fetch the last topic modification
    $stat = $wiki->stat($topic);
  }
  
  // get some history
  $history = $wiki->history($topic, 2);

  // snag the delta  
  $stat["delta"] = $history[0]["delta"];
  
  // encode the topic and push
  echo json_encode($stat);
  flush();
?>