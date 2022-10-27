<?php
require_once '../DBConnection.php';

/**
 * Take a table name and an arry list of columns
 * Each columns should contain these KVP
 * 'name' The column name to be used
 * 'type' if not set it will be set as an empty column
 * 'length' it is required for the length of varchars, and int vs bigint
 * 'isNullable' optional setting this value will make it a nullable field
 */
function createTableFromNameAndColumns($tableName, $columnList){
    $tableName = getFormatedName($tableName);
    $sqlTableCreate = mysqlTableCreateFromName($tableName);
    foreach($columnList as $v){
        $sqlTableCreate .= mysqlAddColumn($v);
    }
    $sqlTableCreate = substr($sqlTableCreate, 0, -2).');';
    $connection = DatabaseConnection::getConnection();
    try{
        // echo "<br/>$sqlTableCreate<br/><br/>";
        $connection->query($sqlTableCreate);
    }catch (mysqli_sql_exception $e) {
        echo "<br/>Create Table error from query: <br/>$sqlTableCreate<br/>Error code: {$e->getCode()}<br/> Error message: {$e->getMessage()}<br/><br/>";
    }
}
// Part one of creating the table starts the query with table name
function mysqlTableCreateFromName($name){
    return "CREATE TABLE IF NOT EXISTS `$name` (";
}
// Part 2 of creating the table, by adding the column names
function mysqlAddColumn($array){
    if($array['name'] === ''){
        return "`drop_trash` varchar(0) DEFAULT NULL, ";
    }
    $nullable = isset($array['isNullable']) ? 'DEFAULT NULL' : 'NOT NULL';
    if(isset($array['type']) && $array['type'] === 'int' && $array['length'] >= 10){
        $array['type'] = "bigint";
    }elseif(isset($array['type']) && $array['type'] === 'varchar' && $array['length'] > 255){
        $array['type'] = "json";
    }elseif(isset($array['type']) && $array['type'] === 'varchar'){
        $array['type'] = "varchar({$array['length']})";
    }elseif(!isset($array['type'])){
        $array['type'] = "varchar(0)";
    }
    return "`{$array['name']}` {$array['type']} $nullable, ";
}
// format string into lowercase snake case string 
function getFormatedName($string){
    // @TODO: improve this regex to include converting spaces to underscores
    $string = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    $array = explode(' ',trim($string));
    $column = trim(strtolower(implode('_', $array))); 
    if($column === ''){
        return 'drop_trash';
    }
    return $column;
}
// single call to add KVP to column list array 
function addToColumnList($columnList, $key, $name, $value){
    $columnList = startColumnList($columnList, $key, $name);
    $columnList = updateColumnList($columnList, $key, $value);
    return $columnList;
}

// starts the column list by passing in a name and setting default length to 0
function startColumnList($columnList, $key, $name){
    $columnName = getFormatedName($name);
    $columnList[$key]['name'] = $columnName;
    if(!isset($columnList[$key]['length'])){
        $columnList[$key]['length'] = 0;
    }
    return $columnList;
}

// finish the column list by checking value type, identifying if it is nullable, and updating length
function updateColumnList($columnList, $key, $value){
    if(is_null($value)){
        $columnList[$key]['isNullable'] = true;
    }else{
        $type = identifyField(trim($value));
        if(isset($type['isEmpty'])){
            $columnList[$key]['isNullable'] = true;
        }
        if(isset($type['isVerified'])){
            if(isset($columnList[$key]['type']) && $columnList[$key]['type'] !== $type['isVerified']){
                // Converting an into to a float is fine, but otherwise do not change types
                if($columnList[$key]['type'] === 'bool' && ($type['isVerified'] === 'int' || $type['isVerified'] === 'float')){
                    $columnList[$key]['type'] = $type['isVerified'];
                }elseif($columnList[$key]['type'] === 'int' && $type['isVerified'] === 'float'){
                    $columnList[$key]['type'] = $type['isVerified'];
                }elseif(($columnList[$key]['type'] === 'int' || $columnList[$key]['type'] === 'float' || $columnList[$key]['type'] === 'bool') && $type['isVerified'] === 'varchar'){
                    $columnList[$key]['type'] = $type['isVerified'];
                }
            }else{
                $columnList[$key]['type'] = $type['isVerified'];
            }
        }
        if($columnList[$key]['length'] < $type['length']){
            $columnList[$key]['length'] = $type['length'];
        }
    }
    return $columnList;
}
// Identify what kind of variable is in each column
function identifyField($field){
    $array = array();
    $array['value'] = $field;
    $array['length'] = strlen($field);
    $array['isEmpty'] = empty($field);
    $array['isNull'] = is_null($field);
    $array['isArray'] = is_array($field);
    $array['isString'] = is_string($field);
    $array['isDate'] = date_create($field);
    $array['isNumeric'] = is_numeric($field);
    // Have to make sure that the field value does not change when type casting it
    if($field == (bool)$field){
        $array['isBool'] = is_bool((bool)$field);
    }
    if($field == (int)$field){
        $array['isInt'] = is_int((int)$field);
    }
    if($field == (float)$field){
        $array['isFloat'] = is_float((float)$field);
    }
    $response = array();
    $response['length'] = $array['length'];
    // verify field is an number or date type
    if($array['isBool'] && $array['length'] <= 1){
        $response['isVerified'] = 'bool';
    }elseif(!$array['isEmpty'] && $array['length'] >= 2 && $array['isNumeric'] && !isset($array['isInt']) && $array['isFloat'] && substr($array['value'], 0, 1) != 0){
        $response['isVerified'] = 'float';
    }elseif(!$array['isEmpty'] && $array['isNumeric'] && isset($array['isInt']) && $array['isInt'] && substr($array['value'], 0, 1) != 0){
        $response['isVerified'] = 'int';
    }elseif(!$array['isEmpty'] && $array['length'] >= 8 && $array['isDate'] && (strpos($field, '-') || strpos($field, '/'))){
        if($array['length'] > 11){
            $response['isVerified'] = 'datetime';
        }else{
            $response['isVerified'] = 'date';
        }
    }elseif(!$array['isEmpty']){
        $response['isVerified'] = 'varchar';
    }else{
        $response['isEmpty'] = $array['isEmpty'];
    }
    return $response;
}

// Creating the insert query
function mysqlInsertStart($name, $columnList){
    $sql = "INSERT INTO `$name` (";
    foreach($columnList as $column){
        $sql .= "`{$column['name']}`,";
    }
    return substr($sql, 0, -1).") VALUES ";
}

function getCorrectInsertFormat($initialValue, $column, $connection){
    if(isset($column["isNullable"]) && ($initialValue === '' || is_null($initialValue))){
        return "NULL,";
    }
    $sqlInsert = '';
    if(isset($column["type"]) && $column["type"] === 'datetime'){
        $date = date_create($initialValue);
        $insertValue = $date->format("Y-m-d H:i:s");
    }elseif(isset($column["type"]) && $column["type"] === 'date'){
        $date = date_create($initialValue);
        $insertValue = $date->format("Y-m-d");
    }elseif(isset($column["type"]) && $column["type"] === 'bool'){
        if($initialValue === true){
            return "true,";
        }else{
            return "false,";
        }
    }else{
        $insertValue = $initialValue;
    }
    return "'{$connection->real_escape_string($insertValue)}',";
}

function getJsonFileContent($fileName){
    $file = file_get_contents($fileName);
    return json_decode($file, true);
}

function getUrlContent($url, $method, $cookie = null, $fields = null, $responseHeader = false){
    $url = trim($url);
    $ch = curl_init();// Initialize a connection with cURL (ch = cURL handle, or "channel")
    curl_setopt($ch, CURLOPT_URL, $url);// Set the URL
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);// Set the HTTP method
    if($cookie){
        curl_setopt($ch, CURLOPT_HTTPHEADER, $cookie);// send the manually set cookie
    }
    if($method === 'POST' && $fields){
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    if($responseHeader){
        curl_setopt($ch, CURLOPT_HEADER, 1);// Return the headers as part of response
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);// Return the response instead of printing it out
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);// follow redirects... 
    $response = curl_exec($ch);// Send the request and store the result in $response
    curl_close($ch);// Close cURL resource to free up system resources
    return $response;
}

// Used to get individual properties 
function getAssessorRecords(){
    $connection = DatabaseConnection::getConnection();
    $sql = "SELECT `account_number`, `primary_owner`, `primary_owner_mailing_address_street`, `primary_property_address`  FROM `scraped_assessor_records` WHERE 1;";
    $results = $connection->query($sql);
    if($results === false){
        return [];
    }
    return $results->fetch_all(MYSQLI_ASSOC);
}

// This is the initial entry point
function startScrapingData($streetList, $neighborhoodName, $year){
    scrapeRecordsByStreet($streetList, $neighborhoodName);
    scrapeIndividualAssessorRecords($year);
    getWellPermitsInArea($neighborhoodName);
    cleanUpDatabaseTablesEmptyColumns();
    echo "<br/>Finished :)<br/>";
}

function scrapeRecordsByStreet($streetList, $neighborhoodName){
    echo "<br/>Starting to get records for the streets provided scrapeRecordsByStreet<br/>";
    $columnList = $cleanRecords = array();
    foreach($streetList as $streetName){
        $paginationPage = 0;//starts at 0 increment by 40 for each page beyond the first
        $response = useStreetNameForCurlQuery($streetName, $paginationPage);
        $cleanRecords = getCommunityRecords($response, $cleanRecords, $neighborhoodName, $columnList);
    }
    $tableName = 'scraped_assessor_records';
    genericInsert($tableName, $columnList, $cleanRecords);
}

// recursive function for getting all the records for the street
function useStreetNameForCurlQuery($streetName, $page, $records = array()){
    $street = strtolower($streetName);
    $file = "./json/$street-$page.json";
    if(file_exists($file)){
        $response = getJsonFileContent($file);
    }else{
        echo "<br/>File not  found: $file<br/>";
        $streetParts = explode(' ', $street);
        $initialQuery = '{"query":{"bool":{"disable_coord":true,"should":[{"constant_score":{"filter":{"term":{"accountNumber":"%STREET%"}},"boost":5}},{"constant_score":{"filter":{"term":{"stateParcelNumber":"%STREET%"}},"boost":5}},{"constant_score":{"filter":{"match_phrase_prefix":{"addresses":{"query":"%STREET%","slop":1}}},"boost":2.5}},{"constant_score":{"filter":{"match_phrase_prefix":{"ownerNames":{"query":"%STREET%","slop":3}}},"boost":2.25}},{"bool":{"disable_coord":true,"minimum_should_match":2,"should":[{"constant_score":{"filter":{"term":{"_all":"%STREET_ONE%"}}}},{"constant_score":{"filter":{"term":{"_all":"%STREET_TWO%"}}}}]}}]}},"sort":[{"_score":{"order":"desc"}},{"primaryStreetName":{"order":"asc","missing":"_last"}},{"primaryStreetDirection":{"order":"asc","missing":"_last"}},{"primaryAddressNumber":{"order":"asc","missing":"_last"}},{"primaryOwnerName":{"order":"asc","missing":"_last"}}],"from":%START_NUMBER%,"size":40,"_source":["display.*"]}';
        $query = str_replace("%STREET%", $street, $initialQuery);
        $query = str_replace("%STREET_ONE%", $streetParts[0], $query);
        $query = str_replace("%STREET_TWO%", $streetParts[1], $query);
        $query = str_replace("%START_NUMBER%", $page, $query);
        $url = "https://apps.douglas.co.us/assessor/simple-search";
        $cookie = array('cookie: SearchHistory=[{"CatID":0,"Query":"antelope trl"}]');
        $responseJson = getUrlContent($url, 'POST', $cookie, $query);
        file_put_contents($file, $responseJson);
        $response = json_decode($responseJson, true);
    }
    $totalCount = $response["hits"]["total"];
    $addresses = $response["hits"]["hits"];
    $records = array_merge($records, $addresses);
    if($totalCount > 40 && count($addresses) === 40){
        $records = useStreetNameForCurlQuery($street, $page+40, $records);
    }
    return $records;
}

function getCommunityRecords($records, $recordOut, $neighborhoodName, &$columnList){
    foreach($records as $values){
        $possibleRecord = [];
        // I am only expecting a single neighbor hood to be set up, so this is a single string comparison
        if(isset($values["_source"]["display"]["subdivision"]) && strcasecmp($values["_source"]["display"]["subdivision"], $neighborhoodName) == 0){
            foreach($values["_source"]["display"] as $k => $v){
                switch($k){
                    case "stateParcelNumber":
                    case "accountNumber":
                    case "legalDescription":
                        $columnList = addToColumnList($columnList, $k, $k, $v);
                        $possibleRecord[$k] = $v;
                        break;
                    case "primaryOwner":
                        $columnList = addToColumnList($columnList, $k, $k, $v["name"]);
                        $columnList = addToColumnList($columnList, $k."_mailingAddress_street", $k."_mailingAddress_street", $v["mailingAddress"]["street"]);
                        $columnList = addToColumnList($columnList, $k."_mailingAddress_street2", $k."_mailingAddress_street2", $v["mailingAddress"]["street2"]);
                        $columnList = addToColumnList($columnList, $k."_mailingAddress_city", $k."_mailingAddress_city", $v["mailingAddress"]["city"]);
                        $columnList = addToColumnList($columnList, $k."_mailingAddress_zipCode", $k."_mailingAddress_zipCode", $v["mailingAddress"]["zipCode"]);
                        $possibleRecord[$k] = $v["name"];
                        $possibleRecord[$k."_mailingAddress_street"] = $v["mailingAddress"]["street"];
                        $possibleRecord[$k."_mailingAddress_street2"] = $v["mailingAddress"]["street2"];
                        $possibleRecord[$k."_mailingAddress_city"] = $v["mailingAddress"]["city"];
                        $possibleRecord[$k."_mailingAddress_zipCode"] = $v["mailingAddress"]["zipCode"];
                        break;
                    case "primaryPropertyAddress":
                        $columnList = addToColumnList($columnList, $k, $k, $v["street"]);
                        $possibleRecord[$k] = $v["street"];
                        break;
                }
            }
            $recordOut[] = $possibleRecord;
        }
    }
    return $recordOut;
}

function scrapeIndividualAssessorRecords($year){
    echo "<br/><br/>Starting to get individual property records inside function scrapeIndividualAssessorRecords<br/><br/>";
    $assessorRecords = getAssessorRecords();
    $columnListAppeals = $columnListOwners = $columnListAddresses = $columnListValuesByAbstractCode = $columnListSales = $columnListBuildings = $columnListBuildingPermitAuthority = $columnListSubdivision = $columnListTaxAuthorities = $columnListLandAttributes = $columnListLandSegments = $columnListAccounts = array();
    $recordAppeals = $recordOwners = $recordAddresses = $recordValuesByAbstractCode = $recordSales = $recordBuildings = $recordBuildingPermitAuthority = $recordSubdivision = $recordTaxAuthorities = $recordLandAttributes = $recordLandSegments = $recordAccounts = array();
    $tablePrefix = "assessorPropertyRecord";
    foreach($assessorRecords as $record){
        $accountNumber = $record['account_number'];
        $file = "./json/{$accountNumber}.json";
        if(file_exists($file)){
            $response = getJsonFileContent($file);
        }else{
            echo "<br/>File not  found: $file<br/>";
            $url = "https://apps.douglas.co.us/realware/DATA/{$year}/detail/{$accountNumber}.json";
            //saving records to not need to call again...
            $response = getUrlContent($url, 'GET');
            file_put_contents($file, $response);
            $response = json_decode($response, true);
        }
        [$columnListTaxAuthorities, $recordTaxAuthorities] = genericParseListArray($response['taxAuthorities'], $accountNumber, $columnListTaxAuthorities, $recordTaxAuthorities);
        [$columnListAppeals, $recordAppeals] = genericParseListArray($response['appeals'], $accountNumber, $columnListAppeals, $recordAppeals);
        [$columnListOwners, $recordOwners] = genericParseListArray($response['owners'], $accountNumber, $columnListOwners, $recordOwners);
        [$columnListAddresses, $recordAddresses] = genericParseListArray($response['addresses'], $accountNumber, $columnListAddresses, $recordAddresses);
        [$columnListValuesByAbstractCode, $recordValuesByAbstractCode] = genericParseListArray($response['valuesByAbstractCode'], $accountNumber, $columnListValuesByAbstractCode, $recordValuesByAbstractCode);
        [$columnListSales, $recordSales] = genericParseListArray($response['sales'], $accountNumber, $columnListSales, $recordSales);
        [$columnListBuildings, $recordBuildings] = genericParseListArray($response['buildings'], $accountNumber, $columnListBuildings, $recordBuildings);
        [$columnListLandAttributes, $recordLandAttributes] = genericParseListArray($response['landAttributes'], $accountNumber, $columnListLandAttributes, $recordLandAttributes);
        [$columnListLandSegments, $recordLandSegments] = genericParseListArray($response['landSegments'], $accountNumber, $columnListLandSegments, $recordLandSegments);
        [$columnListBuildingPermitAuthority, $record] = genericParseArray($response['buildingPermitAuthority'], $accountNumber, $columnListBuildingPermitAuthority, []);
        $recordBuildingPermitAuthority[] = $record;
        [$columnListSubdivision, $record] = genericParseArray($response['subdivision'], $accountNumber, $columnListSubdivision, []);
        $recordSubdivision[] = $record;
        unset($response['appeals'], $response['owners'], $response['addresses'], $response['valuesByAbstractCode'], $response['sales'], $response['buildings'], $response['buildingPermitAuthority'], $response['subdivision'], $response['landAttributes'], $response['landSegments'], $response['taxAuthorities'], $response['novTaxYears']);
        [$columnListAccounts, $record] = genericParseArray($response, $accountNumber, $columnListAccounts, []);
        $recordAccounts[] = $record;
    }
    genericInsert($tablePrefix.'Account', $columnListAccounts, $recordAccounts);
    genericInsert($tablePrefix.'Appeals', $columnListAppeals, $recordAppeals);
    genericInsert($tablePrefix.'Owners', $columnListOwners, $recordOwners);
    genericInsert($tablePrefix.'Addresses', $columnListAddresses, $recordAddresses);
    genericInsert($tablePrefix.'ValuesByAbstractCode', $columnListValuesByAbstractCode, $recordValuesByAbstractCode);
    genericInsert($tablePrefix.'Salaes', $columnListSales, $recordSales);
    genericInsert($tablePrefix.'Buildings', $columnListBuildings, $recordBuildings);
    genericInsert($tablePrefix.'LandAttributes', $columnListLandAttributes, $recordLandAttributes);
    genericInsert($tablePrefix.'LandSegments', $columnListLandSegments, $recordLandSegments);
    genericInsert($tablePrefix.'BuildingPermitAuthority', $columnListBuildingPermitAuthority, $recordBuildingPermitAuthority);
    genericInsert($tablePrefix.'Subdivision', $columnListSubdivision, $recordSubdivision);
    genericInsert($tablePrefix.'TaxAuthorities', $columnListTaxAuthorities, $recordTaxAuthorities);
}

function genericParseListArray($array, $accountNumber, $columnList, $recordList){
    $record = array();
    if(empty($array)){
        return [$columnList, $recordList];
    }
    foreach($array as $listItem){
        [$columnList, $record] = genericParseArray($listItem, $accountNumber, $columnList, $record);
        $recordList[] = $record;
    }
    return [$columnList, $recordList];
}

function genericParseArray($array, $accountNumber, $columnList, $record){
    if(empty($array)){
        return [$columnList, $record];
    }
    foreach($array as $k => $v){
        if(is_array($v) && array_is_list($v)){
            $v = json_encode($v);
            $columnList = addToColumnList($columnList, $k, $k, $v);
            $record[$k] = $v;
        }elseif(is_array($v)){
            foreach($v as $subKey => $subValue){
                $columnList = addToColumnList($columnList, $k.' '.$subKey, $k.' '.$subKey, $subValue);
                $record[$k.' '.$subKey] = $subValue;
            }
        }else{
            $columnList = addToColumnList($columnList, $k, $k, $v);
            $record[$k] = $v;
        }
    }
    $columnList = addToColumnList($columnList, 'accountNumber', 'accountNumber', $accountNumber);
    $record['accountNumber'] = $accountNumber;
    return [$columnList, $record];
}
// the same as genericParseArray, but do not need to pass in account number
function genericSimpleParseArray($array, $columnList, $record){
    if(empty($array)){
        return [$columnList, $record];
    }
    foreach($array as $k => $v){
        if(is_array($v) && array_is_list($v)){
            $v = json_encode($v);
            $columnList = addToColumnList($columnList, $k, $k, $v);
            $record[$k] = $v;
        }elseif(is_array($v)){
            foreach($v as $subKey => $subValue){
                $columnList = addToColumnList($columnList, $k.' '.$subKey, $k.' '.$subKey, $subValue);
                $record[$k.' '.$subKey] = $subValue;
            }
        }else{
            $columnList = addToColumnList($columnList, $k, $k, $v);
            $record[$k] = $v;
        }
    }
    return [$columnList, $record];
}

function genericInsert($tableName, $columnList, $record){
    if(empty($record)){
         return false;
    }
    $tableName = getFormatedName($tableName);
    createTableFromNameAndColumns($tableName, $columnList);
    $sqlInsert = mysqlInsertStart($tableName, $columnList);
    $chunks = array_chunk($record, 1000);// Chunking up the insert records to prevent transfer limit
    foreach($chunks as $chunk){
        addRecordsToInsertQuery($columnList, $chunk, $sqlInsert);
    }
}

function addRecordsToInsertQuery($columnList, $record, $sqlInsert){
    $connection = DatabaseConnection::getConnection();
    if(!array_is_list($record)){
        $sqlInsert .= "(";
    }
    foreach($record as $k => $v){
        if(is_array($v)){
            $sqlInsert .= "(";
            foreach($v as $key => $value){
                $sqlInsert .= getCorrectInsertFormat($value, $columnList[$key], $connection);
            }
            $sqlInsert = substr($sqlInsert, 0, -1);
            $sqlInsert .= "),";
        }else{
            $sqlInsert .= getCorrectInsertFormat($v, $columnList[$k], $connection);
        }
    }
    $sqlInsert = substr($sqlInsert, 0, -1);
    if(!array_is_list($record)){
        $sqlInsert .= ")";
    }
    $sqlInsert .= ";";
    // echo "<br/>$sqlInsert<br/>";
    try{
        $connection->query($sqlInsert);
    }catch (mysqli_sql_exception $e) {
        echo "<br/><br/>Error code: {$e->getCode()}<br/> Error message: {$e->getMessage()}<br/><br/>";
    }
}

function pullOutCookie($result){
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matchFound);
    $cookies = array();
    foreach($matchFound[1] as $item) {
        parse_str($item,  $cookie);
        $cookies = array_merge($cookies,  $cookie);
    }
    return $cookies;
}

function getWellPermitsInArea($neighborhood){
    echo "<br/><br/>Starting to get well permit records for within 2 miles of {$neighborhood} inside function getWellPermitsInArea<br/><br/>";
    $file = "./json/well-permits-{$neighborhood}.json";
    if(file_exists($file)){
        $response = getJsonFileContent($file);
    }else{
        echo "<br/>File not found {$file} attempting to save results<br/><br/>";
        $neighborhood = urlencode($neighborhood);
        $firstUrl = "https://dwr.state.co.us/Tools/WellPermits?submitButton=Submit&SelectedGeoValue=waterDivisionDiv&SelectedPermitUse=All&SelectedAdditionalValue=addressDiv&AddressSearch.AddressMain={$neighborhood}%2C%20CO%2C%20USA&AddressSearch.RadiusSearch=2&AddressSearch.State=CO&AddressSearch.Units=0";
        $result = getUrlContent($firstUrl, 'GET', null, null, true);
        $cookies = pullOutCookie($result);
        $cookie = array("cookie: ASP.NET_SessionId={$cookies['ASP_NET_SessionId']}");
        $fields = 'sort=&group=&filter=';
        $secondUrl = 'https://dwr.state.co.us/Tools/WellPermits/Search/GridPage?grid=WellPermitsGrid';
        $results = getUrlContent($secondUrl, 'POST', $cookie, $fields);
        file_put_contents($file, $results);
        $response = json_decode($results, true);
    }
    if(isset($response["Total"]) && $response["Total"] > 1 && isset($response["Data"])){
        $accountNumber = 0;
        $columnList = $recordList = $record = array();
        foreach($response["Data"] as $block){
            [$columnList, $record] = genericSimpleParseArray($block, $columnList, []);
            $recordList[] = $record;
        }
        genericInsert('WellPermits', $columnList, $recordList);
    }
}
// removes table columns with empty varchars or a single distinct value
function cleanUpDatabaseTablesEmptyColumns(){
    echo "<br/>Starting to clean up DB records inside cleanUpDatabaseTablesEmptyColumns<br/>";
    $connection = DatabaseConnection::getConnection();
    $dbName = DatabaseConnection::getDBName();
    $sql = "SHOW TABLES FROM {$dbName};";
    try{
        $results = $connection->query($sql);
        // go through DB tables
        foreach($results->fetch_all(MYSQLI_ASSOC) as $record){
            $dropColumnList = [];
            $tableName = $record["Tables_in_$dbName"];
            // Go through table fields to remove empty columns(ex: varchar(0))
            $sqlTable = "DESCRIBE {$tableName};";
            $sqlSelect = "SELECT ";
            $results = $connection->query($sqlTable);
            foreach($results->fetch_all(MYSQLI_ASSOC) as $value){
                if($value["Type"] === "varchar(0)"){
                    if(!in_array($value["Field"], $dropColumnList)){
                        $dropColumnList[] = "`{$value['Field']}`";
                    }
                }else{
                    $sqlSelect .= " COUNT(DISTINCT `{$value['Field']}`),";
                }
            }
            $sqlSelect = substr($sqlSelect, 0, -1)." FROM $tableName;";
            $results = $connection->query($sqlSelect);
            foreach($results->fetch_all(MYSQLI_ASSOC) as $k => $v){
                foreach($v as $key => $value){
                    if($value == 1){
                        $column = substr($key, 15, -1);
                        if(!in_array($column, $dropColumnList)){
                            $dropColumnList[] = $column;
                        }
                    }
                }
            }
            if(count($dropColumnList)){
                $sqlAlterTable = "ALTER TABLE `$tableName` ";
                foreach($dropColumnList as $column){
                    $sqlAlterTable .= "DROP COLUMN {$column}, ";
                }
                $sqlAlterTable = substr($sqlAlterTable, 0, -2).';';
                // echo "$sqlAlterTable<br/><br/>";
                $connection->query($sqlAlterTable);
            }
        }
    }catch (mysqli_sql_exception $e) {
        echo "<br/>Error running cleanup queries<br/> code: {$e->getCode()}<br/> message: {$e->getMessage()}<br/>";
    }
}
