<?php
/**
 * Plugin Name:       Apollo
 * Plugin URI:        https://wordpress.org/plugins/apollo
 * Description:       Manually or automatically generate invoices and send PDFs as attachments for WooCommerce orders.
 * Version:           1.0.1
 * Author:            Studio404
 * Text Domain:       apollo
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) or exit;

define( 'APOLLO_VERSION', '1.0.1' );

function apollo_load_plugin() {

  if ( ! class_exists( 'WooCommerce' ) ) {
		return;
  }

  if ( ! defined( 'APOLLO_FILE' ) ) {
		define( 'APOLLO_FILE', __FILE__ );
  }


  if ( ! defined( 'APOLLO_DIR' ) ) {
    define( 'APOLLO_DIR', plugin_dir_path(__FILE__ ));
  }

  if ( ! defined( 'APOLLO_DOCUMENTS_DIR' ) ) {
    define( 'APOLLO_DOCUMENTS_DIR', wp_upload_dir()['basedir'] . '/apollo-documents' );
  }

  if ( ! defined( 'APOLLO_URL' ) ) {
    define( 'APOLLO_URL', untrailingslashit( plugins_url( '', APOLLO_FILE ) ) );
  }

  if ( file_exists( APOLLO_DIR . 'includes/apollo.php' ) ) {
		require_once APOLLO_DIR . 'includes/apollo.php';
  }

  if ( file_exists( APOLLO_DIR . 'includes/admin/settings/main-settings.php' ) ) {
		require_once APOLLO_DIR . 'includes/admin/settings/main-settings.php';
  }

  if ( file_exists( APOLLO_DIR . 'includes/admin/settings/general.php' ) ) {
		require_once APOLLO_DIR . 'includes/admin/settings/general.php';
  }

  if ( file_exists( APOLLO_DIR . 'includes/admin/invoice.php' ) ) {
		require_once APOLLO_DIR . 'includes/admin/invoice.php';
  }

  if ( file_exists( APOLLO_DIR . 'vendor/spaceinvoices/vendor/autoload.php' ) ) {
		require_once APOLLO_DIR . 'vendor/spaceinvoices/vendor/autoload.php';
  }

  Apollo::instance();
}

add_action( 'plugins_loaded', 'apollo_load_plugin' );

add_filter( 'woocommerce_email_attachments', 'add_apollo_document_to_email', 10, 3 );
function add_apollo_document_to_email( $attachments, $status, $order ) {

  $auto_invoice = (bool) get_option('apollo_general_settings')['apollo_send-invoice'];
  $auto_invoice_status = get_option('apollo_general_settings')['apollo_invoice-status'];
  $invoice_id = get_post_meta( $order->ID, 'apollo_invoice_id', true);
  $invoice_number = get_post_meta( $order->ID, 'apollo_invoice_number', true);
  $pdf_invoice_path = APOLLO_DOCUMENTS_DIR."/invoice - ".$invoice_number.".pdf";

  $auto_estimate = (bool) get_option('apollo_general_settings')['apollo_send-estimate'];
  $estimate_number = get_post_meta( $order->ID, 'apollo_estimate_number', true);
  $estimate_id = get_post_meta( $order->ID, 'apollo_estimate_id', true);
  $pdf_estimate_path = APOLLO_DOCUMENTS_DIR."/estimate - ".$estimate_number.".pdf";

  if ($auto_estimate && $payment_method === 'bacs' && $status !== 'customer_invoice') { // new order; bank transfer
    $estimate = Apollo_invoice::create($order->ID, 'estimate');
    $attachments[] = Apollo_invoice::getPdf($estimate['id'], $estimate['number'], 'estimate');
    update_post_meta( $order->ID, 'apollo_estimate_sent', true );

  } else if ($auto_invoice && $auto_invoice_status === $status) { // new order; status matches invoice settings
    $invoice = Apollo_invoice::create($order->ID, 'invoice');
    $attachments[] = Apollo_invoice::getPdf($invoice['id'], $invoice['number'], 'invoice');
    update_post_meta( $order->ID, 'apollo_invoice_sent', true );

  } else if($invoice_id && $status === 'customer_invoice') { // sent maunally from order (invoice)
    if(file_exists($pdf_invoice_path)) {
      $attachments[] = $pdf_invoice_path;
    } else {
      $attachments[] = Apollo_invoice::getPdf($invoice_id, $invoice_number, 'invoice');
    }
    update_post_meta( $order->ID, 'apollo_invoice_sent', true );

  } else if($estimate_id && $status === 'customer_invoice') { // sent maunally from order (estimate)
    if(file_exists($pdf_estimate_path)) {
      $attachments[] = $pdf_estimate_path;
    } else {
      $attachments[] = Apollo_invoice::getPdf($estimate_id, $estimate_number, 'estimate');
    }
    update_post_meta( $order->ID, 'apollo_estimate_sent', true );
  }
  return $attachments;
}