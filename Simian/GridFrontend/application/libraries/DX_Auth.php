<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * DX Auth Class
 *
 * Authentication library for Code Igniter.
 *
 * @author		Dexcell
 * @version		1.0.6
 * @based on	CL Auth by Jason Ashdown (http://http://www.jasonashdown.co.uk/)
 * @link			http://dexcell.shinsengumiteam.com/dx_auth
 * @license		MIT License Copyright (c) 2008 Erick Hartanto
 * @credits		http://dexcell.shinsengumiteam.com/dx_auth/general/credits.html
 */
 
class DX_Auth
{
	// Private
	var $_banned;
	var $_ban_reason;
	var $_auth_error;	// Contain user error when login
	
	function DX_Auth()
	{
		$this->ci =& get_instance();

		log_message('debug', 'DX Auth Initialized');

		// Load required library
		$this->ci->load->library('Session');
		$this->ci->load->database();
		
		// Load DX Auth and OpenID configs
		$this->ci->load->config('dx_auth');
		$this->ci->load->config('openid');
		
		// Load DX Auth language		
		$this->ci->lang->load('dx_auth');
		
		// Load DX Auth event
		$this->ci->load->library('DX_Auth_Event');
		
		// Initialize
		$this->_init();
	}

	/* Private function */

	function _init()
	{
		// When we load this library, auto Login any returning users
		$this->autologin();
		
		// Init helper config variable
		$this->email_activation = $this->ci->config->item('DX_email_activation');
		
		$this->allow_registration = $this->ci->config->item('DX_allow_registration');
		$this->captcha_registration = $this->ci->config->item('DX_captcha_registration');
		
		$this->enabled_openid = $this->ci->config->item('openid_enabled');
		$this->captcha_login = $this->ci->config->item('DX_captcha_login');
		
		// URIs
		$this->banned_uri = $this->ci->config->item('DX_banned_uri');
		$this->deny_uri = $this->ci->config->item('DX_deny_uri');
		$this->login_uri = $this->ci->config->item('DX_login_uri');
		$this->logout_uri = $this->ci->config->item('DX_logout_uri');
		$this->register_uri = $this->ci->config->item('DX_register_uri');
		$this->activate_uri = $this->ci->config->item('DX_activate_uri');
		$this->forgot_password_uri = $this->ci->config->item('DX_forgot_password_uri');
		$this->reset_password_uri = $this->ci->config->item('DX_reset_password_uri');
		$this->change_password_uri = $this->ci->config->item('DX_change_password_uri');	
		$this->cancel_account_uri = $this->ci->config->item('DX_cancel_account_uri');	
		
		// Forms view
		$this->login_view = $this->ci->config->item('DX_login_view');
		$this->register_view = $this->ci->config->item('DX_register_view');
		$this->register_openid_view = $this->ci->config->item('DX_register_openid_view');
		$this->forgot_password_view = $this->ci->config->item('DX_forgot_password_view');
		$this->change_password_view = $this->ci->config->item('DX_change_password_view');
		$this->cancel_account_view = $this->ci->config->item('DX_cancel_account_view');
		
		// Pages view
		$this->deny_view = $this->ci->config->item('DX_deny_view');
		$this->banned_view = $this->ci->config->item('DX_banned_view');
		$this->logged_in_view = $this->ci->config->item('DX_logged_in_view');
		$this->logout_view = $this->ci->config->item('DX_logout_view');		
		
		$this->register_success_view = $this->ci->config->item('DX_register_success_view');
		$this->activate_success_view = $this->ci->config->item('DX_activate_success_view');
		$this->forgot_password_success_view = $this->ci->config->item('DX_forgot_password_success_view');
		$this->reset_password_success_view = $this->ci->config->item('DX_reset_password_success_view');
		$this->change_password_success_view = $this->ci->config->item('DX_change_password_success_view');
		
		$this->register_disabled_view = $this->ci->config->item('DX_register_disabled_view');
		$this->activate_failed_view = $this->ci->config->item('DX_activate_failed_view');
		$this->reset_password_failed_view = $this->ci->config->item('DX_reset_password_failed_view');
	}
	
	function _gen_pass($len = 8)
	{
		// No Zero (for user clarity);
		$pool = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		$str = '';
		for ($i = 0; $i < $len; $i++)
		{
			$str .= substr($pool, mt_rand(0, strlen($pool) -1), 1);
		}

		return $str;
	}	

	/*
	* Function: _encode
	* Modified for DX_Auth
	* Original Author: FreakAuth_light 1.1
	*/
	function _encode($password)
	{
		$majorsalt = $this->ci->config->item('DX_salt');
		
		// if PHP5
		if (function_exists('str_split'))
		{
			$_pass = str_split($password);
		}
		// if PHP4
		else
		{
			$_pass = array();
			if (is_string($password))
			{
				for ($i = 0; $i < strlen($password); $i++)
				{
					array_push($_pass, $password[$i]);
				}
			}
		}

		// encrypts every single letter of the password
		foreach ($_pass as $_hashpass)
		{
			$majorsalt .= md5($_hashpass);
		}

		// encrypts the string combinations of every single encrypted letter
		// and finally returns the encrypted password
		return md5($majorsalt);
	}
	
	function _array_in_array($needle, $haystack) 
	{
    // Make sure $needle is an array for foreach
    if( ! is_array($needle)) 
		{
			$needle = array($needle);
		}
    
		// For each value in $needle, return TRUE if in $haystack
    foreach ($needle as $pin)
		{
			if (in_array($pin, $haystack)) 
				return TRUE;
		}
    // Return FALSE if none of the values from $needle are found in $haystack
    return FALSE;
	}

	
	function _email($to, $from, $subject, $message)
	{
		$this->ci->load->library('Email');
		$email = $this->ci->email;

		$email->from($from);
		$email->to($to);
		$email->subject($subject);
		$email->message($message);

		return $email->send();
	}
	
	// Set last ip and last login function when user login
	function _set_last_ip_and_last_login($user_id)
	{
		$data = array();
		
		if ($this->ci->config->item('DX_login_record_ip'))
		{	
			$data['last_ip'] = $this->ci->input->ip_address();
		}
		
		if ($this->ci->config->item('DX_login_record_time'))
		{
			$data['last_login'] = date('Y-m-d H:i:s', time());
		}
		
		if ( ! empty($data))
		{
			// Load model
			$this->ci->load->model('dx_auth/users', 'users');
			// Update record
			$this->ci->users->set_user($user_id, $data);
		}
	}
	
	// Increase login attempt
	function _increase_login_attempt()
	{		
		if ($this->ci->config->item('DX_count_login_attempts') AND ! $this->is_max_login_attempts_exceeded())
		{
			// Load model
			$this->ci->load->model('dx_auth/login_attempts', 'login_attempts');		
			// Increase login attempts for current IP
			$this->ci->login_attempts->increase_attempt($this->ci->input->ip_address());
		}
	}

	// Clear login attempts
	function _clear_login_attempts()
	{
		if ($this->ci->config->item('DX_count_login_attempts'))
		{
			// Load model
			$this->ci->load->model('dx_auth/login_attempts', 'login_attempts');		
			// Clear login attempts for current IP
			$this->ci->login_attempts->clear_attempts($this->ci->input->ip_address());
		}
	}
	
	function _get_user_level($user_id)
	{
	    $query = array(
        	'RequestMethod' => 'GetUser',
        	'UserID' => $user_id
        );
        
        $response = rest_post($this->ci->config->item('user_service'), $query);
        
        if (element('Success', $response) && is_array($response['User']))
            return (int)element('AccessLevel', $response['User'], 0);
	    
		return 0;
	}

	/* Autologin related function */

	function _create_autologin($user_id)
	{
		$result = FALSE;
		
		// User wants to be remembered
		$user = array(
			'key_id' => substr(md5(uniqid(rand().$this->ci->input->cookie($this->ci->config->item('sess_cookie_name')))), 0, 16),
			'user_id' => $user_id
		);
		
		// Load Models
		$this->ci->load->model('dx_auth/user_autologin', 'user_autologin');

		// Prune keys
		$this->ci->user_autologin->prune_keys($user['user_id']);

		if ($this->ci->user_autologin->store_key($user['key_id'], $user['user_id']))
		{
			// Set Users AutoLogin cookie
			$this->_auto_cookie($user);

			$result = TRUE;
		}

		return $result;
	}

	function autologin()
	{
		$result = FALSE;
		
		if ($auto = $this->ci->input->cookie($this->ci->config->item('DX_autologin_cookie_name')) AND ! $this->ci->session->userdata('DX_logged_in'))
		{
			// Extract data
			$auto = unserialize($auto);
			
			if (isset($auto['key_id']) AND $auto['key_id'] AND $auto['user_id'])
			{
				// Load Models				
				$this->ci->load->model('dx_auth/user_autologin', 'user_autologin');

				// Get key
				$query = $this->ci->user_autologin->get_key($auto['key_id'], $auto['user_id']);								

				if ($result = $query->row())
				{
					// User verified, log them in
					$this->_set_session($result);
					// Renew users cookie to prevent it from expiring
					$this->_auto_cookie($auto);
					
					// Set last ip and last login
					$this->_set_last_ip_and_last_login($auto['user_id']);
					
					$result = TRUE;
				}
			}
		}
		
		return $result;
	}

	function _delete_autologin()
	{
		if ($auto = $this->ci->input->cookie($this->ci->config->item('DX_autologin_cookie_name')))
		{
			// Load Cookie Helper
			$this->ci->load->helper('cookie');

			// Load Models
			$this->ci->load->model('dx_auth/user_autologin', 'user_autologin');

			// Extract data
			$auto = unserialize($auto);			

			// Delete db entry
			$this->ci->user_autologin->delete_key($auto['key_id'], $auto['user_id']);

			// Make cookie expired
			set_cookie($this->ci->config->item('DX_autologin_cookie_name'),	'',	-1);
		}
	}

	function _set_session($data)
	{
		// Get user AccessLevel
		$userlevel = $this->_get_user_level($data->user_id);
	    
		// Set session data array
		$user = array(
			'DX_user_id'					=> $data->id,
			'DX_username'					=> $data->username,
		    'DX_simiangrid_id'				=> $data->user_id,
			'DX_user_level'					=> $userlevel,					
			'DX_logged_in'					=> TRUE
		);

		$this->ci->session->set_userdata($user);
	}

	function _auto_cookie($data)
	{
		// Load Cookie Helper
		$this->ci->load->helper('cookie');

		$cookie = array(
			'name' 		=> $this->ci->config->item('DX_autologin_cookie_name'),
			'value'		=> serialize($data),
			'expire'	=> $this->ci->config->item('DX_autologin_cookie_life')
		);

		set_cookie($cookie);
	}

	/* End of Auto login related function */
	
	/* Helper function */
	
	function check_uri_permissions($allow = TRUE)
	{
		// First check if user already logged in or not
		if ($this->is_logged_in())
		{
			// If user is not admin
			if ( ! $this->is_admin())
			{
				// Get variable from current URI
				$controller = '/'.$this->ci->uri->rsegment(1).'/';
				if ($this->ci->uri->rsegment(2) != '')
				{
					$action = $controller.$this->ci->uri->rsegment(2).'/';
				}
				else
				{
					$action = $controller.'index/';
				}
				
				$have_access = TRUE;
				
				// Deny non-admins from the backend controller
				if (strstr($controller, '/backend/'))
				    $have_access = FALSE;
				
				// Trigger event
				$this->ci->dx_auth_event->checked_uri_permissions($this->get_user_id(), $have_access);
				
				if ( ! $have_access)
				{
					// User didn't have previlege to access current URI, so we show user 403 forbidden access
					$this->deny_access();
				}				
			}
		}
		else
		{
			// User haven't logged in, so just redirect user to login page
			$this->deny_access('login');
		}
	}

	function deny_access($uri = 'deny')
	{
		$this->ci->load->helper('url');
	
		if ($uri == 'login')
		{
			redirect($this->ci->config->item('DX_login_uri'), 'location');
		}
		else if ($uri == 'banned')
		{
			redirect($this->ci->config->item('DX_banned_uri'), 'location');
		}
		else
		{
			redirect($this->ci->config->item('DX_deny_uri'), 'location');			
		}
		exit;
	}
	
	// Get user id
	function get_user_id()
	{
		return $this->ci->session->userdata('DX_user_id');
	}

	// Get username string
	function get_username()
	{
		return $this->ci->session->userdata('DX_username');
	}
	
	function get_name()
	{
	    return $this->ci->session->userdata('DX_username');
	}
	
	// Get user SimianGrid UUID
	function get_simiangrid_id()
	{
	    return $this->ci->session->userdata('DX_simiangrid_id');
	}
		
	// Check is user is has admin privilege
	function is_admin()
	{
		return (int)$this->ci->session->userdata('DX_user_level') >= 200;
	}
	
	// Check if user is logged in
	function is_logged_in()
	{
		return $this->ci->session->userdata('DX_logged_in');
	}

	// Check if user is a banned user, call this only after calling login() and returning FALSE
	function is_banned()
	{
		return $this->_banned;
	}
	
	// Get ban reason, call this only after calling login() and returning FALSE
	function get_ban_reason()
	{
		return $this->_ban_reason;
	}
	
	// Check if username is available to use, by making sure there is no same username in the database
	function is_username_available($username)
	{
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		$this->ci->load->model('dx_auth/user_temp', 'user_temp');

		$users = $this->ci->users->check_username($username);
		$temp = $this->ci->user_temp->check_username($username);
		
		return $users->num_rows() + $temp->num_rows() == 0;
	}
	
	// Check if email is available to use, by making sure there is no same email in the database
	function is_email_available($email)
	{
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		$this->ci->load->model('dx_auth/user_temp', 'user_temp');

		$users = $this->ci->users->check_email($email);
		$temp = $this->ci->user_temp->check_email($email);
		
		return $users->num_rows() + $temp->num_rows() == 0;
	}			
	
	// Check if login attempts bigger than max login attempts specified in config
	function is_max_login_attempts_exceeded()
	{
		$this->ci->load->model('dx_auth/login_attempts', 'login_attempts');
		
		return ($this->ci->login_attempts->check_attempts($this->ci->input->ip_address())->num_rows() >= $this->ci->config->item('DX_max_login_attempts'));
	}
	
	function get_auth_error()
	{
		return $this->_auth_error;
	}
	
	function set_auth_error($error)
	{
	    $this->_auth_error = $error;
	}
	
	/* End of Helper function */
	
	/* Main function */

	function openid_login($openid, $remember = TRUE)
	{
	    // Load Models
	    $this->ci->load->model('dx_auth/users', 'users');
	    
	    // Try to authorize this OpenID with SimianGrid
	    $query = array(
    	    'RequestMethod' => 'AuthorizeIdentity',
		    'Identifier' => $openid,
		    'Credential' => '',
		    'Type' => 'openid'
        );
        
        $response = rest_post($this->ci->config->item('user_service'), $query);
        
        if (element('Success', $response) AND element('UserID', $response))
        {
            // Authorization succeeded
            $userID = $response['UserID'];
            
            // Fetch account data for this user
            $query = array(
            	'RequestMethod' => 'GetUser',
            	'UserID' => $userID
            );
            
            $response = rest_post($this->ci->config->item('user_service'), $query);
            
            if (element('Success', $response) && is_array($response['User']))
            {
                // Fetch this user from the SimianGridFrontend user table
                $username = $response['User']['Name'];
                
                if ($query = $this->ci->users->get_user_by_username($username) AND $query->num_rows() == 1)
                {
                    // Get user record
                	$row = $query->row();
                	
                	// Log in user 
				    $this->_set_session($row);
				    
				    if ($remember)
        			{
        			    // Create auto login if user want to be remembered
        				$this->_create_autologin($row->id);
        			}
        			
        			// Set last ip and last login
        			$this->_set_last_ip_and_last_login($row->id);
        			// Clear login attempts
        			$this->_clear_login_attempts();
        			
        			// Trigger event
        			$this->ci->dx_auth_event->user_logged_in($row->id);
        			
        			return TRUE;
                }
                else
                {
                    $this->_auth_error = "Database lookup failed for $username";
                }
            }
            else
            {
                $this->_auth_error = "GetUser call failed for user $userID";
            }
        }
        else
        {
            $this->_auth_error = "Could not authorize OpenID $openid, do you need to register?";
        }
        
        log_message('error', $this->_auth_error);
        return FALSE;
	}
	
	function login($first_name, $last_name, $password, $remember = TRUE)
	{
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		$this->ci->load->model('dx_auth/user_temp', 'user_temp');
		$this->ci->load->model('dx_auth/login_attempts', 'login_attempts');
		
		// Default return value
		$result = FALSE;
		
		if ( ! empty($first_name) AND ! empty($last_name) AND ! empty($password))
		{
		    $login = $first_name . ' ' . $last_name;
		    
			// Get which function to use based on config
			if ($this->ci->config->item('DX_login_using_username') AND $this->ci->config->item('DX_login_using_email'))
			{
				$get_user_function = 'get_login';
			}
			else if ($this->ci->config->item('DX_login_using_email'))
			{
				$get_user_function = 'get_user_by_email';
			}
			else
			{
				$get_user_function = 'get_user_by_username';
			}
		    
			// Get user query
			if ($query = $this->ci->users->$get_user_function($login) AND $query->num_rows() == 1)
			{
				// Get user record
				$row = $query->row();

				// Check if user is banned or not
				if ($row->banned > 0)
				{
					// Set user as banned
					$this->_banned = TRUE;					
					// Set ban reason
					$this->_ban_reason = $row->ban_reason;
				}
				// If it's not a banned user then try to login
				else
				{
				    if ($this->_check_simiangrid_auth($first_name . ' ' . $last_name, $password))
				    {
				        // Log in user 
						$this->_set_session($row);
						
						if ($row->newpass)
						{
							// Clear any Reset Passwords
							$this->ci->users->clear_newpass($row->id); 
						}
						
						if ($remember)
						{
							// Create auto login if user want to be remembered
							$this->_create_autologin($row->id);
						}						
						
						// Set last ip and last login
						$this->_set_last_ip_and_last_login($row->id);
						// Clear login attempts
						$this->_clear_login_attempts();
						
						// Trigger event
						$this->ci->dx_auth_event->user_logged_in($row->id);

						// Set return value
						$result = TRUE;
				    }
					else						
					{
						// Increase login attempts
						$this->_increase_login_attempt();
						// Set error message
						$this->_auth_error = $this->ci->lang->line('auth_login_incorrect_password');
					}
				}				
			}
			// Check if login is still not activated
			elseif ($query = $this->ci->user_temp->$get_user_function($login) AND $query->num_rows() == 1)
			{
				// Set error message
				$this->_auth_error = $this->ci->lang->line('auth_not_activated');
			}
			else
			{
				// Increase login attempts
				$this->_increase_login_attempt();
				// Set error message
				$this->_auth_error = $this->ci->lang->line('auth_login_username_not_exist');
			}
		}

		return $result;
	}

	function logout()
	{
		// Trigger event
		$this->ci->dx_auth_event->user_logging_out($this->get_user_id());
	
		// Delete auto login
		if ($this->ci->input->cookie($this->ci->config->item('DX_autologin_cookie_name'))) {
			$this->_delete_autologin();
		}
		
		// Destroy session
		$this->ci->session->sess_destroy();		
	}
	
	function _create_simiangrid_inventory($userID, $avtype)
	{
	    $query = array(
        	'RequestMethod' => 'AddInventory',
	    	'AvatarType' => $avtype,
        	'OwnerID' => $userID
        );
        
        $response = rest_post($this->ci->config->item('inventory_service'), $query);
        
        if (element('Success', $response))
            return true;
        
        log_message('error', "Failed to create user inventory for $userID: " . element('Message', $response, 'Unknown error'));
        $this->_auth_error = 'Failed to create user inventory: ' . element('Message', $response, 'Unknown error');
        return false;
	}
	
    function _create_simiangrid_user($first_name, $last_name, $password, $email, $avtype, $userid, $openid)
	{
	    $fullname = $first_name . ' ' . $last_name;
	    
	    // Create the user account
	    $query = array(
		    'RequestMethod' => 'AddUser',
		    'UserID' => $userid,
			'Name' => $fullname,
		    'Email' => $email
		);
		
		$response = rest_post($this->ci->config->item('user_service'), $query);
		
		if (element('Success', $response))
		{
		    if ($this->ci->users->create_simiangrid_identities($fullname, $userid, $password, $openid))
		    {
		        // Create an inventory for this user
        		if ($this->_create_simiangrid_inventory($userid, $avtype))
        		{
        		    log_message('info', "Created SimianGrid user $fullname with ID $userid");
        		    return TRUE;
        		}
        		else
        		{
        		    $this->_auth_error = "Failed to create an inventory for $fullname with ID $userid";
        		}
		    }
		    else
		    {
		        $this->_auth_error = "Failed to create login identities for $fullname with ID $userid";
		    }
    		
    		// If some part of the process failed try to delete the user account. This will also
    		// delete any identities associated with the userid
    		$query = array(
    		    'RequestMethod' => 'RemoveUser',
    		    'UserID' => $userid
    		);
    		rest_post($this->ci->config->item('user_service'), $query);
		}
		else
		{
		    $this->_auth_error = 'Failed to contact the user service: ' . element('Message', $response, 'Unknown error');
		}
		
		log_message('error', $this->_auth_error);
		return FALSE;
	}
	
    function _check_simiangrid_auth($username, $password)
	{
	    $query = array(
        	'RequestMethod'  => 'AuthorizeIdentity',
        	'Identifier'     => $username,
	        'Credential'     => '$1$' . md5($password),
	        'Type'           => 'md5hash'
        );
        
        $response = rest_post($this->ci->config->item('user_service'), $query);
        
        return element('Success', $response, false);
	}
	
	function register($first_name, $last_name, $password, $email, $avtype, $openID = null)
	{
	    $username = $first_name . ' ' . $last_name;
	    $userid = random_uuid();
	    
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		$this->ci->load->model('dx_auth/user_temp', 'user_temp');
		
		// Create a user and identities in SimianGrid
		if (!$this->_create_simiangrid_user($first_name, $last_name, $password, $email, $avtype, $userid, $openID))
		    return FALSE;
		
		// Default return value
        $result = FALSE;

		// New user array
		$new_user = array(
		    'user_id'				=> $userid,
			'username'				=> $username,
			'email'					=> $email,
			'last_ip'				=> $this->ci->input->ip_address()
		);

		// Do we need to send email to activate user
		if ($this->ci->config->item('DX_email_activation'))
		{
			// Add activation key to user array
			$new_user['activation_key'] = md5(rand().microtime());
			
			// Create temporary user in database which means the user still unactivated.
			$insert = $this->ci->user_temp->create_temp($new_user);
		}
		else
		{				
			// Create user 
			$insert = $this->ci->users->create_user($new_user);
			// Trigger event
			$this->ci->dx_auth_event->user_activated($this->ci->db->insert_id());				
		}
		
		if ($insert)
		{
			// Replace password with plain for email
			$new_user['password'] = $password;
			
			$result = $new_user;
			
			// Send email based on config
		
			// Check if user need to activate it's account using email
			if ($this->ci->config->item('DX_email_activation'))
			{
				// Create email
				$from = $this->ci->config->item('DX_webmaster_email');
				$subject = sprintf($this->ci->lang->line('auth_activate_subject'), $this->ci->config->item('DX_website_name'));

				// Activation Link
				$escaped_username = rawurlencode($new_user['username']);
				$new_user['activate_url'] = site_url($this->ci->config->item('DX_activate_uri')."{$escaped_username}/{$new_user['activation_key']}");
				
				// Trigger event and get email content
				$this->ci->dx_auth_event->sending_activation_email($new_user, $message);

				// Send email with activation link
				$this->_email($email, $from, $subject, $message);
			}
			else
			{
				// Check if need to email account details						
				if ($this->ci->config->item('DX_email_account_details')) 
				{
					// Create email
					$from = $this->ci->config->item('DX_webmaster_email');
					$subject = sprintf($this->ci->lang->line('auth_account_subject'), $this->ci->config->item('DX_website_name')); 
					
					// Trigger event and get email content
					$this->ci->dx_auth_event->sending_account_email($new_user, $message);

					// Send email with account details
					$this->_email($email, $from, $subject, $message);														
				}
			}
		}
		
		return $result;
	}

	function forgot_password($login)
	{
		// Default return value
		$result = FALSE;
	
		if ($login)
		{
			// Load Model
			$this->ci->load->model('dx_auth/users', 'users');
			// Load Helper
			$this->ci->load->helper('url');

			// Get login and check if it exists 
			if ($query = $this->ci->users->get_login($login) AND $query->num_rows() == 1)
			{
				// Get User data
				$row = $query->row();
				
				// Check if there is already new password created but waiting to be activated for this login
				if ( ! $row->newpass_key)
				{
					// Appearantly there is no password created yet for this login, so we generate a new password
					$data['password'] = $this->_gen_pass();

					// Create key
					$data['key'] = md5(rand().microtime());

					// Set new password (but it haven't activated yet)
					$this->ci->users->newpass($row->id, $data['password'], $data['key']);

					// Create reset password link to be included in email
					$escaped_username = rawurlencode($row->username);
					$data['reset_password_uri'] = site_url($this->ci->config->item('DX_reset_password_uri')."{$escaped_username}/{$data['key']}");
					
					// Create email
					$from = $this->ci->config->item('DX_webmaster_email');
					$subject = $this->ci->lang->line('auth_forgot_password_subject'); 
					
					// Trigger event and get email content
					$this->ci->dx_auth_event->sending_forgot_password_email($data, $message);

					// Send instruction email
					$this->_email($row->email, $from, $subject, $message);
					
					$result = TRUE;
				}
				else
				{
					// There is already new password waiting to be activated
					$this->_auth_error = $this->ci->lang->line('auth_request_sent');
				}
			}
			else
			{
				$this->_auth_error = $this->ci->lang->line('auth_username_or_email_not_exist');
			}
		}
		
		return $result;
	}

	function reset_password($username, $key = '')
	{
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		$this->ci->load->model('dx_auth/user_autologin', 'user_autologin');
		
		// Default return value
		$result = FALSE;
		
		// Default user_id set to none
		$user_id = 0;
		
		// Get user id
		if ($query = $this->ci->users->get_user_by_username($username) AND $query->num_rows() == 1)
		{
		    $fullname = $username;
			$user_id = $query->row()->id;
			
			// Try to activate new password
			if ( ! empty($username) AND ! empty($key) AND $this->ci->users->activate_newpass($fullname, $user_id, $key) AND $this->ci->db->affected_rows() > 0 )
			{
				// Clear previously setup new password and keys
				$this->ci->user_autologin->clear_keys($user_id);
				
				$result = TRUE;
			}
		}
		return $result;
	}

	function activate($username, $key = '')
	{		
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		$this->ci->load->model('dx_auth/user_temp', 'user_temp');
		
		// Default return value
		$result = FALSE;
				
		if ($this->ci->config->item('DX_email_activation'))
		{
			// Delete user whose account expired (not activated until expired time)
			$this->ci->user_temp->prune_temp();
		}

		// Activate user
		if ($query = $this->ci->user_temp->activate_user($username, $key) AND $query->num_rows() > 0)
		{
			// Get user 
			$row = $query->row_array();

			$del = $row['id'];

			// Unset any unwanted fields
			unset($row['id']); // We don't want to copy the id across
			unset($row['activation_key']);
			unset($row['password']);

			// Create user
			if ($this->ci->users->create_user($row))
			{
				// Trigger event
				$this->ci->dx_auth_event->user_activated($this->ci->db->insert_id());	
				
				// Delete user from temp
				$this->ci->user_temp->delete_user($del);		
				
				$result = TRUE;
			}
		}

		return $result;
	}

	function change_password($old_pass, $new_pass)
	{
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		
		// Default return value
		$result = FAlSE;
		
		// Check the old password
		if ($this->_check_simiangrid_auth($this->get_name(), $old_pass))
		{
		    // Replace old password with new password
		    if ($this->ci->users->create_simiangrid_identities($this->get_name(), $this->get_simiangrid_id(), $new_pass, NULL))
		    {
    		    // Trigger event
    			$this->ci->dx_auth_event->user_changed_password($this->get_user_id(), $new_pass);
    			
    			$result = TRUE;
		    }
		    else
		    {
		        $this->_auth_error = "Error communicating with the identity service";
		    }
		}
		else
		{
		    $this->_auth_error = $this->ci->lang->line('auth_incorrect_old_password');
		}
		
		return $result;
	}
	
	function cancel_account($password)
	{
		// Load Models
		$this->ci->load->model('dx_auth/users', 'users');
		
		// Default return value
		$result = FAlSE;
		
		// Check password
		if ($this->_check_simiangrid_auth($this->get_name(), $password))
		{
		    // Trigger event
			$this->ci->dx_auth_event->user_canceling_account($this->get_user_id());

			// Delete user
			$result = $this->ci->users->delete_user($this->get_user_id());
			
			// Force logout
			$this->logout();
		}
	    else
		{
		    $this->_auth_error = $this->ci->lang->line('auth_incorrect_password');
		}
		
		return $result;
	}
	
	/* End of main function */
	
	/* Recaptcha function */		
		
	function get_recaptcha_reload_link($text = 'Get another CAPTCHA')
	{
		return '<a href="javascript:Recaptcha.reload()">'.$text.'</a>';
	}
		
	function get_recaptcha_switch_image_audio_link($switch_image_text = 'Get an image CAPTCHA', $switch_audio_text = 'Get an audio CAPTCHA')
	{
		return '<div class="recaptcha_only_if_image"><a href="javascript:Recaptcha.switch_type(\'audio\')">'.$switch_audio_text.'</a></div>
			<div class="recaptcha_only_if_audio"><a href="javascript:Recaptcha.switch_type(\'image\')">'.$switch_image_text.'</a></div>';
	}
	
	function get_recaptcha_label($image_text = 'Enter the words above', $audio_text = 'Enter the numbers you hear')
	{
		return '<span class="recaptcha_only_if_image">'.$image_text.'</span>
			<span class="recaptcha_only_if_audio">'.$audio_text.'</span>';
	}
	
	// Get captcha image
	function get_recaptcha_image()
	{
		return '<div id="recaptcha_image"></div>';
	}
	
	// Get captcha input box 
	// IMPORTANT: You should at least use this function when showing captcha even for testing, otherwise reCAPTCHA image won't show up
	// because reCAPTCHA javascript will try to find input type with id="recaptcha_response_field" and name="recaptcha_response_field"
	function get_recaptcha_input()
	{
		return '<input type="text" id="recaptcha_response_field" name="recaptcha_response_field" />';
	}
	
	// Get recaptcha javascript and non javasript html
	// IMPORTANT: you should put call this function the last, after you are using some of get_recaptcha_xxx function above.
	function get_recaptcha_html()
	{
		// Load reCAPTCHA helper function
		$this->ci->load->helper('recaptcha');
		
		// Add custom theme so we can get only image
		$options = "<script>
			var RecaptchaOptions = {
				 theme: 'custom',
				 custom_theme_widget: 'recaptcha_widget'
			};
			</script>";					
			
		// Get reCAPTCHA javascript and non javascript HTML
		$html = recaptcha_get_html($this->ci->config->item('DX_recaptcha_public_key'));
		
		return $options.$html;
	}
	
	// Check if entered captcha code match with the image.
	// Use this in callback function in your form validation
	function is_recaptcha_match()
	{
		$this->ci->load->helper('recaptcha');
		
		$resp = recaptcha_check_answer($this->ci->config->item('DX_recaptcha_private_key'),
			$_SERVER["REMOTE_ADDR"],				
			$_POST["recaptcha_challenge_field"],
			$_POST["recaptcha_response_field"]);
			
		return $resp->is_valid;
	}
		
	/* End of Recaptcha function */
}
