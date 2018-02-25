<?php
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

for($i=0;$i <= $lastPageNum; $i++)
{
    $html = file_get_contents('https://www.multigp.com/mgp/chapters?Chapter_page='.$i);
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    getChapterNodes($dom);
}

function getChapterNodes($doc)
{
    global $nextPage,$prevPage,$lastPageNum,$currentPageNum,$db;
    $xpath = new DomXPath($doc);
    $nodesChapterId = $xpath->query('//*[@id="yw2"]/div[5]/span');
    $nodesChapterNames = $xpath->query('//*[@id="featured-chapters"]/div[2]/h3/a');
    $nodesChapterLinks = $xpath->query('//*[@id="featured-chapters"]/div[2]/h3/a/@href');
    $nodesChapterImage = $xpath->query('//*[@id="featured-chapters"]/div[1]/a/img/@src');
    $nodesChapterLocation = $xpath->query('//*[@id="featured-chapters"]/div[2]/div[1]/em');      
    $nodesChapterNumOfMembers = $xpath->query('//*[@id="featured-chapters"]/div[2]/div[2]/div[1]/span[1]');  
    $nodesChapterNumOfEvents = $xpath->query('//*[@id="featured-chapters"]/div[2]/div[2]/div[2]/span[1]'); 
   
    foreach($doc->getElementsByTagName('a') as $link) 
    {
        if($link->textContent == "Next >")
        {
            $nextPage = "multigp.com" . $link->getAttribute('href');
        }    
        
        if($link->textContent == "< Previous")
        {
            $prevPage = "multigp.com" . $link->getAttribute('href');
            $currentPage = filter_var($link->getAttribute('href'), FILTER_SANITIZE_NUMBER_INT);
            $currentPageNum = $currentPage++;               
        }   
        
        if($link->nodeValue == "Last >>")
        {
            $lastPageNum = filter_var($link->getAttribute('href'), FILTER_SANITIZE_NUMBER_INT);            
        }   
    }
     
    for($i = 0; $i < $nodesChapterNames->count();$i++)
    {    
        $chapterId = mysqli_real_escape_string($db,$nodesChapterId->item($i)->textContent);
        $chapterName = mysqli_real_escape_string($db,$nodesChapterNames->item($i)->nodeValue);
        $chapterEventsHeld = mysqli_real_escape_string($db,$nodesChapterNumOfEvents->item($i)->textContent);
        $chapterMemberTotal = mysqli_real_escape_string($db,$nodesChapterNumOfMembers->item($i)->textContent);
        $chapterLocation = mysqli_real_escape_string($db,$nodesChapterLocation->item($i)->textContent);
        $chapterURL = mysqli_real_escape_string ($db, "multigp.com" . $nodesChapterLinks->item($i)->textContent);
        $chapterImageURL = mysqli_real_escape_string($db, $nodesChapterImage->item($i)->textContent);
       
        $sql = "INSERT INTO `chapters`(`id`, `name`, `events`, `members`, `location`, `mgpurl`, `image`)"
                ."VALUES ('{$chapterId}', '{$chapterName}', '{$chapterEventsHeld}','{$chapterMemberTotal}','{$chapterLocation}','{$chapterURL}','{$chapterImageURL}')" 
                ."ON DUPLICATE KEY UPDATE `id`='{$chapterId}', `name`='{$chapterName}', `events`='{$chapterEventsHeld}', `members`='{$chapterMemberTotal}', `location`='{$chapterLocation}', `mgpurl`='{$chapterURL}', `image`='{$chapterImageURL}'";
        if(mysqli_query($db, $sql))
        {
            echo "Records inserted successfully.  $chapterURL <br/>";
        } 
        else
        {
            echo "ERROR: Could not able to execute $sql. <br/>" . mysqli_error($db). "<br/>";
            mysqli_close($db);
        }
        echo $nodesChapterId->item($i)->nodeValue . "<br>" . $nodesChapterNames->item($i)->nodeValue . "<br>" . "multigp.com" . $nodesChapterLinks->item($i)->textContent . "<br>" . $nodesChapterImage->item($i)->textContent . "<br>" . $nodesChapterLocation->item($i)->textContent . "<br>" . $nodesChapterNumOfMembers->item($i)->textContent . "<br>" . $nodesChapterNumOfEvents->item($i)->textContent. "<br><br><br>"; 
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