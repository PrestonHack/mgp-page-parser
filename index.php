<?php
$nextPage;
$prevPage;
$lastPageNum = 2;
$currentPageNum;

for($i=0;$i < $lastPageNum; $i++)
{
    $html = file_get_contents('https://www.multigp.com/mgp/chapters?Chapter_page='.$i);
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    getChapterNodes($dom);
}
//not used
function getNumberOfChapters()
{
    $ele = $dom->getElementById("yw2")->firstChild->nextSibling->textContent;
    $str = substr($ele, 18);
    $numberOfChapters = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
    echo "<br>". $numberOfChapters;
}

function getChapterNodes($doc)
{
    global $nextPage,$prevPage,$lastPageNum,$currentPageNum;
    $xpath = new DomXPath($doc);
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
        echo $nodesChapterNames->item($i)->nodeValue . "<br>" . "multigp.com" . $nodesChapterLinks->item($i)->textContent . "<br>" . $nodesChapterImage->item($i)->textContent . "<br>" . $nodesChapterLocation->item($i)->textContent . "<br>" . $nodesChapterNumOfMembers->item($i)->textContent . "<br>" . $nodesChapterNumOfEvents->item($i)->textContent. "<br><br><br>"; 
    }
} 

function outerHTML($e) 
{
    $doc = new DOMDocument();
    $doc->appendChild($doc->importNode($e, true));
    return $doc->saveHTML();
}

?>