<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Crawler_extract_details extends CI_Controller {

	public $country = '';
	public $rs_db = '';
	public $nest_db = '';

	public $products_table 	= '';
	public $website_id 		= '';
	public $table_suffix	= '';

	public $base_url		= '';

	public $table = '';

	public $array_set = array();

	public $update_test = 0;

	public $subcategory_start = 0;

	public $limit = '';

	public $order = '';

	public $repeat = 0;

	//http://app.competitor.com/index.php/crawler_extract_details?country=id&table_suffix=_mataharimall_bac_final&id=173
	public function __construct()
	{
		parent::__construct();
		//header("Content-Type:text/plain"); 
		echo 'MY PID : ' .getmypid() . '<br><br>';
		ini_set('max_execution_time', 0);
		set_time_limit(0);
		ini_set('memory_limit','-1');

		$this->load->library('lib_crawler_scraper/crawler/special_case/website_compiler');
		$this->load->model('crawler_extract_details_nest_model', 'Crawler_extract_details');
		$this->load->library('lib_crawler_scraper/core/price_format'); 
		  
	}
	
	public function index()
	{	
		$this->country 			= isset($_GET['country']) ? $_GET['country'] : '';
		$this->website_id 		= isset($_GET['id']) ? $_GET['id'] : '0'; // Required
		$this->table_suffix 	= isset($_GET['table_suffix']) ? $_GET['table_suffix'] : '_'.time();
		$this->products_table 	= "products_".$this->website_id.$this->table_suffix;
		$this->update_test		= isset($_GET['update_test']) ? $_GET['update_test'] : 0;
		$this->subcategory_start = isset($_GET['start']) ? $_GET['start'] : '0';
		$this->limit  			= isset($_GET['limit']) ? $_GET['limit'] : '';
		$this->order  			= isset($_GET['order']) ? $_GET['order'] : 'ASC';
		$this->repeat  			= isset($_GET['repeat']) ? $_GET['repeat'] : 0;

		$this->set_db($this->country);

		if($this->repeat){
			$this->repeat_run_crawler_extraction();
		}else{
			$this->run_crawler_extraction();
		}	
	}

	public function repeat_run_crawler_extraction(){
		$run_stop = 0;
        while($run_stop <= $this->repeat){
            $this->run_crawler_extraction();
            $run_stop++;
        }
	}

	public function run_crawler_extraction(){
		$get_competitor_details = $this->get_competitor_details($this->nest_db, $this->products_table);
		$this->extract_all_details($get_competitor_details);
	}

	public function set_db($country)
    {
        $this->rs_db = 'pcrawler_'.$country;
        $this->nest_db = 'pcrawler_'.$country;
    }

    public function get_competitor_details($db, $products_table){

    	if($this->order != ''){
    		$this->order = 'ORDER BY id_product ' . $this->order;
    	}

    	if($this->limit != ''){
    		$this->limit = 'LIMIT ' . $this->limit;
    	}

		$get_competitor_details = $this->Crawler_extract_details->get_competitor_details($db, $products_table, $this->subcategory_start, $this->order, $this->limit)->result();
		return $get_competitor_details;
	}	

	public function extract_all_details($get_competitor_details){
		$product_query_count = 0;
		//header('content-type: text/plain');

		foreach ($get_competitor_details as $competitor_detail) {
			//website url
			echo $competitor_detail->url;
			$this->base_url = parse_url($competitor_detail->url, PHP_URL_HOST);	
			
			#special
			$data_array = $this->website_compiler->get_extract_details($this->country, $this->website_id, $competitor_detail->details);

			if($data_array){
				$this->build_array_set($data_array, $competitor_detail->id_product);

				$product_query_count ++;
				if ($product_query_count == 20){
					$query_set = $this->build_query_set($this->array_set);
					$this->update_competitor_info($this->nest_db, $this->products_table, $query_set);
					$this->array_set = array();
					$product_query_count = 0;
				}
			}
		}

		$query_set = $this->build_query_set($this->array_set);
		$this->update_competitor_info($this->nest_db, $this->products_table, $query_set);
		$this->array_set = array();
		$product_query_count = 0;
	}

	//get_data_array

	public function generate_url($link){
		if(substr($link, 0, 1) != "/"){
                $url = $this->base_url."/".$link;
        }else{
                $url = $this->base_url.$link; 
        }

        $link = "https://".$url;

        return $link;
	}

	public function build_array_set($data_array, $id_product){
		foreach ($data_array as $column => $data) {
			$getkey = null;
			$column_exists = 0;
			if($this->array_set){
				foreach ($this->array_set as $key => $check_column) {
					if(key($check_column) == $column){
						$getkey = $key;
						$column_exists = 1;
					}

				}
			}

			if($column_exists == 0){
				array_push($this->array_set, array($column => array(array($data => $id_product))));
			}else{
				$column_set = array($data => $id_product);
				array_push($this->array_set[$getkey][$column], $column_set);
			}
		}
	}

	public function build_query_set($array_set){
		$query_set = '';
		$query_set_where = '';
		foreach ($array_set as $key_column => $column) {
			$query_set .= key($column) . ' = ';
			$query_set .= ' CASE ';
			foreach ($array_set[$key_column][key($column)] as $key => $competitor_data) {
				#$query_set .= ' WHEN id_product = "'. $competitor_data[key($competitor_data)] .'" THEN "'. str_replace('"', '""', key($competitor_data)) .'"';
				$query_set .= ' WHEN id_product = "'. $competitor_data[key($competitor_data)] .'" THEN "'. mysql_real_escape_string(key($competitor_data)) .'"';

				if ($key_column == 0) {
					$query_set_where .= $competitor_data[key($competitor_data)] . ', ';	
				}
			}
			$query_set .= ' END, ';
		}

		$query_set = rtrim($query_set, ', ');
		$query_set_where = rtrim($query_set_where, ', ');
		$query_set_where = ' WHERE id_product IN ('. $query_set_where .')';
		$query_set = $query_set . $query_set_where;
		return $query_set;
	}
	
	public function update_competitor_info($db, $products_table, $query_set){
		$this->Crawler_extract_details->update_competitor_info($db, $products_table, $query_set, $this->update_test);
	}

	public function sample(){
		$string = ' 1,144.00 บาท';
		$final_formatted_price = $this->price_format->format_price_th($string ,12);	
		echo $final_formatted_price;
	}

	public function end_start($contents, $start, $end){
		$first_step = explode( $start , $contents );
		if (array_key_exists(1, $first_step)) {
		    $second_step = explode($end , $first_step[1] );
			//print_r($second_step);
			return $second_step[0];
		}else{
		    return null;
		}
	}
		
}

/*
<th.[^>]*>Seller</th>\s*<td>(.[^<]*)</td>|<th.[^>]*>Seller</th>\s*<td>(.[^<]*)</td>|<th.[^>]*>Return/Exchange Address</th>\s*<td>(.[^<]*)</td>|<th.[^>]*>Notice on Return/Exchange</th>\s*<td>(.[^<]*)</td>

<th.[^>]*>Seller</th>\s*<td>(.[^<]*)</td>
<th.[^>]*>Ship-From Address</th>\s*<td>(.[^<]*)</td>
<th.[^>]*>Return/Exchange Address</th>\s*<td>(.[^<]*)</td>
<th.[^>]*>Notice on Return/Exchange</th>\s*<td>(.[^<]*)</td>
*/