<?php
/*
Plugin Name: External Database Authentication Reloaded
Plugin URI: http://www.7mediaws.org/extend/plugins/external-db-auth-reloaded/
Description: Used to externally authenticate WP users with an existing user DB.
Version: 1.1
Author: Joshua Parker
Author URI: http://www.joshparker.us/
Original Author: Charlene Barina
Original Author URI: http://www.ploofle.com

    Copyright 2007  Charlene Barina  (email : cbarina@u.washington.edu)

    This program is free software; you can redistribute it and/or modify
    it  under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function pp_db_auth_activate() {
	add_option('pp_db_type',"MySQL","External database type");
	add_option('pp_db_mdb2_path',"","Path to MDB2 (if non-standard)");
	add_option('pp_host',"","External database hostname");
	add_option('pp_db_port',"","Database port (if non-standard)");
	add_option('pp_db',"","External database name");
	add_option('pp_db_user',"","External database username");
	add_option('pp_db_pw',"","External database password");
	add_option('pp_db_table',"","External database table for authentication");
	add_option('pp_db_namefield',"","External database field for username");
	add_option('pp_db_pwfield',"","External database field for password");
	add_option('pp_db_first_name',"");
	add_option('pp_db_last_name',"");
	add_option('pp_db_user_url',"");
	add_option('pp_db_user_email',"");
	add_option('pp_db_description',"");
	add_option('pp_db_aim',"");
	add_option('pp_db_yim',"");
	add_option('pp_db_jabber',"");
	add_option('pp_db_enc',"","Type of encoding for external db (default SHA1? or MD5?)");
	add_option('pp_db_other_enc',"");
	add_option('pp_db_error_msg',"","Custom login message");
	add_option('pp_db_role_bool','');
	add_option('pp_db_role','');
	add_option('pp_db_role_value','');
	add_option('pp_db_site_url','');
}

function pp_db_auth_init(){
	register_setting('pp_db_auth','pp_db_type');
	register_setting('pp_db_auth','pp_db_mdb2_path');
	register_setting('pp_db_auth','pp_host');
	register_setting('pp_db_auth','pp_db_port');
	register_setting('pp_db_auth','pp_db');
	register_setting('pp_db_auth','pp_db_user');
	register_setting('pp_db_auth','pp_db_pw');
	register_setting('pp_db_auth','pp_db_table');
	register_setting('pp_db_auth','pp_db_namefield');
	register_setting('pp_db_auth','pp_db_pwfield');
	register_setting('pp_db_auth','pp_db_first_name');
	register_setting('pp_db_auth','pp_db_last_name');
	register_setting('pp_db_auth','pp_db_user_url');
	register_setting('pp_db_auth','pp_db_user_email');
	register_setting('pp_db_auth','pp_db_description');
	register_setting('pp_db_auth','pp_db_aim');
	register_setting('pp_db_auth','pp_db_yim');
	register_setting('pp_db_auth','pp_db_jabber');
	register_setting('pp_db_auth','pp_db_enc');
	register_setting('pp_db_auth','pp_db_other_enc');
	register_setting('pp_db_auth','pp_db_error_msg');
	register_setting('pp_db_auth','pp_db_role');
	register_setting('pp_db_auth','pp_db_role_bool');
	register_setting('pp_db_auth','pp_db_role_value');
	register_setting('pp_db_auth','pp_db_site_url');
}

//page for config menu
function pp_db_auth_add_menu() {
	add_options_page("External DB settings", "External DB settings", 'manage_options', __FILE__, "pp_db_auth_display_options");
}

//actual configuration screen
function pp_db_auth_display_options() { 
    $db_types[] = "MySQL";
    $db_types[] = "MSSQL";
    $db_types[] = "PgSQL";
?>
	<div class="wrap">
	<h2><?php _e( 'External Database Authentication Settings' ); ?></h2>        
	<form method="post" action="options.php">
	<?php settings_fields('pp_db_auth'); ?>
        <h3><?php _e( 'External Database Settings' ); ?></h3>
          <strong><?php _e( 'Make sure your WP admin account exists in the external db prior to saving these settings.'); ?></strong>
        <table class="form-table">
        <tr valign="top">
            <th scope="row"><?php _e( 'Database type' ); ?></th>
                <td><select name="pp_db_type" >
                <?php 
                    foreach ($db_types as $key=>$value) { //print out radio buttons
                        if ($value == get_option('pp_db_type'))
                            echo '<option value="'.$value.'" selected="selected">'.$value.'<br/>';
                        else echo '<option value="'.$value.'">'.$value.'<br/>';;
                    }                
                ?>
                </select> 
				</td>
				<td>
					<span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong>; <?php _e( 'If not MySQL, requires' ); ?> <a href="http://pear.php.net/package/MDB2/" target="new"><?php _e( 'PEAR MDB2 package' ); ?></a> <?php _e( 'and relevant database driver package installation.' ); ?></span>
				</td>
        </tr>        
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Path to MDB2.php' ); ?></label></th>
				<td><input type="text" name="pp_db_mdb2_path" value="<?php echo get_option('pp_db_mdb2_path'); ?>" /> </td>
				<td><span class="description"><?php _e( 'Only when using non-MySQL database and in case this isn\'t in some sort of include path in your PHP configuration.  No trailing slash! e.g., /home/username/php' ); ?></span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Host' ); ?></label></th>
				<td><input type="text" name="pp_host" value="<?php echo get_option('pp_host'); ?>" /> </td>
				<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong>; <?php _e( '(often localhost)' ); ?></span> </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Port' ); ?></label></th>
				<td><input type="text" name="pp_db_port" value="<?php echo get_option('pp_db_port'); ?>" /> </td>
				<td><span class="description"><?php _e( 'Only set this if you have a non-standard port for connecting.' ); ?></span></td>
        </tr>        
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Name' ); ?></label></th>
				<td><input type="text" name="pp_db" value="<?php echo get_option('pp_db'); ?>" /></td>
				<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong></span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Username' ); ?></label></th>
				<td><input type="text" name="pp_db_user" value="<?php echo get_option('pp_db_user'); ?>" /></td>
				<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong>; <?php _e( '(recommend select privileges only)' ); ?></span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Password' ); ?></label></th>
				<td><input type="password" name="pp_db_pw" value="<?php echo get_option('pp_db_pw'); ?>" /></td>
				<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong></span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'User table' ); ?></label></th>
				<td><input type="text" name="pp_db_table" value="<?php echo get_option('pp_db_table'); ?>" /></td>
				<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong></span></td>
        </tr>
        </table>
        
        <h3><?php _e( 'External Database Source Fields' ); ?></h3>
        <table class="form-table">
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Username' ); ?></label></th>
				<td><input type="text" name="pp_db_namefield" value="<?php echo get_option('pp_db_namefield'); ?>" /></td>
				<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong></span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Password' ); ?></label></th>
				<td><input type="text" name="pp_db_pwfield" value="<?php echo get_option('pp_db_pwfield'); ?>" /></td>
				<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong></span><td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Password encryption method' ); ?></th>
                <td><select name="pp_db_enc">
                <?php 
                    switch(get_option('pp_db_enc')) {
                    case "SHA1" :
                        echo '<option selected="selected">SHA1</option><option>MD5</option><option>HASH</option><option>PHPass</option><option>Other</option>';
                        break;
                    case "MD5" :
                        echo '<option>SHA1</option><option selected="selected">MD5</option><option>HASH</option><option>PHPass</option><option>Other</option>';
                        break;
					case "HASH" :
                        echo '<option>SHA1</option><option>MD5</option><option selected="selected">HASH</option><option>PHPass</option><option>Other</option>';
                        break;
					case "PHPass" :
                        echo '<option>SHA1</option><option>MD5</option><option>HASH</option><option selected="selected">PHPass</option><option>Other</option>';
                        break;
					case "Other" :
                        echo '<option>SHA1</option><option>MD5</option><option>HASH</option><option>PHPass</option><option selected="selected">Other</option>';
                        break;                                                    
                    default :
                        echo '<option>SHA1</option><option>MD5</option><option selected="selected">HASH</option><option>PHPass</option><option>Other</option>';
                        break;
                    }
                ?>
				</select></td>
			<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong>; <?php _e( 'using "Other" requires you to enter PHP code below!)' ); ?></td>            
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Hash code' ); ?></label></th>
				<td><input type="text" name="pp_db_other_enc" size="50" value="<?php echo get_option('pp_db_other_enc'); ?>" /></td>
				<td><span class="description"><?php _e( 'Only will run if "Other" is selected and needs to be PHP code. Variable you need to set is $password2, and you have access to (original) $username and $password.' ); ?></td>
    	</tr>
		<tr valign="top">
            <th scope="row"><label><?php _e( 'Role check' ); ?></label></th>
			<td><input type="text" name="pp_db_role" value="<?php echo get_option('pp_db_role'); ?>" />
				<br />
				<select name="pp_db_role_bool">
                <?php 
                    switch(get_option('pp_db_role_bool')) {
                    case "is" :
                        echo '<option selected="selected">is</option><option>greater than</option><option>less than</option>';
                        break;
                    case "greater than" :
                        echo '<option>is</option><option selected="selected">greater than</option><option>less than</option>';
                        break;                
                    case "less than" :
                        echo '<option>is</option><option>greater than</option><option selected="selected">less than</option>';
                        break;                                        
                    default :
                        echo '<option selected="selected">is</option><option>greater than</option><option>less than</option>';
                        break;
                    }
                ?>
				</select><br />
				<input type="text" name="pp_db_role_value" value="<?php echo get_option('pp_db_role_value'); ?>" /></td>
				<td><span class="description"><?php _e( 'Use this if you have certain user role ids in your external database to further restrict allowed logins.  If unused, leave fields blank.' ); ?></span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'First name' ); ?></label></th>
			<td><input type="text" name="pp_db_first_name" value="<?php echo get_option('pp_db_first_name'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Last name' ); ?></label></th>
			<td><input type="text" name="pp_db_last_name" value="<?php echo get_option('pp_db_last_name'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Homepage' ); ?></label></th>
			<td><input type="text" name="pp_db_user_url" value="<?php echo get_option('pp_db_user_url'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Email' ); ?></label></th>
			<td><input type="text" name="pp_db_user_email" value="<?php echo get_option('pp_db_user_email'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'Bio/description' ); ?></label></th>
			<td><input type="text" name="pp_db_description" value="<?php echo get_option('pp_db_description'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'AIM screen name' ); ?></label></th>
			<td><input type="text" name="pp_db_aim" value="<?php echo get_option('pp_db_aim'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'YIM screen name' ); ?></label></th>
			<td><input type="text" name="pp_db_yim" value="<?php echo get_option('pp_db_yim'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e( 'JABBER screen name' ); ?></label></th>
			<td><input type="text" name="pp_db_jabber" value="<?php echo get_option('pp_db_jabber'); ?>" /></td>
        </tr>
        </table>
        <h3><?php _e( 'Other' ); ?></h3>
        <table class="form-table">
    	<tr valign="top">
        	<th scope="row"><label><?php _e( 'External Site URL' ); ?></label></th>
			<td><input type="text" name="pp_db_site_url" value="<?php echo get_option('pp_db_site_url'); ?>" /></td>
			<td><span class="description"><strong style="color:red;"><?php _e( 'required' ); ?></strong></span></td>
        </tr>
        <tr valign="top">
                <th scope="row"><?php _e( 'Custom login message' ); ?></th>
                <td><textarea name="pp_db_error_msg" cols=40 rows=4><?php echo htmlspecialchars(get_option('pp_db_error_msg'));?></textarea></td>
                <td><span class="description"><?php _e( 'Shows up in login box, e.g., to tell them where to get an account. You can use HTML in this text.' ); ?></td>
        </tr>        
    </table>
	
	<p class="submit">
	<input type="submit" name="Submit" value="Save changes" />
	</p>
	</form>
	</div>
<?php
}

//sort-of wrapper for all DB interactions
function db_functions($driver,$process,$resource,$query) {
    if ($driver == "MySQL") {	//use built-in PHP mysql connection
        switch($process) {
            case "connect" :
                $port = get_option('pp_db_port');                
                if (!empty($port))   $port = ":".get_option('pp_db_port');
                $resource = mysql_connect(get_option('pp_host').$port, get_option('pp_db_user'), get_option('pp_db_pw'),true) or die(mysql_error());                
                mysql_select_db(get_option('pp_db'),$resource) or die(mysql_error());
                return $resource;
                break;
            case "query":
                $result = mysql_query($query,$resource) or die(mysql_error());
                return $result;
                break;            
            case "numrows":
                return mysql_num_rows($resource);
                break;
            case "fetch":
                return mysql_fetch_assoc($resource);            
                break;
            case "close":
                mysql_close($resource);            
                break;
        }
    }
    else {  //Use MDB2   
        $mdbpath = get_option('pp_db_mdb2_path')."/MDB2.php";        
        require_once($mdbpath);
        switch($process) {
            case "connect" :                
                $port = get_option('pp_db_port');                
                if (!empty($port))   $port = ":".get_option('pp_db_port');                
                $url = strtolower($driver)."://".get_option('pp_db_user').":".get_option('pp_db_pw')."@".get_option('pp_host').$port."/".get_option('pp_db');                
                $resource =& MDB2::connect($url);
                if(PEAR::isError($resource)) die("Error while connecting : " . $resource->getMessage());
                return $resource;        
                break;
            case "query":    
                $result = $resource->query($query);
                if(PEAR::isError($result)) die('Failed to issue query, error message : ' . $result->getMessage());                            
                return $result;
                break;            
            case "numrows":
                return $resource->numRows();
                break;
            case "fetch":
                return $resource->fetchRow(MDB2_FETCHMODE_ASSOC);                
                break;
            case "close":
                $resource->disconnect();                
                break;
        }
    }
}

function pp_hash_password($password) {
	// By default, use the portable hash from phpass
	$pp_hasher = new PasswordHash(8, FALSE);

		return $pp_hasher->HashPassword($password);
}
 
function pp_check_password($password, $hash, $user_id = '') {

	// If the hash is still md5...
	if ( strlen($hash) <= 32 ) {
		$check = ( $hash == md5($password) );
	if ( $check && $user_id ) {
		// Rehash using new hash.
		pp_set_password($password, $user_id);
		$hash = pp_hash_password($password);
	}

	return apply_filters('check_password', $check, $password, $hash, $user_id);
	}

	// If the stored hash is longer than an MD5, presume the
	// new style phpass portable hash.
	$pp_hasher = new PasswordHash(8, FALSE);

	$check = $pp_hasher->CheckPassword($password, $hash);

		return apply_filters('check_password', $check, $password, $hash, $user_id);
}

//actual meat of plugin - essentially, you're setting $username and $password to pass on to the system.
//You check from your external system and insert/update users into the WP system just before WP actually
//authenticates with its own database.
function pp_db_auth_check_login($username,$password) {
	require_once('./wp-includes/registration.php');
	require_once('./wp-includes/user.php');
	require_once('./wp-includes/pluggable.php');
	require_once('./wp-includes/class-phpass.php');

	if( $username == '' && $password=='') return;

	$resource = mysql_connect(get_option('pp_host').$port, get_option('pp_db_user'), get_option('pp_db_pw'), true);                
    mysql_select_db(get_option('pp_db'),$resource);
    
    $pp_hasher = new PasswordHash(8, FALSE);
	
	$mem = get_option('pp_db_table');
   
	$sql = mysql_query( "SELECT " . get_option('pp_db_namefield') . ", " . get_option('pp_db_pwfield') . " FROM `" . $mem . "` WHERE " . get_option('pp_db_namefield') . " = '" . $username . "'" );

	$row = mysql_fetch_assoc( $sql );
	//print_r($row);
    //first figure out the DB type and connect...
    $driver = get_option('pp_db_type');
	//if on same host have to use resource id to make sure you don't lose the wp db connection        
    	 
    $mdbpath = get_option('pp_db_mdb2_path')."/MDB2.php";        
    if ($mdbpath != "/MDB2.php") @require_once($mdbpath);
    
    $resource = db_functions($driver,"connect","","");
	//prepare the db for unicode queries
	//to pick up umlauts, non-latin text, etc., without choking
	$utfquery = "SET NAMES 'utf8'";
	$resultutf = db_functions($driver,"query",$resource,$utfquery);  

	//do the password hash for comparing
	switch(get_option('pp_db_enc')) {
		case "SHA1" :
			$password2 = sha1($password);
			break;
		case "MD5" :
			$password2 = md5($password);
			break;
		case "HASH" :
			$password2 = pp_check_password($password, $row['password']);
			break;
		case "PHPass" :
			$password2 = pp_check_password($password, $row['password']);
			break;	
        case "Other" :             //right now defaulting to plaintext.  People can change code here for their own special hash
			$salt = '/2aX16zPnnIgfMwkOjGX4S';
			$hmac = base64_encode( hash_hmac ( 'sha512', $password, $salt, true ) );
			$password2 = crypt($hmac,$row['password']);
			
            //eval(get_option('pp_db_other_enc'));
            break;
	}
   
   //first check to see if login exists in external db
   $query = "SELECT count(*) AS numrows FROM " . get_option('pp_db_table') . " WHERE ".get_option('pp_db_namefield')." = '$username'";

   $result = db_functions($driver,"query",$resource,$query);    
   $numrows = db_functions($driver,"fetch",$result,"");
   $numrows = $numrows["numrows"];
   	
    if ($numrows) {
	     //then check to see if pw matches and get other fields...
        $sqlfields['first_name'] = get_option('pp_db_first_name');
        $sqlfields['last_name'] = get_option('pp_db_last_name');
        $sqlfields['user_url'] = get_option('pp_db_user_url');
        $sqlfields['user_email'] = get_option('pp_db_user_email');
        $sqlfields['description'] = get_option('pp_db_description');
        $sqlfields['aim'] = get_option('pp_db_aim');
        $sqlfields['yim'] = get_option('pp_db_yim');
        $sqlfields['jabber'] = get_option('pp_db_jabber');	
		$sqlfields['pp_db_role'] = get_option('pp_db_role');
		  
        foreach($sqlfields as $key=>$value) {				
            if ($value == "") unset($sqlfields[$key]);
        }
        $sqlfields2 = implode(", ",$sqlfields);
    
        //just so queries won't error out if there are no relevant fields for extended data.
        if (empty($sqlfields2)) $sqlfields2 = get_option('pp_db_namefield');
		
		if(get_option('pp_db_enc') == 'HASH') {
		    $query = "SELECT $sqlfields2 FROM " . get_option('pp_db_table') . " WHERE ".get_option('pp_db_namefield')." = '$username' AND active = '1'";                            			
		    $result = db_functions($driver,"query",$resource,$query);    
	        $numrows = db_functions($driver,"numrows",$result,"");
			
		} elseif(get_option('pp_db_enc') == 'PHPass') {
			$query = "SELECT $sqlfields2 FROM " . get_option('pp_db_table') . " WHERE ".get_option('pp_db_namefield')." = '$username'";                            			
		    $result = db_functions($driver,"query",$resource,$query);    
	        $numrows = db_functions($driver,"numrows",$result,"");
			
		} elseif(get_option('pp_db_enc') == 'SHA1' || get_option('pp_db_enc') == 'MD5') {
			$query = "SELECT $sqlfields2 FROM " . get_option('pp_db_table') . " WHERE ".get_option('pp_db_namefield')." = '$username' AND ".get_option('pp_db_pwfield')." = '$password2'";                            			
		    $result = db_functions($driver,"query",$resource,$query);    
	        $numrows = db_functions($driver,"numrows",$result,"");
			
		} elseif(get_option('pp_db_enc') == 'Other') {
			$query = "SELECT $sqlfields2 FROM " . get_option('pp_db_table') . " WHERE ".get_option('pp_db_namefield')." = '$username' AND ".get_option('pp_db_pwfield')." = '$password2'";                            			
		    $result = db_functions($driver,"query",$resource,$query);    
	        $numrows = db_functions($driver,"numrows",$result,"");
		}
		
		if ($numrows) {    //create/update wp account from external database if login/pw exact match exists in that db		
            $extfields = db_functions($driver,"fetch",$result,""); 
			$process = TRUE;
				
			//check role, if present.
			$role = get_option('pp_db_role');
			if (!empty($role)) {	//build the role checker too					
				$rolevalue = $extfields[$sqlfields['pp_db_role']];			
				$rolethresh = get_option('pp_db_role_value');
				$rolebool = get_option('pp_db_role_bool');					
				global $pp_error;
				if ($rolebool == 'is') {
					if ($rolevalue == $rolethresh) {}
					else {
						$username = NULL;
						$pp_error = "wrongrole";													
						$process = FALSE;
					}
				}
				if ($rolebool == 'greater than') {
					if ($rolevalue > $rolethresh) {}
					else {					
						$username = NULL;
						$pp_error = "wrongrole";														
						$process = FALSE;
					}
				}
				if ($rolebool == 'less than') {
					if ($rolevalue < $rolethresh) {}
					else {
						$username = NULL;
						$pp_error = "wrongrole";
						$process = FALSE;
					}
				}			
			}								
			//only continue with user update/creation if login/pw is valid AND, if used, proper role perms
			if((get_option('pp_db_enc') == 'HASH' || get_option('pp_db_enc') == 'PHPass') && pp_check_password( $password, $row['password'] )) {
			if ($process) {
				$userarray['user_login'] = $username;
				$userarray['user_pass'] = $password;                    
				$userarray['first_name'] = $extfields[$sqlfields['first_name']];
				$userarray['last_name'] = $extfields[$sqlfields['last_name']];        
				$userarray['user_url'] = $extfields[$sqlfields['user_url']];
				$userarray['user_email'] = $extfields[$sqlfields['user_email']];
				$userarray['description'] = $extfields[$sqlfields['description']];
				$userarray['aim'] = $extfields[$sqlfields['aim']];
				$userarray['yim'] = $extfields[$sqlfields['yim']];
				$userarray['jabber'] = $extfields[$sqlfields['jabber']];
				$userarray['display_name'] = $extfields[$sqlfields['first_name']]." ".$extfields[$sqlfields['last_name']];            
				
				//also if no extended data fields
				if ($userarray['display_name'] == " ") $userarray['display_name'] = $username;
				
				db_functions($driver,"close",$resource,"");
				
				//looks like wp functions clean up data before entry, so I'm not going to try to clean out fields beforehand.
				if ($id = username_exists($username)) {   //just do an update
					 $userarray['ID'] = $id;
					 wp_update_user($userarray);
				}
				else wp_insert_user($userarray);          //otherwise create
			} } 
			
			if(get_option('pp_db_enc') == 'MD5' || get_option('pp_db_enc') == 'SHA1') {
				if ($process) {
				$userarray['user_login'] = $username;
				$userarray['user_pass'] = $password;                    
				$userarray['first_name'] = $extfields[$sqlfields['first_name']];
				$userarray['last_name'] = $extfields[$sqlfields['last_name']];        
				$userarray['user_url'] = $extfields[$sqlfields['user_url']];
				$userarray['user_email'] = $extfields[$sqlfields['user_email']];
				$userarray['description'] = $extfields[$sqlfields['description']];
				$userarray['aim'] = $extfields[$sqlfields['aim']];
				$userarray['yim'] = $extfields[$sqlfields['yim']];
				$userarray['jabber'] = $extfields[$sqlfields['jabber']];
				$userarray['display_name'] = $extfields[$sqlfields['first_name']]." ".$extfields[$sqlfields['last_name']];            
				
				//also if no extended data fields
				if ($userarray['display_name'] == " ") $userarray['display_name'] = $username;
				
				db_functions($driver,"close",$resource,"");
				
				//looks like wp functions clean up data before entry, so I'm not going to try to clean out fields beforehand.
				if ($id = username_exists($username)) {   //just do an update
					 $userarray['ID'] = $id;
					 wp_update_user($userarray);
				}
				else wp_insert_user($userarray);
				}
			}
		
			if(get_option('pp_db_enc') == 'Other') {
				if ($process) {
				$userarray['user_login'] = $username;
				$userarray['user_pass'] = $password;                    
				$userarray['first_name'] = $extfields[$sqlfields['first_name']];
				$userarray['last_name'] = $extfields[$sqlfields['last_name']];        
				$userarray['user_url'] = $extfields[$sqlfields['user_url']];
				$userarray['user_email'] = $extfields[$sqlfields['user_email']];
				$userarray['description'] = $extfields[$sqlfields['description']];
				$userarray['aim'] = $extfields[$sqlfields['aim']];
				$userarray['yim'] = $extfields[$sqlfields['yim']];
				$userarray['jabber'] = $extfields[$sqlfields['jabber']];
				$userarray['display_name'] = $extfields[$sqlfields['first_name']]." ".$extfields[$sqlfields['last_name']];            
				
				//also if no extended data fields
				if ($userarray['display_name'] == " ") $userarray['display_name'] = $username;
				
				db_functions($driver,"close",$resource,"");
				
				//looks like wp functions clean up data before entry, so I'm not going to try to clean out fields beforehand.
				if ($id = username_exists($username)) {   //just do an update
					 $userarray['ID'] = $id;
					 wp_update_user($userarray);
				}
				else wp_insert_user($userarray);
				}
			}
        }        		  
		else {	//username exists but wrong password...			
			global $pp_error;
			$pp_error = "wrongpw";				
			$username = NULL;
		}
	}
	else {  //don't let login even if it's in the WP db - it needs to come only from the external db.
		global $pp_error;
		$pp_error = "notindb";
		$username = NULL;
	}
	//}  
}


//gives warning for login - where to get "source" login
function pp_db_auth_warning() {
   echo "<p class=\"message\">".get_option('pp_db_error_msg')."</p>";
}

function pp_db_errors() {
	global $error;
	global $pp_error;
	if ($pp_error == "notindb")
		return "<strong>ERROR:</strong> Username not found.";
	else if ($pp_error == "wrongrole")
		return "<strong>ERROR:</strong> You don't have permissions to log in.";
	else if ($pp_error == "wrongpw")
		return "<strong>ERROR:</strong> Invalid password.";
	else
		return $error;
}

//hopefully grays stuff out.
function pp_db_warning() {
	echo '<strong style="color:red;">Any changes made below WILL NOT be preserved when you login again. You have to change your personal information per instructions found @ <a href="' . get_option('pp_db_site_url') . '">login box</a>.</strong>'; 
}

//disables the (useless) password reset option in WP when this plugin is enabled.
function pp_db_show_password_fields() {
	return 0;
}


/*
 * Disable functions.  Idea taken from http auth plugin.
 */
function disable_function_register() {	
	$errors = new WP_Error();
	$errors->add('registerdisabled', __('User registration is not available from this site, so you can\'t create an account or retrieve your password from here. See the message above.'));
	?></form><br /><div id="login_error"><?php _e( 'User registration is not available from this site, so you can\'t create an account or retrieve your password from here. See the message above.' ); ?></div>
		<p id="backtoblog"><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('&larr; Back to %s'), get_bloginfo('title', 'display' )); ?></a></p>
	<?php
	exit();
}

function disable_function() {	
	$errors = new WP_Error();
	$errors->add('registerdisabled', __('User registration is not available from this site, so you can\'t create an account or retrieve your password from here. See the message above.'));
	login_header(__('Log In'), '', $errors);
	?>
	<p id="backtoblog"><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('&larr; Back to %s'), get_bloginfo('title', 'display' )); ?></a></p>
	<?php
	exit();
}


add_action('admin_init', 'pp_db_auth_init' );
add_action('admin_menu', 'pp_db_auth_add_menu');
add_action('wp_authenticate', 'pp_db_auth_check_login', 1, 2 );
//add_action('lost_password', 'disable_function');
//add_action('user_register', 'disable_function');
add_action('register_form', 'disable_function_register');
add_action('retrieve_password', 'disable_function');
add_action('password_reset', 'disable_function');
add_action('profile_personal_options','pp_db_warning');
//add_filter('login_errors','pp_db_errors');
//add_filter('show_password_fields','pp_db_show_password_fields');
//add_filter('login_message','pp_db_auth_warning');

register_activation_hook( __FILE__, 'pp_db_auth_activate' );
?>