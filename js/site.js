(function(){
  // jresig's date printer
  prettyDate = window.prettyDate = function prettyDate(time){
    var date = new Date(parseInt(time) * 1000),
        diff = ((new Date()).getTime() - date.getTime()) / 1000,
        day_diff = Math.floor(diff / 86400);
    
    if (isNaN(day_diff) || day_diff < 0 || day_diff >= 31) { return "never"; }
    
    return day_diff == 0 && (
        diff < 60 && "just now" ||
        diff < 120 && "1 minute ago" ||
        diff < 3600 && Math.floor( diff / 60 ) + " minutes ago" ||
        diff < 7200 && "1 hour ago" ||
      diff < 86400 && Math.floor( diff / 3600 ) + " hours ago") ||
      day_diff == 1 && "Yesterday" ||
      day_diff < 7 && day_diff + " days ago" ||
      day_diff < 31 && Math.ceil( day_diff / 7 ) + " weeks ago";
  }
  
  // declare global comet object
  CometWiki = window.CometWiki = {
    url: "/comet.php",
    topic: 0,
    timestamp: 0,

    ajax: null,
    timeout: null,

    error: false,
    
    handler: null,
    location: null,
    
    init: function(topic, handler){
      // summary
      // Do init stuff here.
      this.topic = topic;
      this.handler = handler || function(){};
      
      if (google.loader.ClientLocation !== null){
        this.location = google.loader.ClientLocation.latitude + "," + google.loader.ClientLocation.longitude;
      }
      
      this.connect();
    },
    connect: function(){
      // summary
      // Run connection logic here.
      var self = this;
      
      // short-circuit: we haven't subscribed, so exit
      if (this.topic === 0){ return; }
      
      // fire an ajax object
      this.ajax = $.ajax({
        timeout: 30000,
        dataType: 'json',
        url: this.url,
        data: { topic: this.topic, timestamp: this.timestamp },
        success: function(data){
          // if the ready state is false, 
          if (this.topic === 0){ return; }
          
          if (self.timestamp === 0) {
            // assign the timestamp and exit
            self.timestamp = data.updated;
            return;
          } else if (data !== null) {
            // assign the timestamp
            self.timestamp = data.updated;
          
            // handle the incoming data
            self.handler(data);
          
            // set the error state to false
            self.error = false;
          }
        },
        complete: function(data){
          // if the error state is in effect
          if (self.error){
            // if the timeout is currently set, clear it
            if (self.timeout !== null) {
              clearTimeout(self.timeout);
            }
            
            // if the ready state is true
            if (self.topic !== 0) {
              // re-attempt connection in 5s
              setTimeout(self.connect, 5000);
            }
            
          // ready state is true and the error state is not in effect
          } else if (self.topic !== 0) {
            self.connect();
          }
          
          // clear the error state
          self.error = false;
        }
      });
    },
    disconnect: function(){
      // summary
      // Ignore the current request and don't make any further ones.
      this.topic = 0;
    },
    put: function(data){
      // summary
      // Send some information on a given channel.
      var data = { topic: this.topic, content: data };
      
      // if the location has a value, add that to the parameters
      if (this.location !== null) {
        data.location = this.location;
      }
      
      // create new ajax request
      $.ajax({
        dataType: 'json',
        url: this.url,
        data: data
      });
    }
  };
})();