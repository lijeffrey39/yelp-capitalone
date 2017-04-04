<?php
$list = [];
$actuallist = [];
$reviews = [];

if (!empty($_GET['terms'])) {
/**
 * Yelp Fusion API code sample.
 *
 * This program demonstrates the capability of the Yelp Fusion API
 * by using the Business Search API to query for businesses by a 
 * search term and location, and the Business API to query additional 
 * information about the top result from the search query.
 * 
 * Please refer to http://www.yelp.com/developers/v3/documentation 
 * for the API documentation.
 * 
 * Sample usage of the program:
 * `php sample.php --term="dinner" --location="San Francisco, CA"`
 */
// OAuth credential placeholders that must be filled in by users.
// You can find them on
// https://www.yelp.com/developers/v3/manage_app
$CLIENT_ID = '6qUOS8lZXym26bDHm5alEw';
$CLIENT_SECRET = 'YAgaPeUo43B9EpzYDOrZesex6vEpZZlqf5lhRK1k8eXEJsIUae3kiWe4hBjDPuhd';
// Complain if credentials haven't been filled out.
//assert($CLIENT_ID, "Please supply your client_id.");
//assert($CLIENT_SECRET, "Please supply your client_secret.");
// API constants, you shouldn't have to change these.   
$API_HOST = "https://api.yelp.com";
$SEARCH_PATH = "/v3/businesses/search";
$BUSINESS_PATH = "/v3/businesses/";  // Business ID will come after slash.
$TOKEN_PATH = "/oauth2/token";
$GRANT_TYPE = "client_credentials";
// Defaults for our simple example.
$DEFAULT_TERM = "dinner";
$DEFAULT_LOCATION = "San Francisco, CA";
$SEARCH_LIMIT = 5;

$ip = $_SERVER['REMOTE_ADDR'];
$geo = unserialize(file_get_contents("http://www.geoplugin.net/php.gp?ip=128.237.161.163"));
$country = $geo["geoplugin_countryName"];
$city = $geo["geoplugin_city"];

$latitude = $geo["geoplugin_latitude"];
$longitude = $geo["geoplugin_longitude"];

/* 
* Given longitude and latitude in North America, return the address using The Google Geocoding API V3
*
*/

/* 
* Check if the json data from Google Geo is valid 
*/

function check_status($jsondata) {
    if ($jsondata["status"] == "OK") return true;
    return false;
}

/*
* Given Google Geocode json, return the value in the specified element of the array
*/

function google_getCountry($jsondata) {
    return Find_Long_Name_Given_Type("country", $jsondata["results"][0]["address_components"]);
}
function google_getProvince($jsondata) {
    return Find_Long_Name_Given_Type("administrative_area_level_1", $jsondata["results"][0]["address_components"], true);
}
function google_getCity($jsondata) {
    return Find_Long_Name_Given_Type("locality", $jsondata["results"][0]["address_components"]);
}
function google_getStreet($jsondata) {
    return Find_Long_Name_Given_Type("street_number", $jsondata["results"][0]["address_components"]) . ' ' . Find_Long_Name_Given_Type("route", $jsondata["results"][0]["address_components"]);
}
function google_getPostalCode($jsondata) {
    return Find_Long_Name_Given_Type("postal_code", $jsondata["results"][0]["address_components"]);
}
function google_getCountryCode($jsondata) {
    return Find_Long_Name_Given_Type("country", $jsondata["results"][0]["address_components"], true);
}
function google_getAddress($jsondata) {
    return $jsondata["results"][0]["formatted_address"];
}

/*
* Searching in Google Geo json, return the long name given the type. 
* (If short_name is true, return short name)
*/

function Find_Long_Name_Given_Type($type, $array, $short_name = false) {
    foreach( $array as $value) {
        if (in_array($type, $value["types"])) {
            if ($short_name)    
                return $value["short_name"];
            return $value["long_name"];
        }
    }
}

function Get_Address_From_Google_Maps($lat, $lon) {

    $url = "http://maps.googleapis.com/maps/api/geocode/json?latlng=$lat,$lon&sensor=false";

    // Make the HTTP request
    $data = @file_get_contents($url);
    // Parse the json response
    $jsondata = json_decode($data,true);

    // If the json data is invalid, return empty array
    if (!check_status($jsondata))   return array();

    $address = array(
        'country' => google_getCountry($jsondata),
        'province' => google_getProvince($jsondata),
        'city' => google_getCity($jsondata),
        'street' => google_getStreet($jsondata),
        'postal_code' => google_getPostalCode($jsondata),
        'country_code' => google_getCountryCode($jsondata),
        'formatted_address' => google_getAddress($jsondata),
        );

    return $address['formatted_address'];
}

$city = Get_Address_From_Google_Maps($latitude, $longitude);


/**
 * Given a bearer token, send a GET request to the API.
 * 
 * @return   OAuth bearer token, obtained using client_id and client_secret.
 */
function obtain_bearer_token() {
    try {
        # Using the built-in cURL library for easiest installation.
        # Extension library HttpRequest would also work here.
        $curl = curl_init();
        if (FALSE === $curl)
            throw new Exception('Failed to initialize');
        $postfields = "client_id=" . $GLOBALS['CLIENT_ID'] .
        "&client_secret=" . $GLOBALS['CLIENT_SECRET'] .
        "&grant_type=" . $GLOBALS['GRANT_TYPE'];
        curl_setopt_array($curl, array(
            CURLOPT_URL => $GLOBALS['API_HOST'] . $GLOBALS['TOKEN_PATH'],
            CURLOPT_RETURNTRANSFER => true,  // Capture response.
            CURLOPT_ENCODING => "",  // Accept gzip/deflate/whatever.
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded",
                ),
            ));
        $response = curl_exec($curl);
        if (FALSE === $response)
            throw new Exception(curl_error($curl), curl_errno($curl));
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (200 != $http_status)
            throw new Exception($response, $http_status);
        curl_close($curl);
    } catch(Exception $e) {
        trigger_error(sprintf(
            'Curl failed with error #%d: %s',
            $e->getCode(), $e->getMessage()),
        E_USER_ERROR);
    }
    $body = json_decode($response);
    $bearer_token = $body->access_token;
    return $bearer_token;
}
/** 
 * Makes a request to the Yelp API and returns the response
 * 
 * @param    $bearer_token   API bearer token from obtain_bearer_token
 * @param    $host    The domain host of the API 
 * @param    $path    The path of the API after the domain.
 * @param    $url_params    Array of query-string parameters.
 * @return   The JSON response from the request      
 */
function request($bearer_token, $host, $path, $url_params = array()) {
    // Send Yelp API Call
    try {
        $curl = curl_init();
        if (FALSE === $curl)
            throw new Exception('Failed to initialize');
        $url = $host . $path . "?" . http_build_query($url_params);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,  // Capture response.
            CURLOPT_ENCODING => "",  // Accept gzip/deflate/whatever.
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer " . $bearer_token,
                "cache-control: no-cache",
                ),
            ));
        $response = curl_exec($curl);
        if (FALSE === $response)
            throw new Exception(curl_error($curl), curl_errno($curl));
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (200 != $http_status)
            throw new Exception($response, $http_status);
        curl_close($curl);
    } catch(Exception $e) {
        trigger_error(sprintf(
            'Curl failed with error #%d: %s',
            $e->getCode(), $e->getMessage()),
        E_USER_ERROR);
    }
    return $response;
}
/**
 * Query the Search API by a search term and location 
 * 
 * @param    $bearer_token   API bearer token from obtain_bearer_token
 * @param    $term        The search term passed to the API 
 * @param    $location    The search location passed to the API 
 * @return   The JSON response from the request 
 */
function search($bearer_token, $term, $location) {
    $url_params = array();
    
    $url_params['term'] = $term;
    $url_params['location'] = $location;
    $url_params['limit'] = $GLOBALS['SEARCH_LIMIT'];
    
    return request($bearer_token, $GLOBALS['API_HOST'], $GLOBALS['SEARCH_PATH'], $url_params);
}
/**
 * Query the Business API by business_id
 * 
 * @param    $bearer_token   API bearer token from obtain_bearer_token
 * @param    $business_id    The ID of the business to query
 * @return   The JSON response from the request 
 */
function get_business($bearer_token, $business_id) {
    $business_path = $GLOBALS['BUSINESS_PATH'] . urlencode($business_id) . "/reviews";
    
    return request($bearer_token, $GLOBALS['API_HOST'], $business_path);
}
/**
 * Queries the API by the input values from the user 
 * 
 * @param    $term        The search term to query
 * @param    $location    The location of the business to query
 */

function query_api($term, $location, &$list, &$reviews) {     
    $bearer_token = obtain_bearer_token();
    if (!empty($_GET['location'])) {
        $location = $_GET['location'];
    }
    $response = json_decode(search($bearer_token, $_GET['terms'], $location));
    if ($response->total != 0){ 
        $list = $response->businesses;
    }

    for ($i = 0; $i < count($list); $i++) {
        $business_id = $response->businesses[$i]->id;
        $buss = json_decode(get_business($bearer_token, $business_id));
        $temp = [];
        for ($j = 0; $j < count($buss->reviews); $j++) {
            array_push($temp, $buss->reviews[$j]->text);
        }
        array_push($reviews, $temp);
    }

    // echo count($list);

    // print sprintf(
    //     "%d businesses found, querying business info for the top result \"%s\"\n\n",         
    //     count($response->businesses),
    //     $business_id
    // );
    
    // print sprintf("Result for business \"%s\" found:\n", $business_id);
    // $pretty_response = json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    // print "$pretty_response\n";
}
/**
 * User input is handled here 
 */
$longopts  = array(
    "term::",
    "location::",
    );

$options = getopt("", $longopts);
$term = $options['term'] ?: $GLOBALS['DEFAULT_TERM'];
$location = $options['location'] ?: $GLOBALS['DEFAULT_LOCATION'];
query_api($term, $city, $list, $reviews);

}

$permlist = json_encode($list);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0"/>
  <title>Yelp</title>

  <style>
      /* Always set the map height explicitly to define the size of the div
      * element that contains the map. */
      #map {
        margin-top: 6px;
        height: 500px;
    }
    /* Optional: Makes the sample page fill the window. */
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
    }
</style>
<!-- CSS  -->
<script async defer
src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD6SFMfY55UDnr7flBr7GuoxbXsSirf-IY">
</script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="css/materialize.css" type="text/css" rel="stylesheet" media="screen,projection"/>
<link href="css/style.css" type="text/css" rel="stylesheet" media="screen,projection"/>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<script scr="http://code.jquery.com/jquery-1.9.1.js"> </script>

<script>
  function getLocation() {
      if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(savePosition, positionError, {timeout:10000});
      } else {
              //Geolocation is not supported by this browser
          }
      }

      // handle the error here
      function positionError(error) {
          var errorCode = error.code;
          var message = error.message;

          alert(message);
      }

      function savePosition(position) {
        $.post("variables.php", {lat: position.coords.latitude, lng: position.coords.longitude});
        $.ajax({
            type:"post",
            url:"variables.php",
            data:{'msg': "hi"},
            cache:false,
            success: function(html){
                $('msg').html(html);
            }
        });
    }
</script>
</head>

<body>
<!--     <input id = "name" onclick="return getLocation()">
    <p id="msg"></p> -->
    <div class="section no-pad-bot" id="index-banner">
        <div class="container">
          <br><br>
          <br><br>
          <h1 class="header center red-text text-darken-4">FoodFindr</h1>

          <div class="row">
            <form class="col s12" action "">
              <div class="row">
                <div class="input-field col s6">
                  <i class="material-icons prefix">account_circle</i>
                  <input id="autocomplete" type="text" class="autocomplete" name = "terms">
                  <label for="autocomplete">What do you want to eat?</label>
              </div>
              <div class="input-field col s6">
                  <i class="material-icons prefix">location_on</i>
                  <input id="icon_telephone" type="tel" class="validate" name = "location">
                  <label for="icon_location">Location</label>
              </div>
          </div>
          <div class="row center">
            <button type = "submit" class="btn-large waves-effect waves-light red darken-4">Submit</button>
            <br/>
        </div>
    </form>
</div>
<br><br>

</div>
</div>

<div class="container">
    <div class="section">
      <!--   Icon Section   -->
      <div class="row">
        <div class="col s9 m9">
        <?php
        function testLangID($arr) {
           $curl = curl_init();
           $header_args = array(
               'Accept: application/json'
               );
           $url_params = array();
           $url_params['term'] = $arr;
           curl_setopt($curl, CURLOPT_URL, "https://watson-api-explorer.mybluemix.net/tone-analyzer/api/v3/tone?version=2016-05-19&text=".http_build_query($url_params));
           curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

           $result = curl_exec($curl);

           curl_close($curl);

           $decoded = json_decode($result, true);

            $num = 0;
                for ($i = 0; $i < 3; $i++) {
                    if (isset($decoded["document_tone"])){
                        $num -= $decoded["document_tone"]["tone_categories"][0]["tones"][$i]["score"];
                    }
                }
                if (isset($decoded["document_tone"])){
                    $num += 4*$decoded["document_tone"]["tone_categories"][0]["tones"][3]["score"];
                }
                if (isset($decoded["document_tone"])){
                    $num -= $decoded["document_tone"]["tone_categories"][0]["tones"][4]["score"];
                }
            return $num;
            //echo print_r($decoded["document_tone"]["tone_categories"][0]["tones"][0]["score"]);
       }

        if (count($list) != 0) {

            $ints = array(1, 2, 3, 4, 5);
            $floats = array(1.5, 2.5, 3.5, 4.5);
            $nums = array();

            $namesarr = array();

            for ($i = 0; $i < count($list); $i++) {
                $name = $list[$i]->name;
                $tot = 0;
                for ($j = 0; $j < 3; $j++) {
                    $tot += (testLangID($reviews[$i][$j]));
                }
                $nums[$tot] = $name;
            }

            ksort($nums);
            //echo print_r($nums);

            


            for ($i = 0; $i < count($list); $i++) {
                $name = $list[$i]->name;
                $imageurl = $list[$i]->image_url;
                $city = $list[$i]->location->city;
                $price = $list[$i]->price;
                $rate = $list[$i]->rating;
                $isint = TRUE;
                if (in_array($rate, $floats)) {
                        //print_r($ints);
                    $isint = FALSE;
                }
                else {
                        //print_r($floats);
                }
                echo '<div class="card hoverable">';
                echo '<div class="card-content">';
                echo '<div class="row">';
                echo '<div class="col m4">';
                echo '<div style="background:center no-repeat url(\'' . $imageurl. '\');background-size:cover;width:190px;height:130px">';
                echo '</div>';
                echo '</div>';
                echo '<div class="col m4">';
                echo '<span class="card-title grey-text text-darken-4"><strong>'.$name.'</strong>';
                echo '<div class="divider"></div>';
                echo '<p><font size="3">' . $city .  ' • ' . $price . ' </font></p>';
                echo '</div>';
                echo '<div class="col m3 offset-m1 center">';
                echo '<br><br><br>';
                if ($isint == FALSE) {
                    $rate = $rate - 0.5;
                }
                for ($j = 0; $j < $rate; $j++) {
                    echo '<i class="material-icons prefix">star</i>';
                }
                if ($isint == FALSE) {
                    echo '<i class="material-icons prefix">star_half</i>';
                    $rate = $rate + 1;
                }
                for ($j = 0; $j < 5 - $rate; $j++) {
                    echo '<i class="material-icons prefix">star_border</i>';
                }
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }  


       ?>
       <div class="icon-block">
        
    </div>
</div>

<div class="col s3 m3">
    <div id="map"></div>
</div>

<div class="col s12 m4">
  <div class="icon-block">
</div>
</div>

<div class="col s12 m4">
  <div class="icon-block">
    
</div>
</div>
</div>

</div>
<br><br>
</div>

<footer class="page-footer red-darken-4">
    <div class="container">
      <div class="row">
        <div class="col l6 s12">
          <h5 class="white-text">About</h5>
          <p class="grey-text text-lighten-4">A more intelligent way to search for restaurants</p>


      </div>
      
</div>
</div>
</div>
<div class="footer-copyright">
  <div class="container">
      Made by <a class="red-text text-darken-4" href="http://lijeffrey39.github.io">Jeffrey Li</a>
  </div>
</div>
</footer>
<script>
    var latit = "<?php echo $latitude; ?>";
    var longi = "<?php echo $longitude; ?>";
    var map;
    var bus = <?=$permlist;?>;
    var markers = [];
    var global_markers = [];
    for (var i = 0; i < bus.length; i++) {
        markers.push([parseFloat(bus[i].coordinates.latitude), parseFloat(bus[i].coordinates.longitude), bus[i].name])
    }
    function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
          center: {lat: parseFloat(latit), lng: parseFloat(longi)},
          zoom: 14
      });
        addMarker();
    }

    var infowindow = new google.maps.InfoWindow({});

    function addMarker() {
        for (var i = 0; i < markers.length; i++) {
            // obtain the attribues of each marker
            var lat = parseFloat(markers[i][0]);
            var lng = parseFloat(markers[i][1]);
            var trailhead_name = markers[i][2];

            var myLatlng = new google.maps.LatLng(lat, lng);

            var contentString = "<html><body><div><p>" + trailhead_name + "</p></div></body></html>";

            var marker = new google.maps.Marker({
                position: myLatlng,
                map: map,
                title: trailhead_name
            });

            marker['infowindow'] = contentString;

            global_markers[i] = marker;

            google.maps.event.addListener(global_markers[i], 'click', function() {
                infowindow.setContent(this['infowindow']);
                infowindow.open(map, this);
            });
        }
    }
    window.onload = initMap;
</script>

<!--  Scripts-->

<script src="https://code.jquery.com/jquery-2.1.1.min.js"></script>
<script src="js/materialize.js"></script>
<script src="js/init.js"></script>

</body>
</html>
