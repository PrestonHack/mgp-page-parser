<?php
ini_set('max_execution_time', 0);

$db = mysqli_connect("127.0.0.1", "root", "", "statsite");

if (!$db) 
{
    echo "Error: Unable to connect to MySQL." . PHP_EOL ."<br/>";
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL ."<br/>";
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL ."<br/>";
    mysqli_close($db);
    exit;
}

//echo "Success: A proper connection to server was made! The database is great." . PHP_EOL;
//echo "Host information: " . mysqli_get_host_info($db) . PHP_EOL;
//mysqli_close($db);

/*
 * get the multigp id from the keys section of each page pilot, chapter, and event have them.
 */

$nextPage;
$prevPage;
$lastPageNum = 1;
$currentPageNum;

for($i=1;$i <= $lastPageNum; $i++)
{
    $html = file_get_contents('https://www.multigp.com/mgp/pilots?User_sort=dateAdded&User_page='.$i);
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    //echo $dom->saveHTML();

    getPilotNodes($dom);
}

function getPilotNodes($doc)
{
    global $nextPage,$prevPage,$lastPageNum,$currentPageNum,$db;
    $xpath = new DomXPath($doc);
    $nodesPilotId = $xpath->query('//*[@id="yw1"]/div[5]/span');
    $nodesPilotNames = $xpath->query('//*[@id="yw1"]/div[3]/div/div/div/div/strong/a');
    $nodesPilotLinks = $xpath->query('//*[@id="yw1"]/div[3]/div/div/div/div/strong/a/@href');
    $nodesPilotImage = $xpath->query('//*[@id="yw1"]/div[3]/div/div/a/img/@src');
    $nodesPilotTeam = $xpath->query('//*[@id="yw1"]/div[3]/div/div/div/div[2]/small/a');
    
    foreach($doc->getElementsByTagName('a') as $link) 
    {
        //var_dump($link->textContent == "Next >" && strpos($link->getAttribute('href'), "Member" ));        
        //var_dump( $link->getAttribute('href'));
        
        if($link->textContent == "Next >" && !strpos($link->getAttribute('href'), "Member" ))
        {
            $nextPage = "multigp.com" . $link->getAttribute('href');
        }    
        
        if($link->textContent == "< Previous" && !strpos($link->getAttribute('href'), "Member" ))
        {
            $prevPage = "multigp.com" . $link->getAttribute('href');
            $currentPage = filter_var($link->getAttribute('href'), FILTER_SANITIZE_NUMBER_INT);
            $currentPageNum = $currentPage--;               
        }   
        
        if($link->nodeValue == "Last >>" && !strpos($link->getAttribute('href'), "Member" ))
        {
            $pos = strpos($link->getAttribute('href'),"&ajax=race-grid" );
            $pos2 = strpos($link->getAttribute('href'),"&Race_page=" );
            $end = $pos2 - $pos;
            $pageNum = substr($link->getAttribute('href'),$pos2);
            $lastPageNum = preg_replace('/[^a-zA-Z0-9\']/','',(filter_var($pageNum, FILTER_SANITIZE_NUMBER_INT)));      
            //echo $lastPageNum;
        }
        else
        {
            $lastPageNum = 1;
        }
    }
     
    for($i = 0; $i < $nodesPilotId->count();$i++)
    {    
        $pilotId = mysqli_real_escape_string($db,$nodesPilotId->item($i)->nodeValue);
        $pilotName = mysqli_real_escape_string($db,$nodesPilotNames->item($i)->nodeValue);
        $pilotTeam = (!empty($nodesPilotTeam->item($i)))? mysqli_real_escape_string($db,$nodesPilotTeam->item($i)->nodeValue) : "None";
        $pilotURL = mysqli_real_escape_string ($db, "www.multigp.com" . $nodesPilotLinks->item($i)->nodeValue);
        $pilotCountry = (!empty($xpath->query('//*[@id="yw1"]/div[3]/div['. $i .']/div/div/div/img/@alt')->item(0)))?mysqli_real_escape_string($db,$xpath->query('//*[@id="yw1"]/div[3]/div['. $i .']/div/div/div/img/@alt')->item(0)->textContent):"None";        
        $pilotImageURL = mysqli_real_escape_string($db, $nodesPilotImage->item($i)->nodeValue);
       
        $sql = "INSERT INTO `pilots`(`id`, `callsign`, `team`, `country`, `profileURL`, `image`)"
                ."VALUES ('{$pilotId}', '{$pilotName}', '{$pilotTeam}','{$pilotCountry}','{$pilotURL}','{$pilotImageURL}')" 
                ."ON DUPLICATE KEY UPDATE `id`='{$pilotId}', `callsign`='{$pilotName}', `team`='{$pilotTeam}', `country`='{$pilotCountry}', `profileURL`='{$pilotURL}', `image`='{$pilotImageURL}'";
        if(mysqli_query($db, $sql))
        {
            echo "Records inserted successfully.  $pilotName <br/>";
        } 
        else
        {
            echo "ERROR: Could not able to execute $sql. <br/>" . mysqli_error($db). "<br/>";
            mysqli_close($db);
        }
       
        //echo "<br>" .$pilotId . "<br>" . $pilotName . "<br>" .$pilotTeam . "<br>" . $pilotURL . "<br>" . $pilotImageURL . "<br>" . $pilotCountry . "<br><br><br>"; 
    }
} 

function outerHTML($e) 
{
    $doc = new DOMDocument();
    $doc->appendChild($doc->importNode($e, true));
    return $doc->saveHTML();
}

function getNumberOfChapters() //not used
{
    $ele = $dom->getElementById("yw2")->firstChild->nextSibling->textContent;
    $str = substr($ele, 18);
    $numberOfChapters = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
    echo "<br>". $numberOfChapters;
}

mysqli_close($db);

?>