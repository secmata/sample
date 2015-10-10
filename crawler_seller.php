<?php  if (! defined('BASEPATH')) exit('No direct script access allowed');

class Crawler_seller{

    public  $nest_db = '',
            $country = '',
            $website_id = '',
            $table_suffix = '',
            $sellers_table = '',
            $rerun = 0,
            $update_test = 0,
            $array_set = array(),
            $create_table = 0,
            $repeat = 0;

    public function __construct()
    {
        
		ini_set('max_execution_time', 0);
        set_time_limit(0);
        ini_set('memory_limit','-1');
        $this->ci =& get_instance();
        $this->ci->load->library('lib_crawler_scraper/crawler/special_case/website_compiler');
        $this->ci->load->model('Crawler_seller_nest_model', 'crawler_seller_nest');
    }

    public function set_value(){
        $this->country          = isset($_GET['country']) ? $_GET['country'] : '';
        $this->website_id       = isset($_GET['id']) ? $_GET['id'] : '0'; // Required
        $this->table_suffix     = isset($_GET['table_suffix']) ? $_GET['table_suffix'] : '_'.time();
        $this->sellers_table    = "sellers_".$this->website_id.$this->table_suffix;
        #$this->sellers_table    = "products_".$this->website_id.$this->table_suffix;
        $this->nest_db          = 'pcrawler_'.$this->country;
        $this->rerun            = isset($_GET['rerun']) ? $_GET['rerun'] : 0;
        $this->update_test      = isset($_GET['update_test']) ? $_GET['update_test'] : 0;
        $this->create_table     = isset($_GET['create_table']) ? $_GET['create_table'] : 0;
        $this->repeat           = isset($_GET['repeat']) ? $_GET['repeat'] : 0;

        #create_table_seller
        if($this->create_table){
            $this->create_table_seller($this->nest_db, $this->sellers_table);
        }
     
        if($this->repeat){
            $this->repeat_run_crawler_seller();
        }else{
            $this->run_crawler_seller();
        }    
    }

    public function repeat_run_crawler_seller(){
        $run_stop = 0;
        while($run_stop <= $this->repeat){
            $this->run_crawler_seller();
            $run_stop++;
        }
    }

    public function run_crawler_seller(){
        $all_seller = $this->get_all_seller();    
        $this->crawl_all_seller($all_seller);
    }

    public function create_table_seller($db, $sellers_table){
        $this->ci->crawler_seller_nest->create_table($db, $sellers_table);
        echo 'create table success!';
        die();
    }
    
    public function get_all_seller(){
        $seller_where_q = $this->ci->website_compiler->get_seller_where_q($this->country, $this->website_id);
        $all_seller = $this->ci->crawler_seller_nest->get_all_seller($this->nest_db, $this->sellers_table, $seller_where_q)->result();
        return $all_seller;
    }

    public function crawl_all_seller($all_seller){
        $seller_query_count = 0;
        foreach ($all_seller as $seller) {

            #add get_web_page_seller
           
            #add while
            $page_stop = 0;
            while($page_stop <= 10){
                $seller_info_array  = $this->ci->website_compiler->get_seller_info($this->country, $this->website_id, $seller->seller_url);   
                if($seller_info_array){
                    break;
                }else{
                    $page_stop++;
                }
            }

            if($seller_info_array){
                $this->build_array_set($seller_info_array, $seller->id_seller);

                $seller_query_count ++;
                if ($seller_query_count == 5){
                    $query_set = $this->build_query_set($this->array_set);
                    $this->update_seller_info($this->nest_db, $this->sellers_table, $query_set);
                    $this->array_set = array();
                    $seller_query_count = 0;
                }
            }
        }

        $query_set = $this->build_query_set($this->array_set);
        $this->update_seller_info($this->nest_db, $this->sellers_table, $query_set);
        $this->array_set = array();
        $seller_query_count = 0; 
    }

    public function build_array_set($seller_info_array, $id_seller){
        foreach ($seller_info_array as $column => $seller_info) {
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
                array_push($this->array_set, array($column => array(array($seller_info => $id_seller))));
            }else{
                $column_set = array($seller_info => $id_seller);
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
            foreach ($array_set[$key_column][key($column)] as $key => $seller_data) {
                $query_set .= ' WHEN id_seller = "'. $seller_data[key($seller_data)] .'" THEN "'. mysql_real_escape_string(key($seller_data)) .'"';

                if ($key_column == 0) {
                    $query_set_where .= $seller_data[key($seller_data)] . ', '; 
                }
            }
            $query_set .= ' END, ';
        }

        $query_set = rtrim($query_set, ', ');
        $query_set_where = rtrim($query_set_where, ', ');
        $query_set_where = ' WHERE id_seller IN ('. $query_set_where .')';
        $query_set = $query_set . $query_set_where;
        return $query_set;
    }

    public function update_seller_info($db, $sellers_table, $query_set){
        $this->ci->crawler_seller_nest->update_seller_info($db, $sellers_table, $query_set, $this->update_test);
    }


  
}/* End of file lib_curl.php */
/* Location: ./application/libraries/crawler_seller.php */