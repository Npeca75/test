#!/usr/bin/php
#
<?php
include '../dtvcommon/func.php';
include '../dtvcommon/func_conn.php';

$db = dbiopen('dtv');
mysqliutf8($db);

$udp = readline('Old IP: ? ');
if (!filter_var($udp, FILTER_VALIDATE_IP)) {
    exit;
}

$port = readline('Old Port: [5000] ? ');
if (intval($port) < 1000 or intval($port) > 50000) {
    $port = 5000;
}

$q = mysqli_query($db, "select * from OutQamInIp where udp='".$udp."' and port='".$port."' order by name,lum");

$cnt = 0;

while ($r = mysqli_fetch_assoc($q)) {
    $cnt++;

    $l = $luminato[$cnt] = $r['lum']; //for ex "C0-Bec"
    $m = $module[$cnt] = $r['module'];
    $c = $con[$cnt] = $r['con'];

    $nin = $r['name'];  //old input name
    $v = $r['vlange'];  //old vlan

    $q2 = mysqli_query($db, "select * from OutQamServ where lum='$l' and red0='$c' and module='$m'");
    $r2 = mysqli_fetch_assoc($q2);

    $nout = trim(str_replace('"', '', $r2['name'])); //service output name
    $snr[$cnt] = $r2['snr'];                             //service nr
    $smod[$cnt] = $r2['smod'];                           //service submodule
    $sidin = $r2['sidin'];
    if (!$nout) {
        $flred[$cnt] = 1;
    } else {
        $flred[$cnt] = 0;
    }

    $lname = explode('-', $l)[0];
    $lcity = explode('-', $l)[1];
    $rlum = mysqli_fetch_assoc(mysqli_query($db, "select * from Devices where devType='Luminato' and name='$lname' and city like '$lcity%'"));

    $ip[$cnt] = $rlum['ip'];   //luminato IP
    $sw[$cnt] = $rlum['sw'];   //luminato version
    $prompt[$cnt] = $rlum['name'].'-'.$rlum['city']; //luminato prompt

    echo "\nLum:\t".$luminato[$cnt]."\t".$ip[$cnt];
    echo "\nMod:\t".$module[$cnt];
    echo "\nSubM:\t".$smod[$cnt];
    echo "\nSNR:\t".$snr[$cnt];
    echo "\nCon:\t".$con[$cnt];
    echo "\nSIN:\t".$sidin;
    echo "\nNames:\t".$nin."\t".$nout;
    echo "\nIs redundancy:\t".$flred[$cnt];
    echo "\n----------------\n";
}

if (!$cnt) {
    echo "\n No match found !!! \n";
    exit;
}

$nip = readline('New IP: ? ');
if (!filter_var($nip, FILTER_VALIDATE_IP)) {
    exit;
}

$nport = readline('New Port: [5000] ? ');
if ($nport == '') {
    $nport = 5000;
}
if (intval($nport) < 1000 or intval($nport) > 50000) {
    exit;
}

$tmp = readline("New Vlan: [$v] ? ");
if ($tmp == '') {
    $nvlan = trim($v);
} else {
    $nvlan = trim($tmp);
}

$tmp = readline("Input Name: [$nin] ? ");
if ($tmp == '') {
    $nin = trim($nin);
} else {
    $nin = trim($tmp);
}

$tmp = readline('Input SID: [1000] ? ');
if ($tmp == '') {
    $sid = '1000';
} else {
    $sid = intval($tmp);
}

$tmp = readline("Output Name: [$nout] ? ");
if ($tmp == '') {
    $nout = trim($nout);
} else {
    $nout = trim($tmp);
}
$nout = '"'.$nout.' "';

$tmp = readline('Type: hd/[sd]/r ? ');
$type = 'tv';
if ($tmp == 'hd') {
    $type = 'hdtv';
}
if ($tmp == 'r') {
    $type = 'radio';
}

$line = readline("IP:$nip | UDP:$nport | Vlan:$nvlan | SID:$sid | Type:$type | nIn:$nin | nOut:$nout ?");

if ($line != 'y') {
    echo "\nEXIT\n";
    dbiclose($db);
    exit;
}

do {
    $h = _connect($sw[$cnt], $ip[$cnt], $prompt[$cnt]);
    if (!$h) {
        echo "\n empty handler\n";
        $cnt--;
        continue;
    }

    if (_write($sw[$cnt], $h, 'end', $prompt[$cnt]) == 1) {
        unset($h);
        $cnt--;
        continue;
    } //test write

    _write($sw[$cnt], $h, 'configure', $prompt[$cnt]);

    _write($sw[$cnt], $h, 'interface ip input '.$module[$cnt].'/'.$con[$cnt], $prompt[$cnt]); //select input interface
    _write($sw[$cnt], $h, 'shutdown', $prompt[$cnt]); //shutdown input
    _write($sw[$cnt], $h, "udp $nip", $prompt[$cnt]); //multicast address
    _write($sw[$cnt], $h, "port $nport", $prompt[$cnt]); //multicast port

    if (!$flred[$cnt]) {
        _write($sw[$cnt], $h, "payload-port vlan $nvlan", $prompt[$cnt]); //vlan ID
        $vlsql = "vlange='".$vlan."', ";
    } else {
        $vlsql = ''; //it is RED, don't touch vlan
    }

    _write($sw[$cnt], $h, "description \"$nin\"", $prompt[$cnt]); //input description
    _write($sw[$cnt], $h, '', $prompt[$cnt]);
    sleep(2); //blank write
    _write($sw[$cnt], $h, 'no shutdown', $prompt[$cnt]); //turn ON input
    _write($sw[$cnt], $h, 'exit', $prompt[$cnt]);
    sleep(2); //interface exit
    mysqli_query($db, "update OutQamInIp set udp='$nip',port='$nport',$vlsql name='$nin' where module='".$module[$cnt]."' and con='".$con[$cnt]."' and lum='".$luminato[$cnt]."'");

    if (!$flred[$cnt]) {
        _write($sw[$cnt], $h, 'interface qam output '.$module[$cnt].'/1. '.$smod[$cnt], $prompt[$cnt]); //select output interface
        _write($sw[$cnt], $h, 'service '.$snr[$cnt], $prompt[$cnt]); //select service
        _write($sw[$cnt], $h, "type $type", $prompt[$cnt]); //type HD/SD
        _write($sw[$cnt], $h, 'input '.$con[$cnt]." sid $sid", $prompt[$cnt]); //connect input to output
        _write($sw[$cnt], $h, "manual-name $nout", $prompt[$cnt]); //set output name
        _write($sw[$cnt], $h, 'manual-provider-name ST_Cable encoding ISO-6937+', $prompt[$cnt]); //provider name
        mysqli_query($db, "update OutQamServ set name='$nout',sidin='$sid',type='$type' where module='".$module[$cnt]."' and red0='".$con[$cnt]."' and lum='".$luminato[$cnt]."'");
    }

    _write($sw[$cnt], $h, 'end', $prompt[$cnt]);  //configure exit
    _write($sw[$cnt], $h, 'exit', '');
    sleep(2);  //main exit

    $cnt--;
    unset($h);
} while ($cnt > 0);

dbiclose($db);
exit;

?>

