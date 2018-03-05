<?php
//error_reporting(E_ALL ^ E_NOTICE);  

$db = new mysqli("127.0.0.1", "root", "", "statsite");
ini_set('max_execution_time', 0);
if (mysqli_connect_errno()) 
{
    echo "Error: Unable to connect to MySQL." . PHP_EOL ."<br/>";
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL ."<br/>";
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL ."<br/>";
    $db->close();
    exit;
}

$nextPage;
$prevPage;
$lastPageNum = 2;
$currentPageNum;
$currentChapter = "";
$currentChapterId;
$chapterSQL = "SELECT `id`, `mgpurl` FROM `chapters` WHERE 1";
$result = $db->query($chapterSQL);

if($result)
{
    while ($row = mysqli_fetch_assoc($result))
    {
        $currentChapter = str_replace("multigp.com/mgp/multigp/../../chapters/view/?chapter=", '', $row["mgpurl"]);
        $currentChapterId = $row['id'];
             
        for($i=1;$i <= $lastPageNum; $i++)
        {
            //sleep(rand(15,20));
            //  GOT ALL COMPLETED EVENTS! EXCEPT COLLECTIVE-RC need to double check how many others, NEED TO GET UPCOMING AND others  upcoming-events   completed-races   https://www.multigp.com/mgp/races?Race%5Bname%5D=&Race%5BchapterId%5D=718&Race%5BseasonId%5D=&Race%5BcourseId%5D=&Race%5Bdistance%5D=&Race%5Bstatus%5D=&Race_page=1&ajax=race-grid
            $html = file_get_contents('https://www.multigp.com/mgp/completed-races?Race%5Bname%5D=&Race%5BchapterId%5D='. $currentChapterId .'&Race%5BseasonId%5D=&Race%5BcourseId%5D=&Race%5Bdistance%5D=&Race%5Bstatus%5D=&Race_page='.$i.'&ajax=race-grid');// . '?Race_page='.$i );

            
           // $html = file_get_contents('http://www.multigp.com/mgp/upcoming-events?Race%5Bname%5D=&Race%5BchapterId%5D='. $currentChapterId .'&Race%5BseasonId%5D=&Race%5BcourseId%5D=&Race%5Bdistance%5D=&Race%5Bstatus%5D=&Race_page='.$i.'&ajax=race-grid');// . '?Race_page='.$i );
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML($html);
            //echo $dom->saveHTML();
            getEventNodes($dom);
            echo "Currently on : " . $currentChapter . " page " . $i . " of " . $lastPageNum. "<br>";
        }
    }
   mysqli_free_result($result);    
}

function getEventNodes($doc)
{
    
    global $nextPage,$prevPage,$lastPageNum,$currentPageNum,$db,$currentChapterId;
    $xpath = new DomXPath($doc);
    $nodesEventId = ($xpath->query('//*[@id="race-grid"]/div[3]/span')->length == 0)?$xpath->query('//*[@id="race-grid"]/div[2]/span'):$xpath->query('//*[@id="race-grid"]/div[3]/span');
    $nodesEventDate = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[1]');
    $nodesEventName = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[2]/a');
    $nodesEventLink = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[2]/a/@href');
    $nodeEventSeason = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[4]/a');
    $nodeEventAttendance = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[6]/div');
    $nodesEventLocation = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[5]/a');
    $trash = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[6]/div/div/ul');
   // $trash2 = $xpath->query('//*[@id="race-grid"]/table/tbody/tr/td[6]/div/div/button');
   
    for($i = 0; $i < $nodesEventId->count();$i++)
    {       
        $attendance;  
        
        if(null !== ($trash->item($i)))
        {
            
            $attendance = $trash->item($i)->parentNode;
            $attendance->removeChild($trash->item($i));
            
        }
        try 
        {
      
            $eventId = $db->real_escape_string($nodesEventId->item($i)->nodeValue);
            $eventDate = $nodesEventDate->item($i)->textContent;
            $dt = new DateTime($eventDate);
            $date =  $dt->format("Y-m-d");
            $eventName = $db->real_escape_string($nodesEventName->item($i)->textContent);
            $eventLink2 = $db->real_escape_string($nodesEventLink->item($i)->textContent);
            $eventLink = mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x25\x27\x5C\x5F]', '\\\0', $eventLink2);
            $eventSeason = $db->real_escape_string(isset($nodeEventSeason->item($i)->textContent)?$nodeEventSeason->item($i)->textContent:"Season not listed");
            $eventLocation = $db->real_escape_string(isset($nodesEventLocation->item($i)->textContent)?$nodesEventLocation->item($i)->textContent:"Location not listed");        
            $eventAttendance = filter_var($nodeEventAttendance->item($i)->textContent,FILTER_SANITIZE_NUMBER_INT);
            $eventRegistrationStatus2 = str_replace(array($eventAttendance,'-'),'',$nodeEventAttendance->item($i)->nodeValue);   
            $eventRegistrationStatus = preg_replace('/\s+/', '', $eventRegistrationStatus2);
      
        } 
        catch (Exception $ex) 
        {
            
        }
        
       // echo $eventAttendance . "<br>" . $eventRegistrationStatus. "<br>" ;
       // echo $eventId . "<br>" . $eventName. "<br>" . $eventRegistrationStatus . "<br>" . $eventLink . "<br>" . $eventSeason. "<br>" . $eventAttendance. "<br>" . $eventLocation. "<br>". $eventDate. "<br><br>" ;

        $sql = "INSERT INTO `events`(`id`, `chapter_id`, `name`, `season`, `event_location`, `pilot_attendance`, `url`, `date`,`registration_status`) "
                ."VALUES ('{$eventId}', '{$currentChapterId}', '{$eventName}','{$eventSeason}','{$eventLocation}','{$eventAttendance}','{$eventLink}','{$date}','{$eventRegistrationStatus}') " 
                ."ON DUPLICATE KEY UPDATE `id`='{$eventId}', `chapter_id`='{$currentChapterId}', `name`='{$eventName}', `season`='{$eventSeason}', `event_location`='{$eventLocation}', `pilot_attendance`='{$eventAttendance}', `registration_status`='{$eventRegistrationStatus}', `url`='{$eventLink}', `date`='{$date}'";
        //echo $sql;
        if($db->query($sql))
        {
            echo "Records inserted successfully. <br/>";
        } 
        else
        {
            echo "ERROR: Could not able to execute $sql. <br/>" . mysqli_error($db). "<br/>";
            mysqli_close($db);
        }

    }
    
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
            echo $lastPageNum;
        }
        else
        {
            $lastPageNum = 1;
        }
    }
    //echo "<br>Last page: " . $lastPageNum;
    //echo $doc->saveHTML();

 }
    
function outerHTML($e) 
{
    $doc = new DOMDocument();
    $doc->appendChild($doc->importNode($e, true));
    return $doc->saveHTML();
}        

?>
