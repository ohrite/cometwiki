<?php
  include_once "twitter.php";
  include_once "htmlpurifier-4.1.0/HTMLPurifier.auto.php";

  function prettyDate($date){
    // summary
    // Pretty-print the current date.
    // Credit: jresig's prettyDate.
    $now = time();
    $diff = ($now - $date);
    $day_diff = floor($diff / 86400);
    
    // short-circuit: invalid state
    if (is_nan($day_diff) || $day_diff < 0 || $day_diff >= 31){ return "never"; }
    
    // less than a day
    if ($day_diff == 0) {
      // under a minute
      if ($diff < 60) {
        return "just now";
        
      // less than two minutes
      } else if ($diff < 120) {
        return "about a minute ago";
        
      // less than an hour
      } else if ($diff < 3600) {
        return floor($diff / 60) . " minutes ago";
        
      // hours
      } else {
        return floor($diff / 3600) . " hours ago";
      }
      
    // a day or more
    } else {
      // less than two days
      if ($day_diff == 1) {
        return "yesterday";
        
      // less than a week
      } else if ($day_diff < 7) {
        return $day_diff . " days ago";
        
      // a long time ago
      } else {
        return ceil($day_diff / 7) . " weeks ago";
      }
    }
  }

  class WikiNotFoundException extends Exception {}

  class Wiki {
    // summary
    // A Wiki, written in PHP.
    
    private $handle = null;
    private $db = "/var/db/" . $_ENV['COMETWIKI_DB'] . ".db";
    private $topics = null;
    
    private $twitter = null;
    private $purifier = null;

    private $twitter_user = $_ENV['COMETWIKI_USER'];
    private $twitter_passwo = $_ENV['COMETWIKI_PASSWORD'];
    
    public function connect() {
      // summary
      // Ensure a connection handle to the database.
      
      // if the handle doesn't exist
      if ($this->handle == null) {
        try {
          // initalize a new PDO connection to the database
          $this->handle = new PDO("sqlite:" . $this->db);
          
          // set exception-mode error handling
          $this->handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          
        } catch(PDOException $e) {
          // die with positional message
          print("Error during Wiki.connect():" . $e->getMessage());
          die();
        }
      }
    }
    
    public function disconnect() {
      // summary
      // Ensure the database is disconnected.
      
      // null out the handle
      $this->handle = null;
    }
    
    public function read($limit) {
      // summary
      // Generate and output a list of the most-visited topics.
      $res = array();
      
      // ensure a connection to the database
      $this->connect();
      
      // if the list of topics doesn't currently exist
      if ($this->topics == null) {
        if (!isset($limit)) {
          $this->topics = $res;
        }
        
        // declare a query string
        $query = "SELECT  edit.topic_id AS id, topic.title AS title, COUNT(edit.topic_id) as edits FROM edit JOIN topic ON edit.topic_id = topic.id GROUP BY edit.topic_id ORDER BY edits DESC";
        
        // if limit has been provided, append that to the query
        if (isset($limit)) {
          $query = $query . " LIMIT " . $limit;
        }
        
        // hit the database for a list of topics
        foreach ($this->handle->query($query) as $row) {
          $res[$row["id"]] = $row;
        }

      // the full list of topics exists
      } else {
        $res = array_slice($this->topics, 0, $limit);
      }
      
      return $res;
    }
    
    public function resolve($user_title) {
      // summary
      // Map a given title string to a topic row.
      $res = null;
      $title = str_replace("'", "", str_replace("_", " ", strtolower($user_title)));
      
      // ensure a connection to the database
      $this->connect();
      
      // hit the database for a list of topics
      foreach ($this->handle->query("SELECT title, id, created FROM topic WHERE title = '" . $title . "' LIMIT 1") as $row) {
        $res = $row;
      }
      
      // if we don't have all the values
      if ($res == null) {
        // throw a new wiki not found exception
        throw new WikiNotFoundException("Topic not found for " . $title);
      }
      
      // remove numeric indices
      unset($res[0]);
      unset($res[1]);
      unset($res[2]);
      
      return $res;
    }
    
    public function exists($topic_id) {
      // summary
      // Determine if a given topic id exists.
      $res = null;
      
      // ensure a connection to the database
      $this->connect();
      
      // hit the database for a list of topics
      foreach ($this->handle->query("SELECT id FROM topic WHERE id = '" . $topic_id . "' LIMIT 1") as $row) {
        $res = $row;
      }
      
      return ($res != null);
    }
    
    public function get($topic_id) {
      // summary
      // Get a record by its identifier.
      $res = null;
      
      // ensure a connection to the database
      $this->connect();
      
      // hit the database for a list of topics
      foreach ($this->handle->query("SELECT id, title, created FROM topic WHERE id = '" . $topic_id . "' LIMIT 1") as $row) {
        $res = $row;
      }
      
      // if we don't have all the values
      if ($res == null) {
        // throw a new wiki not found exception
        throw new WikiNotFoundException("Topic not found for " . $topic_id);
      }
      
      return $res;
    }
    
    public function stat($topic) {
      // summary
      // Get a summary of statistics for the given topic.
      $res = null;
      
      // ensure a connection to the database
      $this->connect();
      
      // snag updated stats
      foreach ($this->handle->query("SELECT created AS updated, content, COUNT(id) AS version FROM edit WHERE topic_id = " . $topic["id"] . " ORDER BY created DESC LIMIT 1") as $row) {
        // if the version is zero, provide some null values
        if ($row["version"] == 0){
          $row["updated"] = 0;
          $row["content"] = "";

        // there's a valid value here
        } else {
          // render the updated value
          $row["updated"] = $row["updated"];
        }
        
        // assign the row object out of scope
        $res = $row;
      }
      
      // content sanity checks
      unset($res[0]);
      unset($res[1]);
      unset($res[2]);
      
      return $res;
    }
    
    public function history($topic, $limit) {
      // summary
      // Get a list of the edits done on a certain topic, with character counts.
      $res = array();
      
      // ensure a connection to the database
      $this->connect();
      
      // set the query
      $query = "SELECT location, created, LENGTH(content) AS length FROM edit WHERE topic_id = " . $topic["id"] . " ORDER BY created DESC";
      
      // if the limit is set
      if (isset($limit)) {
        $query = $query . " LIMIT " . $limit;
      }
      
      // hit the database for a list of topics
      foreach ($this->handle->query($query) as $row) {
        // compute the row delta
        $row["delta"] = $row["length"] - (count($res) ? $res[count($res) - 1]["length"] : 0);
        
        // unset the numeric row indices
        unset($row[0]);
        unset($row[1]);
        unset($row[2]);
        
        // shift the row onto the end
        array_push($res, $row);
      }
      
      return $res;
    }
    
    public function create($user_title) {
      // summary
      // Create a new topic.
      $res = null;
      $title = str_replace("_", " ", strtolower($user_title));
      
      // ensure a connection to the database
      $this->connect();
      
      // execute an insert with the title
      $this->handle->exec("INSERT INTO topic (title, created) VALUES ('" . $title . "', strftime('%s','now'))");
      
      foreach ($this->handle->query("SELECT id FROM topic WHERE title = '" . $title . "'") as $row) {
        $res = $row["id"];
      }
      
      // execute an insert with the topic
      $this->handle->exec("INSERT INTO edit (topic_id, created) VALUES (" . $res . ", strftime('%s','now'))");
      
      return $this->resolve($title);
    }
    
    public function put($topic, $content, $location) {
      // summary
      // Post a new edit for the given topic.
      
      // ensure a connection to the database
      $this->connect();
      
      // if the twitter object doesn't exist, make it
      if ($this->twitter == null) {
        $this->twitter = new Twitter($this->twitter_user, $this->twitter_password);
      }
      
      // if the purifier object doesn't exit, make it
      if ($this->purifer == null) {
        $this->purifier = new HTMLPurifier();
      }
      
      // if the location is set
      if (!isset($location)) {
        $query = "INSERT INTO edit (topic_id, content, created) VALUES (" . $topic["id"] . ",'" . $this->purifier->purify($content) . "',strftime('%s','now'))";
        
      // the location is currently not set
      } else {
        $query = "INSERT INTO edit (topic_id, content, location, created) VALUES (" . $topic["id"] . ",'" . $this->purifier->purify($content) . "','" . $location . "',strftime('%s','now'))";
      }
      
      // execute the query
      $this->handle->exec($query);
      
      try {
        // update twitter status
        $this->twitter->updateStatus("Updated " . $topic["title"]);
      } catch (TwitterException $e) {
        // ignore the twitter exception
      }
    }
    
    public function delete($topic) {
      // summary
      // Delete the given topic.
      
      // not implemented for now
    }
  }
?>
