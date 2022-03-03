<?php
echo basename($_SERVER['PHP_SELF'])."\n";
set_include_path(get_include_path() . PATH_SEPARATOR . '/srv/Progs/dtvcommon');
require_once 'DB/DB.inc.php';
require_once 'func.php';

function srchipconn($modnr,$conn,$file) {
    $lines = file($file);

    $flint=$fludp=$flport=$flvlan=$flname=0;

    foreach ($lines as $line_num => $line) {
	$line=trim($line);

        if ($line == "interface ip input $modnr/$conn")  { $flint=1;}

        if (strpos ($line, "!Interface") !== false)  { $flint=0; }

	if ($flint and strpos($line,"redundant-stream") !== false  and strpos($line,"input") !== false) {
    	    $tmp=explode(" ",$line);
    	    $red=$tmp[1];
#    	    $out[$modnr][$conn]["red$red"]=$tmp[4];
	    if (isset($tmp[3])) {$out["red$red"]=$tmp[3];}
	    if (isset($tmp[4])) {$out["red$red"]=$tmp[4];}
    	    }

	if ($flint and strpos($line,"udp") !== false ) {
    	    $tmp=explode(" ",$line);
#    	    $out[$modnr][$conn]['udp']=trim($tmp[1]);
    	    $out['udp']=trim($tmp[1]);
    	    $fludp=1;
	}
        
	if ($fludp and (preg_match('(\bport \b[0-9])',$line))) {
    	    $tmp=explode(" ",$line);
#    	    $out[$modnr][$conn]['port']=trim($tmp[1]);
    	    $out['port']=trim($tmp[1]);
    	    $flport=1;
	}

        if ($fludp and strpos($line,"payload-port vlan") !== false ) {
	    $tmp=str_replace("payload-port vlan ","",$line);
#    	    $out[$modnr][$conn]['vlan']=$tmp;
    	    $out['vlan']=$tmp;
    	    $flvlan=1;
	}

        if ($fludp and strpos($line,"description") !== false ) {
	    $tmp=str_replace("description","",$line);
#    	    $out[$modnr][$conn]['name']=$tmp;
    	    $out['name']=trim($tmp);
            $flname=1;
	}

        if ($flint and $line == "exit") {
	    $flint=$fludp=$flport=$flvlan=$flname=0;
	}

    }
return $out;
}

function srchserv($modnr,$submnr,$file) {
    $lines = file($file);

    $flint=$flinp=$flse=$flout=$flname=0;
    $scr=$ecmg[0]=$ecmg[1]=$ecmg[2]=$ecms[0]=$ecms[1]=$ecms[2]=$eccnt=0;	// scrambling

    foreach ($lines as $line_num => $line) {
	$line=trim($line);

#        if (strpos ($line, "interface qam output $modnr/1.$submnr") !== false)  { $flint=1; }
        if ($line == "interface qam output $modnr/1.$submnr") { $flint=1; }

        if (strpos ($line, "!Interface") !== false)  { $flint=0; }
	
	if ($flint and (preg_match('(\bservice \b[0-9])',$line))) {
	    $flse=1;
	    $tmp=explode(" ",$line);
    	    $servicenr=trim($tmp[1]);
	}

	if ($flse and (preg_match('(\binput \b[0-9])',$line))) {
    	    $flinp=1;
    	    $tmp=explode(" ",$line);
    	    $connector=$tmp[1];
    	    $sidin=intval(str_replace("!","",$tmp[3]));
    	    $name="\"";
    	    if (isset($tmp[4])) {$name.=$tmp[4];}
    	    if (isset($tmp[5])) {$name.=" ".$tmp[5];}
    	    if (isset($tmp[6])) {$name.=" ".$tmp[6];}
    	    if (isset($tmp[7])) {$name.=" ".$tmp[7];}
    	    $name.="\""; $name=trim($name);
	    if ($name=="\"(none)\"") {$name="\"\"";}
	}

    
	if ($flse and strpos($line,"output-sid") !== false ) {
    	    $flout=1;
    	    $tmp=explode(" ",$line);
    	    $sidout=trim($tmp[1]);
	}

    
	if ($flout and strpos($line,"manual-name") !== false ) {
    	    $tmp=explode(" ",$line);
    	    
    	    if ($tmp[0] != "no") { $name=trim(str_replace("manual-name","",$line)); }
	    $flname=1;
	}

	if ($flout and $line == "scramble") {
	    $scr=1;
	}

	if ($flname and preg_match('/^ecmg [0-9] stream [0-9]/', $line) ) {
	    $ecms[$eccnt]=explode(" ",$line)[1];	//sream (ibac,kappa,dexim)
	    $ecmg[$eccnt]=explode(" ",$line)[3];	//group (aka profile adult,discovery)
	    $eccnt++;
    	}



#	if ($flint and $flinp and $flse and $flout and $flname) {
#	if ($flse and strpos($line,"exit") !== false ) {
	if ($flse and $line == "exit") {
	    $service[$servicenr]['connector'] =$connector;
	    $service[$servicenr]['sidin']     =$sidin;
	    $service[$servicenr]['sidout']    =$sidout;
	    $service[$servicenr]['name']      =trim($name);
	    $service[$servicenr]['scr']       =$scr;
	    $service[$servicenr]['ecmg1']     =$ecmg[0];
	    $service[$servicenr]['ecmg2']     =$ecmg[1];
	    $service[$servicenr]['ecmg3']     =$ecmg[2];
	    $service[$servicenr]['ecms1']     =$ecms[0];
	    $service[$servicenr]['ecms2']     =$ecms[1];
	    $service[$servicenr]['ecms3']     =$ecms[2];

	    $flinp=$flse=$flout=$flname=0;
	    $scr=$ecmg[0]=$ecmg[1]=$ecmg[2]=$ecms[0]=$ecms[1]=$ecms[2]=$eccnt=0;	// scrambling
	}

    }
if (isset($service)) {return $service;} else {return 0;}
}

$db=dbiopen('dtv');
mysqliutf8($db);

mysqli_query($db,'TRUNCATE TABLE OutQamServ;');

$q=mysqli_query($db,"select * from OutQam order by lum,module,smod");
while ($r=mysqli_fetch_assoc($q)) {

    $lum=$r['lum'];
    $fnb=glob("/home/peca/upload/config/lumcfg/".$r['lum']."*.cfg")[0];

    $module=$r['module'];
    $smod=$r['smod'];
    $ser=srchserv($module,$smod,$fnb);

#    if ($lum == "C2-NCr" ) { print_r($ser); }

    if ($ser != 0) {
	foreach ($ser as $key => $val) {
	    if (!mysqli_num_rows(mysqli_query($db,"select * from OutQamServ where lum='$lum' and module='$module' and smod='$smod' and snr='$key'"))) {
		mysqli_query($db,"insert into OutQamServ set lum='$lum', module='$module',smod='$smod',snr='$key'");
	    }
	    mysqli_query($db,"update OutQamServ set 
	    ecmg1='".$val['ecmg1']."',ecmg2='".$val['ecmg2']."',ecmg3='".$val['ecmg3']."',
	    ecms1='".$val['ecms1']."',ecms2='".$val['ecms2']."',ecms3='".$val['ecms3']."',scr='".$val['scr']."',
	    red0='".$val['connector']."',sidin='".$val['sidin']."',sidout='".$val['sidout']."',name='".$val['name']."'
	    where lum='$lum' and module='$module' and smod='$smod' and snr='$key'" );
#	    mysqli_query($db,"update OutQamServ set con='".$val['connector']."' where lum='$lum' and module='$module' and smod='$smod' and snr='$key'" );
	    }
    }
}


$q=mysqli_query($db,"select * from OutQamServ order by lum,module,red0");

while ($r=mysqli_fetch_assoc($q)) {

    $lum=$r['lum'];
    $fnb=glob("/home/peca/upload/config/lumcfg/".$r['lum']."*.cfg")[0];

    $module=$r['module'];
    $red0=$r['red0'];


    $out=srchipconn($module,$red0,$fnb);

    $qm="";
    if (isset($out['red1'])) { $qm="set red1='".$out['red1']."'"; }
    if (isset($out['red2'])) { $qm.=", red2='".$out['red2']."'"; }

    if ($qm != "") { mysqli_query($db,"update OutQamServ $qm where lum='$lum' and module='$module' and red0='$red0'"); }
}

dbiclose($db);
?>
