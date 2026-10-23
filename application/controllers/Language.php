<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Language extends CI_Controller {

	public function set($language = 'english')
	{
		$this->load->helper(array('url', 'i18n'));

		$languages = app_languages();

		if ( ! isset($languages[$language]))
		{
			$language = 'english';
		}

		$this->input->set_cookie(array(
			'name'   => 'site_lang',
			'value'  => $language,
			'expire' => 31536000,
		));

		$referer = $this->input->server('HTTP_REFERER');
		$base = base_url();

		if (is_string($referer) && strpos($referer, $base) === 0)
		{
			redirect($referer);
		}

		redirect('');
	}
}
