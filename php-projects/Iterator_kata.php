<?php
function xrange($start, $limit, $step = 1) {
    if ($start < $limit) {
        if ($step <= 0) {
            throw new LogicException('Step must be +ve');
        }

        for ($i = $start; $i <= $limit; $i += $step) {
            yield $i;
        }
    } else {
        if ($step >= 0) {
            throw new LogicException('Step must be -ve');
        }

        for ($i = $start; $i >= $limit; $i += $step) {
            yield $i;
        }
    }
}

 $a = [];
    foreach (xrange(1, 1000009, 1) as $number) {   
        $a[] = $number;
    }

function arr($a)
{
	$b = [];
    foreach ($a as $number) {  
        $b[] = $number;
    }
    return $b;
}


function arrIt($a)
{
    foreach ($a as $number) {  
        yield $number;
    }
}


foreach (arr($a) as $value) {
	
	// echo gettype($value) . PHP_EOL;	
	// echo $value;
}

echo PHP_EOL.memory_get_peak_usage(true);exit;


?>
