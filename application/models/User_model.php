<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class User_model extends Basic_model {

	protected $table = 'users';
}
