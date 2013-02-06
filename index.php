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

error_reporting(E_ERROR);
set_time_limit(0);
ini_set('memory_limit', '512M');

$default_filename = "data.json";

$result_array = array(); //clear results holder
$global_result_array = array();
$proxy_url = "";
$url_list_content = "";
$source_url = "";

//simple stat processing
if (isset($_POST["action"])) {

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

	//process our usual stuff
	if ($_POST["action"] == "process") {

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
			$page_result_array = array_unique(str_word_count($page_content_clean, 1));

			$page_word_unique_count = count($page_result_array);

			$global_result_array = array_unique(array_merge($global_result_array, $page_result_array));

			$result_array[$url_target] = $page_word_unique_count;
		}
	} else if ($_POST["action"] == "populate") {
		if (isset($_POST["source_url"]) && !empty($_POST["source_url"])) {
			$source_url = $_POST["source_url"];

			//try to populate our listing from URL provided
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $source_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

			//only include if we have proxy to load
			if ($proxy_url) {
				curl_setopt($ch, CURLOPT_PROXY, $proxy_url);
			}

			$page = curl_exec($ch);
			curl_close($ch);

			$dom_doc = new DOMDocument();
			$dom_doc->loadHTML($page);
			$nodes = $dom_doc->getElementsByTagName("a");

			$url_list_content = ""; //reset
			foreach($nodes as $node) {
				$href = $node->getAttribute("href");

				//skip uneeded url
				if (in_array($href, array(
					"#",
					"/",
					""
				))) {
					continue; //ignore characters in the ignore list
				}

				if (!substr_count($href, $source_url)) {

					//simple check to skip outside address
					if (substr_count($href, "www.") || substr_count($href, "http://") || substr_count($href, "https://")) {
						continue;
					}

					//prefix the address in the url if it doesn't exist
					$href = $source_url . "/" . $href;
				}
				$url_list_content .= $href . "\n";
			}
		}

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
	if (!file_exists($default_filename)) {
		return false;
	}
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
				<label class="control-label">Populate from web page</label>
				<div class="controls">
					<input type="text" name="source_url" value="<?=$source_url;?>" />
					<a class="btn btn-info" id="populate_button">Populate!</a>
				</div>
			</div>
			<div class="control-group">
			    <label class="control-label">URL list</label>
			    <div class="controls">
				<textarea  class="field span12" name="url_list" rows="35"><?=$url_list_content;?></textarea>
			    </div>
			</div>
			<div class="control-group">
			    <label class="control-label"></label>
			    <div class="controls">
				<a class="btn btn-primary" id="count_button">Count!</a>
			    </div>
			</div>
			<input type="hidden" value="process" name="action" id="action_value" />
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
				<tr>
					<td><strong>Global Result</strong></td>
					<td><strong><em><?=count($global_result_array)?> Words of <?=count($result_array)?> page(s)</em></strong></td>
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

			$('#populate_button').click(function() {
				$('#action_value').val("populate");
				$('#counter_data').submit();
			});
	    });
	</script>
</body>
</html>