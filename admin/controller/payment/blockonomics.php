<?php
/**
 * Blockonomics Payment Admin Controller
 */
namespace Opencart\Admin\Controller\Extension\Blockonomics\Payment;

use Opencart\Admin\Config;

class Blockonomics extends \Opencart\System\Engine\Controller {

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Admin Controller Constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);

		require_once(DIR_EXTENSION . 'blockonomics/system/library/blockonomics.php');

		// echo "<h1>WORKIN</h1>";
		// Make langauge strings and Blockonomics Library available to all
		$this->load->language('extension/blockonomics/payment/blockonomics');
		// echo "<h1>WORKIN</h1>";
		$this->blockonomics = new \Opencart\Extension\Blockonomics\System\Library\Blockonomics($registry);
	}
	
	/**
	 * Primary settings page
	 * @return void
	 */
	public function index() {

		$this->document->setTitle($this->language->get('heading_title'));

		$data = array();

		$data['text_changes'] = $this->language->get('text_changes');
		$data['text_congrats'] = $this->language->get('congrats');

		$data['text_statuses'] = $this->language->get('text_statuses');
		$data['text_gen_secret'] = $this->language->get('text_gen_secret');

		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_callback_url'] = $this->language->get('entry_callback_url');
		$data['entry_api_key'] = $this->language->get('entry_api_key');
		$data['entry_underpayment_slack'] = $this->language->get('entry_underpayment_slack');
		$data['entry_callback_secret'] = $this->language->get('entry_callback_secret');
    	$data['entry_complete_status'] = $this->language->get('entry_complete_status');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_gen_secret'] = $this->language->get('button_save');

		$data['url_action'] = $this->url->link('extension/blockonomics/payment/blockonomics|save', 'user_token=' . $this->session->data['user_token']);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token']);
		$data['url_reset'] = $this->url->link('extension/blockonomics/payment/reset', 'user_token=' . $this->session->data['user_token']);
		$data['url_gen_secret'] = $this->url->link('extension/blockonomics/payment/blockonomics|gensecret', 'user_token=' . $this->session->data['user_token']);
		$data['url_test_setup'] = $this->url->link('extension/blockonomics/payment/blockonomics|testsetup', 'user_token=' . $this->session->data['user_token']);

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_payment'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/blockonomics/payment/blockonomics', 'user_token=' . $this->session->data['user_token'])
		);

		#GENERAL
		$data['blockonomics_connection'] = $this->config->get('payment_blockonomics_connection');
		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		$data['blockonomics_geo_zone_id'] = $this->config->get('payment_blockonomics_geo_zone_id');
		$data['blockonomics_status'] = $this->config->get('payment_blockonomics_status');
		$data['blockonomics_sort_order'] = $this->config->get('payment_blockonomics_sort_order');

		#ADVANCED
		$data['blockonomics_callback_url'] = $this->config->get('payment_blockonomics_callback_url');
		$data['blockonomics_callback_secret'] = $this->config->get('payment_blockonomics_callback_secret');
		$data['blockonomics_api_key'] = $this->config->get('payment_blockonomics_api_key');
		$data['blockonomics_underpayment_slack'] = $this->config->get('payment_blockonomics_underpayment_slack');

    #ORDER STATUSES
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['blockonomics_complete_status'] = $this->config->get('payment_blockonomics_complete_status');

		#LAYOUT
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		
		$this->response->setOutput($this->load->view('extension/blockonomics/payment/blockonomics', $data));
	}

	public function save(): void {

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/blockonomics/payment/blockonomics')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!empty($this->request->post['payment_blockonomics_callback_url']) && false === filter_var($this->request->post['payment_blockonomics_callback_url'], FILTER_VALIDATE_URL)) {
			$json['error'] = $this->language->get('error_callback_url');
		}

		if (empty($this->request->post['payment_blockonomics_api_key'])) {
			$json['error'] = $this->language->get('error_api_key');
		}

		if (!$json) {

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting(
				'payment_blockonomics',
				array(
					'payment_blockonomics_geo_zone_id' => $this->request->post['payment_blockonomics_geo_zone_id'],
					'payment_blockonomics_status' => $this->request->post['payment_blockonomics_status'],
					'payment_blockonomics_sort_order' => $this->request->post['payment_blockonomics_sort_order'],
					'payment_blockonomics_callback_secret' => $this->request->post['payment_blockonomics_callback_secret'],
					'payment_blockonomics_callback_url' => $this->request->post['payment_blockonomics_callback_url'],
					'payment_blockonomics_api_key' => $this->request->post['payment_blockonomics_api_key'],
					'payment_blockonomics_underpayment_slack' => $this->request->post['payment_blockonomics_underpayment_slack'],
					'payment_blockonomics_complete_status' => $this->request->post['payment_blockonomics_complete_status']
				)
			);
			
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function gensecret() {

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/blockonomics/payment/blockonomics')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$secret = md5(uniqid(rand(), true));

		$default_callback_url = $this->url->link('extension/blockonomics/payment/blockonomics|callback&secret='.$secret , $this->config->get('config_secure'));
		
		$default_callback_url = str_replace(HTTP_SERVER, HTTP_CATALOG, $default_callback_url);
		
		if (defined('HTTPS_SERVER')) {
			$default_callback_url = str_replace(HTTPS_SERVER, HTTPS_CATALOG, $default_callback_url);
		}

		$default_callback_url = str_replace('&amp;', '&', $default_callback_url);

		$json['callback_secret'] = $secret;
		$json['callback_url'] = $default_callback_url;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
  	}

  	public function testsetup() {
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/blockonomics/payment/blockonomics')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {

			$response = $this->blockonomics->testsetup();

			if($response){
				$json['error'] = $this->language->get('error_'.$response);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install the extension by setting up some smart defaults
	 * @return void
	 */
	public function install() {

    	$this->load->model('localisation/order_status');
		$order_statuses = $this->model_localisation_order_status->getOrderStatuses();
		$default_complete_status = null;

		foreach ($order_statuses as $order_status) {
			if ($order_status['name'] == 'Processing') {
				$default_complete_status = $order_status['order_status_id'];
			}
    	}

		$this->blockonomics->log("info", $default_complete_status );

		//Generate callback secret
		$secret = md5(uniqid(rand(), true));

		$default_callback_url = $this->url->link('extension/blockonomics/payment/blockonomics|callback&secret='.$secret , $this->config->get('config_secure'));
		$default_callback_url = str_replace(HTTP_SERVER, HTTP_CATALOG, $default_callback_url);
		if (defined('HTTPS_SERVER')) {
			$default_callback_url = str_replace(HTTPS_SERVER, HTTPS_CATALOG, $default_callback_url);
		}
		$default_callback_url = str_replace('&amp;', '&', $default_callback_url);
		
		$data['default_callback_url'] = $default_callback_url;

		$this->db->query("DELETE FROM ".DB_PREFIX."setting WHERE code = 'blockonomics'");
		
		$default_settings = array(
			'payment_blockonomics_token' => '',
			'payment_blockonomics_geo_zone_id' => '0',
			'payment_blockonomics_status' => '0',
			'payment_blockonomics_sort_order' => '',
			'payment_blockonomics_callback_secret' => $secret,
			'payment_blockonomics_callback_url' => $default_callback_url,
			'payment_blockonomics_api_key' => '',
			'payment_blockonomics_underpayment_slack' => '0',
			'payment_blockonomics_version' => $this->blockonomics->version,
			'payment_blockonomics_complete_status' => $default_complete_status
		);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('payment_blockonomics', $default_settings);

		$this->db->query(
			"CREATE TABLE IF NOT EXISTS ".DB_PREFIX."blockonomics_bitcoin_orders (
					id INT UNSIGNED NOT NULL AUTO_INCREMENT,
					id_order INT UNSIGNED NOT NULL,
					timestamp INT(8) NOT NULL,
					addr varchar(255) NOT NULL,
					txid varchar(255) NOT NULL,
					status int(8) NOT NULL,
					value double(10,2) NOT NULL,
					bits int(8) NOT NULL,
					bits_payed int(8) NOT NULL,
					PRIMARY KEY (id),
				UNIQUE KEY order_table (addr))"
		);
	}

	/**
	 * Uninstall the extension by removing the settings
	 * @return void
	 */
	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_blockonomics');
		$this->db->query('DROP TABLE IF EXISTS `' .DB_PREFIX. 'blockonomics_bitcoin_orders`;');
	}
}
