<?php
require_once __DIR__ . '/DoiExtractor.php';


$doiFilePath = __DIR__ . '/dois.json';

$dois = json_decode(file_get_contents($doiFilePath), true);

$dois = array_unique($dois);

foreach ($dois as $index => $doi) {
    $url = (new DoiExtractor($doi))->get();
    save($doi, $url);
    echo 'added ' . ($index + 1) . 'dois', PHP_EOL;
}

echo 'Finished', PHP_EOL;

function save($doi, $url)
{
    $urlFilePath = __DIR__ . '/urls.json';
    $urls = json_decode(file_get_contents($urlFilePath), true);
    $urls[$doi] = $url;
    $fp = fopen($urlFilePath, 'w');
    fwrite($fp, json_encode($urls, JSON_UNESCAPED_SLASHES));
    fclose($fp);
    unset($urls);
}
