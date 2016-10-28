<?php
require "bencode.php";

function getUserIP()
{
    $client  = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote  = $_SERVER['REMOTE_ADDR'];

    if(filter_var($client, FILTER_VALIDATE_IP))
    {
        $ip = $client;
    }
    elseif(filter_var($forward, FILTER_VALIDATE_IP))
    {
        $ip = $forward;
    }
    else
    {
        $ip = $remote;
    }

    return $ip;
}

$action = "";
$magnet = false;

if (isset($_POST["action"]) && isset($_POST["url"])){
	$action = $_POST["action"];
	$url = $_POST["url"];
	if (isset($_POST["magnet"])){
		$magnet = (bool)$_POST["magnet"];
	}
}

if ($action == "dl") {
	$regex = "((http:\/\/pobierz\.torrenty\.org\/templates\/pobierz\.php\?id=.+&u=.+&filename=)(.+)\")";
	$regex_alt = "((http:\/\/torrenty\.org\/templates\/pobierz\.php\?id=.+&u=.+&filename=)(.+)\")";

	$regex_title = "/<div class=\"tytul_gora\">(.+)<\/div>/";
	$regex_category = "/<div class=\"kategorie_gora\"><a .+>(.+)<\/a>\/ <a href=\".+\" target=\"_blank\">(.+)<\/a>/";

	if ((substr($url, 0, 28) == "http://torrenty.org/torrent/") || (substr($url, 0, 35) == "http://upload.torrenty.org/torrent/")){
		// tabela torrents
		// TID, 		IP, 		DATE, 				CATEGORY, 			SUBCATEGORY, 		TITLE, 			URL, 			ISMAGNET
		// ^ ID wpisu   ^IP usera   ^data wygenerowania ^kategoria główna   ^kategoria poboczna ^tytuł torrenta ^url torrenta   ^magnet czy .torrent
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);

		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/cookies');

		$return = curl_exec($ch);
		
		preg_match ( $regex, $return, $tmp_link ); // matchowanie adresu do pobierz.php
		if (!$tmp_link)
			preg_match ( $regex_alt, $return, $tmp_link );
		preg_match ( $regex_title, $return, $title ); // matchowanie tytułu torrenta ($title[0]) $title[1] - czysta nazwa torrenta
		preg_match ( $regex_category, $return, $category ); // matchowanie kategorii ($category[1] - category, $category[2] - subcategory)

		if ($tmp_link){
			$ip = getUserIP();
			$date = date( 'c' ); // Data w standardzie ISO 8601 
			$title[1] = str_replace("'", "\"", $title[1]);
			$magnet2 = ((int)((bool)$magnet));
			$query = "INSERT INTO torrents (IP, DATE, CATEGORY, SUBCATEGORY, TITLE, URL, ISMAGNET) VALUES ('$ip', '$date', '$category[1]', '$category[2]', '$title[1]', '$url', $magnet2);\r\n";
			
			file_put_contents( "../log_torrent.log", $query, FILE_APPEND );
			
			$dl_link = $tmp_link[1] . rawurlencode($tmp_link[2]);
			
			$filename_clean = $tmp_link[2];
			$filename = $tmp_link[2] . ".torrent";

			curl_setopt($ch, CURLOPT_URL, $dl_link);

			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_COOKIEFILE, '../cookies');

			$return = curl_exec($ch);

			if ($magnet) {
				$bc = new Bencode;
				$result = $bc->decode($return);
				if (isset($result["announce"])){
					$trackers = array($result["announce"]);
				}else{
					die();
				}
				if (isset($result["announce-list"])) {
					foreach ($result["announce-list"] as $tracker){
						if (is_string($tracker[0]))
							array_push($trackers, $tracker[0]);
					}
				}

				$data = $bc->encode($result["info"]);
				$hash = hash("sha1", $data);

				$final = "magnet:?xt=urn:btih:" . $hash . "&dn=" . rawurlencode($filename_clean);
				foreach ($trackers as $tracker) {
					$final = $final . "&tr=" . $tracker;
				}

				header("Location: " . $final);
				die();

			}else{
				header('Content-Description: File Transfer');
				header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
				header('Pragma: public');
				header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // data w przeszłości
				header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
				// wymuszanie pobierania
				header('Content-Type: application/x-bittorrent');
				// przekazywanie nazwy pliku
				header('Content-Disposition: attachment; filename="'.$filename.'";');
				header('Content-Transfer-Encoding: binary');
				
				echo $return;
				
				die();
			}
		}
		curl_close($ch);
	}
}
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Torrenty - dzakub.com</title>
	
	<meta property="og:title" content="Torrenty od Dzakuba" />
	<meta property="og:type" content="website" />
	<meta property="og:url" content="http://dzakub.com/torrent/" />
	<meta property="og:image" content="http://dzakub.com/torrent/logo.png" />
		
	<link href="bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="css/flat-ui.css" rel="stylesheet">
    <script src="js/jquery-1.8.3.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/flatui-checkbox.js"></script>
</head>

<body style="background-color: #fff">

    <div class="col-md-offset-4 col-md-4" >
        <h3 class="text-center" > Dzięki! </h3> 

        <p style="text-align: justify;">To by było na tyle. torrenty.org ostatecznie przestały działać, czas więc również zakończyć ten projekt. Dzięki, że korzystaliście z tego skryptu, cieszę się, że mogłem udostępnić usługę, którą początkowo stworzyłem tylko dla siebie.</p>
        <p style="text-align: justify;">Poniżej kilka statystyk:</p>
        <dl>
            <dt>Wszystkich zapytań:</dt>
            <dd>638</dd>
            <dt>Najpopularniejsza kategoria:</dt>
            <dd>Filmy XviD / DivX</dd>
            <dt>Wybór magnet vs .torrent:</dt>
            <dd>.torrent</dd>
            <dt>Top 3 miast wg Google:</dt>
            <dd>
                <ol>
                    <li>Rzeszów</li>
                    <li>Poznań</li>
                    <li>Kraków</li>
                </ol>
            </dd>
            </dl>
    </div>

	<div style="width: 80%;" class="row center-block" style="margin-top: 72px;">
    	<div class="col-md-offset-4 col-md-4" >
            <h4 class="text-center" style="color: #aaa;"> Daj torrenta &nbsp; <span class="glyphicon glyphicon-save"></span> </h4> 
    		<div id="forma">
    			<form action="index.php" method="post">
    				<input data-toggle="tooltip" data-placement="left" title="Chodzi o adres do strony z torrentem, np. http://torrenty.org/ torrent/796106" type="text" placeholder="Adres torrenta" class="form-control" name="url" disabled>
    				<input type="hidden" name="action" value="dl">
    				<div class="center-block" style="margin-top: 15px;">
    				<p style="float: right; vertical-align: middle; margin-top:20px;" class="checkbox" for="checkbox1">
    				  <input type="checkbox" name="magnet" value="1" id="checkbox1" data-toggle="checkbox" disabled>
    				  Magnet <span class='glyphicon glyphicon-magnet '></span>
    				</p>
    					<button class="btn btn-hg btn-primary" style="background-color: #aaa;" disabled>
    					 &nbsp; Jedziem! &nbsp;
    					</button>
    				<div style="height:64px;" ></div>
    				<small style="color: #aaa;"> &copy; 2014-2016 by Dzakub </small>
    				</div>	
    			</form>
    		</div>
    	</div>
	</div>
</body>
</html>
