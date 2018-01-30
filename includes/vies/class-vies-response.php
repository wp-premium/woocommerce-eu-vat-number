<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Response object from VIES
 */
class VIES_Response {

	protected $countryCode;
	protected $vatNumber;
	protected $requestDate;
	protected $valid;
	protected $name;
	protected $address;

	/**
	 * Get the country code
	 * @return string
	 */
	public function get_country_code() {
		return $this->countryCode;
	}

	/**
	 * Get the VAT Number
	 * @return string
	 */
	public function get_vat_number() {
		return $this->vatNumber;
	}

	/**
	 * Get the date of the request
	 * @return string
	 */
	public function get_request_date() {
		if ( ! $this->requestDate instanceof DateTime ) {
			$this->requestDate = new DateTime( $this->requestDate );
		}
		return $this->requestDate;
	}

	/**
	 * Whether the number is valid or not
	 * @return string
	 */
	public function is_valid() {
		return $this->valid;
	}

	/**
	 * Get the name
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the address
	 * @return string
	 */
	public function get_address() {
		return $this->address;
	}

}

