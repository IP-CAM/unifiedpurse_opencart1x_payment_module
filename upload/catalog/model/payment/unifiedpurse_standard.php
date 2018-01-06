<?php 

###############################################################################
# PROGRAM     : UnifiedPurse OpenCart Payment Module                                 #
# DATE	      : 01-10-2014                        				              #
# AUTHOR      : IBUKUN OLADIPO                                                #
# WEBSITE     : http://www.tormuto.com	                                      #
###############################################################################

class ModelPaymentUNIFIEDPURSEStandard extends Model {
  	public function getMethod($address, $total) {
		$this->language->load('payment/unifiedpurse_standard');
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('unifiedpurse_standard_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
		
		if ($this->config->get('unifiedpurse_standard_total') > 0 && $this->config->get('unifiedpurse_standard_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('unifiedpurse_standard_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}	

		$currencies = array(
			//'EUR',
            //'USD',
            'NGN'
		);
		
		if (!in_array(strtoupper($this->currency->getCode()), $currencies)) {
			//$status = false;
		}			
					
		$method_data = array();
	
		if ($status) {  
      		$method_data = array( 
        		'code'       => 'unifiedpurse_standard',
        		'title'      => $this->language->get('text_title'),
				'sort_order' => $this->config->get('unifiedpurse_standard_sort_order')
      		);
    	}
   
    	return $method_data;
  	}
}
?>