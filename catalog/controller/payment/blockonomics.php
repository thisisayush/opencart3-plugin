<?php
/**
 * Blockonomics Payment Controller
 */
namespace Opencart\Catalog\Controller\Extension\Blockonomics\Payment;

class Blockonomics extends \Opencart\System\Engine\Controller {

	/** @var boolean $ajax Whether the request was made via AJAX */
	private $ajax = false;

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Controller Constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);

		require_once(DIR_EXTENSION . 'blockonomics/system/library/blockonomics.php');

		// Make langauge strings and Blockonomics Library available to all
		$this->load->language('extension/blockonomics/payment/blockonomics');
		$this->blockonomics = new \Opencart\Extension\Blockonomics\System\Library\Blockonomics($registry);

		// Is this an ajax request?
		if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) &&
			strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
		{
			$this->ajax = true;
		}
	}

	/**
	 * Displays the Payment Method (a redirect button)
	 * @return void
	 */
	public function index() {

		$data['url_redirect'] = $this->url->link('extension/blockonomics/payment/blockonomics|invoice', $this->config->get('config_secure'));
		$data['button_confirm'] = $this->language->get('button_confirm');

		if (isset($this->session->data['error_blockonomics'])) {
			unset($this->session->data['error_blockonomics']);
		}

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/blockonomics')) {
			return $this->load->view($this->config->get('config_template') . '/template/extension/payment/blockonomics', $data);
		} else {
			return $this->load->view('extension/blockonomics/payment/blockonomics', $data);
		}
	}

	/**
	 * Generatew new bitcoin addres and show the invoice
	 * @return void
	 */
	public function invoice() {

		$this->load->model('checkout/order');

		if (!isset($this->session->data['order_id'])) {
			$this->response->redirect($this->url->link('checkout/cart'));
			return;
		}
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		if (false === $order_info) {
			$this->response->redirect($this->url->link('checkout/cart'));
			return;
		}

		$this->document->setTitle($this->language->get('text_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_invoice'),
			'href' => $this->url->link('extension/blockonomics/payment/blockonomics|invoice')
		);

		if (empty($order_info['currency_code'])) {
			$this->log('error', 'Cannot prepare invoice without `currency_code`');
			throw Exception('Cannot prepare invoice without `currency_code`');
		}

		if (empty($order_info['total'])) {
			$this->log('error', 'Cannot prepare invoice without `total`');
			throw Exception('Cannot prepare invoice without `total`');
		}
		
		$this->document->addLink('extension/blockonomics/catalog/view/css/order.css', "stylesheet");
		$this->document->addScript('extension/blockonomics/catalog/view/javascript/reconnecting-websocket.min.js');
		$this->document->addScript('extension/blockonomics/catalog/view/javascript/qrious.min.js');
		$this->document->addScript('extension/blockonomics/catalog/view/javascript/checkout.js');
		
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$data['currency_code'] = $this->config->get('config_currency');
		$price = $this->blockonomics->getBTCPrice();

		$fiat_amount = $order_info['total'];
		$bits = intval(1.0e8*$fiat_amount/$price);
		$satoshi_amount = $this->blockonomics->fix_displaying_small_values($bits);
		$data['satoshi_amount'] = $satoshi_amount;
		$data['fiat_amount'] = number_format($fiat_amount, 2);
		$data['crypto_rate'] = $this->blockonomics->get_crypto_rate_from_params($fiat_amount, $bits);
		$order_id = $order_info['order_id'];
    
		$this->model_checkout_order->addHistory($order_id, 1, "", true);

		$current_time = time();
		$data['order_id'] = $order_id;

		$data['success_url'] = $this->url->link('checkout/success');
		$data['timeout_url'] = $this->url->link('extension/blockonomics/payment/blockonomics|timeout', $this->config->get('config_secure'));
    
		$sql = $this->db->query("SELECT * FROM ".DB_PREFIX."blockonomics_bitcoin_orders WHERE `id_order` = '".$order_id."' LIMIT 1");
		$order = $sql->row;

		// If there is no existing order in database, generate Bitcoin address
		if(!isset($order['id_order'])){
			$response = $this->blockonomics->getNewAddress();
			if($response->response_code == 200) {
				$btc_address=$response->address;
				$data['btc_address'] = $btc_address;
				$data['btc_href'] = "bitcoin:".$btc_address."?amount=".$satoshi_amount;

				$this->blockonomics->log('info', $btc_address, 1);
				$this->blockonomics->log('info', $price, 1);

				//Insert into blockonomics orders table
				$this->db->query("INSERT IGNORE INTO ".DB_PREFIX."blockonomics_bitcoin_orders (id_order, timestamp,  addr, txid, status,value, bits, bits_payed) VALUES
				('".(int)$order_id."','".(int)$current_time."','".$btc_address."', '', -1,'".(float)$fiat_amount."','".(int)$bits."', 0)");
			} else {
				$data['address_error'] = true;
			}
		// If existing order is found, use existing BTC address and update price and timestamp
		} else {
			$data['btc_address'] = $order['addr'];
			$data['btc_href'] = "bitcoin:".$order['addr']."?amount=".$satoshi_amount;

			$query="UPDATE ".DB_PREFIX."blockonomics_bitcoin_orders SET bits='".$bits."',value='".$fiat_amount."',timestamp=".$current_time." WHERE addr='".$order['addr']."'";
			$this->db->query($query);
		}

		$this->response->setOutput($this->load->view('extension/blockonomics/payment/blockonomicsinvoice', $data));
	}

	/**
	 * Convenience wrapper for blockonomics settings
	 *
	 * Automatically persists to database on set and combines getting and setting into one method
	 * Assumes 'blockonomics_' prefix
	 *
	 * @param string $key Setting key
	 * @param string $value Setting value if setting the value
	 * @return string|null|void Setting value, or void if setting the value
	 */
	public function setting($key, $value = null) {
		// Set the setting
		if (func_num_args() === 2) {
			return $this->blockonomics->setting($key, $value);
		}

		// Get the setting
		return $this->blockonomics->setting($key);
	}

	/**
	 * Callback Handler
	 * @return void
	 */
	public function callback() {
		$this->load->model('checkout/order');

		//Get parameters from callback url
		$secret = $_GET['secret'];
		$txid = $_GET['txid'];
		$value = $_GET['value'];
		$status = $_GET['status'];
		$addr = $_GET['addr'];

		if($this->setting('callback_secret') != $secret) {
			die('Invalid secret');
		}

		//Upate order info
		$query="UPDATE ".DB_PREFIX."blockonomics_bitcoin_orders SET status='".(int)$status."',txid='".$txid."',bits_payed=".(int)$value." WHERE addr='".$addr."'";
		$this->db->query($query);

		$sql = $this->db->query("SELECT * FROM ".DB_PREFIX."blockonomics_bitcoin_orders WHERE `addr` = '".$addr."' LIMIT 1");
		$order = $sql->row;

		if(!isset($order['id_order'])){
			$this->log('error', 'Order with bitcoin address '.$addr.' not found in the records.');
			return;
		}

		$comment = "";
		$bits = $order['bits'];
		$expected = $bits / 1.0e8;
		$real_paid = $value / 1.0e8;
		$paid = $value / 1.0e8;

		$underpayment_slack = $this->setting('underpayment_slack') / 100 * $bits;
		if ($value < $bits - $underpayment_slack) {
			$paid = $paid;
		} else {
			$paid = $expected;
		}

		switch ($status) {
			case 0:
				$order_status_id = '1';
				$comment = "Waiting for Confirmation on Bitcoin network<br>" .
							"Bitcoin transaction id: $txid <br>" .
							"You can view the transaction at: <br>" .
							"<a href='https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr' target='_blank'>https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr</a>";
				// Progress the order status
				$this->model_checkout_order->addHistory($order['id_order'], $order_status_id, $comment, true);
				break;
			case 1:
				break;
			case 2:
				if ($paid < $expected) {
					$order_status_id = '7'; // 7 = Canceled
					$comment = "<b>Warning: Invoice canceled as Paid Amount was less than expected</b><br>";
				} else {
					$order_status_id = $this->setting('complete_status');
				}
				$comment .= "Bitcoin transaction id: $txid\r" .
						"Expected amount: $expected BTC\r" .
						"Paid amount: $real_paid BTC\r" .
						"You can view the transaction at:\r" .
						"<a href='https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr' target='_blank'>https://www.blockonomics.co/api/tx?txid=$txid&addr=$addr</a>";
				// Progress the order status
				$this->model_checkout_order->addHistory($order['id_order'], $order_status_id, $comment, true);
				break;
			default:
				$this->response->redirect($this->url->link('checkout/checkout'));
				return;
		}

	}
}
