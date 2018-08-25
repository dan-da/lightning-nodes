#!/usr/bin/env php
<?php

/**
 * This script queries lightning-cli listnodes and formats
 * the response into a markdown file
 */

/* sample listnodes response JSON
  "nodes": [
    {
      "nodeid": "0297bab0d86a08db807102f4e79e5639eb44dcfc914476051fb3e4057ae6772d00", 
      "alias": "Bitonic", 
      "color": "192171", 
      "last_timestamp": 1532588756, 
      "addresses": [
        {
          "type": "ipv4", 
          "address": "92.111.70.106", 
          "port": 9735
        }
      ]
    }, 
    ...
 ]
*/ 


exec($_SERVER['HOME'] . '/bin/lightning-cli listnodes', $output, $rc );
if( $rc != 0 ) {
    echo "lightning-cli failed with code $rc\n\n";
    exit($rc);
}
$buf = implode("\n", $output);
$data = json_decode($buf, true);

$rows = [];
$totals = [
    'nodes' => 0,
    'addresses' => 0,
    'torv3' => 0,
    'torv2' => 0,
    'ipv6' => 0,
    'ipv4' => 0,
];

$bytype = [ 'torv3' => [], 'torv2' => [], 'ipv6' => [], 'ipv4' => [] ];
 
foreach($data['nodes'] as $node ) {
    if( !@$node['addresses'] ) {
        continue;
    }
    $totals['nodes'] ++;

    foreach($node['addresses'] as $addr) {
       if(!@$addr['type'] ) {  // some are empty.  bug in lightningd?
           continue;
       }
       $row = $node;
       unset($row['addresses']);
       $rows[] = array_merge($row, $addr);
       $bytype[ $addr['type'] ][] = array_merge($row, $addr);
       $totals['addresses'] ++;
       $totals[ $addr['type'] ] ++;
    }
}
usort( $rows, 'tscmp' );
function tscmp($a, $b) {
   return -($a['last_timestamp'] - $b['last_timestamp']);
}
 
foreach( $bytype as &$list ) {
   usort( $list, 'tscmp' );
}

file_put_contents(__DIR__ . '/nodes-by-addr-type.json', json_encode($bytype, JSON_PRETTY_PRINT));

function print_node_table($rows, $addrtype) {

    $buf = <<< END
|alias|last seen|address|id|
|-----|---------|-------|--|

END;

    foreach($rows as $row) {

        if($row['type'] == $addrtype) {
            $buf .= sprintf('|%s|%s|%s|%s|' . "\n", e($row['alias']), nbr(gmdate('Y.m.d H:i', $row['last_timestamp'])), e($row['address']), e($row['nodeid']));
        }
    }
    echo $buf;
}


function nbr($b) {return str_replace(' ', '&nbsp;', $b);}

// we quote strings with backtick only if string includes a colon.  because markdown
// interprets certain colon delimited strings as emoji chars with no way to escape properly.
// for our purposes mostly it means that ipv6 addrs will be in backticks, which will format them
// evenly anyway.
function e($b) { $quote = strstr($b, ':') ? "`" : ''; return $quote . htmlentities($b) . $quote;}

?>
# lightning-nodes

A historical list of lightning nodes, including .onion, updated daily.

Data obtained from [c-lightning](https://github.com/ElementsProject/lightning) listnodes API.  [json](https://raw.githubusercontent.com/dan-da/lightning-nodes/master/nodes-by-addr-type.json) also available.

Last updated: <?= gmdate('Y-m-d H:i:s e' ); ?>


## Stats

|Desc|count|
|----|----|
<?php
foreach( $totals as $k => $t ) {
    echo sprintf("|%s|%s|\n", ucfirst($k), $t);
}
?>

## Lightning Tor v3 onion:

<?php print_node_table($rows, 'torv3'); ?>

## Lightning Tor v2 onion:

<?php print_node_table($rows, 'torv2'); ?>

## IPV6:

<?php print_node_table($rows, 'ipv6'); ?>

## IPV4:

<?php print_node_table($rows, 'ipv4'); ?>




