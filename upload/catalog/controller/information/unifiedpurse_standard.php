<?php
class ControllerInformationUNIFIEDPURSEStandard extends Controller
 {     
     public function index() 
	 {
	  $this->load->model('checkout/order');
	  
       $this->document->setTitle('UNIFIEDPURSEStandard Transactions'); 

		$this->data['breadcrumbs'] = array(); // Breadcrumbs for your website. 
		$this->data['breadcrumbs'][] = array(
			'text'			=> 'Home',
			'href'			=> $this->url->link('common/home'),           
			'separator'		=> false
		);
		$this->data['breadcrumbs'][] = array(
			'text'			=> 'UNIFIEDPURSEStandard Transactions',
			'href'      	=> $this->url->link('information/unifiedpurse_standard'),
			'separator' 	=> $this->language->get('text_separator')
		);   
		

		if(!empty($this->request->get['access_type']))
		{
			$access_type=$this->request->get['access_type'];
			$this->session->data['access_type']=$access_type;
		}
		else $access_type=$this->session->data['access_type'];
		
		$sql="";
		$toecho="";
		/*
		if($this->user->isLogged())
		{
			$sql="SELECT * FROM ".DB_PREFIX."unifiedpurse_standard";
		}
		else
		*/
		$query=$this->db->query("SHOW TABLES LIKE '".DB_PREFIX."unifiedpurse_standard'");
		
		if(empty($query->rows))$toecho="<h3>This records does not exist yet.</h3>";
		elseif($access_type!='admin'&&!$this->customer->isLogged())$toecho="<h3>Please login first</h3>";
		else
		{
			
			if(!empty($this->request->get['order_id']))
			{
				$order_id=$this->request->get['order_id'];
				
				$query=$this->db->query("SELECT * FROM ".DB_PREFIX."unifiedpurse_standard WHERE order_id='".$this->db->escape($order_id)."' LIMIT 1");
				
				if(empty($query->row))$toecho="<h3>Order record not found!</h3>";
				elseif(!empty($query->row['response_code']))$toecho="<h3>Order $order_id has been already processed!</h3>";
				
				else
				{
					$mertid=$this->config->get('unifiedpurse_mert_id');
					$order_info = $this->model_checkout_order->getOrder($order_id);
					$amount=$query->row['transaction_amount'];
					$currency=$query->row['currency'];
					$unifiedpurse_tranx_id=$query->row['transaction_id'];
					$url="https://unifiedpurse.com/api_v1?action=get_transaction&receiver=$mertid&ref=$unifiedpurse_tranx_id&amount=$amount&currency=$currency";
					$ch = curl_init();
					//	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);			
					curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_URL, $url);
					
					$response = curl_exec($ch);
					$returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					curl_close($ch);
					
					if($returnCode == 200)
					{
						$json=@json_decode($response,true);
					}
					else
					{
						$success=false;
						$json=null;
						$info="Error ($returnCode) accessing unifiedpurse confirmation page";
						//$order_status_id = $this->config->get('unifiedpurse_pending_order_status_id');
					}
					
					
					if(!empty($json))
					{
						if($json['status_msg']=='COMPLETED')
						{
							$order_status_id = $this->config->get('unifiedpurse_completed_status_id');
							$info="Payment Confirmation Successfull";
							$success=true;
						}
						else//transaction not completed for one reason or the other.
						{
							if($json['status_msg']=='FAILED')$order_status_id = $this->config->get('unifiedpurse_failed_status_id');	
							else $order_status_id = $this->config->get('unifiedpurse_pending_order_status_id');	
							$info="Payment Not Cofirmed: ".$json['info'];
						}
						
						if(!$order_info['order_status_id'])$this->model_checkout_order->confirm($order_id, $order_status_id);
						else $this->model_checkout_order->update($order_id, $order_status_id);		

						$this->db->query("UPDATE ".DB_PREFIX."unifiedpurse_standard SET
							approved_amount='".$this->db->escape($json['amount'])."',
							response_code='{$json['status']}',
							response_description='".$this->db->escape($json['info'])."'
							WHERE order_id='$order_id' LIMIT 1");
					}
					
					$toecho.=$info;
				}
			}
		
		
			if($access_type=='admin')$sql="SELECT * FROM ".DB_PREFIX."unifiedpurse_standard ";
			else $sql="SELECT * FROM ".DB_PREFIX."unifiedpurse_standard  WHERE customer_id='".$this->customer->getId()."'";
			
			$query=$this->db->query($sql);
			if(empty($query->rows))$toecho.="<h3>No record found for transactions made through UNIFIEDPURSEStandard</h3>";
			else
			{
			$query=$this->db->query($sql);
			$num=count($query->rows);
			$perpage=10;
			$totalpages=ceil($num/$perpage);
			$p=$this->request->get['p'];
			if($p<1)$p=1;
			if($p>$totalpages)$p=$totalpages;
			$offset=($p-1) *$perpage;
			$sql.=" ORDER BY id DESC LIMIT $offset,$perpage ";
			
				$toecho.="			
						<table style='width:100%;' border=1>
							<tr style='width:100%;text-align:center;'>
								<th>
									S/N
								</th>
								<th>
									EMAIL
								</th>
								<th>
									TRANSACTION
									REFERENCE
								</th>
								<th>
									TRANSACTION DATE
								</th>
								<th>
									TRANSACTION<br/>
									AMOUNT (=N=)
								</th>
								<th>
									APPROVED<br/>
									AMOUNT (=N=)
								</th>
								<th>
									TRANSACTION<br/>
									RESPONSE
								</th>
								<th>
									ACTION
								</th>
							</tr>";
				$sn=0;
				foreach($query->rows as $row)
				{
					$sn++;
					
					if(empty($row['response_code']))
					{
						$transaction_response='(pending)';
						$trans_action=$this->url->link('information/unifiedpurse_standard',"p=$p&order_id={$row['order_id']}");
						
						$trans_action="<a href='$trans_action' style='color:#ffffff;background-color:#38B0E3;padding:4px;border-radius:3px;margin:2px;text-decoration:none;display:inline-block;'>REQUERY</a>";
					}
					else
					{
						$transaction_response=$row['response_description'];
						$trans_action='NONE';						
					}
					
					$toecho.="<tr align='center'>
								<td>
									$sn
								</td>
								<td>
									{$row['customer_email']}
								</td>
								<td>
									{$row['transaction_id']}
								</td>
								<td>
									{$row['date_time']}
								</td>
								<td>
									{$row['transaction_amount']} {$row['currency']}
								</td>
								<td>
									{$row['approved_amount']} {$row['currency']}
								</td>
								<td>
									$transaction_response
								</td>
								<td>
									$trans_action
								</td>								
							 </tr>";
				}
				$toecho.="</table>";
				
				
				
		$pagination="";
		
			$prev=$p-1;
			$next=$p+1;
			
			if($prev>=1)$pagination.=" [<a href='".$this->url->link('information/unifiedpurse_standard',"p=$prev")."'>previous</a>] ";
			
			if($next<=$totalpages)$pagination.=" [<a href='".$this->url->link('information/unifiedpurse_standard',"p=$next")."'>next</a>] ";
		
		
		
		if($totalpages>2)
		{
			if($prev>1)$pagination.=" [<a href='".$this->url->link('information/unifiedpurse_standard',"p=1")."'>first</a>] ";
			if($next<$totalpages)$pagination.=" [<a href='".$this->url->link('information/unifiedpurse_standard',"p=$totalpages")."'>last</a>] ";
		}	
		
		$toecho.="<div>$pagination</div>";
				
			}
		}

		$this->data['toecho']	= $toecho;

		// We call this Fallback system
      if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/information/unifiedpurse_standard.tpl'))$this->template = $this->config->get('config_template') . '/template/information/unifiedpurse_standard.tpl';
      else $this->template = 'default/template/information/unifiedpurse_standard.tpl';
      
      $this->children = array( // Required. The children files for the page.
         'common/column_left', // Column left which will allow you to place modules at the left of your page.
         'common/column_right',
         'common/content_top',
         'common/content_bottom',
         'common/footer', // your footer of your website
         'common/header'
      );
		
      $this->response->setOutput($this->render());
     }
}
?>