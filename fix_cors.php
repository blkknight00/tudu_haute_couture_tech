<?php
$dir = __DIR__ . '/api/';
$files = scandir($dir);

$search1 = "header(\"Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With\");";
$replace1 = "header(\"Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token\");";

$search2 = "header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');";
$replace2 = "header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token');";

$search3 = "header(\"Access-Control-Allow-Headers: Content-Type\");";
$replace3 = "header(\"Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token\");";

foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $path = $dir . $file;
        $content = file_get_contents($path);
        
        if (strpos($content, $search1) !== false || strpos($content, $search2) !== false || strpos($content, $search3) !== false) {
            $content = str_replace($search1, $replace1, $content);
            $content = str_replace($search2, $replace2, $content);
            $content = str_replace($search3, $replace3, $content);
            file_put_contents($path, $content);
            echo "Updated CORS in $file\n";
        }
    }
}
echo "CORS update complete.";
