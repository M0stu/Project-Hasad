<?php
declare(strict_types=1);
require __DIR__.'/test_boot.php';
unset($_SESSION['staff_id']);$_SESSION['member_id']=(int)$argv[2];
$b=batch_detail((int)$argv[1]);$q=quote_totals(6000000,(int)$b['price_per_tonne'],'buyer',$b['governorate'],'Sharqia');
$_POST=['batch_id'=>(string)$b['id'],'quantity'=>'6','unit'=>'tonne','shipping_mode'=>'buyer','destination'=>'Test factory','governorate'=>'Sharqia','organization'=>'Test factory','accept'=>'yes','expected_total'=>number_format($q['total']/100,2,'.','')];
try{$id=create_order();echo 'CREATED:'.$id;}catch(DomainException $e){echo 'REJECTED';exit(2);}
