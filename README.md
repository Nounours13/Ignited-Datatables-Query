<h1>Ignited Datatables QUERY</h1>

Datatables Library for codeigniter 3


Ignited Datatables Query is a wrapper class/library based on the native Datatables server-side implementation by Allan Jardine
found at http://datatables.net/examples/data_sources/server_side.html for CodeIgniter and Vincent Bambico <metal.conspiracy@gmail.com> and Yusuf Ozdemir <yusuf@ozdemir.be>

Fork   : https://github.com/IgnitedDatatables/Ignited-Datatables
Wiki   : https://github.com/IgnitedDatatables/Ignited-Datatables/wiki

Contact: Nounours

<h2>Requirements</h2>

jQuery 1.5+
DataTables 1.10+
CodeIgniter 3.0+

<h2>Install</h2>

To install the library, copy the libraries/datatables2.php file into your application/libraries folder.

<h2>License</h2>
	
DON'T BE A DICK PUBLIC LICENSE

It's a Fork from Ignited Datatables. It's the same initialization on Ignited Datatables.  <br>
The difference is that you can only use <br>
  $this->datatables2->query('your_query');  <br>
and  <br>
  $this->datatables2->generate();  <br>
to make the request in server side for datatables. <br>
 <br>
You can use unset_columns,edit_columns,add_columns like ignited datatables.
