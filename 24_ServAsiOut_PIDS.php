<?php
echo basename($_SERVER['PHP_SELF'])."\n";

include '/srv/Progs/dtvcommon/func.php';

$db=dbiopen('dtv');
mysqliutf8($db);

mysqli_query($db,'TRUNCATE TABLE OutAsiPID;');

$q=mysqli_query($db,"select * from Luminatos where type='asi' and subt='out' order by name");

$flser=0;
$flsid=0;
$flcomp=0;
$flpid=0;

while ($r=mysqli_fetch_assoc($q)) {

    $lum=$r['name']."-".substr($r['city'],0,3);
    $fnb="datalum/".$r['name']."-".$r['city'];

    $lines = file($fnb.".srv");

    foreach ($lines as $line_num => $line) {
	$line=trim($line);

        if (strpos ($line, "Service name encoding") !== false )  { 
    	    $flser=0;
    	    $flsid=0;
	    goto nlin;
    	}
    	
        if (strpos ($line, "SERVICES") !== false)  {
	    $flser=1;
	    goto nlin;
	}

        if ($flser and !$flsid and strpos ($line, "|") !== false)  {
            $tmp=explode("|",$line);
	    if (trim($tmp[0])=="SID") {
	        $flsid=1;
	        goto nlin;
	    }
	}

        if ($flser and $flsid and strpos ($line, "|") !== false)  {
            $tmp=explode("|",$line);
	    $sid=trim($tmp[0]);
	    $type=trim($tmp[2]);
	    $tmp2=explode("/",$tmp[1]);
	    $mod=trim($tmp2[0]);
	    $tmp3=explode(".",$tmp[1]);
	    $smod=trim($tmp3[1]);
	    mysqli_query($db,"update OutAsiServ set type='$type' where lum='$lum' and smod='$smod' and sidout='$sid'");
    	    $flser=0;
    	    $flsid=0;
#    	    echo $lum."\n";
	}    


        if (strpos ($line, "Service provider") !== false )  { 
    	    $flcomp=0;
    	    $flpid=0;
	    goto nlin;
    	}

        if (strpos ($line, "COMPONENTS") !== false)  {
	    $flcomp=1;
	    goto nlin;
	}

        if ($flcomp and !$flpid and strpos ($line, "|") !== false)  {
            $tmp=explode("|",$line);
	    if (trim($tmp[0])=="PID") {
	        $flpid=1;
	        goto nlin;
	    }
	}

        if ($flcomp and $flpid and strpos ($line, "|") !== false)  {
            $tmp=explode("|",$line);
	    $pid=trim($tmp[0]);
	    $type=trim($tmp[1]);

            $type=str_replace("27 (27)","V: H264",$type);
            $type=str_replace("Video (2)","V: MPEG2",$type);
            $type=str_replace("36 (36)","V: HEVC",$type);
            $type=str_replace("Audio (3)","A: MP2",$type);
            $type=str_replace("Audio (4)","A: MP2",$type);
            $type=str_replace("15 (15)","A: AAC",$type);
            $type=str_replace("17 (17)","A: AC3-L",$type);
            $type=str_replace("AC-3 (6)","A: AC3",$type);
            $type=str_replace("Teletext (6)","TTX",$type);
            $type=str_replace("Subtitle (6)","SubT",$type);

            $lng=str_replace(",",";",trim($tmp[6]));
	    mysqli_query($db,"insert into OutAsiPID set lum='$lum',module='$mod',smod='$smod',sidout='$sid',pid='$pid',type='$type',lng='$lng'");
#    	    $flcomp=0;
#    	    $flpid=0;
	}    

nlin:
    }
}
dbiclose($db);

