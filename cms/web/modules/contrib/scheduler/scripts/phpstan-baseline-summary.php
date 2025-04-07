<?php

/**
 * @file
 * Read a PHPSTAN baseline file and summarize the ignored messages.
 *
 * Arguments:
 *   -v --verbose  show verbose debug output
 *   -m --more     more details in debug output
 *   filename      (positional) defaults to phpstan-baseline.neon.
 */

// Get the options.
$rest_index = NULL;
$options = getopt('vm', ['verbose', 'more'], $rest_index);
$quiet = !array_key_exists('v', $options) && !array_key_exists('verbose', $options);
$nodetails = !array_key_exists('m', $options) && !array_key_exists('more', $options);
$quiet = $quiet && $nodetails;
$quiet ?: print "quiet=$quiet\nnodetails=$nodetails\noptions=" . print_r($options, TRUE) . "\n";

// Get the positional arguments.
$pos_args = array_slice($argv, $rest_index);
$quiet ?: print "pos_args=" . print_r($pos_args, TRUE) . "\n";

// The filename is the first (and only) positional argument.
$filename = $pos_args[0] ?? getenv('_PHPSTAN_BASELINE_FILENAME') ?? 'phpstan-baseline.neon';
$quiet ?: print "filename=$filename\n";

$trim_chars = " #^\"\'$\n";
$summary = $overall = [];
$total = $count = 0;
$msg = '';

// Read the file into an array.
$baseline = @file($filename) ?: [];
if (empty($baseline)) {
  print "\n*******\n ERROR: Could not read file $filename\n*******\n";
  exit;
}

foreach ($baseline as $row => $line) {
  $nodetails ?: print "row=$row, line=$line";

  // Match against 'message' or 'count' or 'path' followed by :
  if (preg_match('/\s*(message|count|path)\:\s(.*)$/', $line, $matches)) {
    $nodetails ?: print_r($matches);
    $type = $matches[1];
    $value = stripslashes(trim($matches[2], $trim_chars));
    $nodetails ?: print "\$type=$type, \$value=$value\n";

    switch ($type) {

      case 'message':
        if ($value == '') {
          // Sometimes the message is long and does not start until the next
          // line. So if empty read from $row+1.
          $nodetails ?: print "row=$row, line=$line\nnext row={$baseline[$row+1]} \n";
          $value = stripslashes(trim($baseline[$row + 1], $trim_chars));
        }
        // Remove all double-backslashes.
        $msg = str_replace('\\\\', '\\', $value);
        break;

      case 'count':
        $count = $value;
        isset($summary[$msg]['count']) ? $summary[$msg]['count'] += $count : $summary[$msg]['count'] = $count;
        $total += $count;
        $nodetails ?: print "\$summary[$msg]['count']={$summary[$msg]['count']}\n";
        break;

      case 'path':
        $summary[$msg]['paths'][] = $value;
        $nodetails ?: print "\$summary[$msg]=" . print_r($summary[$msg], TRUE) . "\n";
        isset($overall[$value]) ? $overall[$value] += $count : $overall[$value] = $count;
        break;

      default:
        break;
    }
  }
}

// Sort by descending count.
$a_count = array_column($summary, 'count');
$quiet ?: print "a_count=" . print_r($a_count, TRUE) . "\n";
array_multisort($a_count, SORT_DESC, $summary);

arsort($overall);
$quiet ?: print "overall=" . print_r($overall, TRUE) . "\n";

$divider = str_repeat('-', 75) . "\n";
print "{$divider}Summary of PHPStan messages in {$filename}\n";
foreach ($summary as $msg => $values) {
  print "{$divider}{$msg}\n\n{$values['count']} occurrence(s) in " . count($values['paths']) . " file(s)\n";
  foreach ($values['paths'] as $path) {
    print "   $path\n";
  }
}

print "{$divider}Number of messages by file\n\n";
foreach ($overall as $file => $num) {
  print "   $num in $file\n";
}

print "{$divider}Different types of message: " . count($summary) . "\n";
print "Total number of messages: {$total}\n{$divider}";

exit;
