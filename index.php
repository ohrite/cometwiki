<!DOCTYPE html>
<html>
  <head>
    <title>cometwiki</title>
    <link rel="stylesheet" type="text/css" href="/css/screen.css" />
    <link rel="stylesheet" type="text/css" href="/css/site.css" />
    <link rel="icon" href="/favicon.ico" type="image/x-icon" />
  </head>
  <?php
    try {
      include "lib/wiki.class.php";
      
      // create a new wiki instance and connect to it
      $wiki = new Wiki();
      
      // get a list of the 5 most popular topics
      $topics = $wiki->read(5);
      
      // get the title
      $title = isset($_GET["q"]) ? htmlentities(str_replace("_", " ", ucwords($_GET["q"]))) : "index";
      
      try {
        // resolve the topic
        $current = $wiki->resolve($title);
        
      } catch (WikiNotFoundException $nf){
        // create the topic
        $current = $wiki->create($title);
      }
      
      // merge the stats with the current item
      $current = array_merge($current, $wiki->stat($current));
      
      // resolve the history
      $history = $wiki->history($current, 10);
      
      // make a new markers array
      $markers = array();
      
      // loop through history
      foreach($history as $event) {
        // if the location is worthwhile
        if (isset($event["location"]) && strlen($event["location"]) > 0) {
          array_push($markers, "markers=" . $event["location"]);
        }
      }
      
    } catch (Exception $e) {
      $error = $e;
    }
  ?>
  <body>
    <div class="container" id="container">
      <div class="span-23 box last">
        <h1>
          Comet Wiki
          <a href="/files/comet-wiki.tgz" style="font-size:0.5em;font-weight:normal">source</a>
          <a href="http://twitter.com/cometwiki" style="font-size:0.5em;font-weight:normal">twitter stream</a>
        </h1>
        <?php if (isset($error)){ ?>
            <div class="span-22 box error last">
              <?= $error ?>
            </div>
        <?php } ?>
      </div>
      <div class="span-5 append-1">
        <?php if (isset($topics)) { ?>
          <h4>Popular Topics</h4>
          <ol>
            <?php foreach ($topics as $topic) { ?>
              <li><a href="/<?= str_replace(" ", "_", ucwords($topic["title"])) ?>"><?= ucwords($topic["title"]) ?></a></li>
            <?php } ?>
          </ol>
          <hr />
        <?php } ?>
        
        <h4>Last Edits</h4>
        <ol id="history">
          <?php if (isset($history)) { ?>
            <?php foreach ($history as $event) { ?>
              <li><?= $event["delta"] < 0 ? "Deleted" : "Added" ?> <?= abs($event["delta"]) ?> characters <br /><?= prettyDate($event["created"]) ?></li>
            <?php } ?>
          <?php } ?>
        </ol>
        <img src="http://maps.google.com/maps/api/staticmap?sensor=false&amp;size=190x200&amp;maptype=roadmap&amp;<?= join("&amp;", $markers) ?>" />
      </div>
      <div class="span-17 box last">
        <h2><?= ucwords($current["title"]) ?></h2>
        <div class="control-box">
          <a id="edit-link" href="#" style="float:right;">Edit page</a>
          <span>Version <span id="current-version"><?= $current["version"] ?></span></span>
          <span>Created <?= prettyDate($current["created"]) ?></span>
          <span>Updated <span id="updated-time"><?= prettyDate($current["updated"]) ?></span></span>
        </div>
        <div id="content"><?= $current["content"] ?></div>
      </div>
    </div>
    
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
    <script src="http://www.google.com/jsapi"></script>
    <script src="/js/site.js"></script>
    <script>
      setTimeout(function(){
        CometWiki.init(<?= $current["id"] ?>, function(data){
          $("#current-version").html(data.version);
          $("#updated-time").html(prettyDate(data.updated));

          $("#history").prepend(["<li>", data.delta < 0 ? "Deleted " : "Added ", data.delta, " characters <br />", prettyDate(data.updated), "</li>"].join(""));
          $("#content").html(data.content);
        });
      
        // bind to a click event for edit
        $("#edit-link").click(function(){
          if ($("#edit-link").html() == "Save edits") {
            CometWiki.put($("#content").html() || "");
            $("#content").attr("contentEditable", false);
            $("#edit-link").html("Edit page");
          } else {
            $("#content").attr("contentEditable", true);
            $("#edit-link").html("Save edits");
          }
        
          return false;
        });
      },0);
    </script>
  </body>
  <?php
    if (isset($wiki)){
      // disconnect from the wiki
      $wiki->disconnect();
      $wiki = null;
    }
  ?>
</html>
