<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Users
 *
 * This model represents user authentication data. It operates the following tables:
 * - user account data,
 * - user profiles
 *
 * @package	Tank_auth
 * @author	Ilya Konyukhov (http://konyukhov.com/soft/)
 */
class Users extends CI_Model
{
	private $table_name			= 'users';			// user accounts
	private $profile_table_name	= 'user_profiles';	// user profiles
	private $dbprefix;

	function __construct()
	{
		parent::__construct();

		$ci =& get_instance();
		$this->table_name			= $ci->config->item('db_table_prefix', 'tank_auth').$this->table_name;
		$this->profile_table_name	= $ci->config->item('db_table_prefix', 'tank_auth').$this->profile_table_name;
		$this->dbprefix = $ci->config->item('db_table_prefix', 'tank_auth');
	}

	/**
	 * Get user record by Id
	 *
	 * @param	int
	 * @param	bool
	 * @return	object
	 */
	function get_user_by_id($user_id, $activated)
	{
		$this->db->where('id', $user_id);
		$this->db->where('activated', $activated ? 1 : 0);

		$query = $this->db->get($this->table_name);
		if ($query->num_rows() == 1) return $query->row();
		return NULL;
	}

	/**
	 * Get user record by login (username or email)
	 *
	 * @param	string
	 * @return	object
	 */
	function get_user_by_login($login)
	{
		$this->db->where('LOWER(username)=', strtolower($login));
		$this->db->or_where('LOWER(email)=', strtolower($login));

		$query = $this->db->get($this->table_name);
		if ($query->num_rows() == 1) return $query->row();
		return NULL;
	}

	/**
	 * Get user record by username
	 *
	 * @param	string
	 * @return	object
	 */
	function get_user_by_username($username)
	{
		$this->db->where('LOWER(username)=', strtolower($username));

		$query = $this->db->get($this->table_name);
		if ($query->num_rows() == 1) return $query->row();
		return NULL;
	}

	/**
	 * Get user record by email
	 *
	 * @param	string
	 * @return	object
	 */
	function get_user_by_email($email)
	{
		$this->db->where('LOWER(email)=', strtolower($email));

		$query = $this->db->get($this->table_name);
		if ($query->num_rows() == 1) return $query->row();
		return NULL;
	}

	/**
	 * Check if username available for registering
	 *
	 * @param	string
	 * @return	bool
	 */
	function is_username_available($username)
	{
		$this->db->select('1', FALSE);
		$this->db->where('LOWER(username)=', strtolower($username));

		$query = $this->db->get($this->table_name);
		return $query->num_rows() == 0;
	}

	/**
	 * Check if email available for registering
	 *
	 * @param	string
	 * @return	bool
	 */
	function is_email_available($email)
	{
		$this->db->select('1', FALSE);
		$this->db->where('LOWER(email)=', strtolower($email));
		$this->db->or_where('LOWER(new_email)=', strtolower($email));

		$query = $this->db->get($this->table_name);
		return $query->num_rows() == 0;
	}

	/**
	 * Create new user record
	 *
	 * @param	array
	 * @param	bool
	 * @return	array
	 */
	function create_user($data, $activated)
	{
		$data['created'] = date('Y-m-d H:i:s');
		$data['activated'] = $activated ? 1 : 0;

		if ($this->db->insert($this->table_name, $data)) {
			$user_id = $this->db->insert_id();
			if ($activated)	$this->create_profile($user_id, $data['meta']);
			return array('user_id' => $user_id);
		}
		
		return NULL;
	}

	/**
	 * Activate user if activation key is valid.
	 * Can be called for not activated users only.
	 *
	 * @param	int
	 * @param	string
	 * @param	bool
	 * @return	bool
	 */
	function activate_user($user_id, $activation_key, $activate_by_email)
	{
		$this->db->select('1', FALSE);
		$this->db->where('id', $user_id);
		if ($activate_by_email) {
			$this->db->where('new_email_key', $activation_key);
		} else {
			$this->db->where('new_password_key', $activation_key);
		}
		$this->db->where('activated', 0);
		$query = $this->db->get($this->table_name);

		if ($query->num_rows() == 1) {

			$this->db->set('activated', 1);
			$this->db->set('new_email_key', NULL);
			$this->db->where('id', $user_id);
			$this->db->update($this->table_name);

			$this->create_profile($user_id, $this->get_user_meta($user_id));
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Purge table of non-activated users
	 *
	 * @param	int
	 * @return	void
	 */
	function purge_na($expire_period = 172800)
	{
		$this->db->where('activated', 0);
		$this->db->where('UNIX_TIMESTAMP(created) <', time() - $expire_period);
		$this->db->delete($this->table_name);
	}

	/**
	 * Delete user record
	 *
	 * @param	int
	 * @return	bool
	 */
	function delete_user($user_id)
	{
		$this->db->trans_start();
		$this->db->query("DELETE FROM {$this->table_name} WHERE id=?", array($user_id));
		$this->db->query("DELETE FROM {$this->profile_table_name} WHERE id=?", array($user_id));
		$this->db->query("DELETE FROM {$this->dbprefix}user_roles WHERE user_id=?", array($user_id));
		$this->db->query("DELETE FROM {$this->dbprefix}overrides WHERE user_id=?", array($user_id));
		$this->db->query("DELETE FROM {$this->dbprefix}user_autologin WHERE user_id=?", array($user_id));
		$this->db->trans_complete();
		
		return $this->db->trans_status() ? TRUE : FALSE;
	}

	/**
	 * Set new password key for user.
	 * This key can be used for authentication when resetting user's password.
	 *
	 * @param	int
	 * @param	string
	 * @return	bool
	 */
	function set_password_key($user_id, $new_pass_key)
	{
		$this->db->set('new_password_key', $new_pass_key);
		$this->db->set('new_password_requested', date('Y-m-d H:i:s'));
		$this->db->where('id', $user_id);

		$this->db->update($this->table_name);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Check if given password key is valid and user is authenticated.
	 *
	 * @param	int
	 * @param	string
	 * @param	int
	 * @return	void
	 */
	function can_reset_password($user_id, $new_pass_key, $expire_period = 900)
	{
		$this->db->select('1', FALSE);
		$this->db->where('id', $user_id);
		$this->db->where('new_password_key', $new_pass_key);
		$this->db->where('UNIX_TIMESTAMP(new_password_requested) >', time() - $expire_period);

		$query = $this->db->get($this->table_name);
		return $query->num_rows() == 1;
	}

	/**
	 * Change user password if password key is valid and user is authenticated.
	 *
	 * @param	int
	 * @param	string
	 * @param	string
	 * @param	int
	 * @return	bool
	 */
	function reset_password($user_id, $new_pass, $new_pass_key, $expire_period = 900)
	{
		$this->db->set('password', $new_pass);
		$this->db->set('new_password_key', NULL);
		$this->db->set('new_password_requested', NULL);
		$this->db->where('id', $user_id);
		$this->db->where('new_password_key', $new_pass_key);
		$this->db->where('UNIX_TIMESTAMP(new_password_requested) >=', time() - $expire_period);

		$this->db->update($this->table_name);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Change user password
	 *
	 * @param	int
	 * @param	string
	 * @return	bool
	 */
	function change_password($user_id, $new_pass)
	{
		$this->db->set('password', $new_pass);
		$this->db->where('id', $user_id);

		$this->db->update($this->table_name);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Set new email for user (may be activated or not).
	 * The new email cannot be used for login or notification before it is activated.
	 *
	 * @param	int
	 * @param	string
	 * @param	string
	 * @param	bool
	 * @return	bool
	 */
	function set_new_email($user_id, $new_email, $new_email_key, $activated)
	{
		$this->db->set($activated ? 'new_email' : 'email', $new_email);
		$this->db->set('new_email_key', $new_email_key);
		$this->db->where('id', $user_id);
		$this->db->where('activated', $activated ? 1 : 0);

		$this->db->update($this->table_name);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Activate new email (replace old email with new one) if activation key is valid.
	 *
	 * @param	int
	 * @param	string
	 * @return	bool
	 */
	function activate_new_email($user_id, $new_email_key)
	{
		$this->db->set('email', 'new_email', FALSE);
		$this->db->set('new_email', NULL);
		$this->db->set('new_email_key', NULL);
		$this->db->where('id', $user_id);
		$this->db->where('new_email_key', $new_email_key);

		$this->db->update($this->table_name);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Update user login info, such as IP-address or login time, and
	 * clear previously generated (but not activated) passwords.
	 *
	 * @param	int
	 * @param	bool
	 * @param	bool
	 * @return	void
	 */
	function update_login_info($user_id, $record_ip, $record_time)
	{
		$this->db->set('new_password_key', NULL);
		$this->db->set('new_password_requested', NULL);

		if ($record_ip)		$this->db->set('last_ip', $this->input->ip_address());
		if ($record_time)	$this->db->set('last_login', date('Y-m-d H:i:s'));

		$this->db->where('id', $user_id);
		$this->db->update($this->table_name);
	}

	/**
	 * Ban user
	 *
	 * @param	int
	 * @param	string
	 * @return	void
	 */
	function ban_user($user_id, $reason = NULL)
	{
		$this->db->where('id', $user_id);
		$this->db->update($this->table_name, array(
			'banned'		=> 1,
			'ban_reason'	=> $reason,
		));
	}

	/**
	 * Unban user
	 *
	 * @param	int
	 * @return	void
	 */
	function unban_user($user_id)
	{
		$this->db->where('id', $user_id);
		$this->db->update($this->table_name, array(
			'banned'		=> 0,
			'ban_reason'	=> NULL,
		));
	}

	/**
	 * Create an empty profile for a new user
	 *
	 * @param	int
	 * @return	bool
	 */
	private function create_profile($user_id, $meta)
	{
		$data['id'] = $user_id;
		if($meta){
			$meta = unserialize($meta);
			foreach($meta as $key=>$val){
				if($val === '1' || $val === '0') $meta[$key] = (int)$val;
			}
			$data = array_merge($data, $meta);			
		}
		
		// If there's no admin then make this person the admin
		$query = $this->db->query("SELECT role_id FROM {$this->dbprefix}user_roles WHERE role_id=1 LIMIT 1");
		if($query->num_rows() > 0){
			// If admin exists, use the default role
			$query = $this->db->query("SELECT role_id FROM {$this->dbprefix}roles WHERE `default`=? LIMIT 1", array(1));
			$row = $query->row_array();
			$this->db->query("INSERT INTO {$this->dbprefix}user_roles (user_id, role_id) VALUES (?, ?)", array($user_id, $row['role_id']));
		}
		else {
			// User is admin
			$this->db->query("INSERT INTO {$this->dbprefix}user_roles (user_id, role_id) VALUES (?, ?)", array($user_id, 1));
		}
		
		return $this->db->insert($this->profile_table_name, $data);
	}
		
	/**
	 * Checks if a role exists
	 */
	private function role_exists($role){
		if(is_int($role)){
			$query = $this->db->query("SELECT role_id FROM {$this->dbprefix}roles WHERE role_id=? LIMIT 1", array($role));
		}
		elseif(is_string($role)){
			$role = trim($role);
			$query = $this->db->query("SELECT role FROM {$this->dbprefix}roles WHERE role=? LIMIT 1", array($role));
		}
		
		return (bool)$query->num_rows();
	}
	
	/**
	 * Gets the datatype of a table
	 */
	public function get_profile_datatypes(){
		$query = $this->db->query('SELECT column_name, data_type FROM information_schema.columns WHERE table_name=?', array($this->profile_table_name));
		return $query->result_array();
	}
	
	/**
	 *
	 */
	private function get_user_meta($user_id){
		$q = $this->db->query("SELECT meta FROM {$this->table_name} WHERE id=?", array($user_id));
		$row = $q->row_array();
		return $row['meta'];
	}
	
	/**
	 * Gets the permissions assigned to a role
	 *
	 * @param int $role_id
	 */
	public function get_role_permissions($role_id){
		$query = $this->db->query("SELECT permission FROM {$this->dbprefix}permissions INNER JOIN {$this->dbprefix}role_permissions USING(permission_id) WHERE role_id=?", array($role_id));
		return $query->result_array();
	}
	
	/**
	 * Get any overrides a user may have
	 */
	public function get_permission_overrides($user_id){
		$query = $this->db->query("SELECT permission, allow FROM {$this->dbprefix}permissions INNER JOIN {$this->dbprefix}overrides USING(permission_id) WHERE user_id=?", array($user_id));
		return $query->result_array();
	}
	
	/**
	 * Gets all the permissions of a user based on his role/s
	 */
	public function get_permissions($user_id){
		// Does not include overrites yet
		$q = $this->db->query("SELECT GROUP_CONCAT(DISTINCT permission) permission FROM {$this->dbprefix}user_roles JOIN {$this->dbprefix}roles USING(role_id) JOIN {$this->dbprefix}role_permissions USING(role_id) JOIN {$this->dbprefix}permissions USING(permission_id) WHERE user_id=?", array($user_id));
		$row = $q->row_array();
		return explode(',', $row['permission']);
	}
	
	/**
	 * Returns a multidimensional array with info on the user's roles in associative format
	 * Keys: 'role_id', 'role', 'full', 'default'
	 * 
	 */
	public function get_roles($user_id){
		$query = $this->db->query("SELECT b.role_id, b.role, b.full, b.default FROM {$this->dbprefix}user_roles a INNER JOIN {$this->dbprefix}roles b USING(role_id) WHERE a.user_id=?", array($user_id));
		return $query->result_array();
	}
	
	/**
	 * Add permission to the `overrides` table
	 */
	public function add_override($user_id, $permission, $allow){
		if(is_string($permission)){
			$permission_id = $this->get_permission_id($permission);
			$this->db->query("INSERT IGNORE {$this->dbprefix}overrides VALUES (?, ?, ?)", array($user_id, $permission_id, $allow));
		}
		elseif(is_array($permission)){
			$this->db->trans_start();
			foreach($permission as $val){
				$permission_id = $this->get_permission_id($val);
				$this->db->query("INSERT IGNORE {$this->dbprefix}overrides VALUES (?, ?, ?)", array($user_id, $permission_id, $allow));
			}
			$this->db->trans_complete();
			
			return $this->db->trans_status();
		}
		else {
			return FALSE;
		}
	}
	
	/**
	 * Remove permission from the `overrides` table
	 */
	public function remove_override($user_id, $permission){
		if(is_string($permission)){
			$permission_id = $this->get_permission_id($permission);
			$this->db->query("DELETE FROM {$this->dbprefix}overrides WHERE user_id=? AND permission_id=?", array($user_id, $permission_id));
			
			return $this->db->trans_status();
		}
		elseif(is_array($permission)){
			$this->db->trans_start();
			foreach($permission as $val){
				$permission_id = $this->get_permission_id($val);
				$this->db->query("DELETE FROM {$this->dbprefix}overrides WHERE user_id=? AND permission_id=?", array($user_id, $permission_id));
			}
			$this->db->trans_complete();
			
			return $this->db->trans_status();
		}
		else {
			return FALSE;
		}
	}
	
	/**
	 * Flip the override `allow` column from 1 to 0 and vice versa
	 */
	public function flip_override($user_id, $permission){
		$permission_id = $this->get_permission_id($permission);
		
		// Get the current value
		$q = $this->db->query("SELECT allow FROM {$this->dbprefix}overrides WHERE user_id=? AND permission_id=? LIMIT 1", array($user_id, $permission_id));
		$row = $q->row_array();
		
		// Flip it
		$allow = (int)$row['allow'] ? 0 : 1;
		
		// Save it
		return $this->db->query("UPDATE {$this->dbprefix}overrides SET allow=? WHERE user_id=? AND permission_id=?", array($allow, $user_id, $permission_id));
	}
	
	/**
	 * Get the permission_id of any permission
	 * @param string $permission
	 */
	public function get_permission_id($permission){
		$query = $this->db->query("SELECT permission_id FROM {$this->dbprefix}permissions WHERE permission=? LIMIT 1", array($permission));
		$row = $query->row_array();
		
		return $row['permission_id'];
	}
	
	/**
	 * Add role to user
	 * @param int $user_id
	 * @param multi $role: int `role_id` or string `role` (not full)
	 */
	public function add_role($user_id, $role){
		// Do nothing if $role is int
		if(is_string($role)){
			$role = trim($role);
			$role = $this->get_role_id($role);
		}
		
		return $this->db->query("INSERT IGNORE INTO {$this->dbprefix}user_roles VALUES (?, ?)", array($user_id, $role));
	}
	
	/**
	 * Remove role from user. Cannot remove role if user only has 1 role
	 * @param int $user_id
	 * @param multi $role: int `role_id` or string `role` (not full)
	 */
	public function remove_role($user_id, $role){
		if($this->has_role($user_id, $role)){			
			// If there's only 1 role then removal is denied
			$query = $this->db->query("SELECT COUNT(*) count FROM {$this->dbprefix}user_roles WHERE user_id=?", array($user_id));
			$row = $query->row_array();
			$count = $row['count'];
			if($count > 1){
				// Do nothing if $role is int
				if(is_string($role)){
					$role = trim($role);
					$role = $this->get_role_id($role);
				}
				
				return $this->db->query("DELETE FROM {$this->dbprefix}user_roles WHERE user_id=? AND role_id=?", array($user_id, $role));
			}
			else {
				return FALSE;
			}
		}
		else {
			return TRUE;
		}
	}
	
	/**
	 * Change a user's role for another
	 */
	public function change_role($user_id, $old, $new){
		// Do nothing if $role is int
		if(is_string($old)){
				$old = trim($old);
				$old = $this->get_role_id($old);
		}
		if(is_string($new)){
				$new = trim($new);
				$new = $this->get_role_id($new);
		}
		
		return $this->db->query("UPDATE {$this->dbprefix}user_roles SET role_id=? WHERE role_id=? AND user_id=?", array($new, $old, $user_id));
	}
	
	/**
	 * Does user already have this role?
	 *
	 * @param int $user_id
	 * @param multi $role: int `role_id` or string `role` (not full)
	 * @return bool
	 */
	private function has_role($user_id, $role){
		// Do nothing if $role is int
		if(is_string($role)){
			// Get the role_id of that string
			$role = trim($role);
			$query = $this->db->query("SELECT role_id FROM {$this->dbprefix}roles WHERE role=? LIMIT 1", array($role));
			$row = $query->row_array();
			$role = $row['role_id'];
		}
		
		$query = $this->db->query("SELECT role_id FROM {$this->dbprefix}user_roles WHERE user_id=? AND role_id=? LIMIT 1", array($user_id, $role));
		return $query->num_rows() ? TRUE : FALSE;
	}
	
	/**
	 * Get the role_id of a role
	 *
	 * @param string $role: The `role` value of the role
	 */
	private function get_role_id($role){
		$query = $this->db->query("SELECT role_id FROM {$this->dbprefix}roles WHERE role=?", array($role));
		$row = $query->row_array();
		
		return $row['role_id'];
	}
	
	/**
	 * Get user profile data
	 */
	public function get_user_profile($user_id){
		$query = $this->db->query("SELECT * FROM {$this->profile_table_name} WHERE id=? LIMIT 1", array($user_id));
		return $query->row_array();
	}
	
	/**
	 * Check if user account is approved
	 */
	public function is_approved($user_id){
		$query = $this->db->query("SELECT approved FROM {$this->table_name} WHERE id=? LIMIT 1", array($user_id));
		$row = $query->row_array();
		
		return (bool)$row['approved'];
	}
	
	/**
	 * Add permission to role
	 */
	public function add_permission($permission, $role){
		$role_id = $this->get_role_id($role);
		
		if(is_array($permission)){
			$this->db->trans_start();
			foreach($permission as $val){
				$permission_id = $this->get_permission_id($val);
				$this->db->query("INSERT IGNORE {$this->dbprefix}role_permissions VALUES (?, ?)", array($role_id, $permission_id));
			}
			$this->db->trans_complete();
			
			return $this->db->trans_status();
		}
		elseif(is_string($permission)){
			$permission_id = $this->get_permission_id($permission);
			return $this->db->query("INSERT IGNORE {$this->dbprefix}role_permissions VALUES (?, ?)", array($role_id, $permission_id));
		}
	}
	
	/**
	 * Remove permission from role
	 */
	public function remove_permission($permission, $role){
		$role_id = $this->get_role_id($role);
		
		if(is_array($permission)){
			$this->db->trans_start();
			foreach($permission as $val){
				$permission_id = $this->get_permission_id($val);
				$this->db->query("DELETE FROM {$this->dbprefix}role_permissions WHERE role_id=? AND permission_id=?", array($role_id, $permission_id));
			}
			$this->db->trans_complete();
			
			return $this->db->trans_status();
		}
		elseif(is_string($permission)){
			$permission_id = $this->get_permission_id($permission);
			return $this->db->query("DELETE FROM {$this->dbprefix}role_permissions WHERE role_id=? AND permission_id=?", array($role_id, $permission_id));
		}
	}
	
	/**
	 * Add a new permission to the `permissions` table
	 */
	public function new_permission($permission, $description, $parent = '', $sort = NULL){
		$q = $this->db->query("SELECT permission FROM {$this->dbprefix}permissions WHERE permission=? LIMIT 1", array($permission));
		
		if(!$q->num_rows()){
			return $this->db->query("INSERT INTO {$this->dbprefix}permissions VALUES (NULL, ?, ?, ?, ?)", array($permission, $description, $parent, $sort));
		}
		
		return TRUE;
	}
	
	/**
	 * Delete permission from the `permissions` table
	 */
	public function clear_permission($permission){
		if(is_array($permission)){
			// Create the question marks
			$qmarks = '';
			foreach($permission as $val){
				$qmarks .= '?';
				if($val != end($permission)) $qmarks .= ', ';
			}
			
			// Get the ids
			$query = $this->db->query("SELECT permission_id FROM {$this->dbprefix}permissions WHERE permission IN({$qmarks})", $permission);
			$row = $query->result_array();
			
			// Convert to single array (same as tank_auth->multi_to_single())
			$keys = array_keys($row[0]);
			foreach($row as $val){
				$ids[] = $val[$keys[0]];
			}
			
			$this->db->trans_start();
			$this->db->query("DELETE FROM {$this->dbprefix}permissions WHERE permission IN({$qmarks})", $permission);
			$this->db->query("DELETE FROM {$this->dbprefix}role_permissions WHERE permission_id IN({$qmarks})", $ids);
			$this->db->trans_complete();
			
			return $this->db->trans_status();
		}
		elseif(is_string($permission)){
			$permission_id = $this->get_permission_id($permission);
			$this->db->trans_start();
			$this->db->query("DELETE FROM {$this->dbprefix}permissions WHERE permission=?", array($permission));
			$this->db->query("DELETE FROM {$this->dbprefix}role_permissions WHERE permission_id=?", array($permission_id));
			$this->db->trans_complete();
			//return 
			
			return $this->db->trans_status();
		}
	}
	
	/**
	 * Save permission
	 */
	public function save_permission($data){
		extract($data);
		$permission_id = is_string($permission_ident) ? $this->get_permission_id($permission_ident) : $permission_ident;
		unset($data['permission_ident']);
		
		$str = '';
		foreach($data as $key=>$val){
			if($val !== FALSE){
				$str .= "{$key}=?";
				$arr[] = $val;
				
				if($val != end($data)) $str .= ', ';
			}
		}
		$arr[] = $permission_id;
		
		return $this->db->query("UPDATE {$this->dbprefix}permissions SET {$str} WHERE permission_id=?", $arr);
		//return "UPDATE {$this->dbprefix}permissions SET {$str} WHERE permission_id=?"; // Debug output query string
	}
	
	/**
	 * Approve a user
	 * @param int $user_id User ID
	 * @return bool
	 */
	public function approve_user($user_id){
		return $this->db->query("UPDATE {$this->table_name} SET approved=? WHERE id=?", array(1, $user_id));
	}
	
	/**
	 * Unapprove a user
	 * @param int $user_id User ID
	 * @return bool
	 */
	public function unapprove_user($user_id){
		return $this->db->query("UPDATE {$this->table_name} SET approved=? WHERE id=?", array(0, $user_id));
	}

	
	/**
	 * Gets values from the db for use in a dropdown field for new registrations
	 * @param array $fields 2 column names which will form the <select> key=>value pair
	 * @param string $where Create a more detailed query for example: "WHERE a=b LIMIT 10" It's up to you.
	 * @return multi array|option|li (default: array)
	 */
	public function create_regdb_dropdown($dbname, $fields, $where = ''){
		// If you typed 3 fields, this makes sure you only get the first 2
		$arr[] = $fields[0];
		$arr[] = $fields[1];
		$str = implode(', ', $arr);
		$dbname = $dbname[0];
		
		$query = $this->db->query(trim("SELECT {$str} FROM {$dbname} {$where}"));
		$row = $query->result_array();
		$row = $this->tank_auth->multi_to_assoc($row, array('0'=>'- choose -'));
		
		return $row;
	}
	
}

/* End of file users.php */
/* Location: ./application/models/auth/users.php */