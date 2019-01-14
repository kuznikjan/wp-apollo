<?php
defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'Apollo_General_Settings' ) ) {

	class Apollo_General_Settings extends Apollo_Main_Settings {

		public function __construct() {
			$this->settings_key = 'apollo_general_settings';
			$this->settings_tab = __( 'General', 'apollo-invoices' );
			$this->fields       = $this->get_fields();
			$this->sections     = $this->get_sections();
			$this->defaults     = $this->get_defaults();

			parent::__construct();
		}

		private function get_sections() {
			$sections = array(
				'token'         => array(
					'title'       => __( 'Tokens Options', 'apollo-invoices' ),
					'description' => sprintf( __( 'You can get your token and organization ID at <a target="_blank" href="%1$s">Apollo official page</a>. Token and organization fields are required, in order for plugin to work.', 'apollo-invoices' ), 'https://getapollo.io'),
				),
				'mail'      => array(
          'title' => __( 'Mailing Options', 'apollo-invoices' ),
					'description' => sprintf(
						__( '<p>The PDF invoice will be generated when WooCommerce sends the corresponding email. The email should be <a target="_blank" href="%1$s">enabled</a> in order to automatically generate and send the PDF invoice.</p>
						<p> You can manually create estimates or inovice for each order. You can send estimate or inovice (if both are created, only invoice is sent) by choosing "Email invoice / order details to customer" option under "Order actions". Note that invoice/estimate PDF will be sent only if it was created before sending email.</p>
					', 'apollo-invoices' ), 'admin.php?page=wc-settings&tab=email'),
				),
			);

			return $sections;
		}

		private function get_fields() {
			$settings = array(
        array(
					'id'       => 'apollo-token',
					'name'     => $this->prefix . 'token',
					'title'    => __( 'Apollo token', 'apollo-invoices' ),
					'callback' => array( $this, 'input_callback' ),
					'page'     => $this->settings_key,
					'section'  => 'token',
					'type'     => 'text',
					'desc'     => '',
					'default'  => '',
        ),
        array(
					'id'       => 'apollo-organization-id',
					'name'     => $this->prefix . 'organization-id',
					'title'    => __( 'Organization ID', 'apollo-invoices' ),
					'callback' => array( $this, 'input_callback' ),
					'page'     => $this->settings_key,
					'section'  => 'token',
					'type'     => 'text',
					'desc'     => '',
					'default'  => '',
				),
				array(
					'id'       => 'apollo-send-invoice',
					'name'     => $this->prefix . 'send-invoice',
					'title'    => __( 'Attach invoices to Emails', 'apollo-invoices' ),
					'callback' => array( $this, 'input_callback' ),
					'page'     => $this->settings_key,
					'section'  => 'mail',
					'type'     => 'checkbox',
					'desc'     => __( 'Send invoice automatically', 'apollo-invoices' )
					              . '<br/><div class="apollo-notes">' . __( 'Invoice will be automatically created, marked as PAID and attached to order, if order status matches chosen status. <b>Note that invoice should be sent after payment was already confirmed</b>.', 'apollo-invoices' ) . '</div>',
					'default'  => 0,
				),
				array(
					'id'       => 'apollo-invoice-status',
					'name'     => $this->prefix . 'invoice-status',
					'title'    => 'Order status',
					'callback' => array( $this, 'select_callback' ),
					'page'     => $this->settings_key,
					'section'  => 'mail',
					'type'     => 'select',
					'desc'     => '<div class="apollo-notes">' . __( 'Invoice will be sent when new order with chosen status is created.', 'apollo-invoices' ) . '</div>',
					'options'  => array(
						'customer_on_hold_order'  => __( 'On hold', 'apollo-invoices' ),
						'customer_processing_order'  => __( 'Processing', 'apollo-invoices' ),
						'customer_completed_order'  => __( 'Completed', 'apollo-invoices' ),
					),
					'default'  => 'customer_completed_order',
				),
				array(
					'id'       => 'apollo-send-estimate',
					'name'     => $this->prefix . 'send-estimate',
					'title'    => __( 'Attach estimates to Emails', 'apollo-invoices' ),
					'callback' => array( $this, 'input_callback' ),
					'page'     => $this->settings_key,
					'section'  => 'mail',
					'type'     => 'checkbox',
					'desc'     => __( 'Send estimate automatically', 'apollo-invoices' )
					              . '<br/><div class="apollo-notes">' . __( 'Only sends estimates if order payment type is "Direct bank transfer".', 'apollo-invoices' ) . '</div>',
					'default'  => 0,
				),
			);

			return apply_filters( 'apollo_general_settings', $settings );
    }

    public function sanitize( $input ) {
			$output = get_option( $this->settings_key );

			foreach ( $output as $key => $value ) {
				if ( ! isset( $input[ $key ] ) ) {
					$output[ $key ] = is_array( $output[ $key ] ) ? array() : '';
					continue;
				}

				if ( is_array( $output[ $key ] ) ) {
					$output[ $key ] = $input[ $key ];
					continue;
				}

				$output[ $key ] = stripslashes( $input[ $key ] );
			}

			return apply_filters( 'apollo_sanitized_' . $this->settings_key, $output, $input );
		}
	}
}
