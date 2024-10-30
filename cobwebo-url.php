<?php
/*
Plugin Name: Cobwebo URL Plugin
Description: Plugin to shorten the URLs.
Version: 1.0
*/

// Api URL
$api_url = 'http://cobwebo.com/api.php';

// Short URL prefix
$short_url_prefix = 'http://cobwebo.com/c/';

// Keyword list limit.
$keyword_list_limit = 10;

// registers a plugin function to be run when the plugin is activated.
register_activation_hook( __FILE__, 'cobwebourl_install');

add_action('admin_menu', 'cobwebourl_menu'); // Action to add menu items for the plugin.
add_filter('the_content', 'cobwebourl_replace_keywords', 1); // Action to modify/filter the content.

// Include file to post data on remote server.
if( !class_exists('WP_Http')) {
  include_once( ABSPATH . WPINC. '/class-http.php' );
}


global $cobwebourl_db_version;
$cobwebourl_db_version = "1.0";

// Call to the function to initialize Cobwebo URL settings.
$cobwebourl_settings = cobwebourl_initialize_and_get_settings();


/**
 * Function to initialize default Cobwebo URL settings.
 *
 * @return void
 */
function cobwebourl_initialize_and_get_settings() {
	$defaults = array(
		'email' => '',
		'password' => '',  
		);

	add_option('cobwebourl_settings', $defaults, 'Options for Cobwebo URL.');
	return get_option('cobwebourl_settings');	
}//end cobwebourl_initialize_and_get_settings()



/**
 * Function to create table on activation of plugin.
 * 
 * @return void 
 */
function cobwebourl_install () {
   global $wpdb;
   global $cobwebourl_db_version;

   $table_name = $wpdb->prefix . "cobwebourls";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
     $sql = "CREATE TABLE " . $table_name . " (
              id INT NOT NULL AUTO_INCREMENT,
              phrase VARCHAR(255) NOT NULL,
              url TEXT NOT NULL,
              keyword VARCHAR(55) NOT NULL,
              add_bar_placement VARCHAR(10) NOT NULL,
              replacement_count INT NOT NULL DEFAULT 1,
              notes TEXT NULL,
              created date NOT NULL,
              UNIQUE KEY id (id)
            );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option("cobwebourl_db_version", $cobwebourl_db_version);

   }
}//end cobwebourl_install()


/**
 * Function to register the management page
 *
 * @return void
 */ 
function cobwebourl_menu() {
   global $submenu;
  //add_options_page('Cobwebo URL options', 'Cobwebo URL', 'manage_options', 'cobwebourl-settings', 'cobwebourl_options');
  add_menu_page('Cobwebo URL - Configuration', 'Cobwebo URL', 'administrator', basename(__FILE__), 'cobwebourl_options');
	add_submenu_page(basename(__FILE__), 'Cobwebo URL - Configuration', 'Configuration', 'administrator', basename(__FILE__), 'cobwebourl_options');
	add_submenu_page(basename(__FILE__), 'Cobwebo URL - Ajouter un lien', 'Ajouter un lien', 'administrator', basename(__FILE__). 'add', 'cobwebourl_add_keyword');
	add_submenu_page(basename(__FILE__), 'Cobwebo URL -  Liste des liens', 'Liste des liens', 'administrator', basename(__FILE__). 'list', 'cobwebourl_list_keywords');
	add_submenu_page(basename(__FILE__), 'Cobwebo URL -  Aide (Help)', 'Aide (Help)', 'administrator', basename(__FILE__). 'help', 'cobwebourl_help');

  // Modify the submenu to open the url in new window.
  $submenu['cobwebo-url.php'][3] = array( 'Aide (Help)', 'manage_options' , 'http://cobwebo.com/cobwebovideos.ag.php\' target="_blank" ' ); 

}//end swp_menu()


/**
 * Function to manage admin settings.
 * 
 * @return void 
 */
function cobwebourl_options() {
  global $cobwebourl_settings, $api_url;
  $email = $cobwebourl_settings['email'];
  $password = $cobwebourl_settings['password'];
  if (!current_user_can('manage_options'))  {
      wp_die( __('You do not have sufficient permissions to access this page.') );
  }
  if (!empty($_POST))
  {
      $email = $_POST['cobwebourl_email'];
      $password = $_POST['cobwebourl_password'];

      // Build data to be posted on api to validate user login details.
      $data = array(
        'email' => $email,  
        'password' => $password,
        'action' => 'authenticate'
      ); 

      // Post data on api.
      $request = new WP_Http;
      $result = $request->request( $api_url, array( 'method' => 'POST', 'body' => $data) );

      // Get the response returned by the api.
      if (!empty($result->errors)) {
        $response_error = $result->errors;  
      } else if (isset($result['body'])) { 
        $response = json_decode($result['body']);
      }

      if (isset($response->status->code) && 1 == $response->status->code) {
	      $cobwebourl_settings['email'] = $email;
	      $cobwebourl_settings['password'] = $password;
	      update_option('cobwebourl_settings', $cobwebourl_settings);
        $message = 'Data saved successfully.';
      } elseif (isset($response_error['http_request_failed']) && !empty($response_error['http_request_failed'])) {
        $message = $response_error['http_request_failed'][0];
      } elseif (isset($response->status->code)) {
        $message = isset($response->status->text) ? $response->status->text : 'Invalid Email / Password.';
      } else {
        $message = 'Failed to save data.';
      }
      if (isset($message) && $message) { 
?>
      <div class = 'updated'>
      	<p><strong><?php echo $message; ?></strong></p>
      </div>
<?php
      }
  }
?>

    <div class="wrap">
        <div class="icon32" id="icon-options-general"><br></div>
        <h2>Cobwebo URL - R&#233;glages (Settings)</h2>
        <p>Veuillez indiquer ci-dessous vos codes de connexion au site cobwebo.com pour pouvoir utiliser cette extension virale sur votre blog Wordpress.</p>
        <p>Vous n&#39;avez pas encore de compte cobwebo?</p>
        <p><a href=" http://cobwebo.com/" target="_blank">Cliquez ici pour vous inscrire</a></p>
        <form method="post" action="">
            <table class="form-table">
              <tbody>
                <tr valign="top">
                  <th scope="row"><label for="cobwebourl_email">Cobwebo Email</label></th>
                  <td><input type="text" id="cobwebourl_email" name="cobwebourl_email" size=60 value="<?php print $email; ?>" /></td>
                </tr>
                <tr valign="top">
                  <th scope="row"><label for="cobwebourl_password">Mot de passe (Password)</label></th>
                  <td><input type="password" id="cobwebourl_password" name="cobwebourl_password" size=60 value="<?php print $password; ?>" /> </td>
                </tr>
              </tbody>  
            </table>
            <p class="submit">
              <input type="submit" value="Ok" class="button-primary" name="Submit">
            </p>
        </form>
    </div>

<?php
 }//end cobwebourl_options()


/**
 * Function to add keywords.
 * 
 * @return void 
 */
function cobwebourl_add_keyword() {
  global $cobwebourl_settings, $api_url, $wpdb;
		// Input initialization
		$table_name = $wpdb->prefix . "cobwebourls";

  if (!current_user_can('manage_options'))  {
      wp_die( __('You do not have sufficient permissions to access this page.') );
  }

  // Initialize variables
  $url = $notes = $phrase = $top = $bottom = '';
  $id = $action = null;
  $replacement_count = 1;
  if (isset($_GET['action']) && 'edit' == $_GET['action'] && isset($_GET['id']) && $_GET['id']) {
    $id = $wpdb->escape($_GET['id']);
    $keyword_data = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id");

    $url     = $keyword_data->url;
    $notes   = $keyword_data->notes;
    $phrase  = $keyword_data->phrase;
    $bar_position = $keyword_data->add_bar_placement;
    $replacement_count = $keyword_data->replacement_count;
    $current_keyword = $keyword_data->keyword;
    // Build action to set in form action tag on the edit keyword page.
    // Added noheader in query string as admin.php won’t output anything 
    // if you call your plugin page with an optional &noheader
    //$action = "admin.php?page=". basename(__FILE__). "add&action=edit&id=$id&noheader";
    $params = array('action' => 'edit', 'id' => $id, 'noheader' => 'true');
    $action = add_query_arg($params) ;
  }

  if (!empty($_POST))
  {
    // Get the required data.
    $url     = $_POST['cobwebourl_url'];
    $notes   = $_POST['cobwebourl_notes'];
    $phrase  = trim($_POST['cobwebourl_phrase']);  
    $bar_position = $_POST['cobwebourl_bar_position'];
    $replacement_count = $_POST['cobwebourl_replacement_count'];

    if ($phrase) { 
      $phrase_exists = is_phrase_exists($phrase, $id);
      if ($phrase_exists) { 
        $message = 'One or more words of Phrase / Keyword already exists. Please choose another one.';
      } else {
        // Build data to be posted on api
        $data = array(
          'email' => $cobwebourl_settings['email'],  
          'password' => $cobwebourl_settings['password'],
          'url' => urlencode($url),
          'notes' => $notes, 
          'position' => $bar_position,
          'action' => 'add'
        ); 
        // Check if keyword is present in the posted data.
        if (isset($current_keyword) && $current_keyword) {
          $data['keyword'] = $current_keyword;
        }

        // Post data on api.
        $request = new WP_Http;
        $result = $request->request( $api_url, array( 'method' => 'POST', 'body' => $data) );

        // Get the response returned by the api.
        if (!empty($result->errors)) {
          $response_error = $result->errors;  
        } else if (isset($result['body'])) { 
          $response = json_decode($result['body']);
        }
        // If data successfully saved through api, save the data to the wordpress table.
        $url = urldecode($url);
        if(isset($response->status->code) && 1 == $response->status->code && isset($response->status->keyword) && $response->status->keyword) {
            // Insert if valid
            $keyword = $response->status->keyword;
            $current_date = date("Y-m-d");
            $save_flag = false;
            
            if (isset($_GET['action']) && 'edit' == $_GET['action'] && isset($_GET['id']) && $_GET['id']) {
              $id = $_GET['id'];
              //$sql = "UPDATE $table_name SET phrase = '$phrase', url = '$url', add_bar_placement = '$bar_position', notes = '$notes' WHERE id = $id";
              $data_to_save = array('phrase' => $phrase, 'url' => $url, 'add_bar_placement' => $bar_position, 'notes' => $notes, 'replacement_count' => $replacement_count);
              $save_flag = $wpdb->update($table_name, $data_to_save, array('id' => $id));
              $message = urlencode('Keyword / Phrase updated succesfully.');
            } else { 
				      /*$sql = "INSERT INTO $table_name
						      (phrase, url, keyword, add_bar_placement, notes, created)
						      VALUES ('$phrase', '$url', '$keyword', '$bar_position', '$notes', '$current_date')";*/
              $data_to_save = array('phrase' => $phrase, 'url' => $url, 'keyword' => $keyword, 'add_bar_placement' => $bar_position, 'replacement_count' => $replacement_count, 'notes' => $notes, 'created' => $current_date);
              $save_flag = $wpdb->insert($table_name, $data_to_save);
              $message = 'Keyword / Phrase added succesfully.';
              $insertFlag = true;
            }

				    if ($save_flag !== false) {
              if ($insertFlag) {
                $url = $notes = $phrase = $bar_position = '';
                $replacement_count = 1;
              } else {
                // Redirect to the listing page if updated successfully.
					      wp_redirect("admin.php?page=".basename(__FILE__)."list&message=$message");
                exit();
              }
				    } else {
					    $message = 'Failed to add phrase / keword. Please try again.';
				    }
        } else if (isset($response_error['http_request_failed']) && !empty($response_error['http_request_failed'])) {
          $message = $response_error['http_request_failed'][0];  
        } else {
          $message = $response->status->text;
        }
      }
    } else {
      $message = 'Enter a valid keyword.';
    }
  } 

  // Set the default bar position
  if (isset($bar_position) && 'bottom' == $bar_position) {
    $bottom = 'CHECKED';
  } else {
    $top = 'CHECKED';
  }  

  if (isset($_REQUEST['message'])){
    $message = $_REQUEST['message'];
  }

  // Added this code to include admin-header file.
  // As on keyword edit page added noheader in query string to properly redirect 
  // to listing page if updated successfully.
  if (isset($_GET['noheader'])) {
    require_once(ABSPATH . 'wp-admin/admin-header.php');
  }

  if (isset($message) && $message) {
?>
    <div class = 'updated'>
      	<p><strong><?php print $message; ?></strong></p>
    </div>
<?php } ?>
    
    <div class="wrap">
        <div class="icon32" id="icon-options-general"><br></div>
        <h2>Cobwebo URL - Add Keyword Phrase</h2>
        <form method="post" action="<?php echo $action; ?>">
            <table class="form-table">
              <tbody>
                <tr valign="top">
                  <th scope="row"><label for="cobwebourl_phrase">Mots Cl&#233;s (Phrase / Keyword)</label></th>
                  <td><input type="text" id="cobwebourl_phrase" name="cobwebourl_phrase" size=60 value="<?php echo stripslashes($phrase); ?>" /></td>
                </tr>
                <tr valign="top">
                  <th scope="row"><label for="cobwebourl_url">URL (http://...)</label></th>
                  <td><input type="text" id="cobwebourl_url" name="cobwebourl_url" size=60 value="<?php echo $url; ?>" /> </td>
                </tr>

                <tr valign="top">
                  <th scope="row"><label for="cobwebourl_bar_position">Emplacement Pub-Barre <br />(Status Bar Position)</label></th>
                  <td>
                    <label><input type="radio" id="cobwebourl_bar_position" name="cobwebourl_bar_position" value="top" <?php echo $top; ?> />Top</label>&nbsp;&nbsp;&nbsp;
                    <label><input type="radio" id="cobwebourl_bar_position" name="cobwebourl_bar_position" value="bottom" <?php echo $bottom; ?> />Bottom</label>  
                  </td>
                </tr>
                <tr valign="top">
                  <th scope="row"><label for="cobwebourl_replacement_count">Emplacements par page <br />(Maximum Replacement Count)</label></th>
                  <td><input type="text" id="cobwebourl_replacement_count" name="cobwebourl_replacement_count" size=60 value="<?php print $replacement_count; ?>" /> </td>
                </tr>
                <tr valign="top">
                  <th scope="row"><label for="cobwebourl_notes">Notes</label></th>
                  <td><textarea id="cobwebourl_notes" name="cobwebourl_notes" cols="50"><?php echo stripslashes($notes); ?></textarea></td>
                </tr>
              </tbody>  
            </table>
            <p class="submit">
              <input type="submit" value="Ok" class="button-primary" name="Submit">
            </p>
        </form>
    </div>
    <div> <br /> <a href = "admin.php?page=<?=basename(__FILE__) ?>list">Liste des mots cl&#233;s (List Keywords)</a></div>
<?php

}//end cobwebourl_add_keyword()


/**
 * Function to list added keywords.
 */
function cobwebourl_list_keywords() {
	global $wpdb, $wp_query, $keyword_list_limit, $short_url_prefix;

  // Check if action is delete, call function to delete the keyword
  if(isset($_GET['action']) && "delete" == $_GET['action'] && isset($_GET['id'])) {
    $message = cobwebourl_delete_keyword();
  }
	
	$table_name = $wpdb->prefix . "cobwebourls";

  // Get the total keyword count.
  $total_keywords = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name"));
  // Get the current page.  
  (isset($_GET['paged']) && $_GET['paged'] > 1) ? $current = $_GET['paged'] : $current = 1;

  $limit = "LIMIT ". (($current - 1) * $keyword_list_limit) . ", $keyword_list_limit"; 

  $sort = 'phrase';
  $order = 'ASC';
  $order_by = "ORDER BY $sort $order";

	// Keywords display here
	$keywords = $wpdb->get_results("SELECT * FROM $table_name $order_by $limit");

  if (isset($_REQUEST['message'])){
    $message = $_REQUEST['message'];
  }
?>
    <div class="icon32" id="icon-options-general"><br></div>
    <h2>Cobwebo URL - Liste des liens (Link List)</h2>
    <p><strong>Attention:</strong> Les liens r&#233;alis&#233;s par l&#39;extension Cobwebo URL ne peuvent &#234;tre effac&#233;s via la plate-forme cobwebo.com. Un lien effac&#233; sur Cobwebo.com sera toujours visible dans le tableau ci-dessous.</p>
    <?php if (isset($message) && $message) { ?>
        <div class = 'updated'>
          	<p><strong><?php print $message; ?></strong></p>
        </div>
    <?php } ?>
  
<?php  
  if(!empty($keywords)) {
    // Build pagination options.
    $total_pages = ceil($total_keywords / $keyword_list_limit);
    $pagination = array(
                   'total' => $total_pages,
                   'current' => $current, 
                   'base' => 'admin.php?page=' . basename(__FILE__). 'list&%_%',
                   'format' => 'paged=%#%',
                  );

?>
    <table class = 'widefat fixed'>
			<thead>
				<tr>  
					<th width="15%">Mots Cl&#233;s (Keywords)</th>
					<th width="30%">URL</th>
					<th width="25%">URL courte (Short URL)</th>            
					<th width="10%">Emplacement Pub-barre (Status Bar Position)</th>
					<th width="10%">Emplacement Par Page (Replacement Count)</th>          
          <th width="10%">Actions</th>
				</tr>
			</thead>
			<tbody>
        <?php foreach ($keywords as $keyword) { ?>
          <tr>
            <td><?php print stripslashes($keyword->phrase); ?></td>
            <td><a href="<?php print $keyword->url; ?>" target="_blank"><?php print $keyword->url; ?></a></td>
            <td><a href="<?php print $short_url_prefix. $keyword->keyword; ?>" target="_blank"><?php print $short_url_prefix. $keyword->keyword; ?></a></td>
            <td><?php print ucfirst($keyword->add_bar_placement); ?></td>
            <td><?php print $keyword->replacement_count; ?></td>
            <td>
              <a href = "admin.php?page=<?=basename(__FILE__) ?>add&action=edit&id=<?= $keyword->id ?>">Editer (Edit)</a> | 
              <a href = "admin.php?page=<?=basename(__FILE__) ?>list&action=delete&id=<?= $keyword->id ?>" onclick="if (!confirm('Are you sure you want to delete this keyword?')) { return false; }">Effacer (Delete)</a>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    <?php print paginate_links($pagination); ?>
<?php
  } else {
?>
  <br />
  <h4>No keywords present.</h4>
<?php } ?>
  <div> <br /> <a href = "admin.php?page=<?=basename(__FILE__) ?>add">Ajouter Mots Cl&#233;s (Add Keywords)</a></div>
<?php
}//end cobwebourl_list_keywords()


/**
 * Function to delete cobwebourl keywords.
 *
 * @return void
 */
function cobwebourl_delete_keyword() {
  global $wpdb, $cobwebourl_settings, $api_url;
	$table_name = $wpdb->prefix . "cobwebourls";
  $id = $wpdb->escape($_GET['id']);
 
  $message = '';
  if ($id) { 
    $keyword = $wpdb->get_var($wpdb->prepare("SELECT keyword FROM $table_name WHERE id = $id"));

	  if($wpdb->query("DELETE FROM $table_name WHERE id='$id'")) {
      if ($keyword) {
        // Build data to be posted on api
        $data = array(
          'email' => $cobwebourl_settings['email'],  
          'password' => $cobwebourl_settings['password'],
          'keyword' => $keyword,
          'action' => 'delete'
        ); 

        $request = new WP_Http;
        $result = $request->request( $api_url, array( 'method' => 'POST', 'body' => $data));
      }
      $message = 'Keyword deleted successfully.';
    } else {
      $message = 'Failed to delete keyword.';
    }
  } else {
    $message = 'Invalid Id.';
  }
  return $message;
}//end cobwebourl_delete_keyword()


/**
 * Function to replace keywods with the links.
 * 
 * @param string $content Content to modify.
 *
 * @return string $content Modified content.   
 */
function cobwebourl_replace_keywords($content = '') {
  global $wpdb, $short_url_prefix;
	$table_name = $wpdb->prefix . "cobwebourls";

  // Get all the keywords
  $keywords = $wpdb->get_results("SELECT phrase, keyword, replacement_count FROM $table_name WHERE replacement_count <> 0");

  // Run a loop to replace the keywords with links.
  if (!empty($keywords)) {
    foreach ($keywords as $keyword) {
      $link = '<a href="'. $short_url_prefix . $keyword->keyword . '" target="_blank">'. $keyword->phrase . '</a>';
      // If replacement count is not set then set it to 0.
      if (!isset($keyword->replacement_count)) {
        $keyword->replacement_count = 0;
      }

      // For keywords with quotes (') to work, we need to disable word boundary matching
      $cleankeyword = stripslashes($keyword->phrase); 
			$cleankeyword = preg_quote($cleankeyword,'\'');

			if (strpos( $cleankeyword  , '\'') > 0) {
			  $regEx = '\'(?!((<.*?)|(<a.*?)))(' . $cleankeyword . ')(?!(([^<>]*?)>)|([^>]*?</a>))\'s';
      } else {
  			$regEx = '\'(?!((<.*?)|(<a.*?)))(\b'. $cleankeyword . '\b)(?!(([^<>]*?)>)|([^>]*?</a>))\'s';				 
      }
      // Replace the content keywords.
			$content = preg_replace($regEx, $link, $content, $keyword->replacement_count);
    }
    //$content = preg_replace($patterns, $replacements, $content);
  }

  return $content;
}//end cobwebourl_replace_keywords()


/**
 * Function to check if one or more words in phrase already exists.
 *
 * @param string $phrase Phrase/Keyword.
 *
 * @return boolean True or False
 */
function is_phrase_exists($phrase = null, $id = null) {
  global $wpdb;
  $table_name = $wpdb->prefix . "cobwebourls";  
  $is_phrase_exists = false;
  if ($phrase) {
    $words = explode(" ", $phrase);
    // Run a loop of words to check if it is already exists,
    if (!empty($words)) {
      $condition = '';
      if ($id) {
        $condition = " AND id <> $id";
      }
      foreach ($words as $word) {
        $sql = "SELECT id FROM $table_name WHERE phrase = '$word'". $condition;
        if($wpdb->get_var($wpdb->prepare($sql))) {
          $is_phrase_exists = true;
          break;
        }
      }
    }
  }

  return $is_phrase_exists;  
}//end is_phrase_exists()

