<?php
header ("Content-type: image/png");
$fontType=10;
$fontSize=90;
if($_GET["c"]){$captchaa = strrev(base64_decode(urldecode($_GET["c"])));}
else{$captchaa ="Undefined";}
$width=277;
$height=37;
$im = imagecreate ($width,$height) or die ("Cannot Initialize new GD image stream");
$bgColour = imagecolorallocate ($im,  225, 225, 232);
$textColour = imagecolorallocate ($im, 5, 5, 5);
imagesetthickness($im,3);
imagestring ($im, $fontType, 111.5,11, $captchaa, $textColour); 
imagepng ($im); 
?>
