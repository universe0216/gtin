<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'models/Basic_model.php';

class Product_model extends Basic_model {

	protected $table = 'products';
}
