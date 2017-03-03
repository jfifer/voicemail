<?php
require_once('./dbconn.php');

list($portal_link, $portal_handle) = dbConnect("localhost", "root", "root", "bi_new");
$count = 0;
$handle = fopen("fs.csv", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        //print_r($line);
        $serverInfo = explode(",", $line);
        
        $ip = trim($serverInfo[0]);
        $host = trim($serverInfo[1]);
        $user = trim($serverInfo[2]);
        $pass = trim($serverInfo[3]);
        if($pass === "siX3ci0cx0re"){ 
           $pass = "siX3ci0vc0re";
        }
        print_r($host." ".$ip."\n"); 
        $conn = ssh2_connect($ip, 22);
        ssh2_auth_password($conn, $user, $pass);

        $stream = ssh2_exec($conn, 'cd /var/spool/asterisk/voicemail; find . -type f -name "*.txt"');
        stream_set_blocking($stream, true);
        $stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
        $fileList = stream_get_contents($stream_out);
        $fileList = explode(PHP_EOL, $fileList);

        foreach($fileList as $file) {
           if(isset($file) && !empty($file)) {
              print_r($file."\n\n");
              $cat_stream = ssh2_exec($conn, 'cd /var/spool/asterisk/voicemail; cat '.$file);
              stream_set_blocking($cat_stream, true);
              if($cat_stream_out = ssh2_fetch_stream($cat_stream, SSH2_STREAM_STDIO)) {
                 $contents = explode("[message]", stream_get_contents($cat_stream_out));
                 $vmData = explode(PHP_EOL, $contents[1]);
                 $dataset = array();
                 foreach($vmData as $set) {
                    $set = explode("=", $set);
                    if(isset($set[1])) {
                       $dataset[$set[0]] = $set[1];
                    } else {
                       $dataset[$set[0]] = null;
                    }
                 } 

                 $newdate = strtotime($dataset['origdate']);
                 $newdate = date("Y-m-d", $newdate);
                 $sql = "INSERT INTO vm_log_new VALUES ('" . $dataset['origmailbox'] . "', '" .
                        $dataset['context'] . "', '" . $dataset['macrocontext'] . "', '" .
                        $dataset['exten'] . "', '" . $dataset['priority'] . "', '" .
                        $dataset['callerchan'] . "', '" . $dataset['callerid'] . "', '" .
                        $newdate . "', '" . $dataset['origtime'] . "', '" .
                        $dataset['category'] . "', " . $dataset['duration'] . ", '" .
                        $host . "')";

                 $res = dbQuery($sql, $portal_link);
              }
           } else {
              break;
           }
        }
        $deleted_stream = ssh2_exec($conn, 'cd /var/tmp/; cat vm.log');
        stream_set_blocking($deleted_stream, true);
        if($deleted_stream_out = ssh2_fetch_stream($deleted_stream, SSH2_STREAM_STDIO)) {
          $contents = stream_get_contents($deleted_stream_out);
          $contents = explode("====\n;\n; Message Information file\n;\n[message]\n", $contents);
          $vmData = explode(PHP_EOL, $contents[1]);
          $dataset = array();
          foreach($vmData as $set) {
            $set = explode("=", $set);
            if(isset($set[1])) {
              $dataset[$set[0]] = $set[1];
            } else {
              $dataset[$set[0]] = null;
            }
          }

          $newdate = strtotime($dataset['origdate']);
          $newdate = date("Y-m-d", $newdate);
          $sql = "INSERT INTO vm_log_new VALUES ('" . $dataset['origmailbox'] . "', '" .
                        $dataset['context'] . "', '" . $dataset['macrocontext'] . "', '" .
                        $dataset['exten'] . "', '" . $dataset['priority'] . "', '" .
                        $dataset['callerchan'] . "', '" . $dataset['callerid'] . "', '" .
                        $newdate . "', '" . $dataset['origtime'] . "', '" .
                        $dataset['category'] . "', " . $dataset['duration'] . ", '" .
                        $host . "')";
          $res = dbQuery($sql, $portal_link);

        }
        $count++;
    }

    fclose($handle);
} else {
    // error opening the file.
} 
?>

