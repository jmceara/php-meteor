<?php
$vcducount = 0;


$vcdu_packet_size = 882;
$vcdu_header_size = 10;

$mpdus = null;
$vcdus = null;


// $fh = fopen ("2015_06_09_15_16_10.vcdu", "rb");
$fh = fopen ( "2015_06_11_14_51_10.vcdu", "rb" );

$cur = 1;

$header = fread ( $fh, filesize ( "2015_06_11_14_51_10.vcdu" ) );

$res = @unpack ( "C*", $header );

fclose ( $fh );

if (sizeof ( $res ) < 10) {
	die ( "" );
}

while ( $cur < sizeof ( $res ) ) {

	$vcdu_header1 = $res[$cur];
	$vcdu_header2 = $res[$cur+1];
	$vcdu_header3 = $res[$cur+2];
	$vcdu_header4 = $res[$cur+3];
	$vcdu_header5 = $res[$cur+4];
	$vcdu_header6 = $res[$cur+5];	
	$insert_zone1 = $res[$cur+6];
	$insert_zone2 = $res[$cur+7];	
	$mpdu_header1 = $res[$cur+8];
	$mpdu_header2 = $res[$cur+9];
	
	
	if ($vcdu_header2 != 5) {
		echo "Abortando pacote!";
		exit ();
	}
	
	$hedpos = (($mpdu_header1 << 8) + $mpdu_header2) & 0x7FF;
	$flag = ($mpdu_header1 >> 3) != 0;
	
	//echo "VCDU=$vcducount flag=$flag Headpos: " . $hedpos . "\n";
	
	// Populate VCDU's object
	$vcdus[$vcducount]['flag'] = $flag;
	$vcdus[$vcducount]['hedpos'] = $hedpos;
	
	
	// Calculate position for start and end of MPDU packets
	$tmp1 = ($cur + $vcdu_header_size) + $hedpos;
	$tmp2 = $cur + ($vcdu_packet_size + $vcdu_header_size);	
	// Debug information
	if ($vcducount==0) {
	//	echo "\nInicio: ".$tmp1;
	//	echo "\nFim: ".$tmp2."\n";
	}
		
	for ($i=$tmp1;$i<$tmp2;$i++) {
		$mpdus[$vcducount][] = $res[$i];
	}
	
	proccessMPDU($mpdus[$vcducount],$vcducount);
	
	// Increment VCDU count
	$vcducount ++;
	
	
	// Forward do proccess nest VCPDU
	$cur = $cur + ($vcdu_packet_size + $vcdu_header_size);
	
}


function proccessMPDU($mpdu,$vcdu) {
	global $vcdus;
	
	// Invalid or ampty MPDU
	if (sizeof($mpdu)<0) {
		return false;
	}
	
	$cur = 0;
	
	while ($cur<sizeof($mpdu)) {
	
		$APID1 = $mpdu[$cur];
		$APID2 = $mpdu[$cur+1];
		
		$CONTROL1 = $mpdu[$cur+2];
		$CONTROL2 = $mpdu[$cur+3];
		
		$PLEN1 = $mpdu[$cur+4];
		$PLEN2 = $mpdu[$cur+5];
	
		$PDUAPID = somaBinario(array($APID1,$APID2));
		$PDUCONTROL = somaBinario(array($CONTROL1,$CONTROL2));
		$PDULEN = somaBinario(array($PLEN1,$PLEN2));		
		$PDUDays = somaBinario(array($mpdu[$cur+6],$mpdu[$cur+7]));		
		$PDUMilisecs = somaBinario(array($mpdu[$cur+8],$mpdu[$cur+9],$mpdu[$cur+10],$mpdu[$cur+11]   ));		
		$PDUMicroseconds = somaBinario(array($mpdu[$cur+12],$mpdu[$cur+13]));
		
		$PDUScanHeader = somaBinario(array($mpdu[$cur+15],$mpdu[$cur+16]));
		$PDUSegmentHeader = somaBinario(array($mpdu[$cur+17],$mpdu[$cur+18],$mpdu[$cur+19]));
		
		
		//$PDUmilisecs = somaBinario(array( $mpdu  ));
		
		$indice = sizeof($vcdus[$vcdu]['packets']);
				
		$vcdus[$vcdu]['packets'][$indice]['APID'] = $PDUAPID & 0x7ff; 
		$vcdus[$vcdu]['packets'][$indice]['miliseconds'] = $PDUMilisecs;
		$vcdus[$vcdu]['packets'][$indice]['PACKETLEN'] = $PDULEN+1;
		$vcdus[$vcdu]['packets'][$indice]['SEQCOUNT'] = $PDUCONTROL & 0x3FFF;
		$vcdus[$vcdu]['packets'][$indice]['SECHEDFLAG'] = (($PDUAPID >> 11) & 1);
		$vcdus[$vcdu]['packets'][$indice]['SIZE'] = 6 + $PDULEN +1;
		$vcdus[$vcdu]['packets'][$indice]['SEQFLAG'] = ($PDUCONTROL >> 14);
		$vcdus[$vcdu]['packets'][$indice]['N_MCU'] = $mpdu[$cur+14];
		$vcdus[$vcdu]['packets'][$indice]['SCANHEADER'] = $PDUScanHeader;
		$vcdus[$vcdu]['packets'][$indice]['SEGMENTHEADER'] = $PDUSegmentHeader;
		
		$total_data_len = $PDULEN;
		for ($j=0;$j<$total_data_len;$j++) {
			$vcdus[$vcdu]['packets'][$indice]['DATA'][] = $mpdu[($cur+20)+$j];
		}
		
		 
		
		
		//if ($vcdu==0) {
		//	print_r($vcdus[$vcdu]['packets'][$cur]);
			
		//}
		
		$cur = $cur + 6 + $PDULEN+1;
		
		
	}
	
}


//print_r($packets[1]);

function somaBinario($valores) {
	
	$saida = "";

	for ($x=0;$x<sizeof($valores);$x++) {
		
		$tmp = decbin($valores[$x]);
		
		if (strlen($tmp)<8) {
			$total = 8 - strlen($tmp);
			for ($i=0;$i<$total;$i++) {
				$tmp = "0".$tmp;
			}
			
		}
		
		$saida = $saida . $tmp;
		
	}

	return bindec($saida);
	
}

// Debuga

$saida = "";

$apid64 = 0;
$apid65 = 0;
$apid66 = 0;
$apid67 = 0;
$apid68 = 0;
$apid69 = 0;

for ($x=0;$x<sizeof($vcdus);$x++) {
	if ($vcdus[$x]['flag']==1) {
		$flag = "true";
	} else {
		$flag = "false";
	}
	$saida = $saida . "VCDU=$x flag={$flag} hedpos={$vcdus[$x]['hedpos']}\n";
	for ($i=0;$i<sizeof($vcdus[$x]['packets']);$i++) {
		
		switch ($vcdus[$x]['packets'][$i]['APID']) {
			case "64":
				$apid64++;
				break;
				
			case "65":
				$apid65++;
				break;

			case "66":
				$apid66++;
				break;
				
			case "67":
				$apid67++;
				break;
				
			case "68":
				$apid68++;
				break;
				
			case "69":
				$apid69++;
				break;
				
		}
		
		$saida = $saida . "CHECKED APID:{$vcdus[$x]['packets'][$i]['APID']} SeqCOUNT:{$vcdus[$x]['packets'][$i]['SEQCOUNT']} PacketLEN:{$vcdus[$x]['packets'][$i]['PACKETLEN']} Size:{$vcdus[$x]['packets'][$i]['SIZE']} SecHedFlag:{$vcdus[$x]['packets'][$i]['SECHEDFLAG']} SeqFlag:{$vcdus[$x]['packets'][$i]['SEQFLAG']}\n";
	}
	
}

//echo nl2br($saida);
echo $saida;

function mostraDados($vcdu,$pdu) {
	
	global $vcdus;
	
	echo "\nAPID: ";
	echo $vcdus[$vcdu]['packets'][$pdu]['APID'];
	
	echo "\nseqCount: ";
	echo $vcdus[$vcdu]['packets'][$pdu]['SEQCOUNT'];
	
	
	
	echo "\nN_MCU: ";
	print_r($vcdus[$vcdu]['packets'][$pdu]['N_MCU']);
	
	echo "\nScanHeader: ";
	print_r($vcdus[$vcdu]['packets'][$pdu]['SCANHEADER']);
	
	echo "\nSegmentHeader: ";
	print_r($vcdus[$vcdu]['packets'][$pdu]['SEGMENTHEADER']);
	
	echo "\n\n";

}


mostraDados(7,3);

mostraDados(7,4);

mostraDados(8,0);

mostraDados(8,1);
/*
$final = null;
for ($x=0;$x<sizeof($vcdus[2]['packets'][0]['DATA']);$x++) {
	$final = $final . pack("C",$vcdus[2]['packets'][0]['DATA'][$x]);
}
*/
//header('Content-Type: image/jpeg;');

//$im = imagecreatefromstring($final);

//imagejpeg($im);
