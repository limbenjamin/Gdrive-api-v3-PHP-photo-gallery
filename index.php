<?php

require __DIR__ . '/vendor/autoload.php';

if( ini_get('allow_url_fopen') ) {
        //die('allow_url_fopen is enabled. file_get_contents should work well');
        function get_url_content($request)
                {
                        return file_get_contents($request);
                }
} else {
        //die('allow_url_fopen is disabled. file_get_contents would not work');
        function get_url_content($request)
                {
                $ch = curl_init($request);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                return curl_exec($ch);
                }
        }

// global consts
$defaultFolderId = ''; // id of photos root dir
$defaultPath = '/photos/'; // default path of this gdrive_nanogallery.php file
$defaultFolderImageUrl = ''; // url of png or jpg for default thumbnail
$showThumbnail = true;
$thumbnailHeight = 156;
$imageHeight = 700;
$imageHeightXS = 400;
$imageHeightSM = 600;
$imageHeightME = 750;
$imageHeightLA = 1000;
$imageHeightXL = 1400;

function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Drive API PHP Quickstart');
    $client->setScopes(Google_Service_Drive::DRIVE_METADATA_READONLY);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

function retrieveFilesArray($folderId){

  $client = getClient();
  $service = new Google_Service_Drive($client);

  // Print the names and IDs for up to 1000 files.
   $optParams = array(
          'pageSize' => 1000,
          'fields' => "nextPageToken, files(id,name,mimeType)",
          'q' => "'".$folderId."' in parents and trashed = false",
          'supportsAllDrives' => True, 
          'includeItemsFromAllDrives' => True
          );
    $results = $service->files->listFiles($optParams);

  $fileArray = array();
  $i = 0;

  foreach ($results->getFiles() as $file) {
    $entryArr = array("id" => $file->id, "name" => $file->name, "mimeType" => $file->mimeType);
    $fileArray[$i] = $entryArr;
    $i++;
  }

  return $fileArray;

}

function retrieveOneFileArray($folderId){

  $client = getClient();
  $service = new Google_Service_Drive($client);

  // Print the names and IDs for up to 1 files.
   $optParams = array(
          'pageSize' => 1,
          'fields' => "nextPageToken, files(id,name,mimeType)",
          'q' => "'".$folderId."' in parents and trashed = false",
          'supportsAllDrives' => True, 
          'includeItemsFromAllDrives' => True
          );
    $results = $service->files->listFiles($optParams);

  $fileArray = array();
  $i = 0;

  foreach ($results->getFiles() as $file) {
    $entryArr = array("id" => $file->id, "name" => $file->name, "mimeType" => $file->mimeType);
    $fileArray[$i] = $entryArr;
    $i++;
  }

  return $fileArray;

}

function filterByMimeType($fileArray, $mimeType){
  // returns array of files only with mimeType
  $filteredFileArray = [];
  foreach($fileArray as $file){
    if (strpos($file['mimeType'], $mimeType) !== false){
      $filteredFileArray[] = $file;
    }
  }

  return $filteredFileArray;
}

function getfileIds($fileArray){
  // returns array of all IDs from input array
  $imageIdsArray = [];
  foreach($fileArray as $file){
    $imageIdsArray[] = $file['id'];
  }
  return $imageIdsArray;
}


function orderImagesByTime($imageArray){
  // returns array of images sorted by the date of picture is taken and name
  $sortingArray = array();
  foreach ($imageArray as $key => $image) {
    $sortingArray["time"][$key] = $image["imageMediaMetadata"]["time"];
    $sortingArray["name"][$key] = $image["name"];
  }
  array_multisort($sortingArray["time"], SORT_ASC, $sortingArray["name"], SORT_ASC, $imageArray);
  return $imageArray;
}

function retrieveImageIds($folderId){
  // returns all images in folder
  $fileArray = retrieveFilesArray($folderId);
  $filteredFileArray = orderImagesByTime(filterByMimeType($fileArray, "image/"));
  return getfileIds($filteredFileArray);
}

function retrieveOneImageId($folderId){
  // returns only one image for the folder (used for thumbnails)
  $fileArray = retrieveOneFileArray($folderId);
  $filteredFileArray = filterByMimeType($fileArray, "image/");

  return $filteredFileArray;
}

function retrieveSubfolderArray($folderId){
  // returns array of all subfolders in folder
  $fileArray = retrieveFilesArray($folderId);
  $subfolderArray = filterByMimeType($fileArray, "folder");
  return $subfolderArray;
}

function retrieveFolderName($folderId){
  $client = getClient();
  $service = new Google_Service_Drive($client);
  // Print the name for folder.
   $optParams = array(
          'pageSize' => 1000,
          'fields' => "nextPageToken, files(id, name)",
          'q' => "mimeType = 'application/vnd.google-apps.folder' and trashed = false",
          'supportsAllDrives' => True, 
          'includeItemsFromAllDrives' => True
          );
    $results = $service->files->listFiles($optParams);

  foreach ($results->getFiles() as $folder) {
    if ($folder->id == $folderId){
      return $folder['name'];
    }
  }
}


// process variables from GET
if($_GET['folderId'] != ""){
  $folderId = $_GET['folderId'];
}
else{
  $folderId = $defaultFolderId;
}

if($_GET['homeId'] != ""){
  $homeId = $_GET['homeId'];
}
else{
  $homeId = $folderId;
}

// retrive data
$folderName = retrieveFolderName($folderId);
$subfolderArray = retrieveSubfolderArray($folderId);
$imageIds = retrieveImageIds($folderId);

// START OF HTML ----------------------------------------------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Photo Gallery</title>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <!-- Bootstrap icons-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Core theme CSS (includes Bootstrap)-->
    <link href="css/styles.css" rel="stylesheet" />
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <!-- Add jQuery library (MANDATORY) -->
    <script type="text/javascript" src="nanogallery/third.party/jquery-1.7.1.min.js"></script>
    <!-- Add nanoGALLERY plugin files (MANDATORY) -->
    <link href="nanogallery/css/nanogallery.css" rel="stylesheet" type="text/css">
    <link href="nanogallery/css/themes/light/nanogallery_light.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="nanogallery/jquery.nanogallery.js"></script>
  </head>

  <body>
      <!-- Page Content-->
      <section class="pt-4">
          <div class="container px-lg-5" style="min-height: 80vh">
              <!-- Page Features-->
              <div class="roundcontent row gx-lg-5" style="width: 96%; margin: 2%; margin-top: 10px; padding: 10px;">
                <?php
                if($homeId !== $folderId){
                  $folderName = retrieveFolderName($folderId);
                  echo "<h4 class='title'>".$folderName."</h4>";
                  echo "<a href='".$defaultPath."?folderId=".$homeId."'><div class='back'><< Back</div></a><br>";
                }else{
                  echo "<h4 class='title'>Photo Gallery</h4>";
                }
                foreach($subfolderArray as $subfolder){
                          echo "<div class='ngal_album col-lg-6 col-xxl-4 mb-5' style='display: inline-grid; justify-content: center; border: 0.1em black; border-style: solid; border-radius: 1em; padding: 1em; margin: 1em !important;'>";
                          echo "<a href='".$defaultPath."?folderId=".$subfolder['id']."&homeId=".$homeId."'>";
                          $thumbnailId = retrieveOneImageId($subfolder['id'], $apiKey)[0]["id"]; 
                          $thumbnailWidth = 200;
                          if (empty($thumbnailId)) {
                                  $thumbnailSrc = $defaultFolderImageUrl;
                          } else {
                                  $thumbnailSrc = "https://drive.google.com/thumbnail?authuser=0&sz=w".$thumbnailWidth."&id=".$thumbnailId;
                          }
                          if (showThumbnail){
                                  echo "<div class='ngal_foto'><img src='".$thumbnailSrc."' width='".$thumbnailWidth."'></div>";
                          }
                  echo "<div class='ngal_content' style='display: inline-grid; justify-content: center;'>";     
                  echo "<div class='album-name'><a href='".$defaultPath."?folderId=".$subfolder['id']."&homeId=".$homeId."'>".$subfolder["name"]."</div></a>";
                  echo "<br />";
                  echo "</div>";
                  echo "</a>";
                  echo "</div>";
                }
                ?>
                <div id="nanoGalleryWrapperDrive"></div>
              </div>
          </div>
      <footer class="py-5 bg-custom-dark">
            <div class="container"><p class="m-0 text-center text-white">Copyright &copy; 2022</p></div>
        </footer>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
  </body>
</html>


<!--JAVASCRIPT CODE-->
<script type="text/javascript">

  jQuery(document).ready(function () {
    jQuery("#nanoGalleryWrapperDrive").nanoGallery({
      items: [
            <?php //PHP code -----------------------------------------------------------------------------------
            foreach($imageIds as $id) {
              echo "{"."\r\n";
              echo "  src: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeight."&id=".$id."',\r\n";
              echo "  srcXS: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightXS."&id=".$id."',\r\n";
              echo "  srcSM: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightSM."&id=".$id."',\r\n";
              echo "  srcME: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightME."&id=".$id."',\r\n";
              echo "  srcLA: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightLA."&id=".$id."',\r\n";
              echo "  srcXL: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$imageHeightXL."&id=".$id."',\r\n";
              echo "  srct: 'https://drive.google.com/thumbnail?authuser=0&sz=h".$thumbnailHeight."&id=".$id."',\r\n";
              echo "  title: '',\r\n";
              echo "  description : ''\r\n";
              echo "},\r\n";
            }
            // END OF PHP -----------------------------------------------------------------------------------------
            ?>
        ],
      thumbnailWidth: 'auto',
      thumbnailHeight: 145,
      theme: 'light',
      colorScheme: 'none',
      thumbnailHoverEffect: [{ name: 'labelAppear75', duration: 300 }],
      thumbnailGutterWidth : 0,
      thumbnailGutterHeight : 0,
      slideshowDelay: 5000,
      i18n: { thumbnailImageDescription: 'Zoom', thumbnailAlbumDescription: 'Open album' },
      thumbnailLabel: { display: true, position: 'overImageOnMiddle', align: 'center' }

    });
  });
</script>