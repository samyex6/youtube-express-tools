# youtube-express-tools

Just some tools made in Youtube API used to monitor news.

Dependencies: Google API, PHPMailer

Currently it only has the function of listing newest videos from specific channels (PIMPNITE, ポケモン公式YouTubeチャンネル, IGN, CHT Nintendo, pokemon, Nintendo).

When a new video is detected, send an email to the specified email addresses.

For Nintendo and IGN, it'll filter away all the videos which do not contain Pok(e|é)mon.

It will produce a file called __list as the database. (Which contains a serialized array)

Default checking timeout: 22 seconds.

Note that the Google API library is not contained in this repo. (also by default it should be located in the previous level directory)
