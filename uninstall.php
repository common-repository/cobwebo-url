<?php

  global $wpdb;
  $table = $wpdb->prefix . "cobwebourls";

  //Delete any options thats stored also?
  delete_option('cobwebourl_db_version');
  delete_option('cobwebourl_settings');
  
  // Execute a query to remove the table.
  $wpdb->query("DROP TABLE IF EXISTS $table");

