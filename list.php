<?php

function sendMail($to, $title, $content) {

    //PHPMailer Object
    $mail = new PHPMailer;

    //From email address and name
    $mail->From       = "pokeuniv@gmail.com";
    $mail->FromName   = "dooooooduo";
    foreach($to as $v) {
        $mail->addAddress($v);
    }
    $mail->addReplyTo("pokeuniv@gmail.com", "Reply");
    $mail->Subject    = $title;
    $mail->Body       = $content;
    $mail->IsSMTP();
    $mail->SMTPDebug  = 1; // debugging: 1 = errors and messages, 2 = messages only
    $mail->SMTPAuth   = true; // authentication enabled
    $mail->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED for Gmail
    $mail->Host       = "smtp.gmail.com";
    $mail->Port       = 465; // or 587
    $mail->Username   = "";
    $mail->Password   = "";

    if(!$mail->send()) {
        return "Mailer Error: " . $mail->ErrorInfo;
    } else {
        return "Message has been sent successfully";
    }
}

// Call set_include_path() as needed to point to your client library.
require_once '../google-api-php-client/src/Google/autoload.php';
require_once '../google-api-php-client/src/Google/Client.php';
require_once '../google-api-php-client/src/Google/Service/YouTube.php';
require_once "vendor/autoload.php";
session_start();

/*
 * You can acquire an OAuth 2.0 client ID and client secret from the
 * Google Developers Console <https://console.developers.google.com/>
 * For more information about using OAuth 2.0 to access Google APIs, please see:
 * <https://developers.google.com/youtube/v3/guides/authentication>
 * Please ensure that you have enabled the YouTube Data API for your project.
 */
$OAUTH2_CLIENT_ID = '173491602289-gar0bre37idhrtdnpci9lhm0rtd2hnis.apps.googleusercontent.com';
$OAUTH2_CLIENT_SECRET = 'NZG13biXHMAk2buIx5EPSuLQ';

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = 'http://localhost:80/youtube/list.php';
$client->setRedirectUri($redirect);
$client->setAccessType('offline');

// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);

if (isset($_GET['code'])) {
    if (strval($_SESSION['state']) !== strval($_GET['state'])) {
        die('The session state did not match.');
    }

    $client->authenticate($_GET['code']);
    $_SESSION['token'] = $client->getAccessToken();
    header('Location: ' . $redirect);
}

if (isset($_SESSION['token'])) {
    $client->setAccessToken($_SESSION['token']);
}

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
    $response = [];
    try {
        // Call the channels.list method to retrieve information about the
        // currently authenticated user's channel.
        $channelsResponse = $youtube->channels->listChannels('contentDetails', array(
            //'forUsername' => 'NintendoTWofficial',
            'id' => 'UCLl3d2fFXyh6V4A2hia25Kg,UCFctpiB_Hnlk3ejWfHqSm6Q,UC2OGLHkY4XTXFUpdWkaNmTA,UC_SI1j1d8vJo_rYMV5o_dRg,UCKy1dAqELo0zrOtPkf0eTMw,UCGIY_O-8vW4rfX98KlMkvRg',
        ));

        $listpath = '_list';
        $old_videos = json_decode(file_get_contents($listpath) ?? '{}', TRUE) ?? [];
        $videos = $new_videos = [];

        foreach ($channelsResponse['items'] as $channel) {
            // Extract the unique playlist ID that identifies the list of videos
            // uploaded to the channel, and then call the playlistItems.list method
            // to retrieve that list.
            $uploadsListId = $channel['contentDetails']['relatedPlaylists']['uploads'];

            $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet', array(
                'playlistId' => $uploadsListId,
                'maxResults' => 6
            ));

            foreach ($playlistItemsResponse['items'] as $playlistItem) {
                $is_old = false;
                $v = [
                    'title'   => $playlistItem['snippet']['title'], 
                    'id'      => $playlistItem['snippet']['resourceId']['videoId'],
                    'channel' => $playlistItem['snippet']['channelTitle'],
                    'time'    => strtotime($playlistItem['snippet']['publishedAt'])
                ];
                if(in_array($v['channel'], ['IGN', 'Nintendo'], TRUE) && !preg_match('/(p|P)ok.mon/', $v['title'])) continue;
                foreach($old_videos as $old_video) {
                    if($old_video['id'] === $v['id']) {
                        $is_old = true;
                        break;
                    }
                }
                if(!$is_old) {
                    $new_videos[] = $v;
                    $mail = [
                        'to'    => ['pokeuniv@gmail.com'],
                        'title' => $v['title'],
                        'body'  => $playlistItem['snippet']['description'] . ' (https://www.youtube.com/watch?v=' . $v['id'] . ')'
                    ];
                    if($v['channel'] !== 'PIMPNITE') {
                        $mail['to'][] = '407199952@qq.com';
                    }
                    $response['mail_status'] = sendMail($mail['to'], $mail['title'], $mail['body']);
                }
                $videos[] = $v;
            }
        }

        $videos = array_merge($new_videos, $old_videos);

        $fp = fopen($listpath, 'w');
        fwrite($fp, json_encode($videos));
        fclose($fp);
        $response = array_merge($response, ['videos' => $videos]);

    } catch (Google_Service_Exception $e) {
        $response = ['error' => 'Service error: ' . $e->getMessage()];
    } catch (Google_Exception $e) {
        $response = [];
        if($client->isAccessTokenExpired()) {
            $state = mt_rand();
            $client->setState($state);
            $_SESSION['state'] = $state;
            $response['url'] = urldecode($client->createAuthUrl());
            if(!empty($_GET['debug'])) {
                echo $response['url'] . '<br>';
            }
        }
        $response['error'] = 'Client error: ' . $e->getMessage();
    }

    $_SESSION['token'] = $client->getAccessToken();
    
} else {
    $state = mt_rand();
    $client->setState($state);
    $_SESSION['state'] = $state;

    $authUrl = $client->createAuthUrl();
    $response = ['url' => $authUrl];
}

ob_start();
echo json_encode($response);
ob_end_flush();

?>
