<?php
/**
 * Really-really quick and dirty implementation of simple web page parser and unique word counter
 * this is by no means optimized and should be used for casual usages only.
 *
 * No content sanitation yet too at the moment.
 *
 * @author: Arvy <arvy@rvbd.net>
 * @date: 6/02/13
 */

$default_filename = "data.json";

$result_array = array(); //clear results holder

//simple stat processing
if (isset($_POST["action"]) && $_POST["action"] == "yes") {

    //save our current settings for later use
    save_file(array(
	"proxy_url" => $_POST["proxy_url"],
	"url_list_content" => $_POST["url_list"]
    ), $default_filename);

    if (isset($_POST["url_list"]) && !empty($_POST["url_list"])) {
	$url_list_content = $_POST["url_list"];

    }

    $proxy_url = false;
    if (isset($_POST["proxy_url"]) && !empty($_POST["proxy_url"])) {
	$proxy_url = $_POST["proxy_url"];
    }

    $source_list = str_replace("\r\n", "\n", $_POST["url_list"]);
    $source_list_array = explode("\n", $source_list);



    //go through each and get the contents
    foreach ($source_list_array as $url_target) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url_target);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

	//only include if we have proxy to load
	if ($proxy_url) {
	    curl_setopt($ch, CURLOPT_PROXY, $proxy_url);
	}

	$page = curl_exec($ch);
	curl_close($ch);

	//strip out the javascripts as much as possible
	$page = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $page);
	$page_content = strip_tags($page);

	//now get the unique stuff
	$page_content_clean = strtolower($page_content);
	$page_word_unique_count = count(array_unique(str_word_count($page_content_clean, 1)));

	$result_array[$url_target] = $page_word_unique_count;
    }

} else {
    //load the file and data
    $data = load_file($default_filename);
    if ($data) {
	$proxy_url = $data["proxy_url"];
	$url_list_content = $data["url_list_content"];
    }
}




/**
 * Loads config file into memory
 */
function load_file($default_filename) {
    $fp = fopen($default_filename, "r");
    $contents = fread($fp, filesize($default_filename));
    fclose($fp);

    if (!$contents) {
	return false;
    }

    return json_decode($contents, true);
}

/**
 * Save config file into memory
 */
function save_file($data_array, $default_filename) {
    $json_data = json_encode($data_array);
    $fp = fopen($default_filename, 'w');
    fwrite($fp, $json_data);
    fclose($fp);
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>Bootstrap 101 Template</title>
	<!-- Bootstrap -->
	<link href="css/bootstrap.css" rel="stylesheet" media="screen">
	<link href="css/style.css" rel="stylesheet" media="screen">


</head>
<body>
	<h2>Simple word counter</h2>
	<div class="container-fluid">

	    <div class="row-fluid">
		<div class="span4">
		    <form id="counter_data" method="POST" class="form-horizontal">
			<div class="control-group">
			    <label class="control-label">Proxy (optional)</label>
			    <div class="controls">
				<input type="text" name="proxy_url" value="<?=$proxy_url;?>" />
			    </div>
			</div>
			<div class="control-group">
			    <label class="control-label">URL list</label>
			    <div class="controls">
				<textarea  class="field" name="url_list" rows="35"><?=$url_list_content;?></textarea>
			    </div>
			</div>
			<div class="control-group">
			    <label class="control-label"></label>
			    <div class="controls">
				<a class="btn btn-primary" id="count_button">Count!</a>
			    </div>
			</div>
			<input type="hidden" value="yes" name="action" />
		    </form>
		</div>
		<div class="span6">
		    <p>Results</p>
		    <?php
		    if (count($result_array)) {
			$total_count = 0;
		    ?>
		    <table class="table table-bordered table-hover">
			<tr>
			    <th>URL</th>
			    <th>Unique words (Approx.)</th>
			</tr>
			<?php
			foreach ($result_array as $url => $count) {
			    $total_count = $total_count + $count;
			?>
			<tr>
			    <td><?=$url?></td>
			    <td><?=$count?></td>
			</tr>
			<?php
			}
			?>
			<tr>
			    <td><strong>Total</strong></td>
			    <td><strong><em><?=$total_count;?></em></strong></td>
			</tr>
			<tr>
			    <td><strong>Average per page</strong></td>
			    <td><strong><em><?=round($total_count / count($result_array));?></em></strong></td>
			</tr>
		    </table>
		    <?php
		    }
		    ?>
		</div>
	    </div>
	</div>
	<script src="js/jquery-latest.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script type="text/javascript">
	    $(function(){
		$('#count_button').click(function() {
			$('#counter_data').submit();
		});
	    });
	</script>
</body>
</html>