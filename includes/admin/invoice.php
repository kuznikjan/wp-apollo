<?php

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'Apollo_invoice' ) ) {
	abstract class Apollo_invoice {
    public static function create( $order_id, $type = 'invoice' ) {

      $invoice_exsists = Apollo_invoice::getInvoice($order_id);
      $estimate_exsists = Apollo_invoice::getEstimate($order_id);

      if ($type === 'invoice' && $invoice_exsists) {
          return $invoice_exsists;
      } else if ($type === 'estimate' && $estimate_exsists) {
          return $estimate_exsists;
      }
      $order = wc_get_order($order_id);
      $output = get_option('apollo_general_settings');
      $token = $output['apollo_token'];
      $organization_id = $output['apollo_organization-id'];

      $test = Spaceinvoices\Spaceinvoices::setAccessToken($token);

      if (!$order || !$output || !$token || !$organization_id) {
        return false;
      }

      $SI_products_data = array();
      $SI_total = 0;
      $order_total = 0;

      foreach ($order->get_items() as $item_id => $item_data) {
        $product = $item_data->get_data();
        $without_tax = floatval($product['total']) / $product['quantity'];
        $tax_amount = floatval($product['total_tax']) / $product['quantity'];

        $tax_percent = ($tax_amount / $without_tax) * 100;

        $product_data = array(
          'name'     => $product['name'],
          'unit'     => 'item',
          'quantity' => $product['quantity'],
          'price'    => $without_tax,
          '_documentItemTaxes' => array(array('rate' => $tax_percent))
        );

        if (floatval($product['total']) < floatval($product['subtotal'])) { // item is discounted
          $product_data['discount'] = (1 - (floatval($product['total']) / floatval($product['subtotal']))) * 100;
          $product_data['price'] = floatval($product['subtotal']) / $product['quantity'];
        }

        $SI_products_data[] = $product_data;
      }

      foreach( $order->get_items('shipping') as $item_id => $item_shipping ) {
        $shipping = $item_shipping->get_data();

        $without_tax = floatval($shipping['total']);
        $tax_amount = floatval($shipping['total_tax']);

        $tax_percent = ($tax_amount / $without_tax) * 100;

        $shipping_data = array(
          'name'     => $shipping['name'],
          'unit'     => 'shipping',
          'quantity' => 1,
          'price'    => $without_tax
        );
        if ($tax_percent != 0) {
          $shipping_data['_documentItemTaxes'] = array(array('rate' => $tax_percent));
        }
        $SI_products_data[] = $shipping_data;
      }


      foreach( $order->get_items('fee') as $item_id => $item_fee ) {
        $fee = $item_fee->get_data();

        $without_tax = floatval($fee['total']);
        $tax_amount = floatval($fee['total_tax']);

        $tax_percent = ($tax_amount / $without_tax) * 100;

        $fee_data = array(
          'name'     => $fee['name'],
          'unit'     => 'fee',
          'quantity' => 1,
          'price'    => $without_tax
        );
        if ($tax_percent != 0) {
          $fee_data['_documentItemTaxes'] = array(array('rate' => $tax_percent));
        }
        $SI_products_data[] = $fee_data;
      }


      $order_data = array(
        "type" => $type,
        "currencyId" => $order->get_currency(),
        "_documentClient" => array(
          'name' 		=> $order->get_billing_company() !== '' ? $order->get_billing_company() : $order->get_billing_first_name(). ' ' .$order->get_billing_last_name(),
          'contact' => $order->get_billing_company() ? $order->get_billing_first_name(). ' ' .$order->get_billing_last_name() : '',
          'address' => $order->get_billing_address_1(),
          'address2'=> $order->get_billing_address_2(),
          'city'    => $order->get_billing_city(),
          'zip'			=> $order->get_billing_postcode(),
          'country' => $order->get_billing_country(),
          'email' 	=> $order->get_billing_email(),
          'phone' 	=> $order->get_billing_phone()
        ),
        "_documentItems" => $SI_products_data
      );

      $create = Spaceinvoices\Documents::create($organization_id, $order_data);
      $document_id = $create->id;
      $document_number = $create->number;

      $document_data = array(
        "type" => $type,
        "id" => $document_id,
        "number" => $document_number,
        "sent" => false
      );

      if ($type === 'invoice') {
        update_post_meta( $order_id, 'apollo_invoice_id', $document_id );
        update_post_meta( $order_id, 'apollo_invoice_number', $document_number );
        update_post_meta( $order_id, 'apollo_invoice_sent', false );

        $pay = Spaceinvoices\Payments::create($document_id, array( // mark invoice as paid
          "type" => "other",
          "date" => date("Y-m-d"),
          "amount" => $order->get_total(),
          "note" => $order->get_payment_method_title()
        ));

      } else if ($type === 'estimate') {
        update_post_meta( $order_id, 'apollo_estimate_id', $document_id );
        update_post_meta( $order_id, 'apollo_estimate_number', $document_number );
        update_post_meta( $order_id, 'apollo_estimate_sent', false );
      }

      return $document_data;
    }

    public static function getInvoice( $order_id ) {
      $id = get_post_meta( $order_id, 'apollo_invoice_id', true);
      if (!$id) {
        return false;
      }
      $number = get_post_meta( $order_id, 'apollo_invoice_number', true);
      $sent = get_post_meta( $order_id, 'apollo_invoice_sent', true);

      return array('id' => $id, 'number' => $number, 'sent' => $sent);
    }

    public static function getEstimate( $order_id ) {
      $id = get_post_meta( $order_id, 'apollo_estimate_id', true);
      if (!$id) {
        return false;
      }
      $number = get_post_meta( $order_id, 'apollo_estimate_number', true);
      $sent = get_post_meta( $order_id, 'apollo_estimate_sent', true);

      return array('id' => $id, 'number' => $number, 'sent' => $sent);
    }

    public function getPdf($id, $number, $type) {
      $pdf_path = APOLLO_DOCUMENTS_DIR."/".$type." - ".$number.".pdf";
      if (file_exists($pdf_path)) {
        return $pdf_path;
      }
      $token = get_option('apollo_general_settings')['apollo_token'];
      if(!$token) {
        return false;
      }
      Spaceinvoices\Spaceinvoices::setAccessToken($token);
      $pdf = Spaceinvoices\Documents::getPdf($id);

      if($pdf->error) {
        // return $pdf;
        return "Error creating PDF";
      }

      $pdf_path = APOLLO_DOCUMENTS_DIR."/".$type." - ".$number.".pdf";

      if(!file_exists(dirname($pdf_path)))
          mkdir(dirname($pdf_path), 0777, true);


      $fp = fopen($pdf_path,"wb");
      fwrite($fp,$pdf);
      fclose($fp);

      return $pdf_path;
    }

    public function viewPdf($id, $number, $type) {
      $pdf_path = APOLLO_DOCUMENTS_DIR."/".$type." - ".$number.".pdf";
      if (!file_exists($pdf_path)) {
        $pdf_path = Apollo_invoice::getPdf($id, $number, $type);
      }
      header( 'Content-type: application/pdf' );
			header( 'Content-Disposition: inline; filename="' . basename( $pdf_path ) . '"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Length: ' . filesize( $pdf_path ) );
			header( 'Accept-Ranges: bytes' );

			readfile( $pdf_path );
      exit;
    }
  }
}