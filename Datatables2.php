<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
  /**
  * Ignited Datatables Query
  *
  * This is a wrapper class/library based on the native Datatables server-side implementation by Allan Jardine
  * found at http://datatables.net/examples/data_sources/server_side.html for CodeIgniter
  * Modified by nounours from Vincent Bambico <metal.conspiracy@gmail.com> and Yusuf Ozdemir <yusuf@ozdemir.be>
  *
  * @package    CodeIgniter
  * @subpackage libraries
  * @category   library
  * @version    2.0 <beta>
  * @author     Vincent Bambico <metal.conspiracy@gmail.com>
  *             Yusuf Ozdemir <yusuf@ozdemir.be>
  *             Nounours
  * @link       
  */
  class Datatables2
  {
    /**
    * Global container variables for chained argument results
    *
    */
    private $ci;
    private $query_send;
    private $query_org;
    private $query_filter;
    private $columns        = array();
    
    private $filter         = array();
    private $add_columns    = array();
    private $edit_columns   = array();
    private $unset_columns  = array();


    private $query          = array();

    /**
    * Copies an instance of CI
    */
    public function __construct()
      {
        $this->ci =& get_instance();
      }

    /**
    * If you establish multiple databases in config/database.php this will allow you to
    * set the database (other than $active_group)
    */
    public function set_database($db_name)
      {
        $db_data = $this->ci->load->database($db_name, TRUE);
        $this->ci->db = $db_data;
      }

   
    
    
    public function filter($key_condition, $val = NULL, $backtick_protect = TRUE)
      {
        $this->filter[] = array($key_condition, $val, $backtick_protect);
        return $this;
      }

   
    /**
    * Sets additional column variables for adding custom columns
    *
    * @param string $column
    * @param string $content
    * @param string $match_replacement
    * @return mixed
    */
    public function add_column($column, $content, $match_replacement = NULL)
      {
        $this->add_columns[$column] = array('content' => $content, 'replacement' => $this->explode(',', $match_replacement));
        return $this;
      }

    /**
    * Sets additional column variables for editing columns
    *
    * @param string $column
    * @param string $content
    * @param string $match_replacement
    * @return mixed
    */
    public function edit_column($column, $content, $match_replacement)
    {
      $this->edit_columns[$column][] = array('content' => $content, 'replacement' => $this->explode(',', $match_replacement));
      return $this;
    }

    /**
    * Unset column
    *
    * @param string $column
    * @return mixed
    */
    public function unset_column($column)
    {
      $column=explode(',',$column);
      $this->unset_columns=array_merge($this->unset_columns,$column);
      return $this;
    }


    /*********/
    /* QUERY */
    /*********/

    public function query($query)
      {
        $this->query_send     = $query;
        $this->query_org      = $query;
        $this->query_filter   = $query;

        $query = $this->ci->db->query($query);

        foreach ($query->list_fields() as $field)
          {
            $column = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$2', $field));
            $column = preg_replace('/.*\.(.*)/i', '$1', $column); // get name after `.`
            $this->columns[] = $column;
          }

        return $this;

      }
    /**
    * Builds all the necessary query segments and performs the main query based on results set from chained statements
    *
    * @param string $output
    * @param string $charset
    * @return string
    */
    public function generate($output = 'json', $charset = 'UTF-8')
    {
      $this->get_ordering();
      $this->get_filtering();
      if(strtolower($output) == 'json')
        {
          $this->get_paging();
        }
        
        
      
      //echo $this->query_send;
      return $this->produce_output(strtolower($output), strtolower($charset));
    }

    /**
    * Generates the LIMIT portion of the query
    *
    * @return mixed
    */
    private function get_paging()
    {
      $iStart   = $this->ci->input->post('start');
      $iLength  = $this->ci->input->post('length');

      if($iLength != '' && $iLength != '-1')
        {
          $query_modified = $this->query_send;
          $query_modified .= ' limit '.(($iStart)? $iStart : 0).','.$iLength;
          $this->query_send = $query_modified;
        }
        
    }

    /**
    * Generates the ORDER BY portion of the query
    *
    * @return mixed
    */
    private function get_ordering()
      {

        $Data   = $this->ci->input->post('columns');
        $ajout  = '';

        if ($this->ci->input->post('order'))
          {
            foreach ($this->ci->input->post('order') as $key)
            {
              
              if($this->check_cType())
                {

                  $ajout  .= $Data[$key['column']]['data'].' '.$key['dir'].',';
                }
              else
                {
                  $ajout  .= $this->columns[$key['column']].' '.$key['dir'].',';
                }  
            }

            $query_modified = $this->query_send;
            $query_modified .= ' order by '.substr($ajout,0,-1);
            $this->query_send = $query_modified;

          }
      }

    /**
    * Generates a %LIKE% portion of the query
    *
    * @return mixed
    */
    private function get_filtering()
    {

      $mColArray = $this->ci->input->post('columns');

      $sWhere   = '';
      $search   = $this->ci->input->post('search');
      $sSearch  = $this->ci->db->escape_like_str(trim($search['value']));
      $columns  = array_values(array_diff($this->columns, $this->unset_columns));

      if($sSearch != '')
        {

          $sSearch_explode = explode(" ",$sSearch);
          foreach ($sSearch_explode as $sSearch)
            {
              $sWhere .= '(';
              for($i = 0; $i < count($mColArray); $i++)
                {
                  if ($mColArray[$i]['searchable'] == 'true' && !array_key_exists($mColArray[$i]['data'], $this->add_columns))
                    {
                      if($this->check_cType())
                        {
                          $sWhere .= $mColArray[$i]['data']. " LIKE '%" . $sSearch . "%' OR ";
                        }               
                      elseif (array_key_exists($i,$columns))
                        {
                          $sWhere .=$this->columns[$i] . " LIKE '%" . $sSearch . "%' OR ";
                        }
                      
                    }
                
                }
              $sWhere = substr_replace($sWhere, '', -3);
              $sWhere .= ') AND ';
             
            }
         
          
        }  

      $sWhere = substr_replace($sWhere, '', -4);

      if($sWhere != '')
        {

          $select_colum = implode(',',$this->columns);
          //$select_colum = substr($select_colum,0,-1);
          $query = $this->query_send;
          $new_query = 'with searchable as ('.$query.') select '.$select_colum.' from searchable where '.$sWhere;
          $this->query_send   = $new_query;
          $this->query_filter = $new_query;

        }
      else
        {
          $this->query_filter = $this->query_org;
        }  
        //$this->ci->db->where('(' . $sWhere . ')');

      // TODO : sRangeSeparator
     

      //foreach($this->filter as $val)
        //$this->ci->db->where($val[0], $val[1], $val[2]);
    }

    /**
    * Compiles the select statement based on the other functions called and runs the query
    *
    * @return mixed
    */
    private function get_display_result()
    {
      
      return $this->ci->db->query($this->query_send);
    }

    /**
    * Builds an encoded string data. Returns JSON by default, and an array of aaData if output is set to raw.
    *
    * @param string $output
    * @param string $charset
    * @return mixed
    */
    private function produce_output($output, $charset)
    {
      $aaData = array();
      $rResult = $this->get_display_result();

      if($output == 'json')
      {
        $iTotal = $this->get_total_results();
        $iFilteredTotal = $this->get_total_results(TRUE);
      }

      foreach($rResult->result_array() as $row_key => $row_val)
      {
        $aaData[$row_key] =  ($this->check_cType())? $row_val : array_values($row_val);

        foreach($this->add_columns as $field => $val)
         if($this->check_cType())
            $aaData[$row_key][$field] = $this->exec_replace($val, $aaData[$row_key]);
          else
            $aaData[$row_key][] = $this->exec_replace($val, $aaData[$row_key]);


        foreach($this->edit_columns as $modkey => $modval)
          foreach($modval as $val)
            $aaData[$row_key][($this->check_cType())? $modkey : array_search($modkey, $this->columns)] = $this->exec_replace($val, $aaData[$row_key]);

        $aaData[$row_key] = array_diff_key($aaData[$row_key], ($this->check_cType())? $this->unset_columns : array_intersect($this->columns, $this->unset_columns));

        if(!$this->check_cType())
          $aaData[$row_key] = array_values($aaData[$row_key]);

      }

      if($output == 'json')
      {
        $sOutput = array
        (
          'draw'                => intval($this->ci->input->post('draw')),
          'recordsTotal'        => $iTotal,
          'recordsFiltered'     => $iFilteredTotal,
          'data'                => $aaData
        );

        if($charset == 'utf-8')
          return json_encode($sOutput);
        else
          return $this->jsonify($sOutput);
      }
      else
        return array('aaData' => $aaData);
    }

    /**
    * Get result count
    *
    * @return integer
    */
    private function get_total_results($filtering = FALSE)
    {
      if($filtering)
        $this->get_filtering();
/*
      foreach($this->joins as $val)
        $this->ci->db->join($val[0], $val[1], $val[2]);

      foreach($this->where as $val)
        $this->ci->db->where($val[0], $val[1], $val[2]);

      foreach($this->or_where as $val)
        $this->ci->db->or_where($val[0], $val[1], $val[2]);
        
      foreach($this->where_in as $val)
        $this->ci->db->where_in($val[0], $val[1]);

      foreach($this->group_by as $val)
        $this->ci->db->group_by($val);

      foreach($this->like as $val)
        $this->ci->db->like($val[0], $val[1], $val[2]);

      foreach($this->or_like as $val)
        $this->ci->db->or_like($val[0], $val[1], $val[2]);

      if(strlen($this->distinct) > 0)
      {
        $this->ci->db->distinct($this->distinct);
        $this->ci->db->select($this->columns);
      }
*/
      $query = $this->ci->db->query($this->query_filter);
      return $query->num_rows();
    }

    /**
    * Runs callback functions and makes replacements
    *
    * @param mixed $custom_val
    * @param mixed $row_data
    * @return string $custom_val['content']
    */
    private function exec_replace($custom_val, $row_data)
    {
      $replace_string = '';
      
      // Go through our array backwards, else $1 (foo) will replace $11, $12 etc with foo1, foo2 etc
      $custom_val['replacement'] = array_reverse($custom_val['replacement'], true);

      if(isset($custom_val['replacement']) && is_array($custom_val['replacement']))
      {
        //Added this line because when the replacement has over 10 elements replaced the variable "$1" first by the "$10"
        $custom_val['replacement'] = array_reverse($custom_val['replacement'], true);
        foreach($custom_val['replacement'] as $key => $val)
        {
          $sval = preg_replace("/(?<!\w)([\'\"])(.*)\\1(?!\w)/i", '$2', trim($val));

      if(preg_match('/(\w+::\w+|\w+)\((.*)\)/i', $val, $matches) && is_callable($matches[1]))
          {
            $func = $matches[1];
            $args = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[,]+/", $matches[2], 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            foreach($args as $args_key => $args_val)
            {
              $args_val = preg_replace("/(?<!\w)([\'\"])(.*)\\1(?!\w)/i", '$2', trim($args_val));
              $args[$args_key] = (in_array($args_val, $this->columns))? ($row_data[($this->check_cType())? $args_val : array_search($args_val, $this->columns)]) : $args_val;
            }

            $replace_string = call_user_func_array($func, $args);
          }
          elseif(in_array($sval, $this->columns))
            $replace_string = $row_data[($this->check_cType())? $sval : array_search($sval, $this->columns)];
          else
            $replace_string = $sval;

          $custom_val['content'] = str_ireplace('$' . ($key + 1), $replace_string, $custom_val['content']);
        }
      }

      return $custom_val['content'];
    }

    /**
    * Check column type -numeric or column name
    *
    * @return bool
    */
    private function check_cType()
    {
      $column = $this->ci->input->post('columns');
      if(is_numeric($column[0]['data']))
        return FALSE;
      else
        return TRUE;
    }


    /**
    * Return the difference of open and close characters
    *
    * @param string $str
    * @param string $open
    * @param string $close
    * @return string $retval
    */
    private function balanceChars($str, $open, $close)
    {
      $openCount = substr_count($str, $open);
      $closeCount = substr_count($str, $close);
      $retval = $openCount - $closeCount;
      return $retval;
    }

    /**
    * Explode, but ignore delimiter until closing characters are found
    *
    * @param string $delimiter
    * @param string $str
    * @param string $open
    * @param string $close
    * @return mixed $retval
    */
    private function explode($delimiter, $str, $open = '(', $close=')')
    {
      $retval = array();
      $hold = array();
      $balance = 0;
      $parts = explode($delimiter, $str);

      foreach($parts as $part)
      {
        $hold[] = $part;
        $balance += $this->balanceChars($part, $open, $close);

        if($balance < 1)
        {
          $retval[] = implode($delimiter, $hold);
          $hold = array();
          $balance = 0;
        }
      }

      if(count($hold) > 0)
        $retval[] = implode($delimiter, $hold);

      return $retval;
    }

    /**
    * Workaround for json_encode's UTF-8 encoding if a different charset needs to be used
    *
    * @param mixed $result
    * @return string
    */
    private function jsonify($result = FALSE)
    {
      if(is_null($result))
        return 'null';

      if($result === FALSE)
        return 'false';

      if($result === TRUE)
        return 'true';

      if(is_scalar($result))
      {
        if(is_float($result))
          return floatval(str_replace(',', '.', strval($result)));

        if(is_string($result))
        {
          static $jsonReplaces = array(array('\\', '/', '\n', '\t', '\r', '\b', '\f', '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
          return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $result) . '"';
        }
        else
          return $result;
      }

      $isList = TRUE;

      for($i = 0, reset($result); $i < count($result); $i++, next($result))
      {
        if(key($result) !== $i)
        {
          $isList = FALSE;
          break;
        }
      }

      $json = array();

      if($isList)
      {
        foreach($result as $value)
          $json[] = $this->jsonify($value);

        return '[' . join(',', $json) . ']';
      }
      else
      {
        foreach($result as $key => $value)
          $json[] = $this->jsonify($key) . ':' . $this->jsonify($value);

        return '{' . join(',', $json) . '}';
      }
    }
	
	 /**
     * returns the sql statement of the last query run
     * @return type
     */
    public function last_query()
    {
      return  $this->ci->db->last_query();
    }
  }
/* End of file Datatables.php */
/* Location: ./application/libraries/Datatables.php */
