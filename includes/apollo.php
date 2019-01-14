<?php

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'Apollo' ) ) {
  final class Apollo {
    protected static $_instance = null;
		private $prefix = 'apollo_';
    public $settings = array();

    public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

    private function __construct() {

			$action = isset($_GET['apollo_action']) ? sanitize_key($_GET['apollo_action']) : '';
			$apollo_invoice_id = isset($_GET['apollo_invoice_id']) ?  $_GET['apollo_invoice_id'] : false;

			$wp_upload_dir = wp_upload_dir();

			add_filter( 'plugin_action_links_' . plugin_basename( APOLLO_FILE ), array($this,'add_action_links') );

			//css
			wp_register_style( 'apollo_settings_css', APOLLO_URL . '/admin/css/custom.css');
			wp_enqueue_style('apollo_settings_css');

			//js
			wp_register_script( 'apollo_settings_js', APOLLO_URL . '/admin/js/custom.js' );
			wp_enqueue_script( 'apollo_settings_js' );

			Apollo_Main_Settings::init_hooks();

			add_action( 'add_meta_boxes', array( $this, 'add_apollo_boxes' ) );
			add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'admin_pdf_callback' ) );

		}

		public function admin_pdf_callback() {
			$action = isset($_GET['apollo_action']) ? sanitize_key($_GET['apollo_action']) : '';
			$order_id = isset($_GET['post']) ? intval( $_GET['post'] ): 0;
			$type = isset($_GET['apollo_type']) ?  $_GET['apollo_type'] : 'invoice';
			$apollo_document_id = isset($_GET['apollo_document_id']) ?  $_GET['apollo_document_id'] : false;
			$apollo_document_number = isset($_GET['apollo_document_number']) ?  $_GET['apollo_document_number'] : false;

			if ($action === 'create') {
				Apollo_invoice::create($order_id, $type);
			} else if ($action === 'pdf' && $apollo_document_id) {
				Apollo_invoice::viewPdf($apollo_document_id, $apollo_document_number, $type);
			}
		}

		function add_apollo_boxes() {
			add_meta_box( 'apollo_invoice', __( 'Apollo - invoice', 'apollo-invoices' ), array( $this, 'display_apollo_invoice_box' ), 'shop_order', 'normal', 'high' );
			add_meta_box( 'apollo_estimate', __( 'Apollo - estimate', 'apollo-invoices' ), array( $this, 'display_apollo_estimate_box' ), 'shop_order', 'side', 'high' );
    }

    function add_action_links( $links ) {
			$settings_url = add_query_arg( array( 'page' => 'apollo-invoices' ), admin_url( 'admin.php' ) );
			array_unshift( $links, sprintf( '<a href="%1$s">%2$s</a>', $settings_url, __( 'Settings', 'apollo-invoices' ) ) );

			return $links;
    }

    public function display_apollo_invoice_box( $order ) {
			$invoice = Apollo_invoice::getInvoice( $order->ID );

			if ( !$invoice ) {
				$url = add_query_arg( array(
					'post' => $order->ID,
					'action' => 'edit',
					'apollo_action' => 'create',
					'apollo_type' => 'invoice'
			), admin_url( 'post.php' ) );

			$wc_order = wc_get_order($order->ID);

			if ($wc_order->get_payment_method() === '') {
				printf( '<a class="button order-page invoice apollo" onclick="notPaidWarn(`%1$s`)" title="Create invoice (also marks is as paid, so generate it after order was paid)">Create</a>', $url);
			} else {
				printf( '<a class="button order-page invoice apollo" href="%1$s" title="Create invoice (also marks is as paid, so generate it after order was paid)">Create</a>', $url);
			}

			} else {
				echo "<table class='order-page-meta-box pdf-invoice apollo'>";

				printf( '<tr>' );
				printf( '<td>%s</td>', __( 'Number:', 'apollo-invoices' ) );
				printf( '<td>%s</td>', $invoice['number'] );
				printf( '</tr>' );

				printf( '<tr>' );
				printf( '<td class="pointer" title="%1$s">%2$s</td>', __( 'You can read about sending inovices in Apollo settings, under Mailing Options', 'apollo-invoices' ), __( 'Sent:', 'apollo-invoices' ) );
				printf( '<td>%s</td>', (bool) $invoice['sent'] ? __( 'Yes', 'apollo-invoices' ) : __( 'No', 'apollo-invoices' ) );
				printf( '</tr>' );

				echo "</table>";

				echo '<p class="invoice-actions">';

				$org_id = get_option('apollo_general_settings')['apollo_organization-id'];

				$view_url = "https://getapollo.io/app/$org_id/documents/view/".$invoice['id'];

				printf( '<a class="button order-page invoice apollo" target="_blank" href="%1$s" title="View invoice on Apollo page">View invoice</a>', $view_url);

				$download_pdf_url = add_query_arg( array(
					'post' => $order->ID,
					'action' => 'edit',
					'apollo_action' => 'pdf',
					'apollo_document_id' => $invoice['id'],
					'apollo_document_number' => $invoice['number'],
					'apollo_type' => 'invoice'
				), admin_url( 'post.php' ) );

				printf( '<a class="button order-page invoice apollo" target="_blank" href="%1$s" title="View invoice PDF">View PDF</a>', $download_pdf_url);

				echo '</p>';

			}
		}

		public function display_apollo_estimate_box( $order ) {
			$estimate = Apollo_invoice::getEstimate( $order->ID );

			if ( !$estimate ) {
				$url = add_query_arg( array(
					'post' => $order->ID,
					'action' => 'edit',
					'apollo_action' => 'create',
					'apollo_type' => 'estimate'
			), admin_url( 'post.php' ) );

				printf( '<a class="button order-page invoice apollo" href="%1$s" title="Create estimate.">Create</a>', $url);

			} else {

				echo "<table class='order-page-meta-box pdf-invoice apollo'>";

				printf( '<tr>' );
				printf( '<td>%s</td>', __( 'Number:', 'apollo-invoices' ) );
				printf( '<td>%s</td>', $estimate['number'] );
				printf( '</tr>' );

				printf( '<tr>' );
				printf( '<td class="pointer" title="%1$s">%2$s</td>', __( 'You can read about sending estimates in Apollo settings, under Mailing Options', 'apollo-invoices' ), __( 'Sent:', 'apollo-invoices' ) );
				printf( '<td>%s</td>', (bool) $estimate['sent'] ? __( 'Yes', 'apollo-invoices' ) : __( 'No', 'apollo-invoices' ) );
				printf( '</tr>' );

				echo "</table>";

				echo '<p class="invoice-actions">';

				$org_id = get_option('apollo_general_settings')['apollo_organization-id'];

				$view_url = "https://getapollo.io/app/$org_id/documents/view/".$estimate['id'];

				printf( '<a class="button order-page invoice apollo" target="_blank" href="%1$s" title="View estimate on Apollo page">View estimate</a>', $view_url);

				$download_pdf_url = add_query_arg( array(
					'post' => $order->ID,
					'action' => 'edit',
					'apollo_action' => 'pdf',
					'apollo_document_id' => $estimate['id'],
					'apollo_document_number' => $estimate['number'],
					'apollo_type' => 'estimate'
				), admin_url( 'post.php' ) );

				printf( '<a class="button order-page invoice apollo" target="_blank" href="%1$s" title="View estimate PDF">View PDF</a>', $download_pdf_url);

				echo '</p>';

			}
		}

		public static function add_plugin_row_meta( $links, $file ) {
			if ( plugin_basename( APOLLO_FILE ) === $file ) {
				$url   = 'https://getapollo.io';
				$title = __( 'Visit Apollo', 'apollo-invoices' );
				$links[] = sprintf( '<a href="%1$s" target="_blank">%2$s</a>', $url, $title );
			}

			return $links;
		}
  }
}