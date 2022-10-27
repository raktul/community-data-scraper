<?php
require_once 'functions.php';

// Default to current year if after May, else default to last year.
$year = (date("m") > 5) ? date("Y") : date("Y",strtotime("-1 year"));
$streetList = [];
$communityName = '';
if(!empty($_POST)){
    $streetList = isset($_POST['street-name']) ? $_POST['street-name'] : [];
    $communityName = isset($_POST['community-name']) ? $_POST['community-name'] : '';
    $year = isset($_POST['year']) ? $_POST['year'] : $year;

    if(!empty($streetList) && !empty($communityName) && !empty($year)){
        echo "I am going to start scraping information related to '{$communityName}' for the year of '{$year}', starting with this list of streets:<br/>";
        foreach($streetList as $k => $street){
            if(!empty($street)){
                echo "{$k}: {$street}, ";
            }else{
                unset($streetList[$k]);
            }
        }
        $result = startScrapingData($streetList, $communityName, $year);
    }
}else{
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Setup Community Scraper</title>
        <script>
            let i = 1;
            function addStreetField(){
                // Create another input
                let input = document.createElement("input");
                input.setAttribute("id", "street-name-"+i);
                input.setAttribute("name", "street-name["+i+"]");
                input.setAttribute("size", "50");
                input.setAttribute("maxlength", "50");
                input.setAttribute("type", "text");
                // Create label for said input
                let label = document.createElement("label");
                label.setAttribute("for", "street-name-"+i);
                label.appendChild(document.createTextNode("Street Name "+i+" "));
                // Create div to contain the input and label
                let div = document.createElement("div");
                div.setAttribute("id", "street-name-input-container-"+i);
                div.appendChild(label);
                div.appendChild(input);
                // Add div before append here and a br after said div
                document.getElementById("append-here").insertAdjacentElement("beforebegin", div);
                div.insertAdjacentElement("afterend", document.createElement("br"));
                i++;
            }
        </script>
    </head>
	<body>
        <h1>Make sure to follow the prerequisites as defined in the README.mb file first!</h1>
		<h2>Please add the bellow information to scrape information for your community.</h2>
        <p>
            This scrapper is designed for Douglas County in the state of Colorado, while this might work outside of Douglas county, it has not been tested and you are using at your own risk.<br/>
            Provided the year you are loooking for, either a past year or the current year are advised. The state does not update the information at the start of the year and might produce some warnings, if this occurs use the latest year and try again.<br/>
            The community name is used to filter out records outside of the community, as such the streets should at least be partialy running through the community.
        </p>
		<div id="addNewCommunity" name="addNewCommunity">
			<form id="newCommunity" name="newCommunity" method="post" action="/phwatch/setup/index.php" >
                <div id="year-input-container">
                    <label for="year">Latest Year Desired</label>
                    <input id="year" name="year" size="4" maxlength="4" value="<?php echo $year ?>" />
                </div>
                <br/>
                <div id="community-name-input-container">
                    <label for="community-name">Community Name</label>
                    <input id="community-name" name="community-name" required size="50" maxlength="50" value="" />
                </div>
                <br/>
                <div id="street-name-input-container-0">
                    <label for="street-name-0">Street Name 0 </label>
                    <input id="street-name-0" name="street-name[0]" required size="50" maxlength="50" value="" />
                </div>
                <br/>
                <span id="append-here"></span>
                <button onclick="event.stopPropagation();event.preventDefault(); addStreetField()">Add Another Street</button>
                <br/><br/>
                <input type="submit" value="Start Scraping" />
			</form>
		</div>

    <footer>
		<p>Development by: Moore, Dick</p>
	</footer>
	</body>
</html>
<?php
// Closing tag for else block to hide the form after submitting it
/** 
DROP DATABASE community_records;
CREATE DATABASE community_records;
*/
}
?>
